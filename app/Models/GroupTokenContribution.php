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
        'token_amount',
        'price_paid',
        'payment_reference',
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
