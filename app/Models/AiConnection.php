<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AiConnection extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decryptedAccessToken(): ?string
    {
        if (! $this->access_token) {
            return null;
        }

        try {
            return decrypt($this->access_token);
        } catch (\Throwable) {
            return null;
        }
    }
}
