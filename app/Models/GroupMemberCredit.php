<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kredit terlindungi milik satu orang di satu grup (hanya bisa dipakai dirinya). */
class GroupMemberCredit extends Model
{
    protected $fillable = ['group_id', 'user_id', 'protected_credits', 'spend_shared_first'];

    protected $casts = [
        'protected_credits' => 'float',
        'spend_shared_first' => 'boolean',
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
