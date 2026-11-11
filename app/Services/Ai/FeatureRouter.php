<?php

namespace App\Services\Ai;

use App\Enums\AiMode;
use App\Models\Group;
use App\Models\Message;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Files\FileRequest;
use App\Services\Ai\Responders\ImageResponder;

/**
 * "@normi biasa" langsung memakai fitur yang dimaksud, tanpa mengarahkan dulu (keputusan user 12 Nov 2026):
 * gambar (buat & edit foto), Canvas, Deep Research, Guided Learning, dan Buat file. Aturan kata kunci lebih dulu;
 * kalimat yang mirip permintaan fitur tapi ragu (mis. "gimana cara bikin website?") diputuskan model termurah
 * (MaintenanceAi, ditanggung platform, tanpa NK). Memilih fitur sendiri (menu + atau tag @normi-…) tidak lewat sini.
 */
final class FeatureRouter
{
    public const KIND = 'feature_router';

    /** Kata kerja permintaan membuat sesuatu. */
    private const MAKE = '(?:buat(?:kan|in|kin)?|bikin(?:in|kan)?|rancang(?:kan)?|desain(?:kan|in)?|develop|kembangkan|coding(?:kan|in)?|ngoding(?:in)?|program(?:kan|in)?|generate|create|make|build)';

    /** Hal yang dikerjakan Canvas: halaman web dan aplikasi kecil yang bisa dipratinjau. */
    private const CANVAS_THING = '(?<![\p{L}])(?:website|web\s*site|web\s*app|webapp|situs|landing\s*page|halaman\s+(?:web|landing|login|profil)|aplikasi|app|game|gim|kalkulator|dashboard|prototipe|prototype|mock\s*up|mockup|tampilan\s+(?:web|aplikasi|app)|ui|portofolio|portfolio|kuis\s+interaktif|quiz|html|to-?do(?:\s*list)?|form(?:ulir)?\s+(?:online|pendaftaran)|kalender\s+interaktif|timer|stopwatch|simulasi|visualisasi\s+interaktif)(?![\p{L}])';

    /** Permintaan riset web: kata kerja riset, atau mencari sumber/data terkini. */
    private const RESEARCH = '(?:riset(?:kan|in)?|research|deep\s*research|telusuri|investigasi|studi\s+literatur|kajian\s+literatur|tinjauan\s+pustaka)';

    private const SEARCH_VERB = '(?:carikan|cariin|cari(?:kan)?|temukan|kumpulkan|rangkum(?:kan)?\s+dari\s+internet)';

    private const SOURCE_THING = '(?:jurnal|paper|artikel\s+ilmiah|penelitian|referensi|sumber|literatur|data|statistik|berita|harga|tren|survei|laporan\s+terbaru|regulasi\s+terbaru|peraturan\s+terbaru)';

    /** Minta diajari bertahap. */
    private const LEARN = '(?:ajari(?:n)?|ajarin|ajarkan|bimbing|tuntun|tutorin|lesin|tutor(?:kan)?)\s+(?:aku|saya|gue|gw|kami|kita|anakku|adikku)|belajar\s+(?:bareng|bertahap|dari\s+(?:nol|dasar|awal)|step\s*by\s*step)|(?:mau|ingin|pengen|pingin)\s+belajar|step\s*by\s*step\s+(?:ajari|ajarin)';

    /** Pertanyaan/penjelasan, bukan permintaan mengerjakan. */
    private const QUESTION = '^(?:gimana|bagaimana|kenapa|mengapa|apa\s+itu|apa\s+bedanya|apakah|jelaskan|jelasin|tips|cara|langkah|kapan|berapa|siapa)\b|\b(?:cara(?:nya)?\s+(?:bikin|buat|membuat|cari|mencari)|gimana\s+cara|bagaimana\s+cara|menurutmu|menurut\s+kamu|bisa\s+gak\s+sih)\b';

    /** Kata yang membuat pesan layak ditanyakan ke router bila aturan tidak yakin. */
    private const FEATURE_WORDS = '(?<![\p{L}])(?:gambar|foto|ilustrasi|logo|poster|desain|kartun|stiker|website|web|situs|aplikasi|app|game|kalkulator|dashboard|html|jurnal|paper|riset|research|referensi|sumber|data\s+terbaru|berita|harga\s+terbaru|ajari|ajarin|belajar|pdf|word|excel|ppt|slide|presentasi|file|dokumen|kode|program|coding|script)(?![\p{L}])';

    private const ROUTER_PROMPT = "Tentukan fitur Normi Chat yang paling tepat untuk pesan anggota grup ini. Balas HANYA JSON {\"feature\": \"...\"}.\n"
        . "- image: minta dibuatkan gambar/ilustrasi/logo/poster, atau mengedit foto (ganti warna, latar, hapus objek, ubah gaya).\n"
        . "- canvas: minta dibuatkan halaman web, aplikasi kecil, game, kalkulator, dashboard, prototipe UI, atau dokumen panjang yang bisa dipratinjau.\n"
        . "- deep_research: minta dicarikan/diriset informasi dari internet: jurnal, referensi, data/statistik/berita/harga terbaru, perbandingan dengan sumber.\n"
        . "- guided_learning: minta diajari suatu topik secara bertahap seperti tutor.\n"
        . "- file: minta dibuatkan file untuk diunduh (PDF, Word, Excel, PowerPoint, CSV, kode).\n"
        . "- chat: selain itu, termasuk pertanyaan tentang CARA membuat sesuatu, minta penjelasan, opini, atau obrolan biasa.\n"
        . "Pilih fitur hanya bila anggota memang meminta hasil itu dikerjakan sekarang.";

    public function __construct(private readonly MaintenanceAi $ai)
    {
    }

    /**
     * Fitur untuk pesan @normi biasa, atau null (jawab dengan chat biasa).
     *
     * @return array{mode: AiMode, file?: FileRequest, image_source?: Message}|null
     */
    public function route(Group $group, Message $message): ?array
    {
        $text = self::requestText($message);
        if ($text === '') {
            return null;
        }

        $file = FileRequest::detect($text);
        if ($file !== null) {
            return ['mode' => AiMode::File, 'file' => $file];
        }

        $photo = self::photoFor($message);
        if ($photo !== null && self::wantsPhotoEdit($text)) {
            return ['mode' => AiMode::Image, 'image_source' => $photo];
        }
        if (ImageResponder::wantsImage($text)) {
            return ['mode' => AiMode::Image];
        }

        $isQuestion = self::matches(self::QUESTION, $text);
        if (! $isQuestion) {
            if (self::matches(self::MAKE . '\s+(?:\S+\s+){0,4}?' . self::CANVAS_THING, $text)) {
                return ['mode' => AiMode::Canvas];
            }
            if (self::matches(self::RESEARCH, $text) || self::matches(self::SEARCH_VERB . '\s+(?:\S+\s+){0,3}?' . self::SOURCE_THING, $text)) {
                return ['mode' => AiMode::DeepResearch];
            }
            if (self::matches(self::LEARN, $text)) {
                return ['mode' => AiMode::GuidedLearning];
            }
        }

        // Ragu: ada kata fitur, atau foto yang dilampirkan/dibalas langsung, tapi aturan tidak yakin.
        $directPhoto = $photo !== null && ((int) $photo->id === (int) $message->id || (int) $photo->id === (int) $message->reply_to_message_id);
        if (! self::matches(self::FEATURE_WORDS, $text) && ! $directPhoto) {
            return null;
        }
        $routed = $this->askRouter($group, $text, $directPhoto);
        if ($routed !== null && $routed['mode'] === AiMode::Image && $directPhoto) {
            $routed['image_source'] = $photo;
        }
        if ($routed !== null && $routed['mode'] === AiMode::File) {
            $routed['file'] = FileRequest::forFileMode($text);
        }

        return $routed;
    }

    /**
     * Teks permintaan tanpa tag. Pesan pendek yang menunjuk pesan lain ("jawabkan dulu ini @normi", "kerjain ya")
     * dibaca bersama pesan anggota yang dibalasnya (kasus Gugur Ganjal 11 Nov 2026).
     */
    public static function requestText(Message $message): string
    {
        $text = self::clean((string) $message->content);
        $target = $message->replyToMessage;
        if ($target && $target->sender_type === 'user' && ! $target->trashed()) {
            $pointer = preg_match('/^(?:(?:tolong|coba|dong|ya|yah|dulu|dl|ini|itu|nih|tuh|kak|bang|bro|sis)\s*)*(?:jawab(?:kan|in|kn)?|kerja(?:kan|in)|bantu(?:in)?|buat(?:kan|in)?|bikin(?:in)?|lanjut(?:kan|in)?|proses|eksekusi|gas|bisa|ini|itu)\b/iu', $text) === 1;
            if ($text === '' || ($pointer && str_word_count($text) <= 6)) {
                $text = trim($text . ' ' . self::clean((string) $target->content));
            }
        }

        return $text;
    }

    /** Foto yang dimaksud: di pesan ini, di pesan yang dibalas, atau foto terakhir penanya dalam 30 menit. */
    public static function photoFor(Message $message): ?Message
    {
        if ($message->hasImageAttachment()) {
            return $message;
        }
        if ($message->replyToMessage?->hasImageAttachment()) {
            return $message->replyToMessage;
        }
        if (! $message->sender_id) {
            return null;
        }

        return Message::query()
            ->where('group_id', $message->group_id)
            ->where('sender_id', $message->sender_id)
            ->where('id', '<', $message->id)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->where('message_type', 'image')
            ->whereNull('visible_to_user_id')
            ->latest('id')
            ->first();
    }

    private const EDIT_VERB = '/\b(edit(?:kan|in)?|retouch|crop|potong|blur|buram(?:kan)?|upscale|hd-?kan|perjelas|pertajam|cerahkan|terangkan|gelapkan|warnai|hilangkan|hilangin|hapus(?:kan|in)?|buang|ganti(?:kan|in)?|ubah(?:kan)?|jadikan|jadiin|tambah(?:kan|in)?|pasang(?:kan)?|remove|change|replace|recolor|restore|restorasi|perbaiki)\b/u';

    private const VISUAL = '/\b(warna|merah|biru|hijau|kuning|putih|hitam|ungu|pink|merah muda|oranye|jingga|coklat|cokelat|abu|emas|perak|latar|background|bg|langit|baju|kemeja|jas|dasi|celana|rambut|wajah|muka|mata|kulit|senyum|kacamata|topi|objek|orang|benda|tulisan|teks|logo|watermark|kartun|anime|ghibli|lukisan|sketsa|ilustrasi|hitam putih|grayscale|vintage|foto|gambar|image|photo|picture)\b/u';

    private const PHOTO_QUESTION = '/\b(apa|apakah|jelaskan|jelasin|bagaimana|gimana|kenapa|mengapa|berapa|siapa|dimana|di mana|kapan|baca(?:kan)?|terjemah(?:kan)?|translate|ringkas(?:kan)?|analisis|analisa|cek|periksa|identifikasi|deskripsikan)\b/u';

    /** Teks meminta foto diubah ("ganti latarnya jadi putih", "bikin dasinya merah"), bukan bertanya tentang isinya. */
    public static function wantsPhotoEdit(string $text): bool
    {
        $text = mb_strtolower($text);
        if (preg_match(self::PHOTO_QUESTION, $text) === 1) {
            return false;
        }
        $isEdit = preg_match(self::EDIT_VERB, $text) === 1 || preg_match('/\b(bikin|buat)\w*\b.{0,60}\b(jadi|menjadi)\b/u', $text) === 1;

        return $isEdit && preg_match(self::VISUAL, $text) === 1;
    }

    /** @return array{mode: AiMode}|null */
    private function askRouter(Group $group, string $text, bool $hasPhoto): ?array
    {
        $result = $this->ai->json(self::KIND, $group, [
            ['role' => 'system', 'content' => self::ROUTER_PROMPT],
            ['role' => 'user', 'content' => ($hasPhoto ? "(Pesan ini menyertakan/membalas foto.)\n" : '') . mb_substr($text, 0, 1200)],
        ], 400, 8);
        $feature = (string) ($result['data']['feature'] ?? 'chat');
        $mode = AiMode::tryFrom($feature);

        return $mode !== null ? ['mode' => $mode] : null;
    }

    private static function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace(AiMode::anyTagPattern(), '', $text)));
    }

    private static function matches(string $pattern, string $text): bool
    {
        return preg_match('/' . $pattern . '/iu', $text) === 1;
    }
}
