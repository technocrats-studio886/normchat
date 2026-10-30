<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu perubahan saldo Normkredit grup. Ditulis hanya oleh App\Services\Credits\CreditWallet,
 * di transaksi yang sama dengan perubahan saldonya.
 */
class CreditLedgerEntry extends Model
{
    /** Saldo grup saat ledger mulai dipakai (ditulis sekali oleh migrasi). */
    public const OPENING = 'opening';
    public const DEPOSIT = 'deposit';
    public const HOLD = 'hold';
    public const SETTLE = 'settle';
    public const RELEASE = 'release';
    public const ADMIN_ADJUST = 'admin_adjust';

    public const UPDATED_AT = null;

    protected $table = 'credit_ledger';

    protected $fillable = [
        'group_id',
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reserved_before',
        'reserved_after',
        'credit_hold_id',
        'ai_usage_log_id',
        'contribution_id',
        'admin_action',
        'pricing_version_id',
        'exchange_rate_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'balance_before' => 'float',
        'balance_after' => 'float',
        'reserved_before' => 'float',
        'reserved_after' => 'float',
    ];
}
