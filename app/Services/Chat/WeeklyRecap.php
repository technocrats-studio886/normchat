<?php

namespace App\Services\Chat;

use App\Events\MessageSent;
use App\Exceptions\InsufficientBudgetException;
use App\Exceptions\SpendLimitReachedException;
use App\Models\AiUsageLog;
use App\Models\Group;
use App\Models\GroupTask;
use App\Models\Message;
use App\Models\Reminder;
use App\Services\Ai\AiTask;
use App\Services\Ai\ChatTranscript;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\SpendGuard;
use App\Services\Credits\CreditWallet;
use App\Services\Credits\ModelAccess;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rekap mingguan otomatis (docs/rancangan-fitur-produktivitas-nov.md bagian 8). Dinyalakan moderator per grup
 * (bawaan mati, keputusan user 11 Nov 2026), memakai Normkredit grup atas nama pemilik grup, model termurah.
 */
class WeeklyRecap
{
    public const KIND = 'weekly_recap';

    public const MIN_MESSAGES = 10;

    private const MAX_INPUT_TOKENS = 14_000;

    private const MAX_OUTPUT_TOKENS = 1_600;

    public const PROMPT = "MODE REKAP MINGGUAN. Tulis rekap seminggu terakhir untuk seluruh anggota grup, dalam Bahasa Indonesia yang ringkas."
        . " Pakai subjudul tebal (bukan heading #) dan poin pendek, hanya bagian yang ada isinya, dengan urutan:"
        . "\n**Topik utama**, **Keputusan**, **Tugas** (yang selesai dan yang masih terbuka, sebut penanggung jawab dan tenggat),"
        . " **Jadwal minggu depan**, **File dan tautan penting**, **Pertanyaan yang belum terjawab**."
        . "\nSebut nama anggota bila relevan. Jangan mengarang; hanya dari data di bawah. Tanpa salam pembuka; maksimal ±250 kata.";

    public function __construct(
        private readonly CreditWallet $wallet,
        private readonly ModelAccess $access,
        private readonly SpendGuard $spend,
        private readonly NormkreditPricing $pricing,
        private readonly PricingVersions $versions,
        private readonly MaintenanceAi $maintenance,
        private readonly MessagePresenter $presenter,
        private readonly GroupTasks $tasks,
    ) {
    }

    /** Jadwal grup ini jatuh pada jam ini (zona waktu pemilik) dan belum ada rekap dalam 6 hari terakhir. */
    public function isDue(Group $group, ?CarbonImmutable $now = null): bool
    {
        if (! $group->weekly_recap_enabled || $group->status !== 'active' || $group->isFrozen()) {
            return false;
        }
        $now ??= CarbonImmutable::now();
        $local = $now->setTimezone(UserTime::zone($group->owner));

        return $local->dayOfWeekIso === (int) $group->weekly_recap_day
            && $local->hour === (int) $group->weekly_recap_hour
            && ($group->weekly_recap_last_at === null || $group->weekly_recap_last_at->lessThan($now->subDays(6)));
    }

    /** @return Message|null pesan rekap, atau null bila dilewati (pesan sedikit, saldo/model tidak tersedia) */
    public function generate(Group $group): ?Message
    {
        // Ditandai di awal: kegagalan pun tidak diulang tiap jam.
        $group->forceFill(['weekly_recap_last_at' => now()])->saveQuietly();
        $owner = $group->owner;
        $from = now()->subDays(7);
        $messages = Message::query()
            ->where('group_id', $group->id)
            ->where('created_at', '>=', $from)
            ->whereNull('visible_to_user_id')
            ->where('sender_type', '!=', Message::SENDER_SYSTEM)
            ->where(fn ($query) => $query->whereNull('system_meta')->orWhereNull('system_meta->weekly_recap'))
            ->with(['sender:id,name', 'replyToMessage.sender:id,name'])
            ->orderByDesc('id')->limit(800)->get()->reverse()->values();
        if (! $owner || $messages->count() < self::MIN_MESSAGES) {
            return null;
        }

        $model = $this->maintenance->model();
        $client = OpenAiClient::server();
        if (! $model || ! $client || ! $this->spend->isOpen() || $this->wallet->balanceFor($group, $owner)->usable() <= 0) {
            return null;
        }

        $request = new Message(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $owner->id, 'content' => 'rekap mingguan']);
        $task = new AiTask($group, $owner, $request, ContextBundle::windowOnly([]), $model, $client, $this->pricing->locked(),
            $this->versions->currentId(), $this->wallet, $this->access, $this->spend);

        [$transcript] = ChatTranscript::build($messages, $owner, self::MAX_INPUT_TOKENS);
        $period = UserTime::format($from, 'j M', $owner) . '–' . UserTime::format(now(), 'j M Y', $owner);
        $prompt = [
            ['role' => 'system', 'content' => self::PROMPT],
            ['role' => 'user', 'content' => "Grup: {$group->name}. Periode {$period}.\n\n"
                . $this->taskSection($group, $from)
                . $this->reminderSection($group, $owner)
                . "Percakapan ({$messages->count()} pesan):\n" . $transcript],
        ];

        try {
            $task->planChat($prompt, self::MAX_OUTPUT_TOKENS);
            [$completion, $usedModel] = $task->complete($prompt, 90);
        } catch (InsufficientBudgetException|SpendLimitReachedException) {
            $task->releaseHold();

            return null;
        } catch (Throwable $e) {
            $task->releaseHold();
            Log::error('Weekly recap failed.', ['group_id' => $group->id, 'error' => $e->getMessage()]);

            return null;
        }
        if (! $completion->hasText()) {
            $task->releaseHold();

            return null;
        }
        $task->recordChat(self::KIND, $usedModel, $completion);
        $creditsDue = $task->pricing->creditsForCost($task->meter->weightedCostUsd());
        $charged = $task->settle($creditsDue);

        $message = Message::create([
            'group_id' => $group->id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'content' => "**Rekap mingguan** · {$period}\n\n" . trim((string) $completion->text),
            'system_meta' => ['weekly_recap' => ['from' => $from->toIso8601String()]],
        ]);
        $this->log($task, $usedModel->model_id, $message, $creditsDue, $charged);
        event(new MessageSent((int) $group->id, $this->presenter->present($message, 'Normi AI', [
            'ai_charged_credits' => $charged,
            'group_credits_remaining' => $group->groupToken()->first()?->credits ?? 0,
        ])));

        return $message;
    }

    private function taskSection(Group $group, $from): string
    {
        $done = GroupTask::query()->where('group_id', $group->id)->where('done_at', '>=', $from)->with('finisher:id,name')->limit(30)->get()
            ->map(fn (GroupTask $task) => '- ' . $task->title . ($task->finisher ? ' (diselesaikan ' . $task->finisher->name . ')' : ''))->implode("\n");
        $open = $this->tasks->openSummary($group);
        if ($done === '' && $open === '') {
            return '';
        }

        return ($done !== '' ? "Tugas selesai minggu ini:\n{$done}\n" : '') . ($open !== '' ? "Tugas yang masih terbuka:\n{$open}\n" : '') . "\n";
    }

    private function reminderSection(Group $group, $owner): string
    {
        $upcoming = Reminder::query()->where('group_id', $group->id)->where('status', Reminder::PENDING)->where('audience', 'group')
            ->where('remind_at', '<=', now()->addDays(7))->orderBy('remind_at')->limit(15)->get()
            ->map(fn (Reminder $reminder) => '- ' . Reminders::whenLabel($reminder, $owner) . ': ' . $reminder->text)->implode("\n");

        return $upcoming !== '' ? "Pengingat 7 hari ke depan:\n{$upcoming}\n\n" : '';
    }

    private function log(AiTask $task, string $model, Message $message, float $creditsDue, float $charged): void
    {
        $tokens = $task->meter->tokensOf(self::KIND);
        $usdToIdr = $task->pricing->usdToIdr();
        AiUsageLog::create([
            'group_id' => $task->group->id,
            'response_message_id' => $message->id,
            'user_id' => $task->requester->id,
            'kind' => self::KIND,
            'model' => $model,
            'input_tokens' => (int) ($tokens['input_tokens'] ?? 0),
            'cached_input_tokens' => (int) ($tokens['cached_input_tokens'] ?? 0),
            'output_tokens' => (int) ($tokens['output_tokens'] ?? 0),
            'breakdown_json' => ['lines' => $task->meter->lines(), 'credits_due' => $creditsDue],
            'provider_cost_usd' => $task->meter->providerCostUsd(),
            'usd_to_idr' => $usdToIdr,
            'provider_cost_idr' => round($task->meter->providerCostUsd() * $usdToIdr, 4),
            'multiplier' => $task->model->multiplier,
            'charged_credits' => $charged,
            'balance_after' => \App\Models\GroupToken::forGroup((int) $task->group->id)->remaining_credits,
            'provider_request_id' => $task->requestId(),
            'pricing_version_id' => $task->pricingVersionId,
            'exchange_rate_id' => $task->pricing->exchangeRateId(),
        ]);
    }
}
