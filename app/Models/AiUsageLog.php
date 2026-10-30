<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    /** Pemeliharaan ingatan Normi: dibayar dari saldo OpenAI, tidak ditagih ke grup. */
    public const MAINTENANCE_KINDS = ['context_summary', 'context_understanding', 'embedding'];

    /** Biaya OpenAI dari permintaan yang ditolak setelah ada biaya (mis. transkripsi), tidak ditagih. */
    public const REFUSED = 'refused';

    /** Log yang bukan jawaban Normi ke grup (tidak dihitung sebagai permintaan/jawaban di laporan). */
    public const NOT_ANSWERS = [...self::MAINTENANCE_KINDS, self::REFUSED];

    protected $fillable = [
        'group_id',
        'request_message_id',
        'response_message_id',
        'user_id',
        'kind',
        'model',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'breakdown_json',
        'provider_cost_usd',
        'usd_to_idr',
        'provider_cost_idr',
        'multiplier',
        'charged_credits',
        'balance_after',
        'balance_before',
        'provider_request_id',
        'cache_hit_percent',
        'pricing_version_id',
        'exchange_rate_id',
    ];

    protected $casts = [
        'breakdown_json' => 'array',
        'provider_cost_usd' => 'float',
        'usd_to_idr' => 'float',
        'provider_cost_idr' => 'float',
        'multiplier' => 'float',
        'charged_credits' => 'float',
        'balance_after' => 'float',
        'balance_before' => 'float',
        'cache_hit_percent' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
