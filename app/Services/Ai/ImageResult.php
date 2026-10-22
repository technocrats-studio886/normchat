<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Response;

/** Hasil Image API (generations / edits). `usage` null bila API tidak mengirimkannya. */
final class ImageResult
{
    private function __construct(
        public readonly ?string $binary,
        public readonly ?string $revisedPrompt,
        public readonly ?array $usage,
        public readonly ?string $errorCode,
    ) {
    }

    public static function fromResponse(Response $response): self
    {
        $b64 = $response->successful() ? $response->json('data.0.b64_json') : null;

        return new self(
            $b64 ? base64_decode($b64) : null,
            $response->json('data.0.revised_prompt'),
            $response->json('usage'),
            $response->successful() ? null : (string) ($response->json('error.code') ?? 'request_failed'),
        );
    }

    public static function failed(string $errorCode): self
    {
        return new self(null, null, null, $errorCode);
    }

    public function successful(): bool
    {
        return $this->binary !== null && $this->binary !== '';
    }

    public function blockedByContentPolicy(): bool
    {
        return in_array($this->errorCode, ['content_policy_violation', 'moderation_blocked'], true);
    }
}
