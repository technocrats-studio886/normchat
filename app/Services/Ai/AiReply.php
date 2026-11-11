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
        /** Fitur Normi yang disarankan lewat tombol di bawah jawaban (App\Enums\AiMode value), mis. "image". */
        public readonly ?string $suggestMode = null,
        /** Data tambahan yang disimpan di system_meta jawaban, mis. ZIP proyek Canvas. */
        public readonly array $meta = [],
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
        return new self($this->kind, trim(($this->text ?? '') . "\n\n---\n" . $note), $this->billable, $this->model, $this->attachment, $this->suggestMode, $this->meta);
    }

    public function withSuggestion(?string $mode): self
    {
        return new self($this->kind, $this->text, $this->billable, $this->model, $this->attachment, $mode, $this->meta);
    }

    public function withMeta(array $meta): self
    {
        return new self($this->kind, $this->text, $this->billable, $this->model, $this->attachment, $this->suggestMode, [...$this->meta, ...$meta]);
    }
}
