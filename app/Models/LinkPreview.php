<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Judul, nama situs, deskripsi, dan gambar sebuah tautan (dibaca FetchLinkPreviewJob). */
class LinkPreview extends Model
{
    public const PENDING = 'pending';
    public const OK = 'ok';
    public const FAILED = 'failed';

    /** Gagal dibaca: dicoba lagi paling cepat sesudah ini. Berhasil: diperbarui bila lebih tua dari ini. */
    public const RETRY_FAILED_HOURS = 24;
    public const REFRESH_OK_DAYS = 7;

    protected $fillable = ['url_hash', 'url', 'title', 'site_name', 'description', 'image_url', 'status', 'fetched_at'];

    protected $casts = ['fetched_at' => 'datetime'];

    public static function hashFor(string $url): string
    {
        return hash('sha256', $url);
    }

    /** @return Collection<string, self> pratinjau per URL */
    public static function forUrls(array $urls): Collection
    {
        if ($urls === []) {
            return collect();
        }

        return static::query()->whereIn('url_hash', array_map(fn ($url) => static::hashFor($url), $urls))->get()->keyBy('url');
    }

    public function needsFetch(): bool
    {
        return match ($this->status) {
            self::FAILED => $this->fetched_at === null || $this->fetched_at->lt(now()->subHours(self::RETRY_FAILED_HOURS)),
            self::OK => $this->fetched_at === null || $this->fetched_at->lt(now()->subDays(self::REFRESH_OK_DAYS)),
            default => $this->updated_at === null || $this->updated_at->lt(now()->subMinutes(10)),
        };
    }

    public function domain(): string
    {
        return preg_replace('/^www\./i', '', (string) parse_url($this->url, PHP_URL_HOST));
    }

    /** Bentuk untuk browser (bubble chat, realtime); null bila belum/gagal dibaca. */
    public function toCard(): ?array
    {
        if ($this->status !== self::OK) {
            return null;
        }

        return [
            'url' => $this->url,
            'title' => $this->title,
            'site_name' => $this->site_name,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'domain' => $this->domain(),
        ];
    }
}
