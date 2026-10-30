<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Estimasi biaya OpenAI yang ditahan untuk satu panggilan (App\Services\Ai\SpendGuard). */
class AiSpendHold extends Model
{
    public const HELD = 'held';
    public const SETTLED = 'settled';
    public const RELEASED = 'released';

    protected $fillable = ['kind', 'group_id', 'credit_hold_id', 'reserved_usd', 'actual_usd', 'status'];

    protected $casts = [
        'reserved_usd' => 'float',
        'actual_usd' => 'float',
    ];
}
