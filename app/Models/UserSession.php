<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu perangkat yang sedang masuk (Akun > Perangkat & sesi). Dicatat App\Http\Middleware\TrackUserSession;
 * id sesi hanya disimpan sebagai hash.
 */
class UserSession extends Model
{
    protected $fillable = ['user_id', 'session_hash', 'device', 'ip', 'location', 'last_active_at', 'revoked_at'];

    protected $casts = [
        'last_active_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public static function hashFor(string $sessionId): string
    {
        return hash('sha256', $sessionId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Sesi yang belum dicabut dan belum melewati batas idle. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('last_active_at', '>=', now()->subDays((int) config('normchat.session_idle_days', 5)));
    }
}
