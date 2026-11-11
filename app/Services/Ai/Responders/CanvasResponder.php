<?php

namespace App\Services\Ai\Responders;

use App\Models\Message;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\Context\ContextUnderstanding;
use App\Services\Ai\Files\FileBuilder;
use App\Services\Ai\ModePrompts;
use App\Services\Ai\PromptBuilder;
use App\Services\Files\StoredFiles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Canvas: satu karya utuh (halaman web interaktif atau dokumen Markdown) yang disimpan
 * sebagai lampiran pesan bertipe `canvas` dan dibuka di panel Code/Preview.
 * Membalas sebuah canvas berarti meminta revisinya.
 */
final class CanvasResponder implements AiResponder
{
    public const MESSAGE_TYPE = 'canvas';

    public const MIME_WEB = 'text/html';

    public const MIME_DOCUMENT = 'text/markdown';

    private const MAX_OUTPUT_TOKENS = 24_000;

    /** Permintaan program/aplikasi: status "sedang menulis program", bukan "sedang menyusun canvas". */
    private const CODE_PATTERN = '/\b(aplikasi|app|apps|program|kode|code|coding|script|skrip|website|web|halaman|landing|game|gim|kalkulator|dashboard|react|vue|svelte|angular|next\.?js|nuxt|laravel|django|flask|express|node|flutter|kotlin|android|ios|html|css|javascript|typescript|python|php|java|api|crud|todo|form|ui|prototipe|prototype|simulasi|visualisasi|chart|grafik|animasi|kuis|quiz)\b/iu';

    private const MAX_PREVIOUS_CHARS = 60_000;

    public function activity(): string
    {
        return AiActivity::CANVAS;
    }

    /** Teks status awal sesuai jenis permintaan. */
    public static function startDetail(string $question): string
    {
        return preg_match(self::CODE_PATTERN, $question) === 1 ? 'sedang menulis program' : 'sedang menyusun canvas';
    }

    public function respond(AiTask $task): AiReply
    {
        $messages = $task->compose(fn (ContextBundle $context) => $task->prompts->conversation(
            PromptBuilder::chatSystemPrompt($task->group) . "\n\n" . ModePrompts::CANVAS,
            $context,
            $task->message
        ));

        // Isi Canvas yang direvisi tidak dihitung batas input (dibatasi MAX_PREVIOUS_CHARS dan NC per permintaan).
        $previous = $this->previousCanvas($task->message->replyToMessage);
        if ($previous !== null) {
            // Disisipkan tepat sebelum permintaan terbaru.
            array_splice($messages, -1, 0, [[
                'role' => 'user',
                'content' => "Canvas sebelumnya (\"{$previous['name']}\") yang harus direvisi sesuai permintaan berikutnya:\n\n" . $previous['content'],
            ]]);
        }

        $detail = self::startDetail(ContextUnderstanding::question($task->message));
        $task->progress($detail === 'sedang menulis program' ? AiActivity::CODE : AiActivity::CANVAS, $detail);
        $task->planChat($messages, self::MAX_OUTPUT_TOKENS);
        [$completion, $model] = $task->complete($messages, 300, ['response_format' => ['type' => 'json_object']]);

        if (! $completion->hasText()) {
            return AiReply::failed('canvas');
        }

        $canvas = self::parse((string) $completion->text);
        if ($canvas === null) {
            return AiReply::notice('canvas', $completion->finishReason === 'length'
                ? 'Canvas terlalu panjang. Coba permintaan yang lebih sederhana.'
                : 'Gagal membuat canvas. Coba lagi.');
        }

        $isWeb = $canvas['kind'] === 'web';
        $path = sprintf('group-%d/%s/canvas-%s.%s', $task->group->id, now()->format('Y/m'), Str::uuid(), $isWeb ? 'html' : 'md');

        try {
            Storage::disk('normchat_attachments')->put($path, $canvas['content']);
        } catch (Throwable $e) {
            Log::error('Canvas store failed.', ['error' => $e->getMessage()]);

            return AiReply::notice('canvas', 'Gagal membuat canvas. Coba lagi.');
        }

        $task->recordChat('canvas', $model, $completion);

        $reply = AiReply::attachment('canvas', $canvas['summary'], [
            'message_type' => self::MESSAGE_TYPE,
            'path' => $path,
            'mime' => $isWeb ? self::MIME_WEB : self::MIME_DOCUMENT,
            'original_name' => (Str::slug($canvas['title']) ?: 'canvas') . ($isWeb ? '.html' : '.md'),
            'size' => strlen($canvas['content']),
        ], $model);

        return $this->withProject($task, $reply, $canvas);
    }

    /**
     * Kode proyek asli (mis. Next.js, Laravel, Flutter) disimpan sebagai ZIP di samping canvas; kartu canvas
     * menampilkan tombol unduhnya (ChatController::canvasProject).
     */
    private function withProject(AiTask $task, AiReply $reply, array $canvas): AiReply
    {
        $files = $canvas['project']['files'] ?? [];
        if (! is_array($files) || $files === []) {
            return $reply;
        }

        $task->progress(AiActivity::CODE, 'sedang mengemas proyek');
        $name = trim((string) ($canvas['project']['name'] ?? '')) ?: $canvas['title'];
        try {
            $zip = app(FileBuilder::class)->build($files, null, $name, forceZip: true);
            $path = sprintf('group-%d/%s/canvas-project-%s.zip', $task->group->id, now()->format('Y/m'), Str::uuid());
            Storage::disk('normchat_attachments')->put($path, $zip['binary']);
        } catch (Throwable $e) {
            Log::warning('Canvas project zip failed.', ['error' => $e->getMessage()]);

            return $reply;
        }

        return $reply->withMeta([
            'canvas_project_path' => $path,
            'canvas_project_name' => $zip['name'],
            'canvas_project_size' => strlen($zip['binary']),
            'canvas_project_files' => count($files),
        ]);
    }

    /** @return array{kind: string, title: string, summary: string, content: string, project: ?array}|null */
    public static function parse(string $raw): ?array
    {
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return null;
        }

        $content = trim((string) ($data['content'] ?? ''));
        if ($content === '') {
            return null;
        }

        $kind = ($data['kind'] ?? '') === 'web' || stripos($content, '<html') !== false ? 'web' : 'document';
        if ($kind === 'document') {
            // Buang code fence bila model membungkus Markdown-nya.
            $content = preg_replace('/^```[a-zA-Z]*\s*\n|\n```\s*$/u', '', $content) ?? $content;
        }

        $title = trim((string) ($data['title'] ?? '')) ?: 'Canvas';

        return [
            'kind' => $kind,
            'title' => Str::limit($title, 80, ''),
            'summary' => trim((string) ($data['summary'] ?? '')) ?: $title,
            'content' => $content,
            'project' => is_array($data['project'] ?? null) ? $data['project'] : null,
        ];
    }

    /** @return array{name: string, content: string}|null */
    private function previousCanvas(?Message $message): ?array
    {
        if (! $message || $message->message_type !== self::MESSAGE_TYPE || ! $message->attachment_path) {
            return null;
        }

        $content = app(StoredFiles::class)->get($message->attachment_disk ?: 'normchat_attachments', $message->attachment_path);

        return is_string($content) && $content !== ''
            ? ['name' => (string) $message->attachment_original_name, 'content' => mb_substr($content, 0, self::MAX_PREVIOUS_CHARS)]
            : null;
    }
}
