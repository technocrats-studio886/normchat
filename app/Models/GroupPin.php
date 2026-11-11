<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pesan yang disematkan di atas chat grup (App\Services\Chat\GroupPins). */
class GroupPin extends Model
{
    protected $fillable = ['group_id', 'message_id', 'pinned_by'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}
