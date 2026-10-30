<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Permintaan @normi yang ditolak sebelum memanggil OpenAI, untuk metrik superadmin. */
class AiRequestRefusal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['group_id', 'user_id', 'reason', 'model'];
}
