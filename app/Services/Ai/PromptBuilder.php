<?php

namespace App\Services\Ai;

use App\Enums\AiMode;
use App\Models\Group;
use App\Models\GroupContextTopic;
use App\Models\Message;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\Responders\CanvasResponder;
use App\Services\AttachmentTextExtractor;
use App\Services\Chat\PollService;
use App\Services\Files\StoredFiles;
use App\Services\NormkreditPricing;
use App\Support\UserTime;
use Illuminate\Support\Facades\Cache;

/**
 * Menyusun prompt untuk model AI dari konteks ContextManager: riwayat pesan, isi lampiran
 * dokumen, gambar (vision), dan transkrip voice note. Transkripsi yang benar-benar
 * memanggil API dicatat ke UsageMeter.
 *
 * Urutan pesan tetap demi prompt cache OpenAI — bagian yang jarang berubah di depan:
 * instruksi Normi → persona grup → ringkasan grup → jendela riwayat (dari jangkar tetap) →
 * konteks tambahan untuk pertanyaan ini (topik, pesan lama, maksud) → pertanyaan.
 */
class PromptBuilder
{
    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024;

    /** Voice note riwayat yang boleh ditranskripsi per permintaan; sisanya hanya bila transkripnya sudah ada. */
    private const MAX_HISTORY_TRANSCRIPTIONS = 5;

    /** @var array<int, true>|null id voice note riwayat yang boleh ditranskripsi; ditetapkan sekali per permintaan */
    private ?array $transcribable = null;

    public function __construct(
        private readonly OpenAiClient $client,
        private readonly UsageMeter $meter,
        private readonly NormkreditPricing $pricing,
    ) {
    }

    public static function chatSystemPrompt(Group $group): string
    {
        $prompt = 'Kamu adalah Normi AI, AI participant di grup chat Normi Chat (dipanggil dengan @normi). Jawab singkat, jelas, relevan konteks grup, dan gunakan Bahasa Indonesia.'
            . "\n\nKamu bisa menggunakan format berikut dalam jawaban:"
            . "\n- **bold** dan *italic* untuk penekanan"
            . "\n- Markdown table untuk data tabular (gunakan | header | format |)"
            . "\n- Mermaid diagram dengan syntax ```mermaid untuk flowchart, sequence diagram, pie chart, dll"
            . "\n- Numbered list dan bullet list"
            . "\nGunakan format yang paling sesuai untuk menjawab pertanyaan user."
            . "\nJika user membalas pesan (reply), prioritaskan konteks pada bagian kutipan reply bila tersedia."
            . "\n\nKamu membaca percakapan grup: tiap pesan anggota diawali waktu dan nama pengirimnya. Anggota sering mengobrol"
            . ' tanpa memanggilmu; saat dipanggil, pahami pembicaraan itu dari ringkasan, riwayat, dan konteks tambahan,'
            . " termasuk rujukan seperti \"itu\", \"yang tadi\", atau \"yang kedua\"."
            . "\nBedakan yang dinyatakan anggota dari dugaanmu. Bila konteks tidak cukup, katakan terus terang atau minta"
            . ' klarifikasi singkat; jangan mengarang isi pembicaraan.'
            . "\nJangan awali jawabanmu dengan nama atau waktu.";

        if ($group->ai_persona_style) {
            $prompt .= "\n\nPersona style: " . $group->ai_persona_style;
        }
        if ($group->ai_persona_guardrails) {
            $prompt .= "\n\nGuardrails: " . $group->ai_persona_guardrails;
        }

        return $prompt;
    }

    /**
     * Pesan chat lengkap: system prompt, ringkasan grup, jendela riwayat, konteks tambahan, lalu
     * permintaan terbaru beserta gambar pada pesan itu / pesan yang dibalasnya.
     */
    public function conversation(string $systemPrompt, ContextBundle $context, Message $request): array
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
            ...$this->memory($context),
            ...$this->history($context->window, $request),
            ...$this->extraContext($context),
            ['role' => 'user', 'content' => $latestParts],
        ];
    }

    /** Ringkasan grup (lapis pertama ingatan), sesudah instruksi dan sebelum riwayat. */
    public function memory(ContextBundle $context): array
    {
        if ($context->summary === null) {
            return [];
        }

        return [['role' => 'system', 'content' => "Ringkasan percakapan grup sebelum riwayat di bawah (dibuat otomatis dan bisa kurang lengkap;"
            . " bila bertentangan, pesan asli yang benar):\n" . $context->summary]];
    }

    /**
     * Konteks khusus pertanyaan ini: ringkasan topik, potongan pesan lama, dan maksud pertanyaan.
     * Diletakkan setelah riwayat karena isinya berbeda tiap pertanyaan.
     */
    public function extraContext(ContextBundle $context): array
    {
        if (! $context->hasExtra()) {
            return [];
        }

        $parts = [];
        if ($context->topics !== []) {
            $parts[] = "Ringkasan topik yang berkaitan:\n" . implode("\n", array_map(
                fn (GroupContextTopic $topic) => '- ' . $topic->title . ': ' . $topic->summary,
                $context->topics
            ));
        }
        if ($context->retrieved !== []) {
            $parts[] = "Potongan riwayat lama yang mungkin relevan (urut waktu):\n" . implode("\n", array_map(
                fn (Message $message) => $this->contextLine($message, withSender: true),
                $context->retrieved
            ));
        }
        if ($context->intent !== null) {
            $parts[] = 'Maksud pertanyaan menurut konteks: ' . $context->intent;
        }
        if ($context->ambiguous) {
            $parts[] = 'Rujukan pertanyaan ini belum jelas dan tidak ditemukan di riwayat. Minta klarifikasi singkat; jangan menebak.';
        }

        return [['role' => 'system', 'content' => "Konteks tambahan untuk pertanyaan berikut:\n\n" . implode("\n\n", $parts)]];
    }

    /**
     * Riwayat grup sebagai pesan chat (tanpa pesan permintaan itu sendiri). Pesan anggota diawali
     * waktu dan nama pengirim; jawaban Normi tanpa awalan.
     *
     * @param  iterable<Message>  $messages
     */
    public function history(iterable $messages, Message $request, bool $skipEmpty = false): array
    {
        // Dipilih sekali: compose() bisa menyusun ulang riwayat, tapi tidak boleh menambah transkripsi.
        $this->transcribable ??= collect($messages)
            ->filter(fn (Message $row) => $row->message_type === 'voice' && $row->attachment_path
                && ! Cache::has('msg-audio-transcript:' . $row->id))
            ->sortByDesc('id')
            ->take(self::MAX_HISTORY_TRANSCRIPTIONS)
            ->mapWithKeys(fn (Message $row) => [(int) $row->id => true])
            ->all();

        $history = [];
        foreach ($messages as $row) {
            if ((int) $row->id === (int) $request->id) {
                continue;
            }

            $line = $this->contextLine($row, withSender: $row->sender_type !== 'ai');
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
        $asker = $request->sender?->name ?? 'Anggota';
        $parts = ['Sekarang ' . UserTime::format(now(), 'l, j F Y H.i') . ". {$asker} bertanya ke Normi:"];

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

        return count($parts) === 1 ? $parts[0] . ' (tanpa teks) Bantu berdasarkan konteks percakapan terbaru.' : implode("\n\n", $parts);
    }

    private function replyContext(Message $request, Message $replyTarget): array
    {
        $parts = [];
        $replySender = $replyTarget->sender_type === 'ai' ? 'Normi AI' : ($replyTarget->sender?->name ?? 'User');

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
        } elseif (! ($request->ai_mode === AiMode::Canvas && $replyTarget->message_type === CanvasResponder::MESSAGE_TYPE)) {
            // Revisi Canvas: isi Canvas lama sudah dikirim utuh oleh CanvasResponder, jangan dua kali.
            $documentText = $this->documentText($replyTarget);
            if ($documentText !== null) {
                $parts[] = 'Isi dokumen yang direply ("' . ($replyTarget->attachment_original_name ?: 'lampiran') . "\"):\n\n" . $documentText;
            }
        }

        return $parts;
    }

    private function contextLine(Message $row, bool $withSender = false): string
    {
        // Jawaban Normi AI selalu tercatat sebagai balasan; penanda di riwayat asisten ditiru model
        // ke jawabannya ("... [reply]"), jadi penanda hanya untuk pesan user, di depan.
        $replyQuoteText = trim((string) ($row->reply_quote_text ?? ''));
        $replyTarget = (int) ($row->reply_to_message_id ?? 0) > 0 ? $row->replyToMessage : null;
        $replyName = $replyTarget ? ($replyTarget->sender_type === 'ai' ? 'Normi AI' : ($replyTarget->sender?->name ?? 'anggota')) : null;
        $replyPrefix = $row->sender_type !== 'ai' && (int) ($row->reply_to_message_id ?? 0) > 0
            ? ($replyQuoteText !== ''
                ? '(membalas ' . ($replyName ?? 'pesan sebelumnya') . ", mengutip \"{$replyQuoteText}\") "
                : '(membalas ' . ($replyName ?? 'pesan sebelumnya') . ') ')
            : '';
        if ($row->forwarded_from_name) {
            $replyPrefix .= '(diteruskan dari ' . $row->forwarded_from_name . ') ';
        }
        if ($withSender) {
            $sender = $row->sender_type === 'ai' ? 'Normi AI' : ($row->sender?->name ?? 'Anggota');
            $replyPrefix = '[' . UserTime::format($row->created_at, 'D j/n H.i') . '] ' . $sender . ': ' . $replyPrefix;
        }

        $content = trim((string) $row->content);
        $poll = $content !== '' ? app(PollService::class)->parse($content) : null;
        if ($poll !== null) {
            return $replyPrefix . 'Polling: ' . $poll['question'] . ' — pilihan: '
                . implode(', ', array_map(fn (array $option) => $option['number'] . '. ' . $option['label'], $poll['options']));
        }
        $attachment = $this->attachmentLine($row);
        if ($attachment === null) {
            return $replyPrefix . ($content !== '' ? $content : '[Pesan sebelumnya berisi lampiran]');
        }

        // Keterangan tidak menggantikan lampirannya: isi dokumen/transkrip tetap terbaca Normi.
        return $replyPrefix . $attachment . ($content !== '' ? "\nKeterangan: " . $content : '');
    }

    /** Lampiran pesan riwayat sebagai teks (gambar, isi dokumen, transkrip voice note); null bila tanpa lampiran. */
    private function attachmentLine(Message $row): ?string
    {
        if (! $row->attachment_path) {
            return null;
        }

        $messageType = strtolower((string) ($row->message_type ?? 'text'));
        if ($messageType === 'image' || $row->hasImageAttachment()) {
            return '[Pesan sebelumnya berisi gambar]';
        }

        if ($messageType === 'voice' || $row->hasAudioAttachment()) {
            $transcript = isset($this->transcribable[(int) $row->id])
                ? $this->transcript($row)
                : $this->cachedTranscript($row);

            return $transcript !== null ? '[Transkrip audio] ' . $transcript : '[Pesan sebelumnya berisi audio]';
        }

        $label = $row->attachment_original_name ?? 'lampiran';
        $documentText = $this->documentText($row);

        return $documentText !== null
            ? "[Dokumen \"{$label}\"]\n" . mb_substr($documentText, 0, 1500)
            : "[Pesan sebelumnya berisi lampiran file: {$label}]";
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

    /** Transkrip yang sudah pernah dibuat, tanpa memanggil API. */
    private function cachedTranscript(Message $message): ?string
    {
        $text = Cache::get('msg-audio-transcript:' . $message->id);

        return is_string($text) && $text !== '' ? $text : null;
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

        $binary = app(StoredFiles::class)->get($message->attachment_disk, $message->attachment_path, $maxBytes);

        return $binary === '' ? null : $binary;
    }
}
