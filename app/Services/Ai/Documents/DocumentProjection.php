<?php

namespace App\Services\Ai\Documents;

use App\Models\Message;
use App\Services\Ai\Files\PilotDocument;
use App\Services\Ai\Tokens;

/**
 * Proyeksi dokumen kanonik untuk model (docs/rancangan-worker-dan-file-nov.md bagian D). Hasil ukur 23 Nov 2026:
 * JSON per baris 125% dari Markdown, JSON/YAML bersarang hingga 179%, baris bernomor + tabel TSV 93%.
 *
 * - question(): tanya jawab. Dokumen kecil utuh; dokumen besar daftar judul + bagian relevan.
 * - revision(): "id| teks" per bagian, supaya patch {"id","old","new"} dicocokkan persis dengan teks aslinya.
 */
class DocumentProjection
{
    /** Dokumen sampai ukuran ini dikirim utuh (bagian lanjutan memakai cache prompt OpenAI). */
    public const FULL_TOKENS = 6000;

    /** Anggaran bagian relevan untuk dokumen besar. */
    public const PART_TOKENS = 3000;

    private const BROAD = '/\b(?:ringkas\w*|rangkum\w*|intisari|garis\s+besar|gambaran\s+umum|jelaskan\s+isi|isi\s+(?:dokumen|file|berkas|pdf|materi)|poin(?:-poin)?\s+(?:penting|utama)|kesimpulan|summary|summari[sz]e|overview|tl;?dr|bahas\s+semua)\b/iu';

    public function __construct(private readonly DocumentSearch $search) {}

    /** Teks dokumen untuk pertanyaan $question (pemanggil sudah memastikan dokumen boleh dilihat penanya). */
    public function question(Message $source, array $document, string $question): string
    {
        $name = $source->attachment_original_name ?: 'lampiran';
        $header = 'Dokumen "'.$name.'"'.($document['page_count'] ?? null ? ', '.$document['page_count'].' halaman' : '')
            .(! empty($document['pages']) ? ', halaman '.$document['pages'] : '').'.';
        $full = self::compact($document['sections'], $document['tables'] ?? []);
        if (Tokens::text($full) <= self::FULL_TOKENS) {
            return $header."\n".$full;
        }

        $outline = array_map(fn (array $item) => '- '.$item['text'], array_slice($document['outline'] ?? [], 0, 60));
        $outlineText = $outline !== [] ? "Daftar judul:\n".implode("\n", $outline)."\n\n" : '';
        $note = 'Dokumen ini panjang; yang dikirim hanya bagian yang relevan. Bila jawabannya ada di bagian lain, katakan bagian'
            .' atau halaman mana yang perlu dibuka, jangan menebak isinya.';
        $parts = preg_match(self::BROAD, $question) === 1
            ? $this->openings($document)
            : $this->relevant($source, $document, $question);

        return $header."\n".$note."\n\n".$outlineText."Bagian dokumen:\n".$parts;
    }

    /**
     * Baris bernomor ("L12| …") dengan tabel Markdown dipadatkan menjadi TSV.
     *
     * @param  list<array<string, string>>  $sections
     */
    public static function compact(array $sections, array $tables = []): string
    {
        $lines = [];
        $table = [];
        $flush = function () use (&$lines, &$table) {
            if ($table !== []) {
                $lines[] = '[tabel, kolom dipisah tab]';
                array_push($lines, ...$table);
                $table = [];
            }
        };
        foreach ($sections as $row) {
            $text = implode(' | ', array_map('strval', $row));
            $trim = trim($text);
            if (str_starts_with($trim, '|') && str_ends_with($trim, '|')) {
                if (! preg_match('/^\|[\s:|-]+\|$/', $trim)) {
                    $table[] = implode("\t", array_map('trim', explode('|', trim($trim, '|'))));
                }

                continue;
            }
            $flush();
            $lines[] = strtoupper((string) array_key_first($row)).'| '.$text;
        }
        $flush();

        return implode("\n", $lines);
    }

    /**
     * Bagian untuk revisi: satu baris per id. Nilai yang mengandung baris baru ditulis sebagai string JSON, supaya
     * "old" yang dikirim model (JSON) tetap sama persis dengan aslinya.
     */
    public static function revision(array $view): string
    {
        $lines = [];
        foreach ($view['sections'] as $row) {
            foreach ($row as $id => $value) {
                $value = (string) $value;
                $lines[] = $id.'| '.(preg_match('/[\r\n]|^\s|\s$/', $value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);
            }
        }

        return implode("\n", $lines);
    }

    /** Awal tiap bagian (judul + dua baris berikutnya) untuk pertanyaan umum pada dokumen besar. */
    private function openings(array $document): string
    {
        $sections = $document['sections'];
        $index = [];
        foreach ($sections as $i => $row) {
            $index[(string) array_key_first($row)] = $i;
        }
        $keep = [];
        foreach ($document['outline'] ?? [] as $item) {
            $start = $index[$item['id']] ?? null;
            if ($start !== null) {
                $keep += array_fill_keys(range($start, min(count($sections) - 1, $start + 2)), true);
            }
        }
        if ($keep === []) {
            $keep = array_fill_keys(range(0, min(count($sections) - 1, 40)), true);
        }

        return $this->within(array_values(array_intersect_key($sections, $keep)), self::PART_TOKENS * 2);
    }

    /** Potongan terindeks yang paling relevan; bila indeks belum ada, baris yang memuat kata dari pertanyaan. */
    private function relevant(Message $source, array $document, string $question): string
    {
        $chunks = $this->search->findIn($source, $question);
        if ($chunks !== []) {
            return implode("\n…\n", $chunks);
        }

        $view = PilotDocument::view($document, $question, true);

        return $this->within($view['sections'], self::PART_TOKENS);
    }

    private function within(array $sections, int $budget): string
    {
        $picked = [];
        $used = 0;
        foreach ($sections as $row) {
            $cost = Tokens::text(implode(' ', $row)) + 4;
            if ($used + $cost > $budget && $picked !== []) {
                break;
            }
            $used += $cost;
            $picked[] = $row;
        }

        return self::compact($picked);
    }
}
