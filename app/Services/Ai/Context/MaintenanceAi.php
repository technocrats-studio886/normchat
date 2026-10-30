<?php

namespace App\Services\Ai\Context;

use App\Exceptions\SpendLimitReachedException;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\Group;
use App\Models\GroupToken;
use App\Services\Ai\ChatCompletion;
use App\Services\Ai\OpenAiClient;
use App\Services\Ai\SpendGuard;
use App\Services\Ai\Tokens;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;

/**
 * Panggilan OpenAI untuk memelihara ingatan Normi (ringkasan, pemahaman pertanyaan, embedding).
 * Dibayar dari saldo akun OpenAI, tidak memotong Normkredit grup: tetap lewat SpendGuard dan
 * tercatat di ai_usage_logs dengan charged_credits = 0. Bila ditolak SpendGuard atau gagal, hasilnya
 * null dan pemanggil melanjutkan tanpa hasil itu.
 */
class MaintenanceAi
{
    public const SUMMARY = 'context_summary';
    public const UNDERSTANDING = 'context_understanding';
    public const EMBEDDING = 'embedding';

    public function __construct(
        private readonly SpendGuard $spend,
        private readonly NormkreditPricing $pricing,
        private readonly PricingVersions $versions,
    ) {
    }

    /** Model kategori termurah yang aktif (sekarang Luna). */
    public function model(): ?AiModel
    {
        return AiModel::query()->where('is_active', true)->get()
            ->sortBy(fn (AiModel $model) => $model->input_usd_per_mtok + $model->output_usd_per_mtok)
            ->first();
    }

    /**
     * Chat completion berformat JSON; null bila tidak bisa dipanggil, gagal, atau bukan JSON.
     *
     * @return array{data: array, model: string, input_tokens: int, output_tokens: int, cost_usd: float}|null
     */
    public function json(string $kind, Group $group, array $messages, int $maxOutputTokens, int $timeout = 60): ?array
    {
        $model = $this->model();
        $client = OpenAiClient::server();
        if (! $model || ! $client) {
            return null;
        }

        try {
            $hold = $this->spend->reserve($kind, $model->costUsd(Tokens::messages($messages), 0, $maxOutputTokens), $group->id);
        } catch (SpendLimitReachedException) {
            return null;
        }

        $completion = $client->chat([
            'model' => $model->model_id,
            'messages' => $messages,
            'max_completion_tokens' => $maxOutputTokens,
            'response_format' => ['type' => 'json_object'],
            'prompt_cache_key' => 'normchat-maintenance-' . $kind,
        ], $timeout);

        if (! $completion->successful) {
            $this->spend->release($hold);

            return null;
        }

        $usage = $completion->usage;
        $cost = $model->costUsd($usage['input_tokens'], $usage['cached_input_tokens'], $usage['output_tokens']);
        $this->spend->settle($hold, $cost);
        $this->log($kind, $group, $model->model_id, $usage, $cost, $completion);

        $data = json_decode((string) $completion->text, true);

        return is_array($data) ? [
            'data' => $data,
            'model' => $model->model_id,
            'input_tokens' => (int) $usage['input_tokens'],
            'output_tokens' => (int) $usage['output_tokens'],
            'cost_usd' => $cost,
        ] : null;
    }

    /**
     * Embedding beberapa teks milik satu grup.
     *
     * @param  list<string>  $texts
     * @param  int  $timeout  detik; pendek untuk embedding pertanyaan di jalur jawaban
     * @return list<list<float>>|null
     */
    public function embed(Group $group, array $texts, int $timeout = 60): ?array
    {
        $client = OpenAiClient::server();
        if (! $client || $texts === []) {
            return null;
        }

        $config = (array) config('normchat.embedding');
        $rate = (float) $config['usd_per_mtok'];
        $estimate = array_sum(array_map(fn (string $text) => Tokens::text($text), $texts)) * $rate / 1_000_000;

        try {
            $hold = $this->spend->reserve(self::EMBEDDING, $estimate, $group->id);
        } catch (SpendLimitReachedException) {
            return null;
        }

        $result = $client->embed((string) $config['model'], $texts, (int) $config['dimensions'], $timeout);
        if ($result === null) {
            $this->spend->release($hold);

            return null;
        }

        $cost = $result['tokens'] * $rate / 1_000_000;
        $this->spend->settle($hold, $cost);
        $this->log(self::EMBEDDING, $group, (string) $config['model'], ['input_tokens' => $result['tokens'], 'cached_input_tokens' => 0, 'output_tokens' => 0], $cost);

        return $result['vectors'];
    }

    private function log(string $kind, Group $group, string $model, array $usage, float $costUsd, ?ChatCompletion $completion = null): void
    {
        $usdToIdr = $this->pricing->usdToIdr();

        // Isi ringkasan/maksud pertanyaan tidak disimpan di log: hanya token, biaya, dan id.
        AiUsageLog::create([
            'group_id' => $group->id,
            'kind' => $kind,
            'model' => $model,
            'input_tokens' => (int) $usage['input_tokens'],
            'cached_input_tokens' => (int) $usage['cached_input_tokens'],
            'output_tokens' => (int) $usage['output_tokens'],
            'breakdown_json' => ['lines' => [['component' => $kind, 'model' => $model, 'cost_usd' => $costUsd, 'multiplier' => 0, 'tokens' => $usage]]],
            'provider_cost_usd' => $costUsd,
            'usd_to_idr' => $usdToIdr,
            'provider_cost_idr' => round($costUsd * $usdToIdr, 4),
            'multiplier' => 0,
            'charged_credits' => 0,
            'balance_after' => GroupToken::forGroup((int) $group->id)->remaining_credits,
            'provider_request_id' => $completion?->requestId,
            'cache_hit_percent' => $usage['input_tokens'] > 0 ? round($usage['cached_input_tokens'] / $usage['input_tokens'] * 100, 2) : null,
            'pricing_version_id' => $this->versions->currentId(),
            'exchange_rate_id' => $this->pricing->exchangeRateId(),
        ]);
    }
}
