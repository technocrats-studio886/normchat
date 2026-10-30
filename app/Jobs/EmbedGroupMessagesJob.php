<?php

namespace App\Jobs;

use App\Models\Group;
use App\Models\GroupContextSummary;
use App\Models\Message;
use App\Models\MessageEmbedding;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Context\MemoryMaintenance;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Embedding pesan grup yang belum punya vektor (atau isinya berubah), per 100 pesan, untuk pencarian
 * semantik ingatan Normi. Biaya dari saldo OpenAI (MaintenanceAi); bila ditolak/gagal, berhenti dan
 * dilanjutkan job berikutnya dari pesan terakhir yang berhasil.
 */
class EmbedGroupMessagesJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;

    /** Batas cicilan per job, supaya grup besar tidak memonopoli antrean. */
    private const MAX_BATCHES = 4;

    private const MAX_CHARS = 1500;

    public int $tries = 5;

    public int $timeout = 300;

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

        $lock = Cache::lock('group-embed:' . $this->groupId, $this->timeout);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $this->embed($group, $ai);
        } finally {
            $lock->release();
        }
    }

    private function embed(Group $group, MaintenanceAi $ai): void
    {
        $state = GroupContextSummary::forGroup($this->groupId);
        $model = (string) config('normchat.embedding.model');
        $batchSize = (int) config('normchat.context.embed_batch', 100);

        for ($batch = 0; $batch < self::MAX_BATCHES; $batch++) {
            $messages = MemoryMaintenance::eligible($this->groupId)
                ->with('sender:id,name')
                ->where('id', '>', (int) $state->embedded_until_message_id)
                ->orderBy('id')
                ->limit($batchSize)
                ->get();
            if ($messages->isEmpty()) {
                return;
            }

            $existing = MessageEmbedding::query()->whereIn('message_id', $messages->pluck('id'))->pluck('content_hash', 'message_id');
            $pending = [];
            foreach ($messages as $message) {
                $text = self::text($message);
                $hash = hash('sha256', $model . "\n" . $text);
                if ($text !== '' && ($existing[$message->id] ?? null) !== $hash) {
                    $pending[] = ['message' => $message, 'text' => $text, 'hash' => $hash];
                }
            }

            if ($pending !== []) {
                $vectors = $ai->embed($group, array_column($pending, 'text'));
                if ($vectors === null) {
                    return;
                }

                foreach ($pending as $index => $row) {
                    MessageEmbedding::query()->updateOrCreate(['message_id' => $row['message']->id], [
                        'group_id' => $this->groupId,
                        'embedding' => json_encode($vectors[$index]),
                        'model' => $model,
                        'content_hash' => $row['hash'],
                    ]);
                }
            }

            $state->update(['embedded_until_message_id' => (int) $messages->last()->id]);
        }

        // Masih ada sisa: lanjut di job berikutnya.
        self::dispatch($this->groupId);
    }

    /** Teks yang di-embed: nama pengirim + isi, atau transkrip/isi dokumen yang sudah pernah dibaca. */
    public static function text(Message $message): string
    {
        $content = trim((string) $message->content);
        if ($content === '') {
            $content = trim((string) (Cache::get('msg-audio-transcript:' . $message->id) ?? Cache::get('msg-doc-text:' . $message->id) ?? ''));
        }
        if ($content === '') {
            return '';
        }

        $sender = $message->sender_type === 'ai' ? 'Normi AI' : ($message->sender?->name ?? 'Anggota');
        if ($message->forwarded_from_name) {
            $sender = $message->forwarded_from_name . ' (diteruskan oleh ' . $sender . ')';
        }

        return $sender . ': ' . Str::limit($content, self::MAX_CHARS, '');
    }
}
