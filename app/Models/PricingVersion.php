<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salinan pengaturan harga Normkredit dan tarif model pada satu waktu. Tidak pernah diubah;
 * setiap perubahan superadmin membuat versi baru (App\Services\Pricing\PricingVersions).
 */
class PricingVersion extends Model
{
    protected $fillable = ['effective_from', 'settings', 'models', 'created_by', 'note'];

    protected $casts = [
        'effective_from' => 'datetime',
        'settings' => 'array',
        'models' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
