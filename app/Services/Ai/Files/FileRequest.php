<?php

namespace App\Services\Ai\Files;

/**
 * Mengenali permintaan membuat file dari teks pesan ("@normi ubah jadi pdf", "buatkan excel anggaran",
 * "simpan sebagai file python", "jadikan zip"). Kasus nyata 10 Nov 2026: "@normi ubah jadi pdf" dengan lampiran
 * screenshot dijawab "Aku belum bisa mengirim file PDF", karena kata "ubah" tidak dikenali.
 */
final class FileRequest
{
    private const VERBS = '/\b(buat(?:kan|in)?|bikin(?:in|kan)?|generate|create|make|export|ekspor|render|ubah(?:kan)?|jadi(?:kan|in)?|convert|konversi(?:kan)?|simpan(?:kan)?|kirim(?:kan|in)?|unduh(?:an)?|download|susun(?:kan)?|tulis(?:kan)?|siapkan|save|tambah(?:kan|in)?|masuk(?:kan|in)|sisip(?:kan|in)?|pasang(?:kan)?|tempel(?:kan)?|revisi|edit|ganti|rename|compile|cetak|print)\b/u';

    /** Format yang cukup disebut namanya ("buatkan excel anggaran"). Format lain perlu kata "file"/ekstensi. */
    private const STRONG = ['pdf', 'docx', 'xlsx', 'pptx', 'csv', 'zip'];

    /** Kata yang menandai format lemah benar-benar diminta sebagai file. */
    private const FILE_WORDS = '/\b(file|berkas|dokumen|unduh(?:an)?|download|lampiran|attachment|simpan(?:kan)?\s+sebagai|save\s+as)\b/u';

    /** Pertanyaan tentang file, bukan permintaan membuatnya ("kenapa pdf yang dikirim rusak?", "cara bikin ppt"). */
    private const QUESTION = '/^(?:gimana|bagaimana|kenapa|mengapa|apa(?:kah)?|jelaskan|jelasin|kapan|berapa|siapa|di\s*mana)\b/u';

    /** Minta diajari caranya: selalu chat, walau menyebut format. */
    private const HOW_TO = '/\b(?:cara(?:nya)?\s+(?:bikin|buat|membuat|mengubah|ubah|convert|export|ekspor|menyimpan|simpan)|gimana\s+cara|bagaimana\s+cara|tutorial|langkah-langkah)\b/u';

    /** Bentuk hasil disebut jelas ("dalam bentuk pdf", "berupa file excel", "kirim dalam word"): permintaan file walau diawali "jelaskan". */
    private const EXPLICIT_OUTPUT = '/\b(?:dalam\s+(?:bentuk|format)|berupa|sebagai\s+file|(?:kirim(?:kan|in)?|kasih)\s+(?:dalam\s+)?)\s*(?:file\s+)?(?:pdf|word|docx|excel|xlsx|ppt|pptx|powerpoint|csv)\b/u';

    /** "butuh file excel", "mau pdf jadwal ronda", "minta dokumen word". */
    private const WANTS = '/\b(?:butuh|perlu|minta|mau|pengen|pingin|ingin)\b(?:\s+\S+){0,3}?\s+(?:file|berkas|dokumen|pdf|excel|word|docx|xlsx|pptx|ppt|powerpoint|csv|slide)\b/u';

    /** @param  list<string>  $formats */
    private function __construct(
        public readonly array $formats,
        public readonly bool $isConversion,
    ) {}

    /** Conversion of the selected text message, with no new writing or editing instructions. */
    public static function exportsRepliedText(\App\Models\Message $request): bool
    {
        if (!$request->reply_to_message_id || $request->attachment_path || filled($request->reply_quote_text)
            || !in_array($request->ai_mode, [null, \App\Enums\AiMode::File], true)) {
            return false;
        }
        $target = $request->replyToMessage;
        if (!$target || $target->message_type !== 'text' || $target->attachment_path || blank($target->content)
            || $target->group_id != $request->group_id || $target->visible_to_user_id || $target->trashed()) {
            return false;
        }
        return (bool) preg_match('/^(?:@normi(?:-file)?\s+)?(?:(?:tolong|coba|bantu)\s+)*(?:buat(?:kan|in)?|bikin(?:kan|in)?|ubah(?:kan)?|jadi(?:kan|in)?|simpan(?:kan)?|ekspor|export|cetak)(?:\s*lah)?\s+(?:(?:pesan|jawaban|teks)\s+)?(?:ini|itu|tadi)(?:\s+(?:menjadi|jadi|ke|sebagai|dalam))?(?:\s+(?:file|format))?\s+pdf(?:\s+(?:ya|dong))?[.! ]*$/iu', trim((string) $request->content));
    }

    /** Format pertama yang diminta; null = biarkan Normi memilih (tag @normi-file tanpa format). */
    public function format(): ?string
    {
        return $this->formats[0] ?? null;
    }

    /** Permintaan file lewat tag @normi-file: format dipilih dari teks bila ada. */
    public static function forFileMode(string $content): self
    {
        $detected = self::detect($content, requireVerb: false);

        return $detected ?? new self([], false);
    }

    /**
     * Bentuk sehari-hari yang menutupi kata kunci (eval 23 Nov 2026: "kirim filenya" dijawab chat biasa):
     * "filenya"/"pdf-nya" → "file"/"pdf", "dibikinin"/"dijadikan" → "bikinin"/"jadikan", "pdf-in"/"word-kan" → "jadikan pdf".
     */
    public static function normalize(string $text): string
    {
        $nouns = 'file|berkas|dokumen|pdf|excel|word|docx|xlsx|pptx|ppt|powerpoint|csv|slide|presentasi|laporan|surat|tabel|spreadsheet|zip';
        $text = (string) preg_replace('/\b('.$nouns.')-?nya\b/iu', '$1', $text);
        $text = (string) preg_replace('/\b(pdf|word|excel|ppt|pptx|docx|xlsx|csv|zip)-?(?:in|kan|kn)\b/iu', 'jadikan $1', $text);

        return (string) preg_replace('/\bdi(buat|bikin|jadi|ubah|convert|konversi|ekspor|export|simpan|kirim|susun|tulis|cetak|print)(kan|in|kin)?\b/iu', '$1$2', $text);
    }

    private static function documentBeforeImage(string $text): bool
    {
        if (preg_match('/\b(gambar|foto|image|picture|ilustrasi|illustration|logo|poster)\b/u', $text, $image, PREG_OFFSET_CAPTURE) !== 1) {
            return true;
        }
        preg_match('/\b(dokumen|file|berkas|laporan|report|surat|notulen|notula|invoice|faktur|kwitansi|kuitansi|nota|proposal|cv|rab|sertifikat|piagam)\b/u', $text, $doc, PREG_OFFSET_CAPTURE);

        return isset($doc[0]) && $doc[0][1] < $image[0][1];
    }

    public static function detect(string $content, bool $requireVerb = true): ?self
    {
        $text = self::normalize(mb_strtolower(trim((string) preg_replace('/(?<![\w@])@[\w-]+/u', ' ', $content))));
        $explicit = preg_match(self::EXPLICIT_OUTPUT, $text) === 1;
        if ($text === '' || ($requireVerb && ! $explicit && preg_match(self::VERBS, $text) !== 1 && preg_match(self::WANTS, $text) !== 1)) {
            return null;
        }
        if ($requireVerb && (preg_match(self::HOW_TO, $text) === 1 || (preg_match(self::QUESTION, $text) === 1 && ! $explicit))) {
            return null;
        }

        $hasFileWord = preg_match(self::FILE_WORDS, $text) === 1;
        $found = [];

        // Ekstensi eksplisit: ".py", "format .xlsx", "nama file laporan.pdf".
        // Nama pustaka seperti "next.js", "chart.js", "vue.js" bukan permintaan file .js.
        if (preg_match_all('/(?:^|[\s(])([\w-]*)\.([a-z0-9]{1,10})\b/u', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[2] as $index => [$ext, $offset]) {
                $ext = $ext === 'yml' ? 'yaml' : ($ext === 'xls' ? 'xlsx' : ($ext === 'doc' ? 'docx' : ($ext === 'ppt' ? 'pptx' : $ext)));
                $bareExtension = $matches[1][$index][0] === '';
                // "main.py" dihitung; "next.js"/"chart.js" (nama pustaka) hanya bila memang diminta sebagai file.
                if (FileFormats::isKnown($ext) && ($bareExtension || $hasFileWord || $ext !== 'js')) {
                    $found[$offset] = $ext;
                }
            }
        }

        foreach (FileFormats::ALIASES as $alias => $ext) {
            $pattern = '/(?<![\p{L}\p{N}._-])'.preg_quote($alias, '/').'(?![\p{L}\p{N}_-])/u';
            if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            if (! in_array($ext, self::STRONG, true) && ! $hasFileWord) {
                continue;
            }
            // "word" di dalam frasa seperti "password" sudah tersaring oleh batas kata; "slide" dan "presentasi" diterima.
            $found[$match[0][1]] ??= $ext;
        }

        if (preg_match('/\bpresentasi\b/u', $text, $match, PREG_OFFSET_CAPTURE) === 1 && ! in_array('pptx', $found, true)
            && preg_match('/\b(buat(?:kan|in)?|bikin(?:in|kan)?|siapkan|susun(?:kan)?)\s+(?:\w+\s+){0,2}presentasi\b/u', $text) === 1) {
            $found[$match[0][1]] = 'pptx';
        }

        ksort($found);
        $formats = array_values(array_unique($found));

        // "buatkan dokumen notulen rapat" tanpa format: PDF seperti sebelumnya (kecuali permintaan gambar).
        // Jenis dokumen yang hampir selalu diminta sebagai berkas (eval 23 Nov 2026: "buat invoice …" dijawab chat).
        if ($formats === [] && preg_match('/\b(dokumen|file|berkas|laporan|report|surat|notulen|notula|invoice|faktur|kwitansi|kuitansi|nota|proposal|cv|rab|sertifikat|piagam)\b/u', $text) === 1
            // Permintaan gambar ("buatkan logo untuk laporan") bukan file, kecuali jenis dokumennya disebut lebih dulu
            // ("buat invoice … jasa desain logo").
            && self::documentBeforeImage($text)
            && ($requireVerb ? preg_match('/\b(buat(?:kan|in)?|bikin(?:in|kan)?|generate|create|export|ekspor|render|siapkan|kirim(?:kan|in)?)\b/u', $text) === 1 || preg_match(self::WANTS, $text) === 1 : true)) {
            $formats = ['pdf'];
        }

        if ($formats === []) {
            return null;
        }

        $isConversion = preg_match('/^(?:(?:tolong|coba|boleh|bantu)\s+)*(?:ubah(?:kan)?|jadi(?:kan|in)?|convert|konversi(?:kan)?|simpan(?:kan)?|export|ekspor|cetak|print)\s+(?:(?:foto|gambar|image|screenshot|ini|itu|jadi|menjadi|sebagai|ke|dalam|format|file)\s+)*pdf(?:\s+(?:ya|dong))?[.!]?$/u', $text) === 1;

        return new self($formats, $isConversion);
    }
}
