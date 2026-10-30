<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Panggilan OpenAI ditolak karena anggaran bulanan atau saldo OpenAI (di atas cadangan) tidak cukup
 * (App\Services\Ai\SpendGuard). Pesannya untuk penanya; alasannya untuk log superadmin.
 */
class SpendLimitReachedException extends RuntimeException
{
    public const USER_MESSAGE = 'Normi AI sedang tidak bisa dipakai. Coba lagi nanti.';

    public function __construct(public readonly string $limit)
    {
        parent::__construct(self::USER_MESSAGE);
    }
}
