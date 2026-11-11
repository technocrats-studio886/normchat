<?php

namespace App\Services\Ai\Context;

use App\Models\AiUsageLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupContextSummary;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Services\Ai\Documents\DocumentSearch;
use App\Services\Ai\Tokens;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Context Manager: memilih konteks untuk satu pertanyaan ke Normi, selalu "per saat pesan itu"
 * (hanya pesan sebelum pesan penanya), dari tiga lapis ingatan:
 *
 * - ringkasan grup (bila cakupannya berakhir sebelum pertanyaan dan tidak basi),
 * - jendela riwayat asli dari jangkar tetap (prefix prompt hanya bertambah di ujung → cache OpenAI kena),
 * - ringkasan topik + pesan lama yang relevan, dipilih bersama ContextUnderstanding.
 *
 * Semua query memakai group_id, tanpa pesan terhapus untuk semua dan tanpa pemberitahuan pribadi.
 */
class ContextManager
{
    /** Batas pesan yang dibaca untuk jendela (jaga-jaga grup sangat ramai). */
    private const WINDOW_FETCH_LIMIT = 400;

    /** Perkiraan token tambahan per pesan (nama pengirim, penanda balasan). */
    private const LINE_OVERHEAD_TOKENS = 12;

    /** Pesan sumber terbaru per topik yang ikut ke Normi. */
    private const TOPIC_SOURCE_MESSAGES = 5;

    public function __construct(
        private readonly ContextUnderstanding $understanding,
        private readonly HistorySearch $search,
        private readonly MaintenanceAi $ai,
        private readonly DocumentSearch $documents,
    ) {
    }

    public function build(Group $group, Message $request): ContextBundle
    {
        $floor = $this->historyFloor($group, $request);
        if ($floor !== null) {
            return $this->withDocuments($group, $request, $this->buildWithinVisibleHistory($group, $request, $floor), null);
        }

        $state = GroupContextSummary::query()->where('group_id', $group->id)->first();
        $summary = $state?->usableSummaryBefore((int) $request->id);
        $anchor = $summary !== null ? $state->window_start_message_id : null;

        $window = $this->window($group, $request, $anchor);
        $windowStart = $window === [] ? (int) $request->id : (int) $window[0]->id;
        $hasOlder = $this->eligible($group, $windowStart)->exists();

        $topics = GroupContextTopic::query()->usableBefore((int) $group->id, (int) $request->id)
            ->orderByDesc('last_message_id')->limit(30)->get();
        $understanding = $this->understanding->analyze($group, $request, $window, $topics, $hasOlder);

        $queryVector = null;
        if ($understanding->needsSearch || ($topics->isNotEmpty() && $hasOlder)) {
            // Di jalur jawaban: batas waktu pendek; bila lambat/gagal, pencarian jalan dengan kata kunci saja.
            $queryVector = $this->ai->embed($group, [$this->queryText($request, $understanding)], (int) config('normchat.context.understanding_timeout', 8))[0] ?? null;
        }

        $picked = $this->pickTopics($topics, $understanding, $queryVector, $windowStart);
        // Sumber jawaban Normi terakhir di jendela (supaya pertanyaan lanjutan tidak membantah jawaban itu),
        // lalu pesan sumber topik terpilih: detail persis (angka, tanggal, kalimat) dari pesan asli.
        $sourceIds = collect($this->previousAnswerSources($window))
            ->merge(collect($picked)->flatMap(fn (GroupContextTopic $topic) => array_slice(array_map('intval', (array) $topic->key_message_ids), -self::TOPIC_SOURCE_MESSAGES)))
            ->filter(fn (int $id) => $id < $windowStart)
            ->unique()
            ->values()
            ->all();

        $retrieved = $understanding->needsSearch || $sourceIds !== []
            ? $this->search->find(
                $group,
                $windowStart,
                $understanding->terms,
                $queryVector,
                $understanding->mustInclude,
                $sourceIds,
                $understanding->range,
                search: $understanding->needsSearch,
            )
            : [];

        return $this->withDocuments($group, $request, new ContextBundle(
            summary: $summary !== null ? self::limitTokens($summary, (int) config('normchat.context.summary_tokens', 600)) : null,
            window: $window,
            topics: $picked,
            retrieved: $retrieved,
            intent: $understanding->intent,
            ambiguous: $understanding->ambiguous && $retrieved === [],
            understandingCalled: $understanding->usedModel,
            searched: $understanding->needsSearch,
            refersToPast: $understanding->refersToPast,
        ), $queryVector);
    }

    /**
     * Pertanyaan yang menyebut dokumen ("menurut modul", "slide kemarin", nama filenya): bagian dokumen grup yang
     * relevan ikut ke prompt. Membalas langsung pesan file tidak perlu, karena isi file itu sudah dikirim utuh.
     */
    private function withDocuments(Group $group, Message $request, ContextBundle $context, ?array $queryVector): ContextBundle
    {
        $question = ContextUnderstanding::question($request);
        $replyTarget = $request->replyToMessage;
        if ($question === '' || ($replyTarget && $replyTarget->message_type === 'file' && $replyTarget->attachment_path)
            || ! DocumentSearch::refersToDocuments($question)) {
            return $context;
        }
        $documents = $this->documents->find($group, $request, $question, $queryVector);

        return $documents === [] ? $context : $context->withDocuments($documents);
    }

    /**
     * Pesan dari jangkar (atau semua, bila belum ada ringkasan) sampai sebelum pertanyaan, dalam
     * anggaran token. Bila melebihi anggaran, pesan paling awal yang dibuang.
     *
     * @return list<Message>
     */
    private function window(Group $group, Message $request, ?int $anchor, ?int $floor = null): array
    {
        $messages = $this->eligible($group, (int) $request->id)
            ->when($anchor !== null, fn (Builder $query) => $query->where('id', '>=', $anchor))
            ->when($floor !== null, fn (Builder $query) => $query->where('id', '>', $floor))
            ->orderByDesc('id')
            ->limit(self::WINDOW_FETCH_LIMIT)
            ->get();

        $budget = (int) config('normchat.context.window_tokens', 6000);
        $kept = [];
        $used = 0;
        foreach ($messages as $message) {
            $cost = self::messageTokens($message);
            if ($used + $cost > $budget && $kept !== []) {
                break;
            }
            $kept[] = $message;
            $used += $cost;
        }

        return array_reverse($kept);
    }

    /**
     * Pesan lama yang dipakai jawaban Normi terakhir di jendela (dicatat di log pemakaiannya).
     *
     * @param  list<Message>  $window
     * @return list<int>
     */
    private function previousAnswerSources(array $window): array
    {
        $answer = collect($window)->last(fn (Message $message) => $message->sender_type === 'ai');
        if (! $answer) {
            return [];
        }

        $context = AiUsageLog::query()
            ->where('response_message_id', $answer->id)
            ->latest('id')
            ->value('breakdown_json')['context'] ?? [];

        return array_values(array_map('intval', (array) ($context['sources'] ?? [])));
    }

    /**
     * Topik untuk pertanyaan ini (maks. 2, dalam anggaran token): yang dirujuk ContextUnderstanding,
     * yang paling mirip maknanya, lalu topik berjalan yang awal pembahasannya sudah di luar jendela.
     *
     * @param  Collection<int, GroupContextTopic>  $topics
     * @return list<GroupContextTopic>
     */
    private function pickTopics(Collection $topics, Understanding $understanding, ?array $queryVector, int $windowStart): array
    {
        if ($topics->isEmpty()) {
            return [];
        }

        $ids = $understanding->topicIds;
        if ($queryVector !== null) {
            $similar = GroupContextTopic::query()
                ->whereIn('id', $topics->pluck('id'))
                ->whereNotNull('embedding')
                ->whereVectorDistanceLessThan('embedding', $queryVector, 1 - (float) config('normchat.context.topic_similarity', 0.45))
                ->orderByVectorDistance('embedding', $queryVector)
                ->limit(2)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = [...$ids, ...$similar];
        }
        $running = $topics->first(fn (GroupContextTopic $topic) => $topic->first_message_id < $windowStart && $topic->last_message_id >= $windowStart);
        if ($running) {
            $ids[] = (int) $running->id;
        }

        $budget = (int) config('normchat.context.topic_tokens', 800);
        $picked = [];
        $used = 0;
        foreach (array_unique($ids) as $id) {
            $topic = $topics->firstWhere('id', $id);
            $cost = $topic ? Tokens::text($topic->title . $topic->summary) : 0;
            if (! $topic || $used + $cost > $budget || count($picked) >= (int) config('normchat.context.max_topics', 2)) {
                continue;
            }
            $picked[] = $topic;
            $used += $cost;
        }

        return $picked;
    }

    /**
     * Penanya yang riwayatnya belum dikirim pemilik (group_members.history_from_message_id): batas id pesan
     * yang boleh ia lihat. Null = seluruh riwayat.
     */
    private function historyFloor(Group $group, Message $request): ?int
    {
        if ($request->sender_type !== 'user' || ! $request->sender_id) {
            return null;
        }
        $floor = GroupMember::query()->where('group_id', $group->id)->where('user_id', $request->sender_id)->value('history_from_message_id');

        return $floor !== null ? (int) $floor : null;
    }

    /**
     * Konteks untuk penanya yang riwayatnya tertutup: hanya pesan sesudah ia bergabung. Ringkasan, topik,
     * dan pencarian ingatan grup tidak dipakai karena bisa memuat obrolan sebelum ia bergabung.
     */
    private function buildWithinVisibleHistory(Group $group, Message $request, int $floor): ContextBundle
    {
        $window = $this->window($group, $request, null, $floor);
        $understanding = $this->understanding->analyze($group, $request, $window, collect(), false);

        return new ContextBundle(
            summary: null,
            window: $window,
            topics: [],
            retrieved: [],
            intent: $understanding->intent,
            ambiguous: $understanding->ambiguous,
            understandingCalled: $understanding->usedModel,
            searched: false,
            refersToPast: $understanding->refersToPast,
        );
    }

    private function queryText(Message $request, Understanding $understanding): string
    {
        return trim(($understanding->intent ?? ContextUnderstanding::question($request)) . ' ' . implode(' ', $understanding->terms));
    }

    private function eligible(Group $group, int $beforeId): Builder
    {
        return Message::query()
            ->with(['sender:id,name', 'replyToMessage.sender:id,name'])
            ->where('group_id', $group->id)
            ->where('id', '<', $beforeId)
            ->whereNull('visible_to_user_id')
            ->where('sender_type', '!=', Message::SENDER_SYSTEM);
    }

    public static function messageTokens(Message $message): int
    {
        $tokens = Tokens::text((string) $message->content) + self::LINE_OVERHEAD_TOKENS;
        if ($message->attachment_path) {
            // Lampiran ikut dikirim di samping keterangannya: penanda singkat, atau isi dokumen (maks. 1.500 karakter).
            $tokens += $message->message_type === 'file' ? Tokens::text(str_repeat('x', 1500)) : 20;
        }

        return $tokens;
    }

    private static function limitTokens(string $text, int $tokens): string
    {
        $chars = (int) ($tokens * Tokens::CHARS_PER_TOKEN);

        return mb_strlen($text) > $chars ? rtrim(mb_substr($text, 0, $chars)) . '…' : $text;
    }
}
