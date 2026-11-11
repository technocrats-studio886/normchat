<?php

namespace App\Services\Ai\Documents;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Services\Ai\Context\ContextUnderstanding;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Tokens;
use Illuminate\Support\Facades\DB;

/**
 * Cari bagian dokumen grup yang relevan dengan pertanyaan ke Normi (kata kunci + makna, digabung Reciprocal Rank
 * Fusion). Hanya dokumen grup itu, dikirim sebelum pertanyaan, tidak terhapus, bukan pemberitahuan pribadi, dan
 * sesudah batas riwayat penanya (group_members.history_from_message_id).
 */
class DocumentSearch
{
    private const CANDIDATES = 20;

    private const RRF_K = 60;

    private const MAX_DISTANCE = 0.6;

    private const MAX_TOKENS = 3000;

    /** Kata yang menandakan pertanyaan merujuk dokumen/lampiran grup. */
    private const DOCUMENT_WORDS = 'dokumen|file|berkas|pdf|docx?|word|pptx?|ppt|slide|slides|salindia|presentasi|materi|modul|makalah|jurnal|paper|laporan|lampiran|excel|xlsx|spreadsheet|sheet|tabel|silabus|rps|handout|catatan kuliah|bab|halaman|hal\.|buku|e-?book|proposal|skripsi|kontrak|surat|notulen|notula';

    public function __construct(private readonly MaintenanceAi $ai)
    {
    }

    public static function refersToDocuments(string $question): bool
    {
        return preg_match('/(?<![\p{L}])(?:' . self::DOCUMENT_WORDS . ')(?![\p{L}])/iu', $question) === 1;
    }

    /**
     * @param  list<float>|null  $queryVector  embedding pertanyaan bila sudah ada
     * @return list<array{message_id: int, name: string, sender: string, sent_at: \Carbon\CarbonInterface, content: string}>
     */
    public function find(Group $group, Message $request, string $question, ?array $queryVector = null): array
    {
        $base = $this->eligible($group, $request);
        if (! (clone $base)->exists()) {
            return [];
        }
        // Nama file yang disebut di pertanyaan ("modul-3.pdf", "laporan keuangan") diutamakan.
        $named = $this->namedMessageIds($group, $request, $question);

        $scores = [];
        $rankings = [$this->keywordIds($base, $question)];
        $queryVector ??= $this->ai->embed($group, [$question], (int) config('normchat.context.understanding_timeout', 8))[0] ?? null;
        if ($queryVector !== null) {
            $rankings[] = $this->semanticIds($base, $queryVector);
        }
        foreach ($rankings as $ranking) {
            foreach ($ranking as $rank => $id) {
                $scores[$id] = ($scores[$id] ?? 0) + 1 / (self::RRF_K + $rank + 1);
            }
        }
        if ($named !== []) {
            $boost = (clone $base)->whereIn('document_chunks.message_id', $named)->whereIn('document_chunks.id', array_keys($scores))->pluck('document_chunks.id');
            foreach ($boost as $id) {
                $scores[(int) $id] += 1 / (self::RRF_K + 1);
            }
            if ($scores === []) {
                // Dokumen disebut namanya tapi kata-katanya tidak cocok: awal dokumen itu.
                foreach ((clone $base)->whereIn('document_chunks.message_id', $named)->orderBy('document_chunks.chunk_index')->limit(3)->pluck('document_chunks.id') as $rank => $id) {
                    $scores[(int) $id] = 1 / (self::RRF_K + $rank + 1);
                }
            }
        }
        if ($scores === []) {
            return [];
        }
        arsort($scores);

        $rows = DB::table('document_chunks')
            ->join('messages', 'messages.id', '=', 'document_chunks.message_id')
            ->leftJoin('users', 'users.id', '=', 'messages.sender_id')
            ->whereIn('document_chunks.id', array_keys($scores))
            ->get(['document_chunks.id', 'document_chunks.message_id', 'document_chunks.chunk_index', 'document_chunks.content',
                'messages.attachment_original_name', 'messages.created_at', 'messages.sender_type', 'users.name as sender_name'])
            ->keyBy('id');

        $picked = [];
        $used = 0;
        foreach (array_keys($scores) as $id) {
            $row = $rows->get($id);
            if (! $row) {
                continue;
            }
            $cost = Tokens::text($row->content) + 20;
            if ($used + $cost > self::MAX_TOKENS && $picked !== []) {
                break;
            }
            $used += $cost;
            $picked[] = [
                'message_id' => (int) $row->message_id,
                'chunk_index' => (int) $row->chunk_index,
                'name' => (string) ($row->attachment_original_name ?: 'lampiran'),
                'sender' => $row->sender_type === 'ai' ? 'Normi AI' : (string) ($row->sender_name ?? 'Anggota'),
                'sent_at' => \Illuminate\Support\Carbon::parse($row->created_at),
                'content' => (string) $row->content,
            ];
        }
        // Urut per dokumen lalu urutan bagian, supaya potongan dari dokumen yang sama terbaca berurutan.
        usort($picked, fn (array $a, array $b) => [$a['message_id'], $a['chunk_index']] <=> [$b['message_id'], $b['chunk_index']]);

        return $picked;
    }

    private function eligible(Group $group, Message $request)
    {
        $floor = $request->sender_type === 'user' && $request->sender_id
            ? (int) (GroupMember::query()->where('group_id', $group->id)->where('user_id', $request->sender_id)->value('history_from_message_id') ?? 0)
            : 0;

        return DB::table('document_chunks')
            ->join('messages', 'messages.id', '=', 'document_chunks.message_id')
            ->where('document_chunks.group_id', $group->id)
            ->where('messages.group_id', $group->id)
            ->where('messages.id', '<', $request->id)
            ->when($floor > 0, fn ($query) => $query->where('messages.id', '>', $floor))
            ->whereNull('messages.deleted_at')
            ->whereNull('messages.visible_to_user_id');
    }

    /** @return list<int> */
    private function keywordIds($base, string $question): array
    {
        $words = ContextUnderstanding::keywords($question);
        if ($words === []) {
            return [];
        }
        $query = implode(' OR ', $words);
        $document = "to_tsvector('simple', document_chunks.content)";

        return (clone $base)
            ->whereRaw("{$document} @@ websearch_to_tsquery('simple', ?)", [$query])
            ->orderByRaw("ts_rank({$document}, websearch_to_tsquery('simple', ?)) desc", [$query])
            ->limit(self::CANDIDATES)
            ->pluck('document_chunks.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function semanticIds($base, array $queryVector): array
    {
        return DB::transaction(function () use ($base, $queryVector) {
            DB::statement("SET LOCAL hnsw.iterative_scan = 'strict_order'");

            return (clone $base)
                ->whereNotNull('document_chunks.embedding')
                ->whereVectorDistanceLessThan('document_chunks.embedding', $queryVector, self::MAX_DISTANCE)
                ->orderByVectorDistance('document_chunks.embedding', $queryVector)
                ->limit(self::CANDIDATES)
                ->pluck('document_chunks.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });
    }

    /** @return list<int> dokumen yang namanya disebut di pertanyaan */
    private function namedMessageIds(Group $group, Message $request, string $question): array
    {
        $lower = mb_strtolower($question);
        $ids = [];
        $documents = Message::query()->where('group_id', $group->id)->where('id', '<', $request->id)->whereNotNull('doc_indexed_at')
            ->whereNull('visible_to_user_id')->orderByDesc('id')->limit(200)->get(['id', 'attachment_original_name']);
        foreach ($documents as $document) {
            $base = mb_strtolower(pathinfo((string) $document->attachment_original_name, PATHINFO_FILENAME));
            $spaced = trim((string) preg_replace('/[_\-.]+/u', ' ', $base));
            if (mb_strlen($spaced) >= 4 && (str_contains($lower, $base) || str_contains($lower, $spaced))) {
                $ids[] = (int) $document->id;
            }
        }

        return $ids;
    }
}
