<?php

namespace App\Services\Ai\Responders;

use App\Enums\AiMode;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\PromptBuilder;
use App\Services\Chat\GroupTasks;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * "@normi buat checklist dari pembahasan tadi" (docs/rancangan-fitur-produktivitas-nov.md bagian 3): Normi menyusun
 * tugas dari permintaan dan percakapan, lalu tugas itu masuk ke Tugas grup.
 */
final class TaskResponder implements AiResponder
{
    public const KIND = 'tasks';

    private const MAX_TASKS = 20;

    public const PROMPT = "MODE TUGAS GRUP. Susun daftar tugas/checklist yang diminta anggota, dari permintaannya dan percakapan grup."
        . " Balas HANYA JSON:\n"
        . '{"tasks": [{"title": "kalimat tugas singkat diawali kata kerja", "assignee": "nama anggota persis dari daftar atau null", "due": "YYYY-MM-DD atau null"}], "reply": "1 kalimat Bahasa Indonesia untuk grup"}' . "\n"
        . "Aturan: maksimal 20 tugas; hanya tugas yang benar-benar disebut atau jelas dari percakapan; jangan mengarang penanggung jawab atau tenggat."
        . " Bila tidak ada tugas yang bisa disusun, tasks kosong dan reply menjelaskan alasannya.";

    /** Pesan ini meminta tugas/checklist dibuat atau ditambahkan. */
    public static function wants(string $content): bool
    {
        $text = trim((string) preg_replace(AiMode::anyTagPattern(), '', $content));

        return preg_match('/(?<![\p{L}])(?:buat(?:kan|in)?|bikin(?:in|kan)?|susun(?:kan)?|catat(?:kan)?|tambah(?:kan|in)?|jadikan|masukkan|masukin|list(?:kan)?)\s+(?:(?:ke|jadi|sebagai|dalam)\s+)?(?:(?:daftar|list)\s+)?(?:tugas|checklist|check\s*list|to-?do(?:\s*list)?|tudu)(?![\p{L}])/iu', $text) === 1;
    }

    public function activity(): string
    {
        return AiActivity::TYPING;
    }

    public function respond(AiTask $task): AiReply
    {
        $task->progress(AiActivity::TYPING, 'sedang menyusun tugas grup');
        $tasks = app(GroupTasks::class);
        $members = implode(', ', array_column($tasks->members($task->group), 'name'));
        $today = CarbonImmutable::now(UserTime::zone($task->requester));
        $system = PromptBuilder::chatSystemPrompt($task->group) . "\n\n" . self::PROMPT
            . "\nAnggota grup: {$members}.\nHari ini " . $today->translatedFormat('l, j F Y') . '.';

        $messages = $task->compose(fn (ContextBundle $context) => $task->prompts->conversation($system, $context, $task->message));
        $task->planChat($messages, 1500);
        [$completion, $model] = $task->complete($messages, 60, ['response_format' => ['type' => 'json_object']]);
        if (! $completion->hasText()) {
            return AiReply::failed(self::KIND);
        }
        $task->recordChat(self::KIND, $model, $completion);

        $data = json_decode((string) $completion->text, true);
        $created = [];
        foreach (array_slice(is_array($data['tasks'] ?? null) ? $data['tasks'] : [], 0, self::MAX_TASKS) as $item) {
            $title = trim((string) ($item['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $due = null;
            try {
                $due = ! empty($item['due']) ? CarbonImmutable::parse((string) $item['due'])->toDateString() : null;
            } catch (Throwable) {
                $due = null;
            }
            try {
                $created[] = $tasks->create($task->group, $task->requester, [
                    'title' => $title,
                    'assignee_id' => $tasks->matchMember($task->group, $item['assignee'] ?? null),
                    'due_date' => $due,
                    'source_message_id' => (int) $task->message->id,
                ], broadcast: false);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                break;
            }
        }

        $intro = trim((string) ($data['reply'] ?? ''));
        if ($created === []) {
            return AiReply::text(self::KIND, $intro !== '' ? $intro : 'Belum ada tugas yang bisa kususun dari percakapan ini. Sebutkan tugasnya, ya.', $model);
        }
        $tasks->changed($task->group);
        $lines = array_map(fn ($item) => '- ☐ ' . $item->title
            . ($item->assignee_id ? ' — ' . ($item->assignee?->name ?? '') : '')
            . ($item->due_date ? ' (tenggat ' . $item->due_date->translatedFormat('l, j M') . ')' : ''), $created);
        $text = ($intro !== '' ? $intro : 'Aku tambahkan ' . count($created) . ' tugas ke Tugas grup.') . "\n\n" . implode("\n", $lines);

        return AiReply::text(self::KIND, $text, $model)->withMeta(['tasks_added' => count($created)]);
    }
}
