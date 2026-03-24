<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = ['group_id', 'sender_type', 'sender_id', 'content'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MessageVersion::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
