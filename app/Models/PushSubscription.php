<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu perangkat/browser yang menerima Web Push untuk seorang user (App\Services\Push\WebPush). */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'device', 'last_success_at', 'failures'];

    protected $hidden = ['auth_token', 'public_key'];

    protected $casts = [
        'last_success_at' => 'datetime',
    ];

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
