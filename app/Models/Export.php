<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'file_name',
        'storage_path',
        'file_type',
        'status',
        'created_by',
        'created_at',
        'filters',
        'message_count',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'filters' => 'array',
    ];

    /** Nama berkas unduhan: jam-menit-bulan-tanggal-tahun lalu nama grup tanpa spasi, mis. 194010272026-Kelas-BD.pdf. */
    public static function fileNameFor(string $groupName, CarbonInterface $at, string $extension): string
    {
        $name = trim((string) preg_replace(['#[\\\\/:*?"<>|]+#', '/\s+/'], ['', '-'], $groupName), '-') ?: 'Grup';

        return $at->format('HimdY') . '-' . $name . '.' . $extension;
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
