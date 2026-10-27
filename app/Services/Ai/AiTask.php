<?php

namespace App\Services\Ai;

use App\Exceptions\InsufficientBudgetException;
use App\Models\AiModel;
use App\Models\CreditHold;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use App\Services\Credits\ModelAccess;
use App\Services\NormkreditPricing;
use Illuminate\Support\Facades\Log;

/**
 * Satu permintaan AI dari seorang anggota: konteksnya, model kategori yang dipilih
 * grup, pencatat biaya, dan Normkredit yang ditahan untuknya.
 */
final class AiTask
{
    public readonly PromptBuilder $prompts;

    public readonly UsageMeter $meter;

    private ?CreditHold $hold = null;

    private ?RequestBudget $budget = null;

    /** @param list<Message> $recentMessages */
    public function __construct(
        public readonly Group $group,
        public readonly User $requester,
        public readonly Message $message,
        public readonly array $recentMessages,
        public readonly AiModel $model,
        public readonly OpenAiClient $client,
        public readonly NormkreditPricing $pricing,
        private readonly CreditWallet $wallet,
        private readonly ModelAccess $access,
    ) {
        $this->meter = new UsageMeter();
        $this->prompts = new PromptBuilder($client, $this->meter, $pricing);
    }

    /**
     * Tentukan panjang jawaban yang masih terbayar oleh kredit penanya, lalu tahan NK-nya.
     *
     * @param  int|null  $outputCeiling  plafon jawaban khusus mode; null = setelan superadmin
     * @param  int  $extraInputTokens  input yang belum ada di $messages (mis. hasil pencarian web)
     *
     * @throws InsufficientBudgetException
     */
    public function planChat(array $messages, ?int $outputCeiling = null, int $extraInputTokens = 0): RequestBudget
    {
        $balance = $this->wallet->balanceFor($this->group, $this->requester);
        // Kategori yang dikunci saldo tidak boleh memakai modal grup.
        $floor = $this->access->floor($this->model);
        $usable = max(0.0, min($balance->usable(), $balance->groupTotal - $floor));
        $inputTokens = self::estimateInputTokens($messages) + $extraInputTokens;
        try {
            $this->budget = RequestBudget::plan($this->model, $inputTokens, $usable, $this->pricing, $outputCeiling);
        } catch (InsufficientBudgetException $e) {
            throw $floor > 0 && $usable < $balance->usable()
                ? new InsufficientBudgetException($this->access->floorMessage($this->model, $balance->groupTotal))
                : $e;
        }
        $this->reserve($this->budget->holdCredits);

        return $this->budget;
    }

    /** @throws InsufficientBudgetException */
    public function reserve(float $credits): void
    {
        $this->hold = $this->wallet->hold($this->group, $this->requester, $credits, $this->model);
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
        $completion = $this->client->research($model->model_id, $messages, $maxOutput, $timeout);

        $fallback = AiModel::defaultModel();
        if ($completion->looksLikeModelIssue() && $fallback && $fallback->model_id !== $model->model_id) {
            Log::warning('OpenAI research model failed, retrying with default catalog model.', [
                'model' => $model->model_id,
                'fallback' => $fallback->model_id,
                'status' => $completion->status,
            ]);

            $model = $fallback;
            $completion = $this->client->research($model->model_id, $messages, $maxOutput, $timeout);
        }

        if (! $completion->successful) {
            Log::warning('OpenAI research failed.', ['status' => $completion->status, 'body' => mb_substr($completion->errorBody, 0, 500)]);
        }

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

    /** @return float NK yang benar-benar terpotong */
    public function settle(float $credits): float
    {
        if (! $this->hold) {
            return 0.0;
        }

        $charged = $this->wallet->settle($this->hold, $credits);
        $this->hold = null;

        return $charged;
    }

    public function releaseHold(): void
    {
        if ($this->hold) {
            $this->wallet->release($this->hold);
            $this->hold = null;
        }
    }

    /** ≈4 karakter per token teks, ≈1.100 token per gambar. */
    public static function estimateInputTokens(array $messages): int
    {
        $chars = 0;
        $images = 0;
        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            if (is_string($content)) {
                $chars += mb_strlen($content);
                continue;
            }

            foreach ((array) $content as $part) {
                if (($part['type'] ?? '') === 'image_url') {
                    $images++;
                } else {
                    $chars += mb_strlen((string) ($part['text'] ?? ''));
                }
            }
        }

        return (int) ceil($chars / 4) + $images * 1_100;
    }
}
