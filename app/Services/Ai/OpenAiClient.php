<?php

namespace App\Services\Ai;

use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Satu-satunya tempat Normchat berbicara HTTP dengan OpenAI. */
class OpenAiClient
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(private readonly string $token)
    {
    }

    /**
     * Kredensial: koneksi AI milik owner grup, lalu API key owner, lalu OPENAI_API_KEY server.
     */
    public static function forOwner(User $owner): ?self
    {
        $connectionToken = AiConnection::query()
            ->where('user_id', $owner->id)
            ->where('provider', 'openai')
            ->first()
            ?->decryptedAccessToken();

        foreach ([$connectionToken, $owner->getApiKey(), config('services.openai.api_key')] as $token) {
            if (is_string($token) && $token !== '') {
                return new self($token);
            }
        }

        return null;
    }

    public function chat(array $payload, int $timeout = 60): ChatCompletion
    {
        try {
            return ChatCompletion::fromResponse(
                Http::withToken($this->token)->timeout($timeout)->post(self::BASE_URL . '/chat/completions', $payload)
            );
        } catch (Throwable $e) {
            Log::error('OpenAI chat request failed.', ['model' => $payload['model'] ?? null, 'error' => $e->getMessage()]);

            return ChatCompletion::failed($e->getMessage());
        }
    }

    public function generateImage(string $model, string $prompt, string $size, string $quality): ImageResult
    {
        try {
            // gpt-image selalu mengembalikan b64_json; response_format tidak didukung.
            return $this->imageResult(Http::withToken($this->token)->timeout(120)->post(self::BASE_URL . '/images/generations', [
                'model' => $model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $size,
                'quality' => $quality,
            ]), $model);
        } catch (Throwable $e) {
            Log::error('OpenAI image generation failed.', ['model' => $model, 'error' => $e->getMessage()]);

            return ImageResult::failed('exception');
        }
    }

    /** Edit / generasi berbasis gambar referensi (multipart image[]). */
    public function editImage(string $model, string $prompt, string $imageBinary, string $filename, string $size, string $quality): ImageResult
    {
        try {
            return $this->imageResult(Http::withToken($this->token)
                ->timeout(120)
                ->attach('image[]', $imageBinary, $filename)
                ->post(self::BASE_URL . '/images/edits', [
                    'model' => $model,
                    'prompt' => $prompt,
                    'size' => $size,
                    'quality' => $quality,
                ]), $model);
        } catch (Throwable $e) {
            Log::error('OpenAI image edit failed.', ['model' => $model, 'error' => $e->getMessage()]);

            return ImageResult::failed('exception');
        }
    }

    /** @return array{text: string, usage: array}|null */
    public function transcribe(string $audioBinary, string $filename, string $model): ?array
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout(90)
                ->attach('file', $audioBinary, $filename)
                ->post(self::BASE_URL . '/audio/transcriptions', [
                    'model' => $model,
                    'response_format' => 'json',
                    'language' => 'id',
                ]);
        } catch (Throwable $e) {
            Log::warning('Audio transcription exception.', ['error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Audio transcription failed.', ['status' => $response->status()]);

            return null;
        }

        return ['text' => trim((string) $response->json('text', '')), 'usage' => (array) $response->json('usage', [])];
    }

    private function imageResult(\Illuminate\Http\Client\Response $response, string $model): ImageResult
    {
        if ($response->failed()) {
            Log::warning('OpenAI image request rejected.', [
                'model' => $model,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
        }

        return ImageResult::fromResponse($response);
    }
}
