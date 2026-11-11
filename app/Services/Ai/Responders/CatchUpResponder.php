<?php

namespace App\Services\Ai\Responders;

use App\Models\Message;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\ChatTranscript;
use App\Services\Ai\PromptBuilder;
use App\Support\UserTime;
use Illuminate\Support\Collection;

/**
 * "Apa yang kulewatkan?" (docs/rancangan-fitur-produktivitas-nov.md bagian 1): ringkasan pesan yang belum dibaca
 * peminta, dari rentang di system_meta.catch_up pertanyaannya. Jawabannya terlihat semua anggota (keputusan user
 * 11 Nov 2026), jadi hanya pesan yang boleh dilihat peminta DAN tanpa pemberitahuan pribadi siapa pun.
 */
final class CatchUpResponder implements AiResponder
{
    public const KIND = 'catch_up';

    /** Pesan paling banyak yang dibaca; grup yang sangat ramai diringkas dari bagian terbarunya. */
    private const MAX_MESSAGES = 600;

    private const MAX_INPUT_TOKENS = 14_000;

    private const MAX_OUTPUT_TOKENS = 1_400;

    public const PROMPT = "MODE RINGKAS PESAN YANG TERLEWAT. {name} baru membuka grup dan belum membaca pesan di bawah."
        . " Ringkasan ini tampil di grup, tapi ditulis untuk {name}. Pakai Bahasa Indonesia yang santai dan jelas, paling banyak 8 poin,"
        . " urut dari yang terpenting:"
        . "\n- hal yang menyebut, menanyai, atau perlu dijawab {name} (paling atas, awali dengan \"Untuk {name}\");"
        . "\n- topik yang dibahas dan kesimpulannya, sebut siapa yang menyampaikan;"
        . "\n- keputusan, jadwal, tenggat, dan tugas (tebalkan tanggal/jam);"
        . "\n- file, foto, polling, atau tautan penting."
        . "\nAbaikan obrolan basa-basi. Jangan mengarang; hanya dari pesan di bawah. Jangan menulis judul atau salam pembuka, langsung poinnya.";

    public function activity(): string
    {
        return AiActivity::TYPING;
    }

    public function respond(AiTask $task): AiReply
    {
        $request = $task->message;
        $range = (array) ($request->system_meta['catch_up'] ?? []);
        $messages = $this->messages($task, (int) ($range['from'] ?? 0), (int) ($range['to'] ?? $request->id));
        if ($messages->isEmpty()) {
            return AiReply::notice(self::KIND, 'Tidak ada pesan baru yang perlu diringkas.');
        }

        $name = $task->requester->name;
        $task->progress(AiActivity::TYPING, 'sedang meringkas ' . $messages->count() . ' pesan');
        [$transcript, $included] = ChatTranscript::build($messages, $task->requester, self::MAX_INPUT_TOKENS);
        $skipped = $messages->count() - $included;

        $prompt = [
            ['role' => 'system', 'content' => PromptBuilder::chatSystemPrompt($task->group) . "\n\n" . str_replace('{name}', $name, self::PROMPT)],
            ['role' => 'user', 'content' => 'Sekarang ' . UserTime::format(now(), 'l, j F Y H.i', $task->requester) . ".\n"
                . ($skipped > 0 ? "({$skipped} pesan lebih awal tidak ikut karena terlalu banyak.)\n" : '')
                . "Pesan yang belum dibaca {$name} (" . $messages->count() . " pesan):\n\n" . $transcript],
        ];

        $task->planChat($prompt, self::MAX_OUTPUT_TOKENS);
        [$completion, $model] = $task->complete($prompt, 90);
        if (! $completion->hasText()) {
            return AiReply::failed(self::KIND);
        }
        $task->recordChat(self::KIND, $model, $completion);

        $header = '**Ringkasan ' . $messages->count() . ' pesan untuk ' . $name . '**';

        return AiReply::text(self::KIND, $header . "\n\n" . trim((string) $completion->text), $model);
    }

    /** @return Collection<int, Message> pesan di rentang (from, to] yang boleh dilihat peminta, urut lama → baru */
    private function messages(AiTask $task, int $from, int $to): Collection
    {
        return Message::query()
            ->where('group_id', $task->group->id)
            ->where('id', '>', $from)
            ->where('id', '<=', $to)
            ->where('id', '<', $task->message->id)
            ->visibleTo($task->requester)
            ->whereNull('visible_to_user_id')
            ->where('sender_type', '!=', Message::SENDER_SYSTEM)
            ->with(['sender:id,name', 'replyToMessage.sender:id,name'])
            ->orderByDesc('id')
            ->limit(self::MAX_MESSAGES)
            ->get()
            ->reverse()
            ->values();
    }
}
