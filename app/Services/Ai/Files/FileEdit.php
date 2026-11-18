<?php

namespace App\Services\Ai\Files;

use App\Models\Message;
use App\Services\Ai\AiTask;
use Illuminate\Support\Str;

final class FileEdit
{
    public static function position(string $text): ?array
    {
        if (! preg_match('/\b(atas|bawah|kop|header|footer|top|bottom)\b/iu', $text, $m)) {
            return null;
        }

        return [
            'position' => in_array(mb_strtolower($m[1]), ['bawah', 'footer', 'bottom'], true) ? 'bottom' : 'top',
            'align' => preg_match('/\b(kanan|right)\b/iu', $text) ? 'right' : (preg_match('/\b(tengah|center)\b/iu', $text) ? 'center' : 'left'),
        ];
    }

    public static function wantsImage(string $text): bool
    {
        return (bool) preg_match('/\blogo\s+(?:ini|terlampir|di\s+(?:atas|bawah|kanan|kiri))\b|\b(?:tambah\w*|sisip\w*|masuk\w*|pasang\w*|tempel\w*|sertakan|dengan|pakai|include|beserta)\b.{0,45}\b(?:foto|gambar|image|logo)\b/isu', $text)
            && ! preg_match('/\b(?:tanpa|jangan(?:\s+pakai)?)\s+(?:logo|gambar|foto)\b/iu', $text);
    }

    public static function filename(string $text): ?string
    {
        if (! preg_match('/\b(?:nama\s+(?:file|berkas)(?:nya)?|rename(?:\s+file)?|(?:file|berkas)\s+(?:dengan\s+nama|bernama))\s*(?:(?:jadi|menjadi|adalah|dengan|ke)\b\s*)?[:=]?\s*["“\x27]?([^"”\x27\n,;!?]+?)(?=\s+(?:dan|lalu|dengan logo)\b|["”\x27\n,;!?]|$)/iu', $text, $m)) {
            return null;
        }

        return Str::slug(pathinfo(trim($m[1], ' .'), PATHINFO_FILENAME)) ?: null;
    }

    public static function visible(AiTask $task, int $id): ?Message
    {
        return Message::where('group_id', $task->group->id)->whereNull('visible_to_user_id')
            ->withinHistoryOf($task->requester)->where('id', '<=', $task->message->id)->find($id);
    }

    public static function pending(Message $message): ?Message
    {
        $reply = $message->replyToMessage;
        if (! $reply && self::position((string) $message->content) && mb_strlen($message->content) < 90) {
            $reply = Message::where('group_id', $message->group_id)->whereNull('visible_to_user_id')
                ->where('id', '<', $message->id)->latest('id')->first();
        }
        if ($reply?->sender_type !== 'ai' || $reply->group_id != $message->group_id || $reply->trashed()
            || ($reply->system_meta['file_position_user'] ?? null) != $message->sender_id) {
            return null;
        }

        return $reply;
    }

    public static function source(AiTask $task, Message $request, ?string $format, bool $revision): ?Message
    {
        $target = $request->reply_to_message_id ? self::visible($task, $request->reply_to_message_id) : null;
        $isDoc = fn ($m) => $m?->attachment_path && $m->message_type === 'file'
            && (in_array(FileFormats::extensionOf((string) $m->attachment_original_name), ['pdf', 'docx', 'xlsx', 'pptx'], true) || PilotDocument::supports($m));
        if ($isDoc($target)) {
            return $target;
        }
        if (! $revision) {
            return null;
        }
        $docs = collect($task->context->window)->filter(fn ($m) => $isDoc($m) && $m->id < $request->id
            && (! $format || FileFormats::extensionOf($m->attachment_original_name) === $format))->sortByDesc('id');
        if ($docs->pluck('attachment_original_name')->unique()->count() !== 1) {
            return null;
        }

        return self::visible($task, $docs->first()->id);
    }
}
