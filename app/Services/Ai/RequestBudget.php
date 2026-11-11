<?php

namespace App\Services\Ai;

use App\Exceptions\InsufficientBudgetException;
use App\Models\AiModel;
use App\Services\NormkreditPricing;

/**
 * Batas satu request chat: panjang jawaban maksimal menyesuaikan kredit yang boleh
 * dipakai penanya, sehingga model mahal tetap bisa dipakai dengan kredit kecil
 * (jawabannya saja yang lebih pendek) tanpa saldo bisa jebol.
 */
final class RequestBudget
{
    /** Jawaban sependek ini masih berguna; di bawahnya request ditolak. */
    public const MIN_OUTPUT_TOKENS = 300;

    private function __construct(
        public readonly int $maxOutputTokens,
        public readonly float $holdCredits,
        public readonly bool $isCapped,
        /** Estimasi biaya OpenAI (USD) dengan output maksimal, ditahan di SpendGuard. */
        public readonly float $providerUsd,
    ) {
    }

    /**
     * @param  int  $inputTokens  perkiraan token input (konteks + prompt)
     * @param  float  $usableCredits  kredit yang boleh dipakai penanya saat ini
     * @param  int|null  $outputCeiling  plafon jawaban khusus mode (mis. Canvas); null = setelan superadmin
     * @param  int  $minOutput  jawaban terpendek yang masih berguna untuk fitur ini. Model reasoning menghabiskan
     *                          sebagian token output untuk "berpikir"; Canvas dengan ±700 token kembali kosong
     *                          (kasus Gugur Ganjal 11 Nov 2026), jadi fitur berat butuh batas lebih tinggi.
     * @param  string|null  $feature  nama fitur untuk pesan penolakan, mis. "Canvas"
     *
     * @throws InsufficientBudgetException
     */
    public static function plan(AiModel $model, int $inputTokens, float $usableCredits, NormkreditPricing $pricing, ?int $outputCeiling = null, int $minOutput = self::MIN_OUTPUT_TOKENS, ?string $feature = null): self
    {
        $configuredMax = $outputCeiling ?? (int) $pricing->get('max_output_tokens');
        $maxPerRequest = (float) $pricing->get('max_credits_per_request');
        $affordable = $maxPerRequest > 0 ? min($usableCredits, $maxPerRequest) : $usableCredits;

        $inputCredits = $pricing->creditsForCost($model->costUsd($inputTokens, 0, 0), $model->multiplier);
        $creditsPerOutputToken = $model->output_usd_per_mtok / 1_000_000 * $model->multiplier
            * $pricing->usdToIdr() / $pricing->rupiahPerCredit();

        $affordableOutput = $creditsPerOutputToken > 0
            ? (int) floor(max(0.0, $affordable - $inputCredits) / $creditsPerOutputToken)
            : $configuredMax;
        $maxOutput = min($configuredMax, $affordableOutput);

        $minimum = max(self::MIN_OUTPUT_TOKENS, min($minOutput, $configuredMax));
        if ($maxOutput < $minimum) {
            throw new InsufficientBudgetException(self::refusal($model, $inputTokens, $usableCredits, $maxPerRequest, $pricing, $minimum, $feature));
        }

        $providerUsd = $model->costUsd($inputTokens, 0, $maxOutput);
        $hold = min($affordable, $pricing->creditsForCost($providerUsd, $model->multiplier));

        // Dibulatkan ke bawah (0,0001 NC) agar tahanan tidak pernah melebihi kredit yang boleh dipakai.
        return new self($maxOutput, floor(round($hold * 10000, 6)) / 10000, $maxOutput < $configuredMax, $providerUsd);
    }

    private static function refusal(AiModel $model, int $inputTokens, float $usable, float $maxPerRequest, NormkreditPricing $pricing, int $minOutput = self::MIN_OUTPUT_TOKENS, ?string $feature = null): string
    {
        $fmt = fn (float $value) => number_format($value, 2, ',', '.');
        $minimum = $pricing->creditsForCost($model->costUsd($inputTokens, 0, $minOutput), $model->multiplier);

        if ($maxPerRequest > 0 && $minimum > $maxPerRequest) {
            return $feature !== null
                ? "Permintaan {$feature} ini terlalu besar untuk sekali jawab. Persingkat permintaannya atau pilih model lain."
                : 'Pesan ini terlalu panjang untuk dijawab. Persingkat pesan atau lampirannya, atau pilih model lain.';
        }

        return $feature !== null
            ? "Normkredit grup belum cukup untuk {$feature} dengan model {$model->label} (sisa " . $fmt($usable) . ', butuh sekitar ' . $fmt($minimum)
                . '). Isi Normkredit atau pilih model yang lebih hemat.'
            : 'Normkredit tidak cukup untuk model ' . $model->label . ' (sisa ' . $fmt($usable) . ', butuh sekitar ' . $fmt($minimum)
                . '). Isi Normkredit atau pilih model lain.';
    }
}
