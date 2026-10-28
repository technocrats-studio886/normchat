<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pesan yang disembunyikan satu anggota dari tampilannya sendiri ("Hapus untuk saya"). */
class MessageHide extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'message_id'];
}
