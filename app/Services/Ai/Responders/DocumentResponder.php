<?php

namespace App\Services\Ai\Responders;

use App\Services\AiDocumentGenerator;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Menulis isi dokumen dalam Markdown lalu merendernya ke format yang diminta. */
final class DocumentResponder implements AiResponder
{
    private const SYSTEM_PROMPT = "Kamu adalah AI yang menulis isi dokumen siap-render dalam Markdown bersih.\n"
        . "Aturan keluaran:\n"
        . "- Hanya keluarkan konten dokumen dalam Markdown (tanpa pembuka 'Tentu saja', tanpa penutup, tanpa code fence).\n"
        . "- Awali dengan satu baris judul (#) yang ringkas dan deskriptif.\n"
        . "- Gunakan heading (##), paragraf, dan bullet (-) bila perlu.\n"
        . "- Bahasa Indonesia, jelas, padat, langsung dipakai.\n"
        . "- Hindari tabel kompleks dan emoji berlebihan.";

    public function __construct(private readonly string $format)
    {
    }

    public function respond(AiTask $task): AiReply
    {
        $userPrompt = trim((string) $task->message->content) ?: 'Buatkan dokumen ringkas berdasarkan konteks percakapan grup.';
        $messages = [
            ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
            ...$task->prompts->history($task->recentMessages, $task->message, skipEmpty: true),
            ['role' => 'user', 'content' => "Format yang diminta user: {$this->format}.\nPermintaan user: {$userPrompt}\n\nTulis seluruh isi dokumen sekarang."],
        ];

        $task->planChat($messages);

        try {
            [$completion, $model] = $task->complete($messages, 90);
            if (! $completion->successful) {
                return AiReply::notice('document', 'Gagal membuat dokumen, coba lagi nanti.');
            }

            $markdown = trim((string) $completion->text);
            if ($markdown === '') {
                return AiReply::notice('document', 'Gagal membuat dokumen: respons AI kosong.');
            }

            // Buang code fence bila model membungkus keluarannya.
            $markdown = preg_replace('/^```[a-zA-Z]*\s*\n|\n```\s*$/u', '', $markdown) ?? $markdown;
            $titleHint = preg_match('/^#\s+(.+)$/mu', $markdown, $titleMatch) ? trim($titleMatch[1]) : null;

            $document = app(AiDocumentGenerator::class)->storeDocument((int) $task->group->id, $this->format, $markdown, $titleHint);
            if (! $document) {
                return AiReply::notice('document', 'Gagal merender dokumen ke format ' . strtoupper($this->format) . '.');
            }
        } catch (Throwable $e) {
            Log::error('Document generation exception.', ['error' => $e->getMessage()]);

            return AiReply::notice('document', 'Gagal membuat dokumen: ' . $e->getMessage());
        }

        $task->recordChat('document', $model, $completion);

        return AiReply::attachment('document', '📄 Dokumen siap: ' . $document['original_name'], [
            'message_type' => $document['message_type'] ?? 'file',
            'path' => $document['path'],
            'mime' => $document['mime'],
            'original_name' => $document['original_name'],
            'size' => $this->fileSize($document['path']),
        ], $model);
    }

    private function fileSize(string $path): ?int
    {
        try {
            return (int) Storage::disk('normchat_attachments')->size($path);
        } catch (Throwable) {
            return null;
        }
    }
}
