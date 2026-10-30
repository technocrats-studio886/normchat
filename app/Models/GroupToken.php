<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saldo Normkredit grup (1 NK = Rp120 secara default), disimpan 4 desimal; tampil 2 desimal.
 *
 * remaining_credits = kredit grup yang belum ditahan; dipakai penuh oleh semua anggota.
 * Perubahan saldo hanya lewat App\Services\Credits\CreditWallet.
 */
class GroupToken extends Model
{
    protected $fillable = [
        'group_id',
        'total_credits',
        'used_credits',
        'remaining_credits',
        'reserved_credits',
        'unlocked_models',
    ];

    protected $casts = [
        'total_credits' => 'float',
        'used_credits' => 'float',
        'remaining_credits' => 'float',
        'reserved_credits' => 'float',
        'unlocked_models' => 'array',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GroupTokenContribution::class, 'group_id', 'group_id');
    }

    /** Saldo untuk ditampilkan (2 desimal, dibulatkan ke bawah agar tidak tampil lebih dari yang ada). */
    public function getCreditsAttribute(): float
    {
        return floor(round((float) $this->remaining_credits * 100, 6)) / 100;
    }

    public static function forGroup(int $groupId): self
    {
        return static::firstOrCreate(['group_id' => $groupId], [
            'total_credits' => 0,
            'used_credits' => 0,
            'remaining_credits' => 0,
            'reserved_credits' => 0,
        ]);
    }
}
