<?php

namespace App\Services\Ai\Context;

use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Services\Ai\Tokens;

/**
 * Konteks yang disusun ContextManager untuk satu pertanyaan ke Normi. Urutannya di prompt:
 * ringkasan grup → jendela riwayat → (ringkasan topik + potongan pesan lama + maksud pertanyaan) → pertanyaan.
 */
final class ContextBundle
{
    /** Pesan lama yang ditemukan saat konteks disusun (sebelum dipotong batas input). */
    public readonly int $found;

    /**
     * @param  list<Message>  $window  pesan terbaru, urut lama → baru
     * @param  list<GroupContextTopic>  $topics
     * @param  list<Message>  $retrieved  pesan lama relevan (di luar jendela), urut lama → baru
     * @param  bool  $searched  ContextUnderstanding memutuskan riwayat lama perlu dicari
     * @param  bool  $trimmed  sebagian konteks dibuang karena batas token input per permintaan
     * @param  bool  $refersToPast  pertanyaan merujuk pembicaraan sebelumnya (bukan pertanyaan umum)
     */
    public function __construct(
        public readonly ?string $summary = null,
        public readonly array $window = [],
        public readonly array $topics = [],
        public readonly array $retrieved = [],
        public readonly ?string $intent = null,
        public readonly bool $ambiguous = false,
        public readonly bool $understandingCalled = false,
        public readonly bool $searched = false,
        public readonly bool $trimmed = false,
        public readonly bool $refersToPast = false,
        ?int $found = null,
    ) {
        $this->found = $found ?? count($retrieved);
    }

    /** Angka saja (tanpa isi chat), disimpan di log pemakaian untuk metrik superadmin. */
    public function stats(): array
    {
        return [
            'summary' => $this->summary !== null,
            'window' => count($this->window),
            'topics' => count($this->topics),
            'retrieved' => count($this->retrieved),
            'understanding' => $this->understandingCalled,
            'searched' => $this->searched,
            'clarify' => $this->ambiguous,
            // Normi gagal mengingat: diminta klarifikasi, atau pertanyaan merujuk pembicaraan sebelumnya
            // tapi tidak ada pesan lama yang ketemu. Pertanyaan umum yang tidak ketemu tidak dihitung.
            'recall_failed' => $this->ambiguous || ($this->refersToPast && $this->searched && $this->found === 0),
            'trimmed' => $this->trimmed,
            // Id pesan lama yang dipakai jawaban ini (bukan isinya); ikut lagi di pertanyaan lanjutan.
            'sources' => array_values(array_map(fn (Message $message) => (int) $message->id, $this->retrieved)),
        ];
    }

    /** Untuk mode yang hanya butuh riwayat terbaru (dan untuk uji). @param list<Message> $window */
    public static function windowOnly(array $window): self
    {
        return new self(window: $window);
    }

    public function hasExtra(): bool
    {
        return $this->topics !== [] || $this->retrieved !== [] || $this->intent !== null || $this->ambiguous;
    }

    /**
     * Konteks yang lebih kecil sekitar $tokens token, untuk batas input per permintaan. Yang dibuang lebih
     * dulu: pesan lama hasil pencarian (terlama dulu), topik (terakhir dulu), pesan paling awal di jendela,
     * lalu ringkasan grup. Null bila tidak ada lagi yang bisa dibuang.
     */
    public function shrinkBy(int $tokens): ?self
    {
        $retrieved = $this->retrieved;
        $topics = $this->topics;
        $window = $this->window;
        $summary = $this->summary;
        $removed = 0;

        while ($removed < max(1, $tokens)) {
            if ($retrieved !== []) {
                $removed += ContextManager::messageTokens(array_shift($retrieved));
            } elseif ($topics !== []) {
                $topic = array_pop($topics);
                $removed += Tokens::text($topic->title . $topic->summary) + 5;
            } elseif ($window !== []) {
                $removed += ContextManager::messageTokens(array_shift($window));
            } elseif ($summary !== null) {
                $removed += Tokens::text($summary);
                $summary = null;
            } else {
                break;
            }
        }

        if ($removed === 0) {
            return null;
        }

        return new self(
            summary: $summary,
            window: $window,
            topics: $topics,
            retrieved: $retrieved,
            intent: $this->intent,
            ambiguous: $this->ambiguous,
            understandingCalled: $this->understandingCalled,
            searched: $this->searched,
            trimmed: true,
            refersToPast: $this->refersToPast,
            found: $this->found,
        );
    }
}
