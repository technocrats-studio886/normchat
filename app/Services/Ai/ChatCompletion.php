<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;

/** Hasil satu panggilan chat completions, dengan usage yang sudah dinormalisasi. */
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
