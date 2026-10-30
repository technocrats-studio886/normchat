<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kurs USD→IDR yang diisi superadmin. applied_rate = market_rate × (1 + selisih kartu). */
class ExchangeRate extends Model
{
    protected $fillable = ['market_rate', 'card_margin_percent', 'applied_rate', 'set_by', 'note'];

    protected $casts = [
        'market_rate' => 'float',
        'card_margin_percent' => 'float',
        'applied_rate' => 'float',
    ];

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by')->withTrashed();
    }
}
