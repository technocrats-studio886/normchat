<?php

namespace App\Support;

/**
 * Nama antrean Redis yang dikelola Horizon (config/horizon.php, docs/rancangan-worker-dan-file-nov.md).
 * Tiap antrean punya supervisor sendiri, jadi pekerjaan panjang di satu antrean tidak menahan antrean lain.
 */
final class Queues
{
    /** Jawaban Normi (ProcessGroupChatQueueJob). */
    public const AI = 'ai';

    /** Pekerjaan singkat yang ditunggu orang: push, pratinjau tautan, email peringatan admin. */
    public const NOTIFY = 'notify';

    /** Ekspor riwayat, cadangan, indeks dokumen, rekap mingguan. */
    public const DOCUMENTS = 'documents';

    /** Embedding dan ringkasan konteks Normi. */
    public const MAINTENANCE = 'maintenance';

    /** Job tanpa antrean khusus dan sisa job lama di Redis saat deploy. */
    public const DEFAULT = 'default';

    /** Label untuk halaman superadmin Antrean. */
    public const LABELS = [
        self::AI => 'Jawaban Normi',
        self::NOTIFY => 'Notifikasi',
        self::DOCUMENTS => 'Dokumen & cadangan',
        self::MAINTENANCE => 'Ingatan Normi',
        self::DEFAULT => 'Lainnya',
    ];

    public static function label(string $queue): string
    {
        return self::LABELS[$queue] ?? $queue;
    }
}
