<?php

namespace App\Services\Ai\Responders;

use App\Enums\AiMode;
use App\Models\Message;
use App\Models\Reminder;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Chat\Reminders;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * "@normi ingatkan grup Jumat jam 7 bawa laptop" (docs/rancangan-fitur-produktivitas-nov.md bagian 2): Normi membaca
 * waktu, isi, ulangan, dan sasaran pengingat, lalu Reminders membuatnya.
 */
final class ReminderResponder implements AiResponder
{
    public const KIND = 'reminder';

    private const VERBS = '(?:ingatkan|ingetin|ingatin|ingetkan|ingatkanin|ingetinn?|remind(?:\s+me)?|(?:buat(?:kan|in)?|bikin(?:in)?|pasang(?:kan)?|set(?:el)?|atur)\s+(?:pengingat|reminder|alarm))';

    public const PROMPT = "Kamu membaca permintaan pengingat di grup chat. Balas HANYA JSON:\n"
        . '{"text": "isi pengingat singkat tanpa kata ingatkan", "remind_at": "YYYY-MM-DDTHH:MM" atau null, "repeat": "none|daily|weekly", "audience": "group|me"}' . "\n"
        . "Aturan:\n"
        . "- Waktu menurut zona waktu penanya. Hari yang disebut tanpa tanggal berarti hari itu yang terdekat ke depan (hari ini bila jamnya belum lewat).\n"
        . "- Jam tanpa keterangan: 1-6 berarti sore/malam bila kalimatnya menyebut malam/sore; \"jam 7 malam\" = 19:00; \"pagi\" = AM.\n"
        . "- Tanggal disebut tanpa jam: pakai 07:00. Tidak ada waktu sama sekali: remind_at null.\n"
        . "- \"tiap hari\"/\"setiap hari\" = daily; \"tiap minggu\"/\"setiap Senin\" = weekly.\n"
        . "- audience \"me\" bila penanya minta diingatkan dirinya sendiri (\"ingatkan aku\", \"ingetin gue\"); selain itu \"group\".\n"
        . '- text dalam Bahasa Indonesia, singkat dan jelas, mis. "Bawa laptop untuk presentasi".';

    /** Pesan ini meminta Normi membuat pengingat. */
    public static function wants(string $content): bool
    {
        $text = trim((string) preg_replace(AiMode::anyTagPattern(), '', $content));

        return preg_match('/(?<![\p{L}])' . self::VERBS . '(?![\p{L}])/iu', $text) === 1
            && preg_match('/^\s*(?:apa|gimana|bagaimana|kenapa|kok)\b/iu', $text) !== 1;
    }

    public function activity(): string
    {
        return AiActivity::TYPING;
    }

    public function respond(AiTask $task): AiReply
    {
        $task->progress(AiActivity::TYPING, 'sedang memasang pengingat');
        $now = CarbonImmutable::now(UserTime::zone($task->requester));
        $request = $task->message;
        $context = $request->replyToMessage && trim((string) $request->replyToMessage->content) !== ''
            ? "\nPesan yang dibalas: " . mb_substr(trim((string) $request->replyToMessage->content), 0, 600)
            : '';
        $prompt = [
            ['role' => 'system', 'content' => self::PROMPT],
            ['role' => 'user', 'content' => 'Sekarang ' . $now->translatedFormat('l, j F Y') . ' pukul ' . $now->format('H:i')
                . ' (' . $now->getTimezone()->getName() . ').' . "\nPermintaan: " . trim((string) preg_replace(AiMode::anyTagPattern(), '', (string) $request->content)) . $context],
        ];
        $task->planChat($prompt, 300);
        [$completion, $model] = $task->complete($prompt, 30, ['response_format' => ['type' => 'json_object']]);
        if (! $completion->hasText()) {
            return AiReply::failed(self::KIND);
        }
        $task->recordChat(self::KIND, $model, $completion);

        $data = json_decode((string) $completion->text, true);
        $text = trim((string) ($data['text'] ?? ''));
        $at = null;
        try {
            $at = ! empty($data['remind_at']) ? CarbonImmutable::parse((string) $data['remind_at'], $now->getTimezone()) : null;
        } catch (Throwable) {
            $at = null;
        }
        if ($text === '' || $at === null) {
            return AiReply::text(self::KIND, 'Kapan aku perlu mengingatkan? Sebutkan hari dan jamnya, misalnya "@normi ingatkan grup Jumat jam 7 malam bawa laptop".', $model);
        }
        if ($at->lessThanOrEqualTo(now())) {
            return AiReply::text(self::KIND, 'Waktu itu sudah lewat (' . $at->translatedFormat('l, j M') . ' pukul ' . $at->format('H.i') . '). Sebutkan waktu yang akan datang, ya.', $model);
        }

        try {
            $reminder = app(Reminders::class)->create($task->group, $task->requester, [
                'text' => $text,
                'remind_at' => $at,
                'repeat' => (string) ($data['repeat'] ?? 'none'),
                'audience' => (string) ($data['audience'] ?? 'group'),
                'source_message_id' => (int) $request->id,
            ], announce: false);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return AiReply::text(self::KIND, $e->getMessage(), $model);
        }

        $who = $reminder->isPersonal() ? 'mengingatkanmu' : 'mengingatkan grup';
        $reply = "Siap, aku akan {$who} " . Reminders::whenLabel($reminder, $task->requester) . ".\n\n> ⏰ " . $reminder->text;

        return AiReply::text(self::KIND, $reply, $model)->withMeta(['reminder_id' => (int) $reminder->id]);
    }
}
