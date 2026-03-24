<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SubscriptionSeat extends Model
{
    protected $fillable = ['subscription_id', 'user_id', 'seat_type', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
