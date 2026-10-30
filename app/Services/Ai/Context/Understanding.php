<?php

namespace App\Services\Ai\Context;

/** Hasil Context Understanding Manager untuk satu pertanyaan. */
final class Understanding
{
    /**
     * @param  list<string>  $terms  kata kunci / kata pencarian untuk HistorySearch
     * @param  list<int>  $topicIds  topik yang dirujuk
     * @param  list<int>  $mustInclude  pesan lama yang wajib ikut (pesan yang dibalas di luar jendela)
     * @param  TimeRange|null  $range  rentang waktu yang disebut pertanyaan; diprioritaskan saat mencari
     * @param  bool  $refersToPast  pertanyaan merujuk pembicaraan sebelumnya (dasar metrik "gagal mengingat")
     */
    public function __construct(
        public readonly bool $needsSearch = false,
        public readonly array $terms = [],
        public readonly array $topicIds = [],
        public readonly array $mustInclude = [],
        public readonly ?string $intent = null,
        public readonly bool $ambiguous = false,
        public readonly bool $usedModel = false,
        public readonly ?TimeRange $range = null,
        public readonly bool $refersToPast = false,
    ) {
    }
}
