<?php

namespace App\Services\Ai;

use App\Exceptions\InsufficientBudgetException;
use App\Exceptions\RequestTooLargeException;
use App\Exceptions\SpendLimitReachedException;
use App\Models\AiModel;
use App\Models\AiSpendHold;
use App\Models\CreditHold;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Credits\CreditWallet;
use App\Services\Credits\ModelAccess;
use App\Services\NormkreditPricing;
use Illuminate\Support\Facades\Log;

/**
 * Satu permintaan AI dari seorang anggota: konteksnya, model kategori yang dipilih
 * grup, pencatat biaya, dan dua tahanan biayanya — estimasi biaya OpenAI di SpendGuard
 * (saldo akun OpenAI) dan Normkredit grup di CreditWallet. $pricing sudah dikunci kursnya,
 * jadi hold, settle, dan log memakai kurs yang sama.
 */
final class AiTask
{
    public readonly PromptBuilder $prompts;

    public readonly UsageMeter $meter;

    private ?CreditHold $hold = null;

    private ?AiSpendHold $spendHold = null;

    private ?RequestBudget $budget = null;

    /** Id respons OpenAI dari panggilan model utama, untuk log. */
    private ?string $requestId = null;

    /** Id tahanan NK, tetap tersedia setelah settle/release untuk menautkan log ke ledger. */
    private ?int $creditHoldId = null;

    /** Biaya OpenAI permintaan ini sudah dicatat di SpendGuard (sekali saja). */
    private bool $spendRecorded = false;

    /** Konteks dari ContextManager; hanya diganti compose() bila melewati batas token input. */
    public ContextBundle $context;

    public function __construct(
        public readonly Group $group,
        public readonly User $requester,
        public readonly Message $message,
        ContextBundle $context,
        public readonly AiModel $model,
        public readonly OpenAiClient $client,
        public readonly NormkreditPricing $pricing,
        public readonly int $pricingVersionId,
        private readonly CreditWallet $wallet,
        private readonly ModelAccess $access,
        private readonly SpendGuard $spend,
    ) {
        $this->context = $context;
        $this->meter = new UsageMeter();
        $this->prompts = new PromptBuilder($client, $this->meter, $pricing);
    }

    /**
     * Susun pesan untuk model dalam batas token input per permintaan (pengaturan harga max_input_tokens).
     * Bila lewat, konteks riwayat diperkecil lalu pesan disusun ulang; konteks yang dipakai tersimpan di
     * $this->context (untuk log). Dipanggil sebelum biaya apa pun ditahan.
     *
     * @param  \Closure(ContextBundle): array  $build
     *
     * @throws RequestTooLargeException pesan penanya sendiri sudah melewati batas
     */
    public function compose(\Closure $build): array
    {
        $limit = (int) $this->pricing->get('max_input_tokens');
        $context = $this->context;

        while (true) {
            $messages = $build($context);
            $over = $limit > 0 ? Tokens::messages($messages) - $limit : 0;
            if ($over <= 0) {
                $this->context = $context;

                return $messages;
            }

            $context = $context->shrinkBy($over) ?? throw new RequestTooLargeException();
        }
    }

    /** Pemisah cache prompt per grup (akuntansi cache OpenAI; bukan isolasi riwayat). */
    public function cacheKey(): string
    {
        return 'normchat-group-' . $this->group->id;
    }

    /**
     * Tentukan panjang jawaban yang masih terbayar oleh kredit penanya, lalu tahan NK-nya.
     *
     * @param  int|null  $outputCeiling  plafon jawaban khusus mode; null = setelan superadmin
     * @param  int  $extraInputTokens  input yang belum ada di $messages (mis. hasil pencarian web)
     * @param  float  $extraUsd  biaya alat di luar token (mis. panggilan web search)
     *
     * @throws InsufficientBudgetException
     * @throws SpendLimitReachedException
     */
    public function planChat(array $messages, ?int $outputCeiling = null, int $extraInputTokens = 0, float $extraUsd = 0.0): RequestBudget
    {
        $balance = $this->wallet->balanceFor($this->group, $this->requester);
        // Kategori yang dikunci saldo tidak boleh memakai modal grup.
        $floor = $this->access->floor($this->model);
        $usable = max(0.0, min($balance->usable(), $balance->groupTotal - $floor));
        $inputTokens = self::estimateInputTokens($messages) + $extraInputTokens;
        try {
            $this->budget = RequestBudget::plan($this->model, $inputTokens, $usable, $this->pricing, $outputCeiling);
        } catch (InsufficientBudgetException $e) {
            if ($floor > 0 && $usable < $balance->usable()) {
                throw new InsufficientBudgetException($this->access->floorMessage($this->model, $balance->groupTotal));
            }
            if ($balance->dailyQuotaLeft !== null && $balance->dailyQuotaLeft < $balance->groupTotal) {
                // Yang habis batas harian dari owner, bukan saldo grup: "Isi Normkredit" tidak menolong.
                throw new InsufficientBudgetException($this->dailyLimitMessage($balance->dailyQuotaLeft, $inputTokens));
            }
            throw $e;
        }
        $this->reserve($this->budget->holdCredits, $this->budget->providerUsd + $extraUsd);

        return $this->budget;
    }

    private function dailyLimitMessage(float $left, int $inputTokens): string
    {
        $fmt = fn (float $value) => number_format($value, 2, ',', '.');
        $minimum = $this->pricing->creditsForCost(
            $this->model->costUsd($inputTokens, 0, RequestBudget::MIN_OUTPUT_TOKENS),
            $this->model->multiplier
        );

        return 'Batas pemakaian harianmu di grup ini tinggal ' . $fmt($left) . ' Normkredit, belum cukup untuk pertanyaan ini'
            . ' (butuh sekitar ' . $fmt($minimum) . '). Coba pertanyaan yang lebih singkat, pilih model yang lebih hemat, atau lanjutkan besok.';
    }

    /**
     * Tahan biaya OpenAI di SpendGuard lebih dulu, lalu Normkredit grup.
     *
     * @throws InsufficientBudgetException
     * @throws SpendLimitReachedException
     */
    public function reserve(float $credits, float $providerUsd): void
    {
        $this->spendHold = $this->spend->reserve('chat', $providerUsd, (int) $this->group->id);
        try {
            $this->hold = $this->wallet->hold($this->group, $this->requester, $credits, $this->model, [
                'pricing_version_id' => $this->pricingVersionId,
                'exchange_rate_id' => $this->pricing->exchangeRateId(),
            ]);
        } catch (InsufficientBudgetException $e) {
            $this->spend->release($this->spendHold);
            $this->spendHold = null;
            throw $e;
        }
        $this->creditHoldId = (int) $this->hold->id;
        $this->spendHold->update(['credit_hold_id' => $this->creditHoldId]);
    }

    public function creditHoldId(): ?int
    {
        return $this->creditHoldId;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * Chat completion dengan model kategori. Bila gagal karena model tidak tersedia,
     * dicoba sekali dengan model default katalog (dan ditagih dengan harga model itu).
     *
     * @return array{0: ChatCompletion, 1: AiModel} hasil dan model yang benar-benar dipakai
     */
    public function complete(array $messages, int $timeout = 60, array $options = []): array
    {
        $model = $this->model;
        $payload = [
            'messages' => $messages,
            'max_completion_tokens' => $this->budget?->maxOutputTokens ?? (int) $this->pricing->get('max_output_tokens'),
            'prompt_cache_key' => $this->cacheKey(),
            ...$options,
        ];

        $completion = $this->client->chat(['model' => $model->model_id, ...$payload], $timeout);

        $fallback = AiModel::defaultModel();
        if ($completion->looksLikeModelIssue() && $fallback && $fallback->model_id !== $model->model_id) {
            Log::warning('OpenAI model failed, retrying with default catalog model.', [
                'model' => $model->model_id,
                'fallback' => $fallback->model_id,
                'status' => $completion->status,
            ]);

            $model = $fallback;
            $completion = $this->client->chat(['model' => $model->model_id, ...$payload], $timeout);
        }

        if (! $completion->successful) {
            Log::warning('OpenAI response failed.', ['status' => $completion->status, 'body' => mb_substr($completion->errorBody, 0, 500)]);
        }
        $this->requestId ??= $completion->requestId;

        return [$completion, $model];
    }

    /**
     * Riset dengan web search (Responses API). Fallback ke model default katalog sama
     * seperti complete().
     *
     * @return array{0: ChatCompletion, 1: AiModel}
     */
    public function research(array $messages, int $timeout = 300): array
    {
        $model = $this->model;
        $maxOutput = $this->budget?->maxOutputTokens ?? (int) $this->pricing->get('max_output_tokens');
        $completion = $this->client->research($model->model_id, $messages, $maxOutput, $timeout, $this->cacheKey());

        $fallback = AiModel::defaultModel();
        if ($completion->looksLikeModelIssue() && $fallback && $fallback->model_id !== $model->model_id) {
            Log::warning('OpenAI research model failed, retrying with default catalog model.', [
                'model' => $model->model_id,
                'fallback' => $fallback->model_id,
                'status' => $completion->status,
            ]);

            $model = $fallback;
            $completion = $this->client->research($model->model_id, $messages, $maxOutput, $timeout, $this->cacheKey());
        }

        if (! $completion->successful) {
            Log::warning('OpenAI research failed.', ['status' => $completion->status, 'body' => mb_substr($completion->errorBody, 0, 500)]);
        }
        $this->requestId ??= $completion->requestId;

        return [$completion, $model];
    }

    /** Biaya per panggilan web search, ditagih dengan media multiplier seperti biaya alat lain. */
    public function recordWebSearch(int $calls): void
    {
        if ($calls <= 0) {
            return;
        }

        $this->meter->add(
            'web_search',
            'web_search',
            $calls * (float) config('normchat.web_search.usd_per_call', 0.01),
            $this->pricing->mediaMultiplier(),
            ['calls' => $calls],
        );
    }

    /** Catat biaya satu chat completion ke meter, dengan multiplier model yang dipakai. */
    public function recordChat(string $component, AiModel $model, ChatCompletion $completion): void
    {
        $usage = $completion->usage;
        $this->meter->add(
            $component,
            $model->model_id,
            $model->costUsd($usage['input_tokens'], $usage['cached_input_tokens'], $usage['output_tokens']),
            $model->multiplier,
            $usage,
        );
    }

    public function isOutputCapped(): bool
    {
        return (bool) $this->budget?->isCapped;
    }

    /**
     * Selesaikan kedua tahanan: Normkredit sesuai $credits, saldo OpenAI sesuai biaya nyata meter.
     *
     * @return float NK yang benar-benar terpotong
     */
    public function settle(float $credits): float
    {
        $this->settleSpend();
        if (! $this->hold) {
            return 0.0;
        }

        $charged = $this->wallet->settle($this->hold, $credits);
        $this->hold = null;

        return $charged;
    }

    /**
     * Balasan tidak ditagih ke grup: Normkredit dikembalikan penuh, tetapi biaya OpenAI yang sudah
     * terjadi (mis. jawaban gagal setelah transkripsi) tetap tercatat di SpendGuard.
     */
    public function releaseHold(): void
    {
        $this->settleSpend();
        if ($this->hold) {
            $this->wallet->release($this->hold);
            $this->hold = null;
        }
    }

    private function settleSpend(): void
    {
        if ($this->spendRecorded) {
            return;
        }
        $this->spendRecorded = true;

        $cost = $this->meter->providerCostUsd();
        if (! $this->spendHold) {
            // Biaya sebelum tahanan dibuat (transkripsi saat menyusun konteks, lalu permintaan ditolak).
            $this->spend->charge('chat', $cost, (int) $this->group->id);

            return;
        }

        $cost > 0 ? $this->spend->settle($this->spendHold, $cost) : $this->spend->release($this->spendHold);
        $this->spendHold = null;
    }

    public static function estimateInputTokens(array $messages): int
    {
        return Tokens::messages($messages);
    }
}
