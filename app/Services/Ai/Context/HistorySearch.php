<?php

namespace App\Services\Ai\Context;

use App\Models\Group;
use App\Models\Message;
use App\Services\Ai\Tokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Mencari pesan lama yang relevan di luar jendela riwayat: kata kunci (full-text Postgres), makna
 * (embedding pgvector), dan rentang waktu yang disebut pertanyaan, digabung dengan Reciprocal Rank Fusion.
 * Pesan sumber topik terpilih dan pesan yang dibalas ikut di atasnya. Hanya pesan grup itu, sebelum
 * $beforeId, tidak terhapus, dan bukan pemberitahuan pribadi; filternya ada di query.
 */
class HistorySearch
{
    private const CANDIDATES = 20;

    private const RRF_K = 60;

    /** Jarak kosinus maksimal agar hasil semantik dianggap relevan. */
    private const MAX_DISTANCE = 0.55;

    /**
     * @param  list<string>  $terms  kata kunci / kata pencarian
     * @param  list<float>|null  $queryVector  embedding pertanyaan (null = hanya kata kunci)
     * @param  list<int>  $mustInclude  pesan yang wajib ikut (mis. pesan yang dibalas)
     * @param  list<int>  $topicMessageIds  pesan sumber topik yang dipilih, di atas hasil pencarian
     * @param  TimeRange|null  $range  rentang waktu yang disebut pertanyaan (prioritas, bukan saringan)
     * @param  bool  $search  false = hanya $mustInclude dan $topicMessageIds, tanpa pencarian
     * @return list<Message> urut lama → baru, dalam batas token
     */
    public function find(Group $group, int $beforeId, array $terms, ?array $queryVector, array $mustInclude = [], array $topicMessageIds = [], ?TimeRange $range = null, bool $search = true): array
    {
        $scores = [];
        if ($search) {
            $rankings = [$this->keywordIds($group, $beforeId, $terms), $this->semanticIds($group, $beforeId, $queryVector)];
            if ($range !== null) {
                $rankings[] = $this->rangeIds($group, $beforeId, $terms, $range);
            }
            foreach ($rankings as $ranking) {
                foreach ($ranking as $rank => $id) {
                    $scores[$id] = ($scores[$id] ?? 0) + 1 / (self::RRF_K + $rank + 1);
                }
            }
            if ($range !== null && $scores !== []) {
                // Hasil kata kunci/semantik yang jatuh di rentang waktu yang disebut naik peringkatnya.
                $inRange = $this->eligible($group, $beforeId)
                    ->whereIn('id', array_keys($scores))
                    ->whereBetween('created_at', [$range->from, $range->to])
                    ->pluck('id');
                foreach ($inRange as $id) {
                    $scores[(int) $id] += 1 / (self::RRF_K + 1);
                }
            }
        }
        // Pesan sumber topik: di atas hasil pencarian mana pun, urut sesuai daftar topik.
        foreach (array_values($topicMessageIds) as $rank => $id) {
            $scores[(int) $id] = 10 - $rank / 1000;
        }
        foreach ($mustInclude as $id) {
            $scores[(int) $id] = PHP_INT_MAX;
        }
        if ($scores === []) {
            return [];
        }

        arsort($scores);
        $limit = (int) config('normchat.context.retrieval_messages', 8) + count($topicMessageIds) + count($mustInclude);
        $top = array_slice(array_keys($scores), 0, $limit);

        return $this->withNeighbours($group, $beforeId, $top);
    }

    /**
     * Pesan di rentang waktu yang disebut: yang cocok kata kunci lebih dulu, sisanya terbaru lebih dulu.
     * Untuk pertanyaan yang hanya menyebut waktu ("kemarin bahas apa?").
     *
     * @return list<int>
     */
    private function rangeIds(Group $group, int $beforeId, array $terms, TimeRange $range): array
    {
        $query = $this->eligible($group, $beforeId)->whereBetween('created_at', [$range->from, $range->to]);
        $words = ContextUnderstanding::keywords(implode(' ', $terms));
        if ($words !== []) {
            $document = "to_tsvector('simple', coalesce(content, ''))";
            $query->orderByRaw("ts_rank({$document}, websearch_to_tsquery('simple', ?)) desc", [implode(' OR ', $words)]);
        }

        return $query->orderByDesc('id')
            ->limit(self::CANDIDATES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function keywordIds(Group $group, int $beforeId, array $terms): array
    {
        // Frasa pencarian (mis. dari Luna) dipecah jadi kata bermakna dan digabung OR: pesan tidak harus
        // memuat semua kata; yang cocok dengan lebih banyak kata naik peringkatnya lewat ts_rank.
        $words = ContextUnderstanding::keywords(implode(' ', $terms));
        if ($words === []) {
            return [];
        }

        $query = implode(' OR ', $words);
        $document = "to_tsvector('simple', coalesce(content, ''))";

        return $this->eligible($group, $beforeId)
            ->whereRaw("{$document} @@ websearch_to_tsquery('simple', ?)", [$query])
            ->orderByRaw("ts_rank({$document}, websearch_to_tsquery('simple', ?)) desc", [$query])
            ->orderByDesc('id')
            ->limit(self::CANDIDATES)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function semanticIds(Group $group, int $beforeId, ?array $queryVector): array
    {
        if ($queryVector === null || $queryVector === []) {
            return [];
        }

        // Indeks HNSW berlaku untuk semua grup; tanpa iterative scan ia hanya memberi ±40 tetangga terdekat
        // global lalu disaring per grup, sehingga grup kecil di antara banyak grup kehilangan hasilnya.
        return DB::transaction(function () use ($group, $beforeId, $queryVector) {
            DB::statement("SET LOCAL hnsw.iterative_scan = 'strict_order'");

            return $this->nearestIds($group, $beforeId, $queryVector);
        });
    }

    /** @return list<int> */
    private function nearestIds(Group $group, int $beforeId, array $queryVector): array
    {
        return DB::table('message_embeddings')
            ->join('messages', 'messages.id', '=', 'message_embeddings.message_id')
            ->where('message_embeddings.group_id', $group->id)
            ->where('messages.group_id', $group->id)
            ->where('messages.id', '<', $beforeId)
            ->whereNull('messages.deleted_at')
            ->whereNull('messages.visible_to_user_id')
            ->whereVectorDistanceLessThan('message_embeddings.embedding', $queryVector, self::MAX_DISTANCE)
            ->orderByVectorDistance('message_embeddings.embedding', $queryVector)
            ->limit(self::CANDIDATES)
            ->pluck('messages.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Pesan terpilih beserta satu pesan sebelum dan sesudahnya (agar tanya-jawab tidak terpotong),
     * dibatasi anggaran token; pesan berperingkat lebih rendah dibuang lebih dulu.
     *
     * @param  list<int>  $ranked
     * @return list<Message>
     */
    private function withNeighbours(Group $group, int $beforeId, array $ranked): array
    {
        if ($ranked === []) {
            return [];
        }

        // Tetangga tiap kandidat (pesan layak sebelum & sesudahnya) dalam satu query, lalu semua pesannya sekaligus.
        $placeholders = implode(', ', array_fill(0, count($ranked), '(?::bigint)'));
        $eligible = 'm.group_id = ? and m.id < ? and m.visible_to_user_id is null and m.deleted_at is null';
        $rows = DB::select(
            "select c.id, (select max(m.id) from messages m where {$eligible} and m.id < c.id) as previous,"
            . " (select min(m.id) from messages m where {$eligible} and m.id > c.id) as next"
            . " from (values {$placeholders}) as c(id)",
            [$group->id, $beforeId, $group->id, $beforeId, ...array_map('intval', $ranked)]
        );
        $neighbours = collect($rows)->keyBy('id');
        $ids = collect($rows)->flatMap(fn ($row) => [$row->id, $row->previous, $row->next])->filter()->unique()->all();
        $messages = $this->eligible($group, $beforeId)->whereIn('id', $ids)->get()->keyBy('id');

        $budget = (int) config('normchat.context.retrieval_tokens', 2000);
        $picked = [];
        $used = 0;

        foreach ($ranked as $id) {
            $center = $messages->get($id);
            if (! $center) {
                continue;
            }

            $row = $neighbours->get($id);
            $previous = $row?->previous ? $messages->get((int) $row->previous) : null;
            $next = $row?->next ? $messages->get((int) $row->next) : null;
            $cluster = array_filter([$previous, $center, $next], fn (?Message $message) => $message && ! isset($picked[$message->id]));

            $cost = array_sum(array_map(fn (Message $message) => Tokens::text($message->content) + 12, $cluster));
            if ($used + $cost > $budget) {
                // Pesan utamanya saja bila tetangganya tidak muat.
                $cost = Tokens::text($center->content) + 12;
                if (isset($picked[$center->id]) || $used + $cost > $budget) {
                    continue;
                }
                $cluster = [$center];
            }

            foreach ($cluster as $message) {
                $picked[$message->id] = $message;
            }
            $used += $cost;
        }

        ksort($picked);

        return array_values($picked);
    }

    private function eligible(Group $group, int $beforeId): Builder
    {
        return Message::query()
            ->with(['sender:id,name', 'replyToMessage.sender:id,name'])
            ->where('group_id', $group->id)
            ->where('id', '<', $beforeId)
            ->whereNull('visible_to_user_id');
    }
}
