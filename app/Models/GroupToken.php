<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saldo Normkredit grup yang terlihat (1 NK = Rp120 secara default, desimal 0,01 NK).
 *
 * remaining_credits = kredit bersama + seluruh kredit terlindungi yang belum ditahan.
 * Perubahan saldo hanya lewat App\Services\Credits\CreditWallet.
 */
class GroupToken extends Model
{
    protected $fillable = [
        'group_id',
        'total_credits',
        'used_credits',
        'remaining_credits',
        'shared_credits',
        'reserved_credits',
    ];

    protected $casts = [
        'total_credits' => 'float',
        'used_credits' => 'float',
        'remaining_credits' => 'float',
        'shared_credits' => 'float',
        'reserved_credits' => 'float',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GroupTokenContribution::class, 'group_id', 'group_id');
    }

    public function getCreditsAttribute(): float
    {
        return round((float) $this->remaining_credits, 2);
    }

    public static function forGroup(int $groupId): self
    {
        return static::firstOrCreate(['group_id' => $groupId], [
            'total_credits' => 0,
            'used_credits' => 0,
            'remaining_credits' => 0,
            'shared_credits' => 0,
            'reserved_credits' => 0,
        ]);
    }
}
