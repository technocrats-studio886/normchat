<?php

namespace App\Services\Ai\Responders;

use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\ModePrompts;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\Context\ContextUnderstanding;

/**
 * Deep Research: model mencari di web (Responses API, alat web_search) lalu menulis
 * laporan bersumber. Biaya = token model (termasuk isi hasil pencarian) + biaya per pencarian.
 */
final class ResearchResponder implements AiResponder
{
    private const MAX_OUTPUT_TOKENS = 8000;

    /** Perkiraan isi hasil pencarian yang masuk sebagai input, untuk menahan NK di muka. */
    private const ESTIMATED_SEARCH_INPUT_TOKENS = 20_000;

    /** Perkiraan jumlah panggilan web search, untuk menahan biaya OpenAI di muka. */
    private const ESTIMATED_SEARCH_CALLS = 5;

    public function activity(): string
    {
        return AiActivity::RESEARCH;
    }

    public function respond(AiTask $task): AiReply
    {
        $messages = $task->compose(fn (ContextBundle $context) => $task->prompts->conversation(
            PromptBuilder::chatSystemPrompt($task->group) . "\n\n" . ModePrompts::DEEP_RESEARCH,
            $context,
            $task->message
        ));

        $task->planChat(
            $messages,
            self::MAX_OUTPUT_TOKENS,
            self::ESTIMATED_SEARCH_INPUT_TOKENS,
            self::ESTIMATED_SEARCH_CALLS * (float) config('normchat.web_search.usd_per_call', 0.01),
        );
        $topic = AiActivity::topic(ContextUnderstanding::question($task->message));
        $task->progress(AiActivity::RESEARCH, $topic !== '' ? "sedang memulai riset tentang {$topic}" : 'sedang memulai riset');
        [$completion, $model] = $task->research($messages, onEvent: self::progressReporter($task));

        if (! $completion->hasText()) {
            return AiReply::failed('research');
        }

        $task->recordChat('research', $model, $completion);
        $task->recordWebSearch($completion->webSearchCalls);

        $reply = AiReply::text('research', self::withSources((string) $completion->text, $completion->citations), $model);

        return $completion->finishReason === 'length'
            ? $reply->withNote('Laporan terpotong. Minta Normi AI melanjutkan.')
            : $reply;
    }

    /**
     * Ubah event stream Responses API menjadi status yang dibaca anggota grup: kueri yang sedang dicari,
     * halaman yang sedang dibaca, lalu "sedang menyusun laporan" saat teks mulai ditulis.
     *
     * @return \Closure(string, array): void
     */
    public static function progressReporter(AiTask $task): \Closure
    {
        $searches = 0;
        $writing = false;

        return function (string $type, array $event) use ($task, &$searches, &$writing): void {
            $item = (array) ($event['item'] ?? []);
            if (in_array($type, ['response.output_item.added', 'response.output_item.done'], true) && ($item['type'] ?? '') === 'web_search_call') {
                $action = (array) ($item['action'] ?? []);
                $query = trim((string) ($action['query'] ?? ''));
                if (($action['type'] ?? 'search') === 'search' && $query !== '') {
                    if ($type === 'response.output_item.done') {
                        $searches++;
                    }
                    $task->progress(AiActivity::RESEARCH, 'sedang mencari "' . $query . '"');
                } elseif (($action['type'] ?? '') === 'open_page' && ($host = parse_url((string) ($action['url'] ?? ''), PHP_URL_HOST))) {
                    $task->progress(AiActivity::RESEARCH, 'sedang membaca ' . preg_replace('/^www\./', '', (string) $host));
                } elseif ($type === 'response.output_item.added') {
                    $task->progress(AiActivity::RESEARCH, 'sedang mencari sumber');
                }

                return;
            }

            if ($type === 'response.output_text.delta' && ! $writing) {
                $writing = true;
                $task->progress(AiActivity::RESEARCH, $searches > 0
                    ? "sedang menyusun laporan dari {$searches} pencarian"
                    : 'sedang menyusun laporan');
            }
        };
    }

    /**
     * Tambahkan daftar sumber bila model belum menautkan semuanya di dalam teks.
     *
     * @param  list<array{url: string, title: string}>  $citations
     */
    private static function withSources(string $text, array $citations): string
    {
        $missing = array_filter($citations, fn (array $source) => ! str_contains($text, $source['url']));
        if ($missing === []) {
            return $text;
        }

        $lines = array_map(
            fn (array $source) => '- [' . str_replace(['[', ']'], '', $source['title']) . '](' . $source['url'] . ')',
            array_values($missing)
        );

        return rtrim($text) . "\n\n## Sumber\n" . implode("\n", $lines);
    }
}
