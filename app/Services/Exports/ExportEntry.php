<?php

namespace App\Services\Exports;

/** Satu pesan yang sudah disiapkan untuk export (tanpa ketergantungan ke format PDF/DOCX). */
final class ExportEntry
{
    /**
     * @param  list<string>  $links
     * @param  array{question: string, options: list<array>, total_votes: int}|null  $poll
     * @param  array{kind: string, name: string, mime: string, url: ?string, image: ?string}|null  $attachment
     */
    public function __construct(
        public readonly string $sender,
        public readonly string $timestamp,
        public readonly string $content,
        public readonly array $links,
        public readonly ?array $poll,
        public readonly ?array $attachment,
    ) {
    }
}
