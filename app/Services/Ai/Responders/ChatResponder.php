<?php

namespace App\Services\Ai\Responders;

use App\Enums\AiMode;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\PromptBuilder;

/** Jawaban teks biasa; dengan $modeInstructions untuk mode seperti Guided Learning. */
final class ChatResponder implements AiResponder
{
    /**
     * Penanda saran fitur di akhir jawaban chat biasa. Server membuangnya dan menampilkan tombol fitur
     * di bawah jawaban (GroupAiAssistant::replyContent, resources/js/chat/messages.js).
     */
    public const SUGGESTION_PROMPT = "Bila jawabanmu mengarahkan anggota ke salah satu fitur Normi Chat di atas, tulis satu penanda di baris terakhir:"
        . " [[fitur:image]], [[fitur:deep_research]], [[fitur:canvas]], [[fitur:guided_learning]], atau [[fitur:file]]."
        . " Penanda itu tampil sebagai tombol untuk langsung memakai fiturnya; jangan menyebut penanda itu dalam kalimat.";

    public function __construct(
        private readonly ?string $modeInstructions = null,
        private readonly string $kind = 'chat',
    ) {
    }

    /** Jawaban teks: "sedang mengetik"; Guided Learning: "sedang menyiapkan materi belajar". */
    public function activity(): string
    {
        return $this->kind === 'guided_learning' ? AiActivity::LEARNING : AiActivity::TYPING;
    }

    public function respond(AiTask $task): AiReply
    {
        $systemPrompt = PromptBuilder::chatSystemPrompt($task->group);
        $systemPrompt .= "\n\n" . ($this->modeInstructions ?? self::SUGGESTION_PROMPT);
        $this->reportReading($task);

        $messages = $task->compose(fn (ContextBundle $context) => $task->prompts->conversation($systemPrompt, $context, $task->message));

        $task->planChat($messages);
        [$completion, $model] = $task->complete($messages);

        if (! $completion->hasText()) {
            return AiReply::failed($this->kind);
        }

        $task->recordChat($this->kind, $model, $completion);
        [$text, $suggestion] = self::extractSuggestion((string) $completion->text);
        $text = $text !== '' ? $text : 'Ketuk tombol di bawah untuk langsung memakai fiturnya.';
        $reply = AiReply::text($this->kind, $text, $model)->withSuggestion($this->modeInstructions === null ? $suggestion : null);

        return $task->isOutputCapped() && $completion->finishReason === 'length'
            ? $reply->withNote('Jawaban dipersingkat karena Normkredit tidak cukup.')
            : $reply;
    }

    /**
     * Buang penanda [[fitur:…]] dari jawaban.
     *
     * @return array{0: string, 1: ?string} [teks tanpa penanda, mode yang disarankan]
     */
    public static function extractSuggestion(string $text): array
    {
        $suggestion = null;
        $clean = (string) preg_replace_callback('/\s*\[\[\s*fitur\s*:\s*([a-z_]+)\s*\]\]/iu', function (array $match) use (&$suggestion) {
            $suggestion ??= AiMode::tryFrom(strtolower($match[1]))?->value;

            return '';
        }, $text);

        return [trim($clean), $suggestion];
    }

    /** Sebelum menjawab: status sesuai lampiran yang sedang dibaca (dokumen, gambar, pesan suara). */
    private function reportReading(AiTask $task): void
    {
        $message = $task->message;
        foreach ([$message, $message->replyToMessage] as $source) {
            if (! $source?->attachment_path) {
                continue;
            }
            $detail = match (true) {
                $source->hasAudioAttachment() => 'sedang mendengarkan pesan suara',
                $source->hasImageAttachment() => 'sedang melihat gambar',
                default => 'sedang membaca ' . ($source->attachment_original_name ?: 'dokumen'),
            };
            $task->progress(AiActivity::READING, $detail);

            return;
        }
    }
}
