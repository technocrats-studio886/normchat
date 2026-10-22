<?php

namespace App\Services\Payments;

/**
 * Normalisasi status transaksi Midtrans/Interdotz yang datang dalam berbagai ejaan.
 */
final class GatewayStatus
{
    private const PAID = ['paid', 'settlement', 'capture', 'success', 'confirmed'];

    private const FAILED = ['expire', 'expired', 'cancel', 'cancelled', 'deny', 'denied', 'failure', 'failed'];

    public static function isPaid(string $status): bool
    {
        return in_array(strtolower($status), self::PAID, true);
    }

    public static function isFailed(string $status): bool
    {
        return in_array(strtolower($status), self::FAILED, true);
    }

    /** GatewayTransaction::PAID / FAILED / PENDING untuk status mentah. */
    public static function state(string $status): string
    {
        return match (true) {
            self::isPaid($status) => GatewayTransaction::PAID,
            self::isFailed($status) => GatewayTransaction::FAILED,
            default => GatewayTransaction::PENDING,
        };
    }
}
