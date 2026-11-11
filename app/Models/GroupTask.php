<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu butir tugas/checklist bersama di grup (App\Services\Chat\GroupTasks). */
class GroupTask extends Model
{
    public const TITLE_MAX = 300;

    protected $fillable = ['group_id', 'title', 'source_message_id', 'created_by', 'assignee_id', 'due_date', 'done_at', 'done_by'];

    protected $casts = [
        'due_date' => 'date',
        'done_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function isDone(): bool
    {
        return $this->done_at !== null;
    }
}
