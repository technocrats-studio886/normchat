<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

/** Pengumuman superadmin untuk semua user: popup atau banner, dengan jadwal tayang. */
class Announcement extends Model
{
    public const POPUP = 'popup';
    public const BANNER = 'banner';

    protected $fillable = ['title', 'body', 'display', 'tone', 'cta_label', 'cta_url', 'starts_at', 'ends_at', 'is_active', 'created_by', 'updated_by'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** Sedang tayang sekarang. */
    public function scopeShowing(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    /** draf | dijadwalkan | tayang | selesai */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'draf',
            $this->ends_at !== null && $this->ends_at->isPast() => 'selesai',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'dijadwalkan',
            default => 'tayang',
        };
    }

    /** Isi aman untuk HTML: di-escape dan tautan https jadi link; baris baru dari CSS (white-space: pre-line). */
    public function bodyHtml(): HtmlString
    {
        $escaped = e((string) $this->body);
        $linked = preg_replace('#https://[^\s<]+#', '<a href="$0" target="_blank" rel="noopener noreferrer nofollow" class="underline underline-offset-2">$0</a>', $escaped);

        return new HtmlString((string) $linked);
    }

    /** Tautan tombol membuka tab baru bila keluar dari aplikasi. */
    public function ctaIsExternal(): bool
    {
        return str_starts_with((string) $this->cta_url, 'https://');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
