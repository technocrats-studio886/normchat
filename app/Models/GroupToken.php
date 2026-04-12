<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupToken extends Model
{
    protected $fillable = [
        'group_id',
        'total_tokens',
        'used_tokens',
        'remaining_tokens',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GroupTokenContribution::class, 'group_id', 'group_id');
    }

    // ── Normkredit helpers (1 normkredit = 2.500 token = Rp2.500) ────

    public function getCreditsAttribute(): float
    {
        return round($this->remaining_tokens / 2_500, 2);
    }

    public function getTotalCreditsAttribute(): float
    {
        return round($this->total_tokens / 2_500, 2);
    }

    public function addTokens(int $amount): void
    {
        $this->increment('total_tokens', $amount);
        $this->increment('remaining_tokens', $amount);
    }

    public function addCredits(float $credits): void
    {
        $tokens = (int) ($credits * 2_500);
        $this->addTokens($tokens);
    }

    /**
     * Consume tokens from the group balance.
     * $actualTokens = tokens actually used by the LLM
     * $multiplier = model cost multiplier
     *
     * Returns the effective tokens charged, or false if insufficient balance.
     */
    public function consumeTokens(int $actualTokens, float $multiplier = 1.0): int|false
    {
        $effectiveTokens = (int) ceil($actualTokens * $multiplier);

        if ($this->remaining_tokens < $effectiveTokens) {
            return false;
        }

        $this->increment('used_tokens', $effectiveTokens);
        $this->decrement('remaining_tokens', $effectiveTokens);

        return $effectiveTokens;
    }

    /**
     * Check if group has enough tokens for an estimated usage.
     */
    public function hasEnoughTokens(int $estimatedActualTokens, float $multiplier = 1.0): bool
    {
        $needed = (int) ceil($estimatedActualTokens * $multiplier);
        return $this->remaining_tokens >= $needed;
    }

    /**
     * Format remaining tokens for display.
     */
    public function formattedRemaining(): string
    {
        if ($this->remaining_tokens >= 1_000_000) {
            return number_format($this->remaining_tokens / 1_000_000, 1) . 'M';
        }
        if ($this->remaining_tokens >= 1_000) {
            return number_format($this->remaining_tokens / 1_000, 1) . 'K';
        }
        return number_format($this->remaining_tokens);
    }
}
