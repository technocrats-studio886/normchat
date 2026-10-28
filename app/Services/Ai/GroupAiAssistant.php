<?php

namespace App\Services\Ai;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\TypingStatus;
use App\Exceptions\InsufficientBudgetException;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\Group;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\MessageVersion;
use App\Models\User;
use App\Services\AiDocumentGenerator;
use App\Services\Ai\Responders\AiResponder;
use App\Services\Ai\Responders\CanvasResponder;
use App\Services\Ai\Responders\ChatResponder;
use App\Services\Ai\Responders\DocumentResponder;
use App\Services\Ai\Responders\ImageResponder;
use App\Services\Ai\Responders\ResearchResponder;
use App\Services\Chat\MessagePresenter;
use App\Services\Credits\CreditWallet;
use App\Services\Credits\ModelAccess;
use App\Services\NormkreditPricing;
use Throwable;

/**
 * Normi AI di dalam grup: memutuskan apakah sebuah pesan perlu dijawab, memilih jenis
 * balasan, menagih Normkredit sesuai pemakaian nyata, lalu memposting balasannya.
 */
class GroupAiAssistant
{
    private const CONTEXT_MESSAGES = 16;

    private const BUSY_MESSAGE = 'Normi AI sedang sibuk. Coba lagi sebentar lagi.';

    public function __construct(
        private readonly NormkreditPricing $pricing,
        private readonly CreditWallet $wallet,
        private readonly ModelAccess $access,
        private readonly MessagePresenter $presenter,
    ) {
    }

    /** $replace: jawaban lama yang diganti (jawab ulang setelah pertanyaan diedit). */
    public function handle(Message $message, ?Message $replace = null): void
    {
        $message->loadMissing('replyToMessage.sender:id,name');
        if (! $message->isAddressedToAi()) {
            return;
        }

        $group = Group::with('groupToken')->find($message->group_id);
        if ($group?->isFrozen()) {
            return;
        }
        $owner = $group ? User::find($group->owner_id) : null;
        $requester = $message->sender_id ? User::find($message->sender_id) : null;
        if (! $group || ! $owner || ! $requester) {
            return;
        }

        $task = $this->prepareTask($group, $owner, $requester, $message);
        if (is_string($task)) {
            $this->postNotice($message, $task);

            return;
        }

        event(new TypingStatus($group->id, 'ai', null, 'Normi AI', true));
        try {
            $reply = $this->responderFor($message)->respond($task);
        } catch (InsufficientBudgetException $e) {
            $this->postNotice($message, $e->getMessage());

            return;
        } catch (Throwable $e) {
            $task->releaseHold();
            throw $e;
        } finally {
            event(new TypingStatus($group->id, 'ai', null, 'Normi AI', false));
        }

        [$reply, $creditsDue, $charged] = $this->bill($task, $reply);
        $replaced = $replace !== null && ! $replace->trashed() && ! $replace->replies()->exists();
        $aiMessage = $replaced ? $this->replaceReply($replace, $message, $reply) : $this->postReply($message, $reply);

        $groupToken = $group->groupToken()->firstOrFail();
        if ($reply->billable) {
            $this->logUsage($task, $reply, $aiMessage, $creditsDue, $charged, $groupToken->credits);
        }

        $this->broadcast($aiMessage, $reply->billable ? $charged : null, $groupToken->credits, $replaced);
    }

    /** @return AiTask|string task siap jalan, atau pesan penolakan untuk grup */
    private function prepareTask(Group $group, User $owner, User $requester, Message $message): AiTask|string
    {
        // Model pilihan anggota di composer, bila masih aktif; selain itu model grup.
        $model = ($message->ai_model ? AiModel::active()->where('model_id', $message->ai_model)->first() : null)
            ?? $group->resolvedAiModel();
        if (! $model) {
            return 'Normi AI belum bisa dipakai. Coba lagi nanti.';
        }

        if ($this->wallet->balanceFor($group, $requester)->usable() <= 0) {
            return 'Normkredit grup habis. Isi Normkredit untuk lanjut.';
        }

        $token = GroupToken::forGroup((int) $group->id);
        if (! $this->access->isUnlocked($token, $model)) {
            return $this->access->lockedMessage($model, $token);
        }

        $dailyLimit = (float) $this->pricing->get('daily_group_credit_limit');
        if ($dailyLimit > 0 && $this->spentToday($group) >= $dailyLimit) {
            return 'Batas pemakaian Normi AI hari ini sudah tercapai. Coba lagi besok.';
        }

        $client = OpenAiClient::forOwner($owner);
        if (! $client) {
            return 'Normi AI belum bisa dipakai. Coba lagi nanti.';
        }

        // Pemberitahuan Normi untuk satu anggota bukan bagian percakapan.
        $recentMessages = Message::query()
            ->where('group_id', $group->id)
            ->whereNull('visible_to_user_id')
            ->orderByDesc('id')
            ->take(self::CONTEXT_MESSAGES)
            ->get()
            ->reverse()
            ->values()
            ->all();

        return new AiTask($group, $requester, $message, $recentMessages, $model, $client, $this->pricing, $this->wallet, $this->access);
    }

    private function responderFor(Message $message): AiResponder
    {
        $mode = match ($message->ai_mode) {
            AiMode::Image => new ImageResponder(),
            AiMode::Canvas => new CanvasResponder(),
            AiMode::DeepResearch => new ResearchResponder(),
            AiMode::GuidedLearning => new ChatResponder(ModePrompts::GUIDED_LEARNING, 'guided_learning'),
            null => null,
        };
        if ($mode !== null) {
            return $mode;
        }

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
            $reply = $reply->withNote('Normkredit grup habis. Isi Normkredit untuk lanjut.');
        }

        return [$reply, $creditsDue, $charged];
    }

    private function postReply(Message $request, AiReply $reply): Message
    {
        $quote = trim((string) ($request->reply_quote_text ?? ''));

        return Message::create([
            'group_id' => $request->group_id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'reply_to_message_id' => $request->id,
            'reply_quote_text' => $quote !== '' ? $quote : null,
            ...$this->replyContent($reply),
        ]);
    }

    /** Jawab ulang: jawaban lama diganti di tempatnya; isi lamanya disimpan sebagai versi. */
    private function replaceReply(Message $answer, Message $request, AiReply $reply): Message
    {
        MessageVersion::create([
            'message_id' => $answer->id,
            'version_number' => ((int) $answer->versions()->max('version_number')) + 1,
            'content_snapshot' => (string) ($answer->content ?? ''),
            'edited_by' => $request->sender_id,
            'edited_at' => now(),
        ]);
        $answer->update($this->replyContent($reply));

        return $answer->refresh()->loadCount('versions');
    }

    /** @return array<string, mixed> isi dan lampiran jawaban */
    private function replyContent(AiReply $reply): array
    {
        $attachment = $reply->attachment;

        return [
            'content' => self::withoutReplyMarkers($reply->text),
            'message_type' => $attachment['message_type'] ?? 'text',
            'attachment_disk' => $attachment ? 'normchat_attachments' : null,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_mime' => $attachment['mime'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
            'attachment_size' => $attachment['size'] ?? null,
        ];
    }

    /**
     * Penanda balasan dari format riwayat lama ("[reply]", "[reply mengutip: "…"]") yang
     * kadang ditiru model di akhir jawabannya; tidak boleh tampil ke grup.
     */
    public static function withoutReplyMarkers(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        return trim((string) preg_replace('/[ \t]*\[reply(?: mengutip: "[^"\n]*")?\]/iu', '', $text));
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

    private function broadcast(Message $aiMessage, ?float $chargedCredits, float $groupCredits, bool $replaced): void
    {
        $payload = $this->presenter->present($aiMessage, 'Normi AI', [
            'ai_charged_credits' => $chargedCredits,
            'group_credits_remaining' => $groupCredits,
        ]);

        event($replaced ? new MessageUpdated($aiMessage->group_id, $payload) : new MessageSent($aiMessage->group_id, $payload));
    }

    /** Pemberitahuan (saldo habis, model terkunci, dll.) hanya untuk penanya, membalas pertanyaannya. */
    private function postNotice(Message $request, string $text): void
    {
        $notice = Message::create([
            'group_id' => $request->group_id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'reply_to_message_id' => $request->id,
            'visible_to_user_id' => $request->sender_id,
            'content' => $text,
        ]);

        event(new MessageSent($request->group_id, $this->presenter->present($notice, 'Normi AI'), (int) $request->sender_id));
    }

    private function spentToday(Group $group): float
    {
        return (float) AiUsageLog::query()
            ->where('group_id', $group->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('charged_credits');
    }
}
