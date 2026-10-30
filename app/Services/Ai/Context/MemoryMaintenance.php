<?php

namespace App\Services\Ai\Context;

use App\Jobs\EmbedGroupMessagesJob;
use App\Jobs\SummarizeGroupContextJob;
use App\Models\Group;
use App\Models\GroupContextSummary;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Models\MessageEmbedding;
use App\Services\Ai\Tokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Pemicu pemeliharaan ingatan Normi, terpisah dari pemicu jawaban: obrolan tanpa tag hanya disimpan.
 * Ingatan baru mulai dibangun setelah grup men-tag Normi (grup lama saat launch: tag pertama sesudah deploy).
 *
 * - Setelah Normi menjawab: embedding pesan baru, dan ringkasan diperbarui bila pesan yang belum diringkas
 *   ≥ ambang (100) atau melebihi anggaran jendela.
 * - Pesan dihapus untuk semua / diedit setelah diringkas: ringkasan & topik terkait langsung tidak dipakai,
 *   lalu dibangun ulang dari awal tanpa pesan itu (beberapa perubahan beruntun digabung dalam satu job).
 * - Grup dihapus: seluruh ingatannya ikut dihapus.
 */
class MemoryMaintenance
{
    /** Jeda sebelum bangun ulang, agar beberapa penghapusan/edit beruntun cukup satu job. */
    private const REBUILD_DELAY_SECONDS = 60;

    public function afterAnswer(Group $group): void
    {
        $state = GroupContextSummary::forGroup((int) $group->id);
        $latest = (int) self::eligible((int) $group->id)->max('id');

        if ($latest > (int) $state->embedded_until_message_id) {
            EmbedGroupMessagesJob::dispatch((int) $group->id);
        }
        // stale_at tanpa permintaan bangun ulang = bangun ulang sebelumnya berhenti di tengah: lanjutkan.
        if ($state->rebuild_requested_at !== null || $state->stale_at !== null || $this->needsSummary($state)) {
            SummarizeGroupContextJob::dispatch((int) $group->id);
        }
    }

    /** Pesan yang belum diringkas mencapai ambang jumlah atau anggaran token jendela. */
    public function needsSummary(GroupContextSummary $state): bool
    {
        $pending = self::eligible((int) $state->group_id)
            ->when($state->covered_until_message_id !== null, fn (Builder $query) => $query->where('id', '>', $state->covered_until_message_id));

        $count = (clone $pending)->count();
        if ($count >= (int) config('normchat.context.summary_every_messages', 100)) {
            return true;
        }

        $chars = (int) (clone $pending)->sum(DB::raw('char_length(coalesce(content, \'\'))'));

        return $count > 0 && (int) ceil($chars / Tokens::CHARS_PER_TOKEN) > (int) config('normchat.context.window_tokens', 6000);
    }

    /** Pesan dihapus untuk semua (oleh pengirim atau moderator) atau diedit. */
    public function messageChanged(Message $message, bool $deleted): void
    {
        MessageEmbedding::query()->where('message_id', $message->id)->delete();

        $state = GroupContextSummary::query()->where('group_id', $message->group_id)->first();
        if (! $state) {
            return;
        }

        if (! $deleted && $state->embedded_until_message_id !== null && $state->embedded_until_message_id >= $message->id) {
            // Isi baru di-embed ulang oleh job berikutnya; pesan lain yang hash-nya sama dilewati.
            $state->update(['embedded_until_message_id' => $message->id - 1]);
        }

        GroupContextTopic::query()
            ->where('group_id', $message->group_id)
            ->where('first_message_id', '<=', $message->id)
            ->where('last_message_id', '>=', $message->id)
            ->update(['stale_at' => now()]);

        if ($state->covered_until_message_id !== null && $message->id <= $state->covered_until_message_id) {
            $this->requestRebuild($state);
        }
    }

    /** Pesan lama kembali (pulihkan cadangan): ingatan dibangun ulang dan pesannya di-embed lagi. */
    public function messagesRestored(int $groupId): void
    {
        $state = GroupContextSummary::query()->where('group_id', $groupId)->first();
        if (! $state) {
            return;
        }

        $state->update(['embedded_until_message_id' => null]);
        if ($state->covered_until_message_id !== null) {
            $this->requestRebuild($state);
        }
    }

    public function purge(int $groupId): void
    {
        MessageEmbedding::query()->where('group_id', $groupId)->delete();
        GroupContextTopic::query()->where('group_id', $groupId)->delete();
        GroupContextSummary::query()->where('group_id', $groupId)->delete();
    }

    private function requestRebuild(GroupContextSummary $state): void
    {
        $state->update(['stale_at' => now(), 'rebuild_requested_at' => now()]);
        SummarizeGroupContextJob::dispatch((int) $state->group_id)->delay(now()->addSeconds(self::REBUILD_DELAY_SECONDS));
    }

    /** Pesan grup yang menjadi bahan ingatan: bukan pemberitahuan pribadi, tidak terhapus. */
    public static function eligible(int $groupId): Builder
    {
        return Message::query()->where('group_id', $groupId)->whereNull('visible_to_user_id');
    }
}
