<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumen kanonik hasil konversi satu lampiran (App\Services\Ai\Documents\DocumentStore). Isinya (bagian ber-id,
 * tabel, teks) disimpan sebagai JSON di disk lampiran; baris ini menyimpan status dan ringkasan ukurannya.
 */
class MessageDocument extends Model
{
    public const PENDING = 'pending';

    public const READY = 'ready';

    public const FAILED = 'failed';

    public const UNSUPPORTED = 'unsupported';

    protected $fillable = ['message_id', 'group_id', 'status', 'converter', 'ext', 'version', 'page_count', 'chars', 'tokens',
        'outline', 'storage_disk', 'storage_path', 'attempts', 'error', 'converted_at'];

    protected $casts = [
        'outline' => 'array',
        'page_count' => 'integer',
        'chars' => 'integer',
        'tokens' => 'integer',
        'attempts' => 'integer',
        'converted_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
