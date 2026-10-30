<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Vektor makna satu pesan untuk pencarian semantik (pgvector). */
class MessageEmbedding extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['message_id', 'group_id', 'embedding', 'model', 'content_hash'];

    protected $hidden = ['embedding'];
}
