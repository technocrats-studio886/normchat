<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupTokenContribution extends Model
{
    protected $fillable = [
        'group_id',
        'user_id',
        'source',
        'credit_amount',
        'shared_credit_amount',
        'price_paid',
        'payment_reference',
    ];

    protected $casts = [
        'credit_amount' => 'float',
        'shared_credit_amount' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
