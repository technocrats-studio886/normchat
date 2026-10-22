<?php

namespace App\Services\Ai;

/**
 * Mencatat setiap komponen API yang benar-benar dipakai satu request (model chat,
 * mesin gambar, transkripsi), masing-masing dengan multiplier-nya.
 */
final class UsageMeter
{
    /** @var list<array{component: string, model: string, cost_usd: float, multiplier: float, tokens: array}> */
    private array $lines = [];

    public function add(string $component, string $model, float $costUsd, float $multiplier, array $tokens = []): void
    {
        $this->lines[] = [
            'component' => $component,
            'model' => $model,
            'cost_usd' => $costUsd,
            'multiplier' => $multiplier,
            'tokens' => $tokens,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /** Biaya asli ke OpenAI (USD). */
    public function providerCostUsd(): float
    {
        return array_sum(array_column($this->lines, 'cost_usd'));
    }

    /** Biaya × multiplier masing-masing komponen (USD), dasar konversi ke Normkredit. */
    public function weightedCostUsd(): float
    {
        return array_sum(array_map(fn (array $line) => $line['cost_usd'] * $line['multiplier'], $this->lines));
    }

    /** Token komponen pertama dengan nama $component (dipakai untuk kolom ringkas di log). */
    public function tokensOf(string $component): array
    {
        foreach ($this->lines as $line) {
            if ($line['component'] === $component) {
                return $line['tokens'];
            }
        }

        return [];
    }

    public function lines(): array
    {
        return $this->lines;
    }
}
