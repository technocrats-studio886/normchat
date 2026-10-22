<?php

namespace App\Services\Ai;

use App\Models\AiModel;

/**
 * Balasan AI yang akan diposting ke grup. Hanya balasan `billable` yang ditagih;
 * pemberitahuan gagal tidak memotong Normkredit.
 *
 * @phpstan-type Attachment array{message_type: string, path: string, mime: string, original_name: string, size: ?int}
 */
final class AiReply
{
    private function __construct(
        public readonly string $kind,
        public readonly ?string $text,
        public readonly bool $billable,
        public readonly ?AiModel $model = null,
        public readonly ?array $attachment = null,
    ) {
    }

    public static function text(string $kind, string $text, AiModel $model): self
    {
        return new self($kind, $text, true, $model);
    }

    /** @param Attachment $attachment */
    public static function attachment(string $kind, ?string $caption, array $attachment, AiModel $model): self
    {
        return new self($kind, $caption, true, $model, $attachment);
    }

    /** Pesan untuk grup tanpa tagihan (mis. pembuatan file gagal). */
    public static function notice(string $kind, string $text): self
    {
        return new self($kind, $text, false);
    }

    /** Provider tidak memberi jawaban; asisten menampilkan pesan "AI sedang sibuk". */
    public static function failed(string $kind): self
    {
        return new self($kind, null, false);
    }

    public function withNote(string $note): self
    {
        return new self($this->kind, trim(($this->text ?? '') . "\n\n---\n" . $note), $this->billable, $this->model, $this->attachment);
    }
}
