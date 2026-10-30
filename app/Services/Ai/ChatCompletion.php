<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;

/**
 * Hasil satu panggilan model (chat completions, atau Responses API untuk web search),
 * dengan usage yang sudah dinormalisasi.
 */
final class ChatCompletion
{
    /** @param array{input_tokens: int, cached_input_tokens: int, output_tokens: int} $usage */
    private function __construct(
        public readonly bool $successful,
        public readonly ?string $text,
        public readonly ?string $finishReason,
        public readonly array $usage,
        public readonly int $status,
        public readonly string $errorBody,
        /** @var list<array{url: string, title: string}> sumber dari web search */
        public readonly array $citations = [],
        public readonly int $webSearchCalls = 0,
        /** Id respons OpenAI (chatcmpl-… / resp_…), disimpan di log untuk audit. */
        public readonly ?string $requestId = null,
    ) {
    }

    public static function fromResponse(Response $response): self
    {
        $usage = (array) $response->json('usage', []);

        return new self(
            $response->successful(),
            $response->json('choices.0.message.content'),
            $response->json('choices.0.finish_reason'),
            [
                'input_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'cached_input_tokens' => (int) data_get($usage, 'prompt_tokens_details.cached_tokens', 0),
                'output_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            ],
            $response->status(),
            $response->successful() ? '' : (string) $response->body(),
            requestId: $response->json('id'),
        );
    }

    /** Responses API: teks dari item `message`, sumber dari anotasi url_citation. */
    public static function fromResponsesApi(Response $response): self
    {
        $text = '';
        $citations = [];
        $searchCalls = 0;
        foreach ((array) $response->json('output', []) as $item) {
            if (($item['type'] ?? '') === 'web_search_call') {
                $searchCalls++;
                continue;
            }
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ((array) ($item['content'] ?? []) as $part) {
                if (($part['type'] ?? '') !== 'output_text') {
                    continue;
                }
                $text .= (string) ($part['text'] ?? '');
                foreach ((array) ($part['annotations'] ?? []) as $annotation) {
                    $url = (string) ($annotation['url'] ?? '');
                    if (($annotation['type'] ?? '') === 'url_citation' && $url !== '') {
                        $citations[$url] = ['url' => $url, 'title' => (string) ($annotation['title'] ?? $url)];
                    }
                }
            }
        }

        $usage = (array) $response->json('usage', []);
        $incomplete = $response->json('status') === 'incomplete';

        return new self(
            $response->successful(),
            $text !== '' ? $text : null,
            $incomplete ? 'length' : ($response->successful() ? 'stop' : null),
            [
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'cached_input_tokens' => (int) data_get($usage, 'input_tokens_details.cached_tokens', 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            ],
            $response->status(),
            $response->successful() ? '' : (string) $response->body(),
            array_values($citations),
            $searchCalls,
            $response->json('id'),
        );
    }

    public static function failed(string $error): self
    {
        return new self(false, null, null, ['input_tokens' => 0, 'cached_input_tokens' => 0, 'output_tokens' => 0], 0, $error);
    }

    public function hasText(): bool
    {
        return $this->successful && trim((string) $this->text) !== '';
    }

    /** Kegagalan yang kemungkinan karena model tidak tersedia, layak dicoba dengan model lain. */
    public function looksLikeModelIssue(): bool
    {
        return ! $this->successful && ($this->status === 404 || str_contains(strtolower($this->errorBody), 'model'));
    }
}
