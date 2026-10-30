<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Biaya OpenAI satu bulan kalender (WIB), dibandingkan dengan anggaran bulanan. */
class AiSpendPeriod extends Model
{
    protected $fillable = ['period', 'spent_usd'];

    protected $casts = [
        'spent_usd' => 'float',
    ];
}
