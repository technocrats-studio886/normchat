<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Biaya hitungan sistem vs biaya nyata dari OpenAI Costs API untuk satu hari UTC. */
class AiCostReconciliation extends Model
{
    protected $fillable = ['date', 'computed_usd', 'openai_usd', 'diff_usd', 'applied_usd', 'diff_percent', 'applied_at'];

    protected $casts = [
        'date' => 'date',
        'computed_usd' => 'float',
        'openai_usd' => 'float',
        'diff_usd' => 'float',
        'applied_usd' => 'float',
        'diff_percent' => 'float',
        'applied_at' => 'datetime',
    ];
}
