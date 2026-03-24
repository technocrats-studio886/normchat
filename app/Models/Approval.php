<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $fillable = [
        'group_id',
        'user_id',
        'status',
        'requested_at',
        'approved_by',
        'rejected_by',
        'note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
