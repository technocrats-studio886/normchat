<?php

namespace App\Jobs;

use App\Models\Group;
use App\Models\GroupContextSummary;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Context\MemoryMaintenance;
use App\Services\Ai\Tokens;
use App\Support\UserTime;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Summary Worker: memperbarui ingatan grup (ringkasan grup + ringkasan topik) dari pesan yang belum
 * diringkas, dicicil per ±6.000 token, satu panggilan model termurah per cicilan. Biaya dari saldo
 * OpenAI (MaintenanceAi), tidak memotong Normkredit.
 *
 * Bila bangun ulang diminta (pesan diringkas lalu dihapus untuk semua / diedit / cadangan dipulihkan),
 * ringkasan dan semua topik dihapus lalu dibangun dari awal dari pesan yang masih ada. Permintaan bangun
 * ulang langsung dihapus begitu ingatan lama dihapus; bila job berhenti di tengah (anggaran/gagal), job
 * berikutnya melanjutkan dari kemajuan terakhir, sedangkan stale_at tetap ada sampai selesai.
 * Setelah selesai, jangkar jendela dipindah ke 30 pesan terakhir yang sudah diringkas.
 */
class SummarizeGroupContextJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    private const MAX_OUTPUT_TOKENS = 1500;

    /** Cicilan per jalan (±120 dtk per panggilan): tetap di bawah --timeout worker 600 dtk; sisanya job berikutnya. */
    private const MAX_CHUNKS = 4;

    private const MAX_LINE_CHARS = 1000;

    private const MAX_TOPICS_PER_CALL = 10;

    private const MAX_KEY_MESSAGES = 20;

    public int $tries = 5;

    public int $timeout = 580;

    public function __construct(public int $groupId)
    {
        // Antrean terpisah yang dikerjakan setelah antrean jawaban (worker: --queue=default,maintenance).
        $this->onQueue('maintenance');
    }

    public function uniqueId(): string
    {
        return (string) $this->groupId;
    }

    public function handle(MaintenanceAi $ai): void
    {
        $group = Group::find($this->groupId);
        if (! $group || $group->isFrozen()) {
            return;
        }

        $lock = Cache::lock('group-context-summary:' . $this->groupId, $this->timeout);
        if (! $lock->get()) {
            $this->release(60);

            return;
        }

        try {
            $this->summarize($group, $ai);
        } finally {
            $lock->release();
        }
    }

    private function summarize(Group $group, MaintenanceAi $ai): void
    {
        $state = GroupContextSummary::forGroup($this->groupId);
        if ($state->rebuild_requested_at !== null) {
            // Ingatan lama mungkin memuat isi yang sudah dihapus: hapus, bukan sekadar ditandai.
            GroupContextTopic::query()->where('group_id', $this->groupId)->delete();
            $state->update([
                'summary' => null,
                'covered_until_message_id' => null,
                'window_start_message_id' => null,
                'source_message_count' => 0,
                'stale_at' => $state->stale_at ?? now(),
                'rebuild_requested_at' => null,
            ]);
        }

        $latestId = (int) MemoryMaintenance::eligible($this->groupId)->max('id');
        $touchedTopics = [];
        for ($chunk = 0; $chunk < self::MAX_CHUNKS; $chunk++) {
            $messages = $this->nextChunk((int) $state->covered_until_message_id, $latestId);
            if ($messages->isEmpty()) {
                break;
            }

            $result = $ai->json(MaintenanceAi::SUMMARY, $group, $this->prompt($state, $messages), self::MAX_OUTPUT_TOKENS, 120);
            if ($result === null) {
                // Ditolak anggaran / gagal: kemajuan yang sudah ada tetap dipakai, dilanjutkan pemicu berikutnya.
                return;
            }

            $touchedTopics = [...$touchedTopics, ...$this->applyTopics($result['data'], $messages)];
            $summary = trim((string) ($result['data']['ringkasan_grup'] ?? ''));
            $state->update([
                'summary' => $summary !== '' ? Str::limit($summary, 4000, '') : $state->summary,
                'covered_until_message_id' => (int) $messages->last()->id,
                'source_message_count' => $state->source_message_count + $messages->count(),
                'model' => $result['model'],
                'input_tokens' => $state->input_tokens + $result['input_tokens'],
                'output_tokens' => $state->output_tokens + $result['output_tokens'],
                'provider_cost_usd' => $state->provider_cost_usd + $result['cost_usd'],
            ]);
        }

        if ($state->covered_until_message_id === null) {
            // Tidak ada pesan tersisa untuk diringkas (mis. semua terhapus): bangun ulang selesai.
            if ($state->fresh()->rebuild_requested_at === null) {
                $state->update(['stale_at' => null]);
            }

            return;
        }

        $this->embedTopics($group, $ai, array_unique($touchedTopics));

        $anchor = MemoryMaintenance::eligible($this->groupId)
            ->where('id', '<=', $state->covered_until_message_id)
            ->orderByDesc('id')
            ->limit((int) config('normchat.context.window_overlap_messages', 30))
            ->pluck('id')
            ->last();

        $state->refresh();
        // Penghapusan/edit baru selama job berjalan meminta bangun ulang lagi.
        $rebuildAgain = $state->rebuild_requested_at !== null;
        $state->update([
            'window_start_message_id' => $anchor,
            'stale_at' => $rebuildAgain ? $state->stale_at : null,
            'rebuild_requested_at' => $rebuildAgain ? $state->rebuild_requested_at : null,
        ]);

        // Ada penghapusan/edit baru selama job berjalan, atau masih ada pesan yang belum diringkas.
        if ($rebuildAgain || (int) $state->covered_until_message_id < $latestId) {
            self::dispatch($this->groupId);
        }
    }

    /** Pesan setelah $afterId sampai $untilId, sebanyak-banyaknya dalam satu cicilan token. */
    private function nextChunk(int $afterId, int $untilId): Collection
    {
        $candidates = MemoryMaintenance::eligible($this->groupId)
            ->with(['sender:id,name', 'replyToMessage.sender:id,name'])
            ->where('id', '>', $afterId)
            ->where('id', '<=', $untilId)
            ->orderBy('id')
            ->limit(500)
            ->get();

        $budget = (int) config('normchat.context.chunk_tokens', 6000);
        $chunk = collect();
        $used = 0;
        foreach ($candidates as $message) {
            $cost = Tokens::text($this->line($message));
            if ($used + $cost > $budget && $chunk->isNotEmpty()) {
                break;
            }
            $chunk->push($message);
            $used += $cost;
        }

        return $chunk;
    }

    private function prompt(GroupContextSummary $state, Collection $messages): array
    {
        $topics = GroupContextTopic::query()->where('group_id', $this->groupId)->orderByDesc('last_message_id')->limit(30)->get();
        $detailed = $topics->take(10)->map(fn (GroupContextTopic $topic) => "#{$topic->id} {$topic->title}\n{$topic->summary}")->implode("\n\n");
        $titles = $topics->slice(10)->map(fn (GroupContextTopic $topic) => "#{$topic->id} {$topic->title}")->implode('; ');

        return [
            ['role' => 'system', 'content' => "Kamu memelihara ingatan Normi AI untuk sebuah grup chat. Perbarui ingatan dari pesan baru.\n"
                . "Balas HANYA JSON:\n"
                . '{"ringkasan_grup": "…", "topik": [{"id": 12 atau null untuk topik baru, "judul": "…", "ringkasan": "…", '
                . '"pesan_awal": id, "pesan_akhir": id, "pesan_penting": [id, …]}]}' . "\n"
                . "Aturan:\n"
                . "- ringkasan_grup (maks. 150 kata): tujuan grup, keputusan penting lintas topik, fakta & batasan jangka panjang,"
                . " siapa memegang tugas apa. Gabungkan ringkasan lama dengan pesan baru; buang yang sudah tidak berlaku.\n"
                . "- topik: hanya pembahasan yang muncul di pesan baru. Pakai id topik lama bila pembahasannya sama, null bila baru."
                . " ringkasan topik (maks. 120 kata): konteks, keputusan yang disepakati, fakta & batasan dari anggota, pertanyaan yang belum selesai.\n"
                . "- pesan_awal, pesan_akhir, pesan_penting: id dari daftar pesan baru (pesan_penting = sumber keputusan/fakta penting).\n"
                . "- Tulis apa yang dinyatakan anggota apa adanya. Kesimpulanmu sendiri tandai \"(dugaan Normi)\".\n"
                . "- Obrolan ringan tanpa isi penting tidak perlu jadi topik. Bahasa Indonesia, sebut nama anggota bila relevan."],
            ['role' => 'user', 'content' => "Ringkasan grup saat ini:\n" . (trim((string) $state->summary) ?: '(belum ada)')
                . "\n\nTopik yang sudah ada:\n" . ($detailed !== '' ? $detailed : '(belum ada)')
                . ($titles !== '' ? "\n\nJudul topik lain: " . $titles : '')
                . "\n\nPesan baru ([#id waktu] Nama: isi):\n" . $messages->map(fn (Message $message) => $this->line($message))->implode("\n")],
        ];
    }

    /** @return list<int> id topik yang dibuat/diperbarui */
    private function applyTopics(array $data, Collection $messages): array
    {
        $firstId = (int) $messages->first()->id;
        $lastId = (int) $messages->last()->id;
        $inChunk = fn ($id) => is_numeric($id) && (int) $id >= $firstId && (int) $id <= $lastId;
        $touched = [];

        foreach (array_slice((array) ($data['topik'] ?? []), 0, self::MAX_TOPICS_PER_CALL) as $row) {
            $title = Str::limit(trim((string) ($row['judul'] ?? '')), 120, '');
            $summary = Str::limit(trim((string) ($row['ringkasan'] ?? '')), 2000, '');
            if (! is_array($row) || $title === '' || $summary === '') {
                continue;
            }

            $start = $inChunk($row['pesan_awal'] ?? null) ? (int) $row['pesan_awal'] : $firstId;
            $end = $inChunk($row['pesan_akhir'] ?? null) ? (int) $row['pesan_akhir'] : $lastId;
            $key = array_values(array_map('intval', array_filter((array) ($row['pesan_penting'] ?? []), $inChunk)));
            $lastActivity = $messages->firstWhere('id', max($start, $end))?->created_at ?? now();

            $topic = is_numeric($row['id'] ?? null)
                ? GroupContextTopic::query()->where('group_id', $this->groupId)->whereKey((int) $row['id'])->first()
                : null;

            if ($topic) {
                $topic->update([
                    'title' => $title,
                    'summary' => $summary,
                    'first_message_id' => min($topic->first_message_id, $start, $end),
                    'last_message_id' => max($topic->last_message_id, $start, $end),
                    'key_message_ids' => array_slice(array_values(array_unique([...(array) $topic->key_message_ids, ...$key])), -self::MAX_KEY_MESSAGES),
                    'last_activity_at' => $lastActivity,
                    'stale_at' => null,
                ]);
            } else {
                $topic = GroupContextTopic::query()->create([
                    'group_id' => $this->groupId,
                    'title' => $title,
                    'summary' => $summary,
                    'first_message_id' => min($start, $end),
                    'last_message_id' => max($start, $end),
                    'key_message_ids' => array_slice($key, -self::MAX_KEY_MESSAGES),
                    'last_activity_at' => $lastActivity,
                ]);
            }
            $touched[] = (int) $topic->id;
        }

        return $touched;
    }

    /** @param list<int> $topicIds */
    private function embedTopics(Group $group, MaintenanceAi $ai, array $topicIds): void
    {
        $topics = GroupContextTopic::query()->whereIn('id', $topicIds)->get();
        if ($topics->isEmpty()) {
            return;
        }

        $vectors = $ai->embed($group, $topics->map(fn (GroupContextTopic $topic) => $topic->title . "\n" . $topic->summary)->all());
        if ($vectors === null) {
            return;
        }
        foreach ($topics->values() as $index => $topic) {
            $topic->update(['embedding' => json_encode($vectors[$index])]);
        }
    }

    private function line(Message $message): string
    {
        $sender = $message->sender_type === 'ai' ? 'Normi AI' : ($message->sender?->name ?? 'Anggota');
        $content = trim((string) $message->content);
        if ($content === '' && $message->attachment_path) {
            $content = '[lampiran: ' . ($message->attachment_original_name ?: $message->message_type) . ']';
        }
        $reply = $message->replyToMessage
            ? '(membalas ' . ($message->replyToMessage->sender_type === 'ai' ? 'Normi AI' : ($message->replyToMessage->sender?->name ?? 'anggota')) . ') '
            : '';

        return '[#' . $message->id . ' ' . UserTime::format($message->created_at, 'j/n H.i') . '] ' . $sender . ': ' . $reply
            . Str::limit($content, self::MAX_LINE_CHARS);
    }
}
