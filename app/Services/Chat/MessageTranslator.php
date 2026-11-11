<?php

namespace App\Services\Chat;

use App\Models\Group;
use App\Models\Message;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Tokens;
use Illuminate\Support\Facades\Cache;

/**
 * Terjemahkan pesan sekali ketuk (docs/rancangan-fitur-produktivitas-nov.md bagian 6). Gratis (keputusan user
 * 11 Nov 2026): model termurah lewat MaintenanceAi (tetap dijaga SpendGuard, tidak memotong NK), di-cache per isi.
 */
class MessageTranslator
{
    public const KIND = 'translation';

    public const MAX_CHARS = 4000;

    private const PROMPT = "Kamu penerjemah pesan grup chat. Bila teks BUKAN Bahasa Indonesia, terjemahkan ke Bahasa Indonesia."
        . " Bila teks sudah Bahasa Indonesia (termasuk bahasa gaul/daerah campur Indonesia), terjemahkan ke Bahasa Inggris."
        . " Pertahankan format Markdown, emoji, @mention, tautan, angka, dan blok kode apa adanya; nada santai tetap santai."
        . " Balas HANYA JSON: {\"source\": \"nama bahasa asal dalam Bahasa Indonesia, mis. Inggris\", \"target\": \"id\" atau \"en\", \"text\": \"hasil terjemahan\"}";

    public function __construct(private readonly MaintenanceAi $ai)
    {
    }

    /** @return array{source: string, target: string, text: string}|null null bila gagal (mis. SpendGuard menolak) */
    public function translate(Group $group, Message $message): ?array
    {
        $content = trim((string) $message->content);
        if ($content === '') {
            return null;
        }
        $content = mb_substr($content, 0, self::MAX_CHARS);
        $key = 'msg-translation:' . $message->id . ':' . hash('xxh3', $content);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->ai->json(self::KIND, $group, [
            ['role' => 'system', 'content' => self::PROMPT],
            ['role' => 'user', 'content' => $content],
        ], min(4000, Tokens::text($content) * 2 + 200), 45);
        $data = $result['data'] ?? null;
        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            return null;
        }

        $translation = [
            'source' => mb_substr(trim((string) ($data['source'] ?? '')), 0, 40) ?: 'bahasa lain',
            'target' => ($data['target'] ?? '') === 'en' ? 'en' : 'id',
            'text' => $text,
        ];
        Cache::put($key, $translation, now()->addDays(30));

        return $translation;
    }
}
