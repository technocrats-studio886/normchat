<?php

namespace App\Services\Exports;

use Carbon\CarbonImmutable;

/** Satu pesan yang sudah disiapkan untuk ekspor (tanpa ketergantungan ke format PDF/DOCX). */
final class ExportEntry
{
    /**
     * @param  array{name: string, text: string}|null  $quote  pesan yang dibalas
     * @param  array{by: string, at: CarbonImmutable}|null  $deleted  penanda pesan yang dihapus untuk semua
     * @param  list<array{content: string, until: CarbonImmutable}>  $versions  isi sebelum diedit, terlama dulu
     * @param  array{question: string, options: list<array>, total_votes: int}|null  $poll
     * @param  array{kind: string, name: string, mime: string, image: ?string}|null  $attachment
     * @param  array{text: string, before: ?string, after: ?string}|null  $notice  pemberitahuan di tengah (bukan bubble)
     */
    public function __construct(
        public readonly string $senderName,
        public readonly bool $isMine,
        public readonly bool $isAi,
        public readonly string $color,
        public readonly ?string $avatar,
        public readonly CarbonImmutable $sentAt,
        public readonly string $content,
        public readonly ?array $quote,
        public readonly ?string $forwardedFrom,
        public readonly ?array $deleted,
        public readonly array $versions,
        public readonly ?CarbonImmutable $editedAt,
        public readonly ?array $poll,
        public readonly ?array $attachment,
        public bool $runStart = true,
        public bool $runEnd = true,
        public readonly ?array $notice = null,
    ) {
    }

    /** Baris pemberitahuan tengah: bergabung, keluar, grup dibuat, ganti nama, dll. */
    public static function notice(CarbonImmutable $at, string $text, ?string $before = null, ?string $after = null): self
    {
        return new self('', false, false, '', null, $at, '', null, null, null, [], null, null, null, notice: ['text' => $text, 'before' => $before, 'after' => $after]);
    }
}
