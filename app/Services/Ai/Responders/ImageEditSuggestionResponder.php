<?php

namespace App\Services\Ai\Responders;

use App\Enums\AiMode;
use App\Models\Message;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;

/**
 * "@normi ubah dasinya jadi merah" pada foto (kasus nyata 10 Nov 2026, grup 20): chat biasa tidak bisa mengedit
 * gambar dan dulu malah menyarankan Canva. Sekarang Normi langsung mengarahkan ke fitur Buat gambar dengan
 * tombol satu ketuk (keputusan user 11 Nov 2026: arahkan + tombol, bukan otomatis). Tanpa panggilan model dan
 * tanpa Normkredit; NK gambar baru terpakai setelah tombol ditekan.
 */
final class ImageEditSuggestionResponder implements AiResponder
{
    /** Kata yang menandai perubahan visual pada foto. */
    private const EDIT_PATTERN = '/\b(edit(?:kan|in)?|retouch|crop|potong|blur|buram(?:kan)?|upscale|hd-?kan|perjelas|pertajam|cerahkan|terangkan|gelapkan|warnai|hilangkan|hilangin|hapus(?:kan|in)?|buang|ganti(?:kan|in)?|ubah(?:kan)?|jadikan|jadiin|tambah(?:kan|in)?|pasang(?:kan)?|remove|change|replace|recolor|restore|restorasi|perbaiki)\b/u';

    private const VISUAL_PATTERN = '/\b(warna|merah|biru|hijau|kuning|putih|hitam|ungu|pink|merah muda|oranye|jingga|coklat|cokelat|abu|emas|perak|latar|background|bg|langit|baju|kemeja|jas|dasi|celana|rambut|wajah|muka|mata|kulit|senyum|kacamata|topi|objek|orang|benda|tulisan|teks|logo|watermark|kartun|anime|ghibli|lukisan|sketsa|ilustrasi|hitam putih|grayscale|vintage|foto|gambar|image|photo|picture)\b/u';

    /** Pertanyaan tentang isi gambar tetap dijawab chat biasa (melihat gambar). */
    private const QUESTION_PATTERN = '/\b(apa|apakah|jelaskan|jelasin|bagaimana|gimana|kenapa|mengapa|berapa|siapa|dimana|di mana|kapan|baca(?:kan)?|terjemah(?:kan)?|translate|ringkas(?:kan)?|analisis|analisa|cek|periksa|identifikasi|deskripsikan)\b/u';

    public function __construct(private readonly Message $imageSource)
    {
    }

    /** Pesan (atau pesan yang dibalasnya) berisi foto, dan teksnya meminta foto itu diubah. */
    public static function wantsImageEdit(Message $message): ?Message
    {
        $source = $message->hasImageAttachment() ? $message : ($message->replyToMessage?->hasImageAttachment() ? $message->replyToMessage : null);
        if ($source === null) {
            return null;
        }

        $text = mb_strtolower(trim((string) preg_replace(AiMode::anyTagPattern(), '', (string) $message->content)));
        if ($text === '' || preg_match(self::QUESTION_PATTERN, $text) === 1) {
            return null;
        }

        // "bikin dasinya menjadi warna merah" juga permintaan edit.
        $isEdit = preg_match(self::EDIT_PATTERN, $text) === 1 || preg_match('/\b(bikin|buat)\w*\b.{0,60}\b(jadi|menjadi)\b/u', $text) === 1;

        return $isEdit && preg_match(self::VISUAL_PATTERN, $text) === 1 ? $source : null;
    }

    public function activity(): string
    {
        return AiActivity::TYPING;
    }

    public function respond(AiTask $task): AiReply
    {
        return AiReply::notice('chat', 'Foto ini bisa langsung kuedit lewat fitur Buat gambar. Ketuk tombol di bawah, permintaan dan fotonya sudah kusiapkan.')
            ->withSuggestion(AiMode::Image->value);
    }

    public function imageSource(): Message
    {
        return $this->imageSource;
    }
}
