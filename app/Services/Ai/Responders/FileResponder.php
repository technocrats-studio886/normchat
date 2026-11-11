<?php

namespace App\Services\Ai\Responders;

use App\Models\Message;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\Files\FileBuilder;
use App\Services\Ai\Files\FileFormats;
use App\Services\Ai\Files\FileRequest;
use App\Services\Ai\PromptBuilder;
use App\Services\Files\StoredFiles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fitur "Buat file" (@normi-file, atau permintaan file lewat @normi): Normi menulis isi file sebagai JSON
 * terstruktur, lalu server merendernya (FileBuilder). Format apa pun: PDF, Word, Excel, PowerPoint, CSV, JSON,
 * HTML, kode program, dan ZIP untuk banyak file sekaligus. "Ubah foto ini jadi PDF" dikerjakan langsung
 * tanpa memanggil model.
 */
final class FileResponder implements AiResponder
{
    private const MAX_OUTPUT_TOKENS = 16_000;

    /** Jenis log pemakaian tetap "document" seperti sebelum fitur ini (metrik superadmin). */
    private const KIND = 'document';

    public const PROMPT = "MODE BUAT FILE. Kamu membuat file yang diminta anggota grup. File akan langsung dikirim ke grup sebagai lampiran.\n"
        . "Balas HANYA dengan JSON valid:\n"
        . '{"title": "judul singkat", "summary": "1 kalimat Bahasa Indonesia untuk grup tentang file yang dibuat", "files": [FILE, ...]}' . "\n"
        . "FILE berbentuk {\"name\": \"nama-file.ext\", ...isi sesuai format}:\n"
        . "- pdf, docx, md, txt: \"content\" berisi Markdown lengkap (judul #, subjudul ##, paragraf, list, tabel Markdown).\n"
        . "- xlsx: \"sheets\": [{\"name\": \"Nama sheet\", \"rows\": [[\"Header 1\", \"Header 2\"], [\"teks\", 1500000], [\"Total\", \"=SUM(B2:B10)\"]]}]."
        . " Angka ditulis sebagai angka (tanpa titik ribuan), rumus diawali \"=\".\n"
        . "- pptx: \"slides\": [{\"title\": \"Judul presentasi\", \"subtitle\": \"…\"}, {\"title\": \"Judul slide\", \"bullets\": [\"poin\", {\"text\": \"sub-poin\", \"level\": 1}]}]."
        . " Slide pertama slide judul; 3-6 poin ringkas per slide.\n"
        . "- csv/tsv: \"sheets\" seperti xlsx (satu sheet) atau \"content\" teks CSV.\n"
        . "- format teks lain (json, html, xml, yaml, sql, svg, ics, vcf, srt, kode program seperti py/js/ts/php/java/kt/dart/go/c/cpp/cs/sh, dll.): \"content\" berisi isi file utuh tanpa code fence.\n"
        . "Aturan:\n"
        . "- Isi harus lengkap, benar, dan langsung bisa dipakai; jangan menulis \"...\" atau \"isi sendiri\".\n"
        . "- Satu permintaan biasanya satu file. Proyek atau beberapa berkas: tulis semuanya di \"files\" dengan jalur folder (mis. \"src/App.jsx\"), server membungkusnya jadi ZIP.\n"
        . "- Nama file huruf kecil, pakai tanda hubung, ekstensi sesuai format.\n"
        . '- Kode program: lengkap dan bisa dijalankan, sertakan README.md singkat bila proyek terdiri dari beberapa file.';

    public function __construct(private readonly ?FileRequest $request = null)
    {
    }

    public function activity(): string
    {
        return AiActivity::FILE;
    }

    public function respond(AiTask $task): AiReply
    {
        $request = $this->request ?? FileRequest::forFileMode((string) $task->message->content);
        $format = $request->format();
        $task->progress(AiActivity::FILE, $format !== null
            ? 'sedang membuat file ' . FileFormats::label($format)
            : 'sedang membuat file');

        $images = $format === 'pdf' && $request->isConversion ? $this->images($task->message) : [];
        if ($images !== []) {
            return $this->imagesToPdf($task, $images);
        }

        $instruction = $format !== null
            ? 'Format yang diminta: ' . implode(', ', array_map(fn ($ext) => FileFormats::label($ext) . " (.{$ext})", $request->formats)) . '.'
            : 'Pilih format file yang paling sesuai dengan permintaan.';
        $messages = $task->compose(fn (ContextBundle $context) => $task->prompts->conversation(
            PromptBuilder::chatSystemPrompt($task->group) . "\n\n" . self::PROMPT . "\n" . $instruction,
            $context,
            $task->message
        ));

        $task->planChat($messages, self::MAX_OUTPUT_TOKENS);
        [$completion, $model] = $task->complete($messages, 240, ['response_format' => ['type' => 'json_object']]);
        if (! $completion->hasText()) {
            return AiReply::failed(self::KIND);
        }
        $task->recordChat(self::KIND, $model, $completion);

        $spec = self::parse((string) $completion->text, $format);
        $title = $spec['title'];

        try {
            $file = app(FileBuilder::class)->build($spec['files'], $format, $title, forceZip: $format === 'zip');
        } catch (Throwable $e) {
            Log::error('AI file build failed.', ['format' => $format, 'error' => $e->getMessage()]);

            return AiReply::notice(self::KIND, $completion->finishReason === 'length'
                ? 'Filenya terlalu panjang untuk dibuat sekaligus. Coba minta bagian yang lebih kecil.'
                : 'Gagal membuat file. Coba lagi.');
        }

        $reply = $this->store($task, $file, $spec['summary'], $model);

        return $completion->finishReason === 'length'
            ? $reply->withNote('Isi file mungkin terpotong karena terlalu panjang.')
            : $reply;
    }

    /**
     * JSON dari model. Bila model membalas teks biasa (tidak sesuai format), teks itu dipakai sebagai isi file.
     *
     * @return array{title: string, summary: string, files: list<array<string, mixed>>}
     */
    public static function parse(string $raw, ?string $format): array
    {
        $data = json_decode($raw, true);
        if (! is_array($data) || ! is_array($data['files'] ?? null) || $data['files'] === []) {
            $markdown = trim((string) preg_replace('/^```[a-zA-Z]*\s*\n|\n```\s*$/u', '', $raw));
            $title = preg_match('/^#\s+(.+)$/mu', $markdown, $m) ? trim($m[1]) : 'Dokumen Normi AI';

            return [
                'title' => Str::limit($title, 80, ''),
                'summary' => '',
                'files' => [['name' => Str::slug($title) . '.' . ($format ?? 'pdf'), 'content' => $markdown]],
            ];
        }

        $title = trim((string) ($data['title'] ?? '')) ?: 'File Normi AI';

        return [
            'title' => Str::limit($title, 80, ''),
            'summary' => trim((string) ($data['summary'] ?? '')),
            'files' => array_values(array_filter($data['files'], 'is_array')),
        ];
    }

    /** @param  array{binary: string, mime: string, name: string, ext: string}  $file */
    private function store(AiTask $task, array $file, string $summary, $model): AiReply
    {
        $path = sprintf('group-%d/%s/ai-file-%s.%s', $task->group->id, now()->format('Y/m'), Str::uuid(), $file['ext']);
        Storage::disk('normchat_attachments')->put($path, $file['binary']);

        return AiReply::attachment(self::KIND, $summary !== '' ? $summary : 'File ' . $file['name'] . ' sudah siap.', [
            'message_type' => 'file',
            'path' => $path,
            'mime' => $file['mime'],
            'original_name' => $file['name'],
            'size' => strlen($file['binary']),
        ], $model);
    }

    /** @param  list<array{binary: string, mime: string}>  $images */
    private function imagesToPdf(AiTask $task, array $images): AiReply
    {
        $task->progress(AiActivity::FILE, 'sedang mengubah gambar ke PDF');
        $name = pathinfo((string) ($task->message->attachment_original_name ?: $task->message->replyToMessage?->attachment_original_name ?: 'gambar'), PATHINFO_FILENAME);

        try {
            $file = app(FileBuilder::class)->imagesToPdf($images, $name);
        } catch (Throwable $e) {
            Log::error('Image to PDF failed.', ['error' => $e->getMessage()]);

            return AiReply::notice(self::KIND, 'Gagal mengubah gambar ke PDF. Coba lagi.');
        }

        return $this->store($task, $file, 'Gambarnya sudah kuubah jadi PDF.', $task->model);
    }

    /** @return list<array{binary: string, mime: string}> gambar di pesan ini atau pesan yang dibalasnya */
    private function images(Message $message): array
    {
        $images = [];
        foreach ([$message, $message->replyToMessage] as $source) {
            if (! $source?->hasImageAttachment() || ! $source->attachment_path) {
                continue;
            }
            $binary = app(StoredFiles::class)->get($source->attachment_disk ?: 'normchat_attachments', $source->attachment_path, 15 * 1024 * 1024);
            $mime = (string) ($source->attachment_mime ?: 'image/jpeg');
            if (is_string($binary) && $binary !== '' && in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                $images[] = ['binary' => $binary, 'mime' => $mime];
            }
        }

        return $images;
    }
}
