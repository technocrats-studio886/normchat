<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Peringatan untuk superadmin (banner + email), satu baris per kejadian (kolom key unik). */
class AdminAlert extends Model
{
    protected $fillable = ['key', 'level', 'title', 'body', 'resolved_at'];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at')->latest('id');
    }
}
