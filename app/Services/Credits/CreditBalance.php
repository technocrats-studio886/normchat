<?php

namespace App\Services\Credits;

/** Kredit grup yang boleh dipakai satu orang saat ini. */
final class CreditBalance
{
    public function __construct(
        public readonly float $groupTotal,
        /** Sisa kuota harian dari owner untuk orang ini; null = tanpa batas. */
        public readonly ?float $dailyQuotaLeft,
    ) {
    }

    public function usable(): float
    {
        $usable = $this->dailyQuotaLeft === null ? $this->groupTotal : min($this->groupTotal, $this->dailyQuotaLeft);

        return round(max(0.0, $usable), 4);
    }
}
