<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
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
    ];

    protected $casts = [
        'breakdown_json' => 'array',
        'provider_cost_usd' => 'float',
        'usd_to_idr' => 'float',
        'provider_cost_idr' => 'float',
        'multiplier' => 'float',
        'charged_credits' => 'float',
        'balance_after' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
