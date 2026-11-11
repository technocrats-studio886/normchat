<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Potongan isi dokumen lampiran grup beserta embedding-nya (App\Services\Ai\Documents\DocumentIndex). */
class DocumentChunk extends Model
{
    protected $fillable = ['group_id', 'message_id', 'chunk_index', 'content', 'embedding'];

    protected $hidden = ['embedding'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
