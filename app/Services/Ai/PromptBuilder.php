<?php

namespace App\Services\Ai;

use App\Models\Group;
use App\Models\Message;
use App\Services\AttachmentTextExtractor;
use App\Services\NormkreditPricing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Menyusun konteks percakapan grup untuk model AI: riwayat pesan, isi lampiran
 * dokumen, gambar (vision), dan transkrip voice note. Transkripsi yang benar-benar
 * memanggil API dicatat ke UsageMeter.
 */
class PromptBuilder
{
    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    public function __construct(
        private readonly OpenAiClient $client,
        private readonly UsageMeter $meter,
        private readonly NormkreditPricing $pricing,
    ) {
    }

    public static function chatSystemPrompt(Group $group): string
    {
        $prompt = 'Kamu adalah AI participant untuk group chat Normchat. Jawab singkat, jelas, relevan konteks grup, dan gunakan Bahasa Indonesia.'
            . "\n\nKamu bisa menggunakan format berikut dalam jawaban:"
            . "\n- **bold** dan *italic* untuk penekanan"
            . "\n- Markdown table untuk data tabular (gunakan | header | format |)"
            . "\n- Mermaid diagram dengan syntax ```mermaid untuk flowchart, sequence diagram, pie chart, dll"
            . "\n- Numbered list dan bullet list"
            . "\nGunakan format yang paling sesuai untuk menjawab pertanyaan user."
            . "\nJika user membalas pesan (reply), prioritaskan konteks pada bagian kutipan reply bila tersedia.";

        if ($group->ai_persona_style) {
            $prompt .= "\n\nPersona style: " . $group->ai_persona_style;
        }
        if ($group->ai_persona_guardrails) {
            $prompt .= "\n\nGuardrails: " . $group->ai_persona_guardrails;
        }

        return $prompt;
    }

    /**
     * Pesan chat lengkap: system prompt, riwayat grup, lalu permintaan terbaru beserta
     * gambar pada pesan itu / pesan yang dibalasnya.
     *
     * @param  iterable<Message>  $recentMessages
     */
    public function conversation(string $systemPrompt, iterable $recentMessages, Message $request): array
    {
        $latestParts = [['type' => 'text', 'text' => $this->latestPromptText($request)]];
        foreach ([$request, $request->replyToMessage] as $imageSource) {
            $dataUri = $this->imageDataUri($imageSource);
            if ($dataUri !== null) {
                $latestParts[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUri]];
            }
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$this->history($recentMessages, $request),
            ['role' => 'user', 'content' => $latestParts],
        ];
    }

    /**
     * Riwayat grup sebagai pesan chat (tanpa pesan permintaan itu sendiri).
     *
     * @param  iterable<Message>  $recentMessages
     */
    public function history(iterable $recentMessages, Message $request, bool $skipEmpty = false): array
    {
        $history = [];
        foreach ($recentMessages as $row) {
            if ((int) $row->id === (int) $request->id) {
                continue;
            }

            $line = $this->contextLine($row);
            if ($skipEmpty && trim($line) === '') {
                continue;
            }

            $history[] = ['role' => $row->sender_type === 'ai' ? 'assistant' : 'user', 'content' => $line];
        }

        return $history;
    }

    /** Gambar referensi untuk diedit: lampiran pesan ini, atau gambar pada pesan yang dibalas. */
    public function referenceImage(Message $request): ?array
    {
        foreach ([$request, $request->replyToMessage] as $source) {
            $binary = $this->imageBinary($source);
            if ($binary !== null) {
                return [
                    'binary' => $binary,
                    'filename' => $source->attachment_original_name ?: basename((string) $source->attachment_path),
                ];
            }
        }

        return null;
    }

    private function latestPromptText(Message $request): string
    {
        $parts = [];

        $baseContent = trim((string) $request->content);
        if ($baseContent !== '') {
            $parts[] = $baseContent;
        }

        if ($request->hasAudioAttachment()) {
            $transcript = $this->transcript($request);
            $parts[] = $transcript !== null
                ? 'Transkrip audio dari pesan ini: ' . $transcript
                : 'Pesan ini memiliki lampiran audio namun transkrip tidak tersedia.';
        } elseif ($request->hasImageAttachment()) {
            $parts[] = 'Pesan ini menyertakan gambar. Gunakan gambar tersebut untuk menjawab.';
        } else {
            $documentText = $this->documentText($request);
            if ($documentText !== null) {
                $parts[] = 'Isi dokumen "' . ($request->attachment_original_name ?: 'lampiran') . "\":\n\n" . $documentText;
            } elseif ($request->attachment_path) {
                $parts[] = 'Pesan ini berisi lampiran namun isinya tidak bisa dibaca AI (format tidak didukung atau gagal parse).';
            }
        }

        $replyTarget = $request->replyToMessage;
        if ($replyTarget) {
            $parts = [...$parts, ...$this->replyContext($request, $replyTarget)];
        }

        return $parts === [] ? 'Bantu user berdasarkan konteks percakapan terbaru.' : implode("\n\n", $parts);
    }

    private function replyContext(Message $request, Message $replyTarget): array
    {
        $parts = [];
        $replySender = $replyTarget->sender_type === 'ai' ? 'NormAI' : ($replyTarget->sender?->name ?? 'User');

        $replyQuoteText = trim((string) ($request->reply_quote_text ?? ''));
        if ($replyQuoteText !== '') {
            $parts[] = "Bagian spesifik yang dibalas user dari pesan {$replySender}: \"{$replyQuoteText}\". Fokus utama jawabanmu pada bagian ini.";
        }

        $replySummary = trim((string) $replyTarget->content);
        if ($replySummary === '') {
            $replySummary = $replyTarget->hasImageAttachment()
                ? '[lampiran gambar]'
                : ($replyTarget->hasAudioAttachment() ? '[lampiran audio]' : '[lampiran file]');
        }
        $parts[] = "Kamu sedang membalas (reply) pesan dari {$replySender}: {$replySummary}";

        if ($replyTarget->hasAudioAttachment()) {
            $transcript = $this->transcript($replyTarget);
            if ($transcript !== null) {
                $parts[] = 'Transkrip audio dari pesan yang direply: ' . $transcript;
            }
        } elseif ($replyTarget->hasImageAttachment()) {
            $parts[] = 'Ada gambar pada pesan yang direply. Perhatikan gambar tersebut dalam jawabanmu.';
        } else {
            $documentText = $this->documentText($replyTarget);
            if ($documentText !== null) {
                $parts[] = 'Isi dokumen yang direply ("' . ($replyTarget->attachment_original_name ?: 'lampiran') . "\"):\n\n" . $documentText;
            }
        }

        return $parts;
    }

    private function contextLine(Message $row): string
    {
        $replyQuoteText = trim((string) ($row->reply_quote_text ?? ''));
        $replyMeta = (int) ($row->reply_to_message_id ?? 0) > 0
            ? ($replyQuoteText !== '' ? " [reply mengutip: \"{$replyQuoteText}\"]" : ' [reply]')
            : '';

        $content = trim((string) $row->content);
        if ($content !== '') {
            return $content . $replyMeta;
        }

        $messageType = strtolower((string) ($row->message_type ?? 'text'));
        if ($messageType === 'image') {
            return '[Pesan sebelumnya berisi gambar]' . $replyMeta;
        }

        if ($messageType === 'file' && $row->attachment_path) {
            $label = $row->attachment_original_name ?? 'lampiran';
            $documentText = $this->documentText($row);

            return $documentText !== null
                ? "[Dokumen \"{$label}\"]\n" . mb_substr($documentText, 0, 1500) . $replyMeta
                : "[Pesan sebelumnya berisi lampiran file: {$label}]" . $replyMeta;
        }

        if ($messageType === 'voice') {
            $transcript = $this->transcript($row);

            return $transcript !== null
                ? '[Transkrip audio] ' . $transcript . $replyMeta
                : '[Pesan sebelumnya berisi audio]' . $replyMeta;
        }

        return '[Pesan sebelumnya berisi lampiran]' . $replyMeta;
    }

    private function documentText(Message $message): ?string
    {
        if (! $message->attachment_path || ! $message->attachment_disk
            || $message->hasImageAttachment() || $message->hasAudioAttachment()) {
            return null;
        }

        return Cache::remember('msg-doc-text:' . $message->id, now()->addHours(12),
            fn () => app(AttachmentTextExtractor::class)->extract($message));
    }

    /**
     * Transkrip di-cache per pesan, jadi biaya hanya tercatat saat benar-benar memanggil API.
     */
    private function transcript(Message $message): ?string
    {
        if (! $message->attachment_disk || ! $message->attachment_path) {
            return null;
        }

        $text = Cache::remember('msg-audio-transcript:' . $message->id, now()->addHours(12), function () use ($message) {
            $binary = $this->attachmentBinary($message);
            if ($binary === null) {
                return null;
            }

            $model = (string) config('normchat.transcription_model.model', 'gpt-4o-mini-transcribe');
            $result = $this->client->transcribe($binary, $message->attachment_original_name ?: basename($message->attachment_path), $model);
            if ($result === null) {
                return null;
            }

            $inputTokens = (int) ($result['usage']['input_tokens'] ?? 0);
            $outputTokens = (int) ($result['usage']['output_tokens'] ?? 0);
            $this->meter->add(
                'transcription',
                $model,
                ($inputTokens * (float) config('normchat.transcription_model.input_usd_per_mtok', 0)
                    + $outputTokens * (float) config('normchat.transcription_model.output_usd_per_mtok', 0)) / 1_000_000,
                $this->pricing->mediaMultiplier(),
                ['input_tokens' => $inputTokens, 'output_tokens' => $outputTokens],
            );

            return $result['text'] !== '' ? $result['text'] : null;
        });

        return $text !== '' ? $text : null;
    }

    private function imageDataUri(?Message $message): ?string
    {
        $binary = $this->imageBinary($message);

        return $binary === null ? null : 'data:' . ($message->attachment_mime ?: 'image/jpeg') . ';base64,' . base64_encode($binary);
    }

    private function imageBinary(?Message $message): ?string
    {
        if (! $message?->hasImageAttachment()) {
            return null;
        }

        return $this->attachmentBinary($message, self::MAX_IMAGE_BYTES);
    }

    private function attachmentBinary(Message $message, ?int $maxBytes = null): ?string
    {
        if (! $message->attachment_disk || ! $message->attachment_path) {
            return null;
        }

        try {
            $disk = Storage::disk($message->attachment_disk);
            if (! $disk->exists($message->attachment_path)) {
                return null;
            }

            if ($maxBytes !== null) {
                $size = (int) $disk->size($message->attachment_path);
                if ($size <= 0 || $size > $maxBytes) {
                    return null;
                }
            }

            $binary = $disk->get($message->attachment_path);

            return $binary === '' || $binary === null ? null : $binary;
        } catch (Throwable $e) {
            Log::warning('Failed to load attachment for AI context.', ['message_id' => $message->id, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
