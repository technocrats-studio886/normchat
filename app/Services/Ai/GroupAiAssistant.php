<?php

namespace App\Services\Ai;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Exceptions\InsufficientBudgetException;
use App\Models\AiUsageLog;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\AiDocumentGenerator;
use App\Services\Ai\Responders\AiResponder;
use App\Services\Ai\Responders\ChatResponder;
use App\Services\Ai\Responders\DocumentResponder;
use App\Services\Ai\Responders\ImageResponder;
use App\Services\Chat\MessagePresenter;
use App\Services\Credits\CreditWallet;
use App\Services\NormkreditPricing;
use Throwable;

/**
 * NormAI di dalam grup: memutuskan apakah sebuah pesan perlu dijawab, memilih jenis
 * balasan, menagih Normkredit sesuai pemakaian nyata, lalu memposting balasannya.
 */
class GroupAiAssistant
{
    private const CONTEXT_MESSAGES = 16;

    private const BUSY_MESSAGE = 'AI sedang sibuk dan belum bisa merespons saat ini. Coba lagi beberapa saat.';

    public function __construct(
        private readonly NormkreditPricing $pricing,
        private readonly CreditWallet $wallet,
        private readonly MessagePresenter $presenter,
    ) {
    }

    public function handle(Message $message): void
    {
        $message->loadMissing('replyToMessage.sender:id,name');
        if (! $message->isAddressedToAi()) {
            return;
        }

        $group = Group::with('groupToken')->find($message->group_id);
        $owner = $group ? User::find($group->owner_id) : null;
        $requester = $message->sender_id ? User::find($message->sender_id) : null;
        if (! $group || ! $owner || ! $requester) {
            return;
        }

        $task = $this->prepareTask($group, $owner, $requester, $message);
        if (is_string($task)) {
            $this->postSystemMessage($group, $task);

            return;
        }

        event(new TypingStatus($group->id, 'ai', null, 'NormAI', true));
        try {
            $reply = $this->responderFor($message)->respond($task);
        } catch (InsufficientBudgetException $e) {
            $this->postSystemMessage($group, $e->getMessage());

            return;
        } catch (Throwable $e) {
            $task->releaseHold();
            throw $e;
        } finally {
            event(new TypingStatus($group->id, 'ai', null, 'NormAI', false));
        }

        [$reply, $creditsDue, $charged] = $this->bill($task, $reply);
        $aiMessage = $this->postReply($message, $reply);

        $groupToken = $group->groupToken()->firstOrFail();
        if ($reply->billable) {
            $this->logUsage($task, $reply, $aiMessage, $creditsDue, $charged, $groupToken->credits);
        }

        $this->broadcast($aiMessage, $reply->billable ? $charged : null, $groupToken->credits);
    }

    /** @return AiTask|string task siap jalan, atau pesan penolakan untuk grup */
    private function prepareTask(Group $group, User $owner, User $requester, Message $message): AiTask|string
    {
        $model = $group->resolvedAiModel();
        if (! $model) {
            return 'Belum ada model AI yang aktif. Hubungi admin Normchat.';
        }

        if ($this->wallet->balanceFor($group, $requester)->usable() <= 0) {
            return 'Kredit Normkredit yang bisa kamu pakai di grup ini habis. Top-up Normkredit atau minta anggota lain membagikan kreditnya ke kredit bersama.';
        }

        $dailyLimit = (float) $this->pricing->get('daily_group_credit_limit');
        if ($dailyLimit > 0 && $this->spentToday($group) >= $dailyLimit) {
            return 'Batas pemakaian AI grup hari ini (' . $this->formatCredits($dailyLimit) . ' Normkredit) sudah tercapai. Coba lagi besok.';
        }

        $client = OpenAiClient::forOwner($owner);
        if (! $client) {
            return 'AI belum dikonfigurasi. Hubungi admin untuk mengisi OPENAI_API_KEY agar fitur @ai aktif.';
        }

        $recentMessages = Message::query()
            ->where('group_id', $group->id)
            ->orderByDesc('id')
            ->take(self::CONTEXT_MESSAGES)
            ->get()
            ->reverse()
            ->values()
            ->all();

        return new AiTask($group, $requester, $message, $recentMessages, $model, $client, $this->pricing, $this->wallet);
    }

    private function responderFor(Message $message): AiResponder
    {
        $content = (string) $message->content;
        if (ImageResponder::wantsImage($content)) {
            return new ImageResponder();
        }

        $format = app(AiDocumentGenerator::class)->detectRequestedFormat($content);

        return $format !== null ? new DocumentResponder($format) : new ChatResponder();
    }

    /**
     * Selesaikan tahanan NK sesuai pemakaian nyata. Balasan yang gagal tidak ditagih.
     *
     * @return array{0: AiReply, 1: float, 2: float} [balasan, NK terutang, NK terpotong]
     */
    private function bill(AiTask $task, AiReply $reply): array
    {
        if ($reply->text === null && $reply->attachment === null) {
            $reply = AiReply::notice($reply->kind, self::BUSY_MESSAGE);
        }

        $creditsDue = $this->pricing->creditsForCost($task->meter->weightedCostUsd());
        if (! $reply->billable || $creditsDue <= 0) {
            $task->releaseHold();

            return [$reply, 0.0, 0.0];
        }

        $charged = $task->settle($creditsDue);
        if ($charged + 0.00001 < $creditsDue) {
            // Pemakaian aktual melebihi kredit yang boleh dipakai: jawaban tetap dikirim.
            $reply = $reply->withNote('Kredit yang bisa kamu pakai tidak mencukupi. Terpotong ' . $this->formatCredits($charged)
                . ' dari ' . $this->formatCredits($creditsDue) . ' Normkredit. Top-up Normkredit untuk melanjutkan penggunaan AI.');
        }

        return [$reply, $creditsDue, $charged];
    }

    private function postReply(Message $request, AiReply $reply): Message
    {
        $quote = trim((string) ($request->reply_quote_text ?? ''));
        $attributes = [
            'group_id' => $request->group_id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'reply_to_message_id' => $request->id,
            'reply_quote_text' => $quote !== '' ? $quote : null,
            'content' => $reply->text,
        ];

        if ($reply->attachment) {
            $attributes += [
                'message_type' => $reply->attachment['message_type'],
                'attachment_disk' => 'normchat_attachments',
                'attachment_path' => $reply->attachment['path'],
                'attachment_mime' => $reply->attachment['mime'],
                'attachment_original_name' => $reply->attachment['original_name'],
                'attachment_size' => $reply->attachment['size'],
            ];
        }

        return Message::create($attributes);
    }

    private function logUsage(AiTask $task, AiReply $reply, Message $aiMessage, float $creditsDue, float $charged, float $balanceAfter): void
    {
        $meter = $task->meter;
        $model = $reply->model ?? $task->model;
        $mainTokens = $meter->tokensOf($reply->kind === 'image' ? 'image_planner' : $reply->kind);
        $usdToIdr = $this->pricing->usdToIdr();

        AiUsageLog::create([
            'group_id' => $task->group->id,
            'request_message_id' => $task->message->id,
            'response_message_id' => $aiMessage->id,
            'user_id' => $task->requester->id,
            'kind' => $reply->kind,
            'model' => $model->model_id,
            'input_tokens' => (int) ($mainTokens['input_tokens'] ?? 0),
            'cached_input_tokens' => (int) ($mainTokens['cached_input_tokens'] ?? 0),
            'output_tokens' => (int) ($mainTokens['output_tokens'] ?? 0),
            'breakdown_json' => ['lines' => $meter->lines(), 'credits_due' => $creditsDue],
            'provider_cost_usd' => $meter->providerCostUsd(),
            'usd_to_idr' => $usdToIdr,
            'provider_cost_idr' => round($meter->providerCostUsd() * $usdToIdr, 4),
            'multiplier' => $model->multiplier,
            'charged_credits' => $charged,
            'balance_after' => $balanceAfter,
        ]);
    }

    private function broadcast(Message $aiMessage, ?float $chargedCredits, float $groupCredits): void
    {
        event(new MessageSent($aiMessage->group_id, $this->presenter->present($aiMessage, 'NormAI', [
            'ai_charged_credits' => $chargedCredits,
            'group_credits_remaining' => $groupCredits,
        ])));
    }

    private function postSystemMessage(Group $group, string $text): void
    {
        $message = Message::create([
            'group_id' => $group->id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'content' => $text,
        ]);

        event(new MessageSent($group->id, $this->presenter->present($message, 'NormAI')));
    }

    private function spentToday(Group $group): float
    {
        return (float) AiUsageLog::query()
            ->where('group_id', $group->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('charged_credits');
    }

    private function formatCredits(float $credits): string
    {
        return number_format($credits, 2, ',', '.');
    }
}
