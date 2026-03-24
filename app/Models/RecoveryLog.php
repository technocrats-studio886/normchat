<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class RecoveryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'backup_id',
        'restored_by',
        'restored_at',
        'reason',
    ];

    protected $casts = [
        'restored_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(GroupBackup::class, 'backup_id');
    }

    public function restorer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }
}
