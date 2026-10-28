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
    ) {
    }
}
