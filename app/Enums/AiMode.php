<?php

namespace App\Enums;

/**
 * Mode jawaban yang dipilih anggota dari menu plus composer. Mode hanya mengubah cara
 * Normi AI menjawab; pesannya tetap harus menyebut @normi agar diproses.
 */
enum AiMode: string
{
    case GuidedLearning = 'guided_learning';
    case Image = 'image';
    case Canvas = 'canvas';
    case DeepResearch = 'deep_research';

    public function label(): string
    {
        return match ($this) {
            self::GuidedLearning => 'Guided Learning',
            self::Image => 'Buat gambar',
            self::Canvas => 'Canvas',
            self::DeepResearch => 'Deep Research',
        };
    }
}
