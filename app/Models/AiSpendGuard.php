<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Status anggaran akun OpenAI (satu baris): anggaran bulanan, saldo prabayar terakhir yang diisi
 * superadmin beserta cadangannya, dan biaya yang sedang ditahan request berjalan.
 */
class AiSpendGuard extends Model
{
    protected $table = 'ai_spend_guard';

    protected $fillable = [
        'monthly_budget_usd',
        'budget_set_at',
        'balance_usd',
        'reserve_usd',
        'balance_set_at',
        'balance_set_by',
        'spent_since_set_usd',
        'reserved_usd',
    ];

    protected $casts = [
        'monthly_budget_usd' => 'float',
        'balance_usd' => 'float',
        'reserve_usd' => 'float',
        'balance_set_at' => 'datetime',
        'budget_set_at' => 'datetime',
        'spent_since_set_usd' => 'float',
        'reserved_usd' => 'float',
    ];

    public static function current(): self
    {
        return static::query()->orderBy('id')->first() ?? static::create([]);
    }

    /** Saldo OpenAI yang masih boleh dipakai sebelum menyentuh cadangan; null = saldo belum diisi. */
    public function balanceHeadroomUsd(): ?float
    {
        if ($this->balance_usd === null) {
            return null;
        }

        return $this->balance_usd - $this->reserve_usd - $this->spent_since_set_usd - $this->reserved_usd;
    }
}
