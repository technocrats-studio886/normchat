<?php

namespace App\Services\Ai\Context;

use App\Models\Group;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Services\Chat\MessagePresenter;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Context Understanding Manager: saat Normi di-tag, menilai apakah ringkasan + jendela riwayat sudah
 * cukup untuk memahami pertanyaan, dan bila belum, apa yang harus dicari di riwayat lama.
 *
 * 1. Aturan backend (selalu, tanpa biaya): pesan yang dibalas di luar jendela, kata rujukan masa lalu,
 *    kata kunci yang tidak ada di jendela, judul topik yang disebut, rentang waktu ("kemarin", "minggu lalu").
 * 2. Hanya bila pertanyaan merujuk sesuatu yang tidak bisa dipetakan aturan, model termurah dipanggil
 *    sekali untuk menulis ulang maksud pertanyaan menjadi kata pencarian dan topik (MaintenanceAi).
 */
class ContextUnderstanding
{
    private const MAX_OUTPUT_TOKENS = 300;

    /** Rujukan ke pembicaraan sebelumnya. */
    private const REFERENCE_PATTERNS = [
        'kemarin', 'kemaren', 'tadi', 'barusan', 'sebelumnya', 'sebelum ini', 'dulu', 'waktu itu', 'minggu lalu',
        'bulan lalu', 'tahun lalu', 'akhirnya', 'keputusan', 'diputuskan', 'kesepakatan', 'disepakati', 'sepakat',
        'yang mana', 'kenapa kita', 'mengapa kita', 'yang kedua', 'yang pertama', 'yang ketiga', 'yang terakhir',
        'yang itu', 'itu tadi', 'tersebut', 'yang dibahas', 'pernah', 'lanjutkan', 'lanjut yang', 'soal yang',
    ];

    private const STOPWORDS = [
        'normi', 'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'ini', 'itu', 'ada', 'apa', 'apakah', 'kenapa', 'mengapa',
        'bagaimana', 'gimana', 'kalau', 'kalo', 'jika', 'untuk', 'buat', 'dengan', 'sama', 'pada', 'dalam', 'jadi',
        'akan', 'sudah', 'udah', 'belum', 'masih', 'bisa', 'boleh', 'harus', 'mau', 'ingin', 'saja', 'aja', 'juga',
        'lagi', 'tolong', 'coba', 'dong', 'deh', 'sih', 'nih', 'kah', 'lah', 'kita', 'kami', 'kamu', 'kalian', 'aku',
        'saya', 'dia', 'mereka', 'menurut', 'menurutmu', 'tadi', 'kemarin', 'akhirnya', 'pilih', 'memilih', 'dipilih',
        'mana', 'kedua', 'pertama', 'ketiga', 'terakhir', 'tersebut', 'soal', 'tentang', 'terus', 'lalu', 'karena',
        'tapi', 'tetapi', 'agar', 'supaya', 'biar', 'oleh', 'para', 'tiap', 'setiap', 'semua', 'banyak', 'sedikit',
        'lebih', 'paling', 'sangat', 'banget', 'the', 'and', 'what', 'how', 'why', 'which', 'this', 'that',
        'jelaskan', 'jawab', 'bantu', 'bikin', 'buatkan', 'sebelumnya', 'dulu', 'pernah', 'bahas', 'dibahas', 'gitu',
        'begitu', 'begini', 'kayak', 'seperti', 'yaitu', 'adalah', 'ya', 'yah', 'oke', 'ok',
        // Kata percakapan umum: tidak menunjuk topik apa pun ("yang itu tadi jadinya gimana?").
        'jadinya', 'akhirnya', 'gmn', 'gimana', 'kok', 'kan', 'nah', 'emang', 'memang', 'beneran', 'sebenarnya',
        'pokoknya', 'intinya', 'hal', 'masalah', 'urusan', 'yg', 'udh', 'blm', 'aja', 'gitu', 'gt', 'tuh', 'dah',
        'kayaknya', 'mungkin', 'harusnya', 'seharusnya', 'katanya', 'gimanain', 'bagaimanakah', 'apanya',
        // Kata penilaian umum: tidak menunjuk topik apa pun ("itu tadi sudah benar?").
        'benar', 'betul', 'salah', 'bagus', 'baik', 'jelek', 'buruk', 'cocok', 'tepat', 'sesuai', 'setuju',
        'bener', 'mantap', 'gak', 'nggak', 'tidak', 'bukan', 'perlu', 'cukup', 'kurang',
    ];

    public function __construct(
        private readonly MaintenanceAi $ai,
        private readonly MessagePresenter $presenter,
    ) {
    }

    /**
     * @param  list<Message>  $window
     * @param  iterable<GroupContextTopic>  $topics  topik yang boleh dipakai untuk pertanyaan ini
     * @param  bool  $hasOlder  ada riwayat sebelum jendela
     */
    public function analyze(Group $group, Message $request, array $window, iterable $topics, bool $hasOlder): Understanding
    {
        if (! $hasOlder) {
            return new Understanding();
        }

        $question = self::question($request);
        $lower = mb_strtolower($question);
        $windowText = mb_strtolower(implode("\n", array_map(fn (Message $message) => (string) $message->content, $window)));
        $windowStart = $window === [] ? $request->id : (int) $window[0]->id;

        $mustInclude = [];
        $replyTo = (int) ($request->reply_to_message_id ?? 0);
        if ($replyTo > 0 && $replyTo < $windowStart) {
            $mustInclude[] = $replyTo;
        }

        // Rentang waktu yang disebut ikut diprioritaskan saat mencari; sebelum jendela saja yang perlu dicari.
        $range = TimeRange::in($question, CarbonImmutable::now(UserTime::zone($request->sender)));
        $firstInWindow = $window[0]->created_at ?? null;
        if ($range !== null && $firstInWindow !== null && $range->from >= $firstInWindow) {
            $range = null;
        }

        $hasReference = collect(self::REFERENCE_PATTERNS)->contains(fn (string $pattern) => preg_match('/(?<![\p{L}])' . preg_quote($pattern, '/') . '(?![\p{L}])/u', $lower) === 1);
        $keywords = self::keywords($question);
        $missing = array_values(array_filter($keywords, fn (string $word) => ! str_contains($windowText, $word)));

        $topicList = collect($topics)->values();
        $namedTopics = $topicList->filter(function (GroupContextTopic $topic) use ($lower, $keywords) {
            $title = mb_strtolower($topic->title);

            return str_contains($lower, $title)
                || collect(self::keywords($topic->title))->contains(fn (string $word) => mb_strlen($word) >= 4 && in_array($word, $keywords, true));
        })->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $refersToPast = $hasReference || $range !== null || $mustInclude !== [];
        if (! $hasReference && $missing === [] && $mustInclude === [] && $range === null) {
            return new Understanding(topicIds: $namedTopics);
        }

        // Rujukan yang tidak bisa dipetakan aturan: minta model memahami maksudnya.
        if ($hasReference && $missing === [] && $namedTopics === []) {
            $model = $this->askModel($group, $question, $request, $window, $topicList->all());
            if ($model !== null) {
                return new Understanding(
                    needsSearch: $model['needs_old'] || $mustInclude !== [] || $range !== null,
                    terms: array_values(array_unique([...$model['queries'], ...$keywords])),
                    topicIds: $model['topics'],
                    mustInclude: $mustInclude,
                    intent: $model['intent'],
                    ambiguous: $model['ambiguous'],
                    usedModel: true,
                    range: $range,
                    refersToPast: $refersToPast || $model['needs_old'],
                );
            }
        }

        return new Understanding(
            needsSearch: true,
            terms: $missing !== [] ? $missing : $keywords,
            topicIds: $namedTopics,
            mustInclude: $mustInclude,
            range: $range,
            refersToPast: $refersToPast,
        );
    }

    /** Pertanyaan tanpa @normi. */
    public static function question(Message $request): string
    {
        return trim((string) preg_replace('/(?<![\w@])@normi(?![\w])/iu', '', (string) $request->content));
    }

    /** @return list<string> kata bermakna (huruf kecil, unik) */
    public static function keywords(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\-]{2,}/u', mb_strtolower($text), $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            fn (string $word) => ! in_array($word, self::STOPWORDS, true) && ! ctype_digit($word)
        )));
    }

    /**
     * @param  list<Message>  $window
     * @param  list<GroupContextTopic>  $topics
     * @return array{intent: ?string, needs_old: bool, queries: list<string>, topics: list<int>, ambiguous: bool}|null
     */
    private function askModel(Group $group, string $question, Message $request, array $window, array $topics): ?array
    {
        $recent = array_slice($window, -10);
        $lines = array_map(fn (Message $message) => $this->presenter->senderName($message) . ': ' . Str::limit((string) $message->content, 400), $recent);
        $topicLines = array_map(
            fn (GroupContextTopic $topic) => '#' . $topic->id . ' ' . $topic->title . ' — ' . Str::limit((string) Str::of($topic->summary)->before("\n"), 160),
            array_slice($topics, 0, 30)
        );

        $data = $this->ai->json(MaintenanceAi::UNDERSTANDING, $group, [
            ['role' => 'system', 'content' => "Kamu membantu Normi AI memahami pertanyaan seorang anggota grup chat sebelum Normi menjawab.\n"
                . "Pertanyaan itu merujuk pembicaraan sebelumnya. Tentukan maksudnya dari pesan terakhir dan daftar topik grup.\n"
                . "Balas HANYA JSON:\n"
                . '{"maksud": "satu kalimat: apa yang sebenarnya ditanyakan, dengan rujukan yang sudah diperjelas", '
                . '"perlu_konteks_lama": true, "kata_pencarian": ["1-3 frasa pendek untuk mencari pesan lama"], '
                . '"topik": [id topik yang relevan], "kejelasan": "jelas" atau "ambigu"}' . "\n"
                . 'kejelasan = "ambigu" bila rujukannya tetap tidak bisa dipastikan dari konteks.'],
            ['role' => 'user', 'content' => "Topik grup:\n" . ($topicLines === [] ? '(belum ada)' : implode("\n", $topicLines))
                . "\n\nPesan terakhir:\n" . ($lines === [] ? '(tidak ada)' : implode("\n", $lines))
                . "\n\nPertanyaan untuk Normi dari " . $this->presenter->senderName($request) . ': ' . $question],
        ], self::MAX_OUTPUT_TOKENS, (int) config('normchat.context.understanding_timeout', 8));

        if ($data === null) {
            return null;
        }
        $data = $data['data'];

        $knownTopics = array_map(fn (GroupContextTopic $topic) => (int) $topic->id, $topics);
        $queries = array_values(array_filter(array_map(
            fn ($query) => Str::limit(trim((string) $query), 100, ''),
            array_slice((array) ($data['kata_pencarian'] ?? []), 0, 3)
        )));
        $intent = trim((string) ($data['maksud'] ?? ''));

        return [
            'intent' => $intent !== '' ? Str::limit($intent, 300, '') : null,
            'needs_old' => (bool) ($data['perlu_konteks_lama'] ?? true),
            'queries' => $queries,
            'topics' => array_values(array_intersect(array_map('intval', (array) ($data['topik'] ?? [])), $knownTopics)),
            'ambiguous' => ($data['kejelasan'] ?? 'jelas') === 'ambigu',
        ];
    }
}
