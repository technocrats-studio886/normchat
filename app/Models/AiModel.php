<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Katalog model chat yang dikelola superadmin. Harga dalam USD per 1 juta token.
 */
class AiModel extends Model
{
    protected $fillable = [
        'model_id',
        'label',
        'description',
        'input_usd_per_mtok',
        'cached_input_usd_per_mtok',
        'output_usd_per_mtok',
        'multiplier',
        'is_active',
        'is_default',
        'sort_order',
        'image_model',
        'image_model_precise',
        'unlock_multiple',
        'lock_multiple',
    ];

    protected $casts = [
        'input_usd_per_mtok' => 'float',
        'cached_input_usd_per_mtok' => 'float',
        'output_usd_per_mtok' => 'float',
        'multiplier' => 'float',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'unlock_multiple' => 'float',
        'lock_multiple' => 'float',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Kategori ini hanya bisa dipakai bila saldo grup cukup (App\Services\Credits\ModelAccess). */
    public function isGated(): bool
    {
        return (float) $this->unlock_multiple > 0;
    }

    /** Mesin gambar internal kategori ini; versi presisi bila diminta dan tersedia. */
    public function imageEngine(bool $precise = false): string
    {
        return $precise && $this->image_model_precise ? $this->image_model_precise : $this->image_model;
    }

    public static function defaultModel(): ?self
    {
        return static::active()->where('is_default', true)->first()
            ?? static::active()->first();
    }

    /**
     * Biaya provider dalam USD. Cached input ditagih dengan tarif cached bila ada,
     * sisanya dengan tarif input biasa.
     */
    public function costUsd(int $inputTokens, int $cachedInputTokens, int $outputTokens): float
    {
        $cached = min(max(0, $cachedInputTokens), max(0, $inputTokens));
        $uncached = max(0, $inputTokens) - $cached;
        $cachedRate = $this->cached_input_usd_per_mtok ?? $this->input_usd_per_mtok;

        return ($uncached * $this->input_usd_per_mtok
            + $cached * $cachedRate
            + max(0, $outputTokens) * $this->output_usd_per_mtok) / 1_000_000;
    }
}
