<?php

namespace App\Services\Credits;

/** Kredit yang boleh dipakai satu orang di grup saat ini. */
final class CreditBalance
{
    public function __construct(
        public readonly float $groupTotal,
        public readonly float $shared,
        public readonly float $protected,
        /** Sisa kuota harian kredit bersama untuk orang ini; null = tanpa batas. */
        public readonly ?float $sharedQuotaLeft,
        public readonly bool $sharedFirst,
    ) {
    }

    public function usableShared(): float
    {
        return $this->sharedQuotaLeft === null ? $this->shared : min($this->shared, $this->sharedQuotaLeft);
    }

    public function usable(): float
    {
        return round($this->usableShared() + $this->protected, 4);
    }
}
