<?php

namespace App\Services\Ai\Responders;

use App\Models\Message;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\ModePrompts;
use App\Services\Ai\PromptBuilder;
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

    private const MAX_OUTPUT_TOKENS = 16_000;

    private const MAX_PREVIOUS_CHARS = 60_000;

    public function respond(AiTask $task): AiReply
    {
        $messages = $task->prompts->conversation(
            PromptBuilder::chatSystemPrompt($task->group) . "\n\n" . ModePrompts::CANVAS,
            $task->recentMessages,
            $task->message
        );

        $previous = $this->previousCanvas($task->message->replyToMessage);
        if ($previous !== null) {
            // Disisipkan tepat sebelum permintaan terbaru.
            array_splice($messages, -1, 0, [[
                'role' => 'user',
                'content' => "Canvas sebelumnya (\"{$previous['name']}\") yang harus direvisi sesuai permintaan berikutnya:\n\n" . $previous['content'],
            ]]);
        }

        $task->planChat($messages, self::MAX_OUTPUT_TOKENS);
        [$completion, $model] = $task->complete($messages, 240, ['response_format' => ['type' => 'json_object']]);

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

        return AiReply::attachment('canvas', $canvas['summary'], [
            'message_type' => self::MESSAGE_TYPE,
            'path' => $path,
            'mime' => $isWeb ? self::MIME_WEB : self::MIME_DOCUMENT,
            'original_name' => (Str::slug($canvas['title']) ?: 'canvas') . ($isWeb ? '.html' : '.md'),
            'size' => strlen($canvas['content']),
        ], $model);
    }

    /** @return array{kind: string, title: string, summary: string, content: string}|null */
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
        ];
    }

    /** @return array{name: string, content: string}|null */
    private function previousCanvas(?Message $message): ?array
    {
        if (! $message || $message->message_type !== self::MESSAGE_TYPE || ! $message->attachment_path) {
            return null;
        }

        try {
            $content = Storage::disk($message->attachment_disk ?: 'normchat_attachments')->get($message->attachment_path);
        } catch (Throwable) {
            return null;
        }

        return is_string($content) && $content !== ''
            ? ['name' => (string) $message->attachment_original_name, 'content' => mb_substr($content, 0, self::MAX_PREVIOUS_CHARS)]
            : null;
    }
}
