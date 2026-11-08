<?php

namespace App\Enums;

/**
 * Mode jawaban yang dipilih anggota dari menu plus composer, atau lewat tag fiturnya
 * (@normi-image, @normi-canvas, @normi-research, @normi-learn). Chat biasa tetap @normi.
 * Memilih mode dari menu menyisipkan tag yang sesuai ke pesan, jadi yang tampil di chat
 * selalu tag fiturnya.
 */
enum AiMode: string
{
    case GuidedLearning = 'guided_learning';
    case Image = 'image';
    case Canvas = 'canvas';
    case DeepResearch = 'deep_research';

    /** Tag chat biasa (tanpa fitur). */
    public const CHAT_TAG = '@normi';

    public function label(): string
    {
        return match ($this) {
            self::GuidedLearning => 'Guided Learning',
            self::Image => 'Buat gambar',
            self::Canvas => 'Canvas',
            self::DeepResearch => 'Deep Research',
        };
    }

    /** Tag mention untuk mode ini, mis. "@normi-image". */
    public function tag(): string
    {
        return self::CHAT_TAG . '-' . $this->tagSuffix();
    }

    private function tagSuffix(): string
    {
        return match ($this) {
            self::GuidedLearning => 'learn',
            self::Image => 'image',
            self::Canvas => 'canvas',
            self::DeepResearch => 'research',
        };
    }

    /** Mode dari tag fitur pertama di pesan ("@normi-image buat kucing" → Image); null bila tidak ada. */
    public static function fromContent(?string $content): ?self
    {
        if ($content === null || $content === '') {
            return null;
        }

        $suffixes = array_map(fn (self $mode) => $mode->tagSuffix(), self::cases());
        $pattern = '/(?<![\w@])' . preg_quote(self::CHAT_TAG, '/') . '-(' . implode('|', $suffixes) . ')(?![\w-])/iu';
        if (preg_match($pattern, $content, $match) !== 1) {
            return null;
        }

        $suffix = strtolower($match[1]);
        foreach (self::cases() as $mode) {
            if ($mode->tagSuffix() === $suffix) {
                return $mode;
            }
        }

        return null;
    }

    /** Regex semua tag Normi (chat dan fitur), untuk membuang tag dari pertanyaan. */
    public static function anyTagPattern(): string
    {
        return '/(?<![\w@])' . preg_quote(self::CHAT_TAG, '/') . '(?:-[a-z]+)?(?![\w-])/iu';
    }

    /**
     * Mode yang menghasilkan jawaban AI berjenis $kind (lihat AiReply::$kind). Dipakai untuk menandai
     * jawaban fitur, supaya balasan ke jawaban itu bisa menawarkan fitur yang sama lagi.
     */
    public static function fromReplyKind(string $kind): ?self
    {
        return match ($kind) {
            'image' => self::Image,
            'canvas' => self::Canvas,
            'research' => self::DeepResearch,
            'guided_learning' => self::GuidedLearning,
            default => null,
        };
    }
}
