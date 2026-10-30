<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ingatan Normi untuk satu grup: ringkasan grup, cakupannya, jangkar jendela riwayat, dan status
 * pemeliharaan (embedding, bangun ulang). Isinya teks chat: tidak ditampilkan ke siapa pun.
 */
class GroupContextSummary extends Model
{
    protected $fillable = [
        'group_id',
        'summary',
        'covered_until_message_id',
        'window_start_message_id',
        'source_message_count',
        'model',
        'input_tokens',
        'output_tokens',
        'provider_cost_usd',
        'embedded_until_message_id',
        'stale_at',
        'rebuild_requested_at',
    ];

    protected $casts = [
        'covered_until_message_id' => 'integer',
        'window_start_message_id' => 'integer',
        'embedded_until_message_id' => 'integer',
        'provider_cost_usd' => 'float',
        'stale_at' => 'datetime',
        'rebuild_requested_at' => 'datetime',
    ];

    public static function forGroup(int $groupId): self
    {
        return static::firstOrCreate(['group_id' => $groupId]);
    }

    /** Ringkasan grup yang boleh dipakai untuk pertanyaan dengan id $beforeMessageId. */
    public function usableSummaryBefore(int $beforeMessageId): ?string
    {
        if ($this->stale_at !== null || trim((string) $this->summary) === '' || $this->covered_until_message_id === null) {
            return null;
        }

        return $this->covered_until_message_id < $beforeMessageId ? $this->summary : null;
    }
}
