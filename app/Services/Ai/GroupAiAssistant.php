<?php

namespace App\Services\Ai;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Exceptions\InsufficientBudgetException;
use App\Exceptions\RequestTooLargeException;
use App\Exceptions\SpendLimitReachedException;
use App\Models\AiModel;
use App\Models\AiRequestRefusal;
use App\Models\AiUsageLog;
use App\Models\CreditLedgerEntry;
use App\Models\Group;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\MessageVersion;
use App\Models\User;
use App\Services\Ai\Context\ContextManager;
use App\Services\Ai\Context\MemoryMaintenance;
use App\Services\Ai\Responders\AiResponder;
use App\Services\Ai\Responders\CanvasResponder;
use App\Services\Ai\Responders\ChatResponder;
use App\Services\Ai\Files\FileRequest;
use App\Services\Ai\Responders\FileResponder;
use App\Services\Ai\Responders\ImageEditSuggestionResponder;
use App\Services\Ai\Responders\ImageResponder;
use App\Services\Ai\Responders\ResearchResponder;
use App\Services\Chat\MessagePresenter;
use App\Services\Credits\CreditWallet;
use App\Services\Credits\ModelAccess;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;
use Throwable;

/**
 * Normi AI di dalam grup: memutuskan apakah sebuah pesan perlu dijawab, menyusun konteksnya
 * (ContextManager), memilih jenis balasan, menagih Normkredit sesuai pemakaian nyata, lalu
 * memposting balasannya dan memicu pemeliharaan ingatan grup.
 */
class GroupAiAssistant
{
    private const BUSY_MESSAGE = 'Normi AI sedang sibuk. Coba lagi sebentar lagi.';

    /** Saran fitur untuk jawaban yang sedang diposting (system_meta jawaban), lihat suggestionMeta(). */
    private ?array $suggestion = null;

    public function __construct(
        private readonly NormkreditPricing $pricing,
        private readonly CreditWallet $wallet,
        private readonly ModelAccess $access,
        private readonly MessagePresenter $presenter,
        private readonly SpendGuard $spend,
        private readonly PricingVersions $versions,
        private readonly ContextManager $contexts,
        private readonly MemoryMaintenance $memory,
    ) {
    }

    /** $replace: jawaban lama yang diganti (jawab ulang setelah pertanyaan diedit). */
    public function handle(Message $message, ?Message $replace = null): void
    {
        $this->suggestion = null;
        $message->loadMissing('replyToMessage.sender:id,name');
        if (! $message->isAddressedToAi()) {
            return;
        }

        $group = Group::with('groupToken')->find($message->group_id);
        if ($group?->isFrozen()) {
            return;
        }
        $requester = $message->sender_id ? User::find($message->sender_id) : null;
        if (! $group || ! $requester) {
            return;
        }

        $task = $this->prepareTask($group, $requester, $message);
        if (is_array($task)) {
            [$reason, $text, $model] = $task;
            $this->refuse($message, $reason, $model, $text);

            return;
        }

        // Status sesuai pekerjaannya: "sedang mengetik" hanya untuk jawaban teks; gambar, riset, canvas,
        // dan dokumen punya status sendiri (laporan user 9 Nov 2026).
        $responder = $this->responderFor($message);
        $activity = $responder->activity();
        AiActivity::report($group->id, $activity);
        try {
            $reply = $responder->respond($task);
        } catch (InsufficientBudgetException $e) {
            $this->refuseTask($task, 'credits', $e->getMessage());

            return;
        } catch (SpendLimitReachedException $e) {
            $this->refuseTask($task, 'spend_guard', $e->getMessage());

            return;
        } catch (RequestTooLargeException $e) {
            $this->refuseTask($task, 'too_long', $e->getMessage());

            return;
        } catch (Throwable $e) {
            $task->releaseHold();
            throw $e;
        } finally {
            AiActivity::stop($group->id, $activity);
        }

        if ($reply->suggestMode !== null) {
            $this->suggestion = $this->suggestionMeta($message, $reply->suggestMode, $responder);
        }
        [$reply, $creditsDue, $charged] = $this->bill($task, $reply);
        $replaced = $replace !== null && ! $replace->trashed() && ! $replace->replies()->exists();
        $aiMessage = $replaced ? $this->replaceReply($replace, $message, $reply) : $this->postReply($message, $reply);
        if ($replaced) {
            // Isi jawaban lama yang mungkin sudah diringkas/di-embed diganti: perlakukan seperti pesan diedit.
            $this->memory->messageChanged($aiMessage, deleted: false);
        }

        $groupToken = $group->groupToken()->firstOrFail();
        // Jawaban yang tidak ditagih tetap dicatat bila sudah ada biaya OpenAI, supaya rekonsiliasi cocok.
        if ($reply->billable || $task->meter->providerCostUsd() > 0) {
            $this->logUsage($task, $reply->kind, $reply->model, $aiMessage, $creditsDue, $charged, $groupToken->remaining_credits);
        }

        $this->broadcast($aiMessage, $reply->billable ? $charged : null, $groupToken->credits, $replaced);
        $this->memory->afterAnswer($group);
    }

    /** @return AiTask|array{0: string, 1: string, 2: ?AiModel} task siap jalan, atau [alasan, pesan penolakan, model] */
    private function prepareTask(Group $group, User $requester, Message $message): AiTask|array
    {
        // Model pilihan anggota di composer, bila masih aktif; selain itu model grup.
        $model = ($message->ai_model ? AiModel::active()->where('model_id', $message->ai_model)->first() : null)
            ?? $group->resolvedAiModel();
        $client = OpenAiClient::server();
        if (! $model || ! $client) {
            return ['no_model', 'Normi AI belum bisa dipakai. Coba lagi nanti.', $model];
        }

        if ($this->wallet->balanceFor($group, $requester)->usable() <= 0) {
            return ['credits', 'Normkredit grup habis. Isi Normkredit untuk lanjut.', $model];
        }

        $token = GroupToken::forGroup((int) $group->id);
        if (! $this->access->isUnlocked($token, $model)) {
            return ['model_locked', $this->access->lockedMessage($model, $token), $model];
        }

        $dailyLimit = (float) $this->pricing->get('daily_group_credit_limit');
        if ($dailyLimit > 0 && $this->spentToday($group) >= $dailyLimit) {
            return ['daily_limit', 'Batas pemakaian Normi AI hari ini sudah tercapai. Coba lagi besok.', $model];
        }

        // Dicek sebelum ada panggilan OpenAI apa pun (pemahaman pertanyaan, transkripsi).
        if (! $this->spend->isOpen()) {
            return ['spend_guard', SpendLimitReachedException::USER_MESSAGE, $model];
        }

        return new AiTask(
            $group,
            $requester,
            $message,
            $this->contexts->build($group, $message),
            $model,
            $client,
            $this->pricing->locked(),
            $this->versions->currentId(),
            $this->wallet,
            $this->access,
            $this->spend,
        );
    }

    private function responderFor(Message $message): AiResponder
    {
        // Pesan lama tanpa ai_mode tetap bisa memakai fitur lewat tag (@normi-image, dst.).
        $mode = match ($message->ai_mode ?? AiMode::fromContent($message->content)) {
            AiMode::Image => new ImageResponder(),
            AiMode::Canvas => new CanvasResponder(),
            AiMode::DeepResearch => new ResearchResponder(),
            AiMode::GuidedLearning => new ChatResponder(ModePrompts::GUIDED_LEARNING, 'guided_learning'),
            AiMode::File => new FileResponder(),
            null => null,
        };
        if ($mode !== null) {
            return $mode;
        }

        // @normi biasa: permintaan yang jelas milik sebuah fitur langsung dikerjakan fitur itu (file, gambar baru),
        // kecuali edit foto yang diarahkan dulu ke fitur Buat gambar (keputusan user 11 Nov 2026).
        $content = (string) $message->content;
        $file = FileRequest::detect($content);
        if ($file !== null) {
            return new FileResponder($file);
        }
        if (ImageResponder::wantsImage($content)) {
            return new ImageResponder();
        }
        $imageSource = ImageEditSuggestionResponder::wantsImageEdit($message);

        return $imageSource !== null ? new ImageEditSuggestionResponder($imageSource) : new ChatResponder();
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

        $creditsDue = $task->pricing->creditsForCost($task->meter->weightedCostUsd());
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
            // Jawaban fitur (gambar, canvas, riset, guided learning) ditandai modenya: balasan ke jawaban
            // ini menanyakan dulu apakah fitur yang sama dipakai lagi (resources/js/chat/composer/ai-options.js).
            'ai_mode' => $reply->billable ? AiMode::fromReplyKind($reply->kind) : null,
            // Tombol "pakai fitur" di bawah jawaban (resources/js/chat/messages.js).
            'system_meta' => ($this->suggestion ?? []) + $reply->meta ?: null,
            'message_type' => $attachment['message_type'] ?? 'text',
            'attachment_disk' => $attachment ? 'normchat_attachments' : null,
            'attachment_path' => $attachment['path'] ?? null,
            'attachment_mime' => $attachment['mime'] ?? null,
            'attachment_original_name' => $attachment['original_name'] ?? null,
            'attachment_size' => $attachment['size'] ?? null,
        ];
    }

    /**
     * Data tombol saran fitur: mode, pesan yang dibalas saat tombol ditekan (pesan berisi foto untuk edit gambar,
     * selain itu pertanyaannya), dan teks permintaan tanpa tag untuk mengisi kolom ketik.
     *
     * @return array{suggest_mode: string, suggest_reply_to: int, suggest_text: string}
     */
    private function suggestionMeta(Message $request, string $mode, AiResponder $responder): array
    {
        $replyTo = $responder instanceof ImageEditSuggestionResponder ? $responder->imageSource() : $request;
        if ($mode === AiMode::Image->value && ! $replyTo->hasImageAttachment()) {
            // "@normi" saja setelah mengirim foto (kasus 10 Nov 2026): foto terbaru si penanya yang dirujuk.
            $replyTo = Message::query()
                ->where('group_id', $request->group_id)
                ->where('sender_id', $request->sender_id)
                ->where('id', '<=', $request->id)
                ->where('message_type', 'image')
                ->latest('id')
                ->first() ?? $replyTo;
        }

        $text = trim((string) preg_replace(AiMode::anyTagPattern(), '', (string) $request->content));
        if ($text === '' && (int) $replyTo->id !== (int) $request->id) {
            $text = trim((string) preg_replace(AiMode::anyTagPattern(), '', (string) $replyTo->content));
        }

        return [
            'suggest_mode' => $mode,
            'suggest_reply_to' => (int) $replyTo->id,
            'suggest_text' => mb_substr((string) preg_replace('/\s+/u', ' ', $text), 0, 500),
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

    /**
     * Permintaan ditolak setelah AiTask dibuat: NC dikembalikan, biaya OpenAI yang terlanjur keluar
     * (mis. transkripsi voice note) tetap dicatat dengan kind "refused" dan 0 NC.
     */
    private function refuseTask(AiTask $task, string $reason, string $text): void
    {
        $task->releaseHold();
        if ($task->meter->providerCostUsd() > 0) {
            $this->logUsage($task, 'refused', null, null, 0.0, 0.0, GroupToken::forGroup((int) $task->group->id)->remaining_credits);
        }
        $this->refuse($task->message, $reason, $task->model, $text);
    }

    private function logUsage(AiTask $task, string $kind, ?AiModel $usedModel, ?Message $aiMessage, float $creditsDue, float $charged, float $balanceAfter): void
    {
        $meter = $task->meter;
        $model = $usedModel ?? $task->model;
        $mainTokens = $meter->tokensOf($kind === 'image' ? 'image_planner' : $kind);
        $usdToIdr = $task->pricing->usdToIdr();
        $inputTokens = (int) ($mainTokens['input_tokens'] ?? 0);
        $holdEntry = $task->creditHoldId()
            ? CreditLedgerEntry::query()->where('credit_hold_id', $task->creditHoldId())->where('type', CreditLedgerEntry::HOLD)->first()
            : null;

        $log = AiUsageLog::create([
            'group_id' => $task->group->id,
            'request_message_id' => $task->message->id,
            'response_message_id' => $aiMessage?->id,
            'user_id' => $task->requester->id,
            'kind' => $kind,
            'model' => $model->model_id,
            'input_tokens' => (int) ($mainTokens['input_tokens'] ?? 0),
            'cached_input_tokens' => (int) ($mainTokens['cached_input_tokens'] ?? 0),
            'output_tokens' => (int) ($mainTokens['output_tokens'] ?? 0),
            'breakdown_json' => ['lines' => $meter->lines(), 'credits_due' => $creditsDue, 'context' => $task->context->stats()],
            'provider_cost_usd' => $meter->providerCostUsd(),
            'usd_to_idr' => $usdToIdr,
            'provider_cost_idr' => round($meter->providerCostUsd() * $usdToIdr, 4),
            'multiplier' => $model->multiplier,
            'charged_credits' => $charged,
            'balance_after' => $balanceAfter,
            'balance_before' => $holdEntry?->balance_before,
            'provider_request_id' => $task->requestId(),
            'cache_hit_percent' => $inputTokens > 0 ? round((int) ($mainTokens['cached_input_tokens'] ?? 0) / $inputTokens * 100, 2) : null,
            'pricing_version_id' => $task->pricingVersionId,
            'exchange_rate_id' => $task->pricing->exchangeRateId(),
        ]);

        if ($task->creditHoldId()) {
            CreditLedgerEntry::query()->where('credit_hold_id', $task->creditHoldId())->where('type', CreditLedgerEntry::SETTLE)
                ->update(['ai_usage_log_id' => $log->id]);
        }
    }

    private function broadcast(Message $aiMessage, ?float $chargedCredits, float $groupCredits, bool $replaced): void
    {
        $payload = $this->presenter->present($aiMessage, 'Normi AI', [
            'ai_charged_credits' => $chargedCredits,
            'group_credits_remaining' => $groupCredits,
        ]);

        event($replaced ? new MessageUpdated($aiMessage->group_id, $payload) : new MessageSent($aiMessage->group_id, $payload));
    }

    /** Permintaan ditolak sebelum dijawab: dicatat untuk metrik superadmin, penanya diberi tahu. */
    private function refuse(Message $request, string $reason, ?AiModel $model, string $text): void
    {
        AiRequestRefusal::create([
            'group_id' => $request->group_id,
            'user_id' => $request->sender_id,
            'reason' => $reason,
            'model' => $model?->model_id,
        ]);
        $this->postNotice($request, $text);
        // Anggota lain sudah melihat status "Normi sedang …" sejak pertanyaan terkirim; hentikan.
        AiActivity::stop((int) $request->group_id);
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
