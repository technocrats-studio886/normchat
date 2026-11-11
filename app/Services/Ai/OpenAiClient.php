<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Satu-satunya tempat Normi Chat berbicara HTTP dengan OpenAI. */
class OpenAiClient
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(private readonly string $token)
    {
    }

    /**
     * Satu API key server (OPENAI_API_KEY) untuk semua grup. Konteks tiap grup tetap terpisah karena
     * disusun backend per grup; key hanya mengautentikasi akun OpenAI Normi Chat.
     */
    public static function server(): ?self
    {
        $token = config('services.openai.api_key');

        return is_string($token) && $token !== '' ? new self($token) : null;
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

    /**
     * Responses API dengan alat web search. $messages memakai format chat completions
     * (system + riwayat + user) dan diubah ke format input Responses API di sini.
     *
     * $onEvent: bila diisi, respons dibaca sebagai stream (server-sent events) dan setiap event diteruskan
     * sebagai ($type, $data), mis. kueri pencarian web yang sedang berjalan. Hasil akhirnya tetap respons
     * lengkap (event response.completed), jadi penagihan sama dengan mode tanpa stream.
     *
     * @param  (\Closure(string, array): void)|null  $onEvent
     */
    public function research(string $model, array $messages, int $maxOutputTokens, int $timeout = 300, ?string $cacheKey = null, ?\Closure $onEvent = null): ChatCompletion
    {
        $instructions = [];
        $input = [];
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $instructions[] = (string) $message['content'];
                continue;
            }
            $input[] = ['role' => $message['role'], 'content' => self::responsesContent($message['content'] ?? '', $message['role'])];
        }

        $payload = [
            'model' => $model,
            'instructions' => implode("\n\n", $instructions),
            'input' => $input,
            'tools' => [['type' => 'web_search']],
            'max_output_tokens' => $maxOutputTokens,
            ...($cacheKey !== null ? ['prompt_cache_key' => $cacheKey] : []),
        ];

        try {
            if ($onEvent === null) {
                return ChatCompletion::fromResponsesApi(Http::withToken($this->token)->timeout($timeout)->post(self::BASE_URL . '/responses', $payload));
            }

            $response = Http::withToken($this->token)
                ->timeout($timeout)
                ->withOptions(['stream' => true])
                ->post(self::BASE_URL . '/responses', [...$payload, 'stream' => true]);

            return $this->readResearchStream($response, $onEvent);
        } catch (Throwable $e) {
            Log::error('OpenAI research request failed.', ['model' => $model, 'error' => $e->getMessage()]);

            return ChatCompletion::failed($e->getMessage());
        }
    }

    /**
     * Baca server-sent events Responses API sampai event akhir (completed / incomplete / failed).
     *
     * @param  \Closure(string, array): void  $onEvent
     */
    private function readResearchStream(\Illuminate\Http\Client\Response $response, \Closure $onEvent): ChatCompletion
    {
        $body = $response->toPsrResponse()->getBody();
        if (! $response->successful()) {
            return ChatCompletion::fromResponsesPayload([], $response->status(), (string) $body);
        }

        $buffer = '';
        $final = null;
        $handle = function (string $block) use (&$final, $onEvent): void {
            $data = '';
            foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data .= ltrim(substr($line, 5));
                }
            }
            if ($data === '' || $data === '[DONE]') {
                return;
            }
            $event = json_decode($data, true);
            if (! is_array($event)) {
                return;
            }
            $type = (string) ($event['type'] ?? '');
            if (in_array($type, ['response.completed', 'response.incomplete', 'response.failed'], true)) {
                $final = (array) ($event['response'] ?? []);
            }
            try {
                $onEvent($type, $event);
            } catch (Throwable $e) {
                // Status ke grup gagal dikirim: riset tetap berjalan.
                Log::warning('Research progress callback failed.', ['error' => $e->getMessage()]);
            }
        };

        while (! $body->eof()) {
            $buffer .= $body->read(8192);
            while (preg_match('/\r?\n\r?\n/', $buffer, $match, PREG_OFFSET_CAPTURE) === 1) {
                $handle(substr($buffer, 0, $match[0][1]));
                $buffer = substr($buffer, $match[0][1] + strlen($match[0][0]));
            }
        }
        if (trim($buffer) !== '') {
            $handle($buffer);
        }

        if ($final === null) {
            return ChatCompletion::failed('Stream riset berakhir tanpa respons akhir.');
        }

        return ChatCompletion::fromResponsesPayload($final, ($final['status'] ?? '') === 'failed' ? 500 : 200, ($final['status'] ?? '') === 'failed' ? json_encode($final['error'] ?? []) : '');
    }

    /** Bagian pesan chat completions (text / image_url) → Responses API (input_text / input_image). */
    private static function responsesContent(string|array $content, string $role): string|array
    {
        if (is_string($content) || $role === 'assistant') {
            return is_string($content) ? $content : collect($content)->pluck('text')->filter()->implode("\n");
        }

        return array_map(fn (array $part) => ($part['type'] ?? '') === 'image_url'
            ? ['type' => 'input_image', 'image_url' => (string) data_get($part, 'image_url.url')]
            : ['type' => 'input_text', 'text' => (string) ($part['text'] ?? '')], $content);
    }

    /**
     * Embedding untuk beberapa teks sekaligus (urutan hasil = urutan masukan).
     *
     * @param  list<string>  $inputs
     * @return array{vectors: list<list<float>>, tokens: int}|null
     */
    public function embed(string $model, array $inputs, int $dimensions, int $timeout = 60): ?array
    {
        try {
            $response = Http::withToken($this->token)->timeout($timeout)->post(self::BASE_URL . '/embeddings', [
                'model' => $model,
                'input' => array_values($inputs),
                'dimensions' => $dimensions,
            ]);
        } catch (Throwable $e) {
            Log::warning('OpenAI embedding request failed.', ['model' => $model, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('OpenAI embedding rejected.', ['model' => $model, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);

            return null;
        }

        $vectors = collect((array) $response->json('data', []))
            ->sortBy('index')
            ->map(fn (array $row) => array_map('floatval', (array) ($row['embedding'] ?? [])))
            ->values()
            ->all();

        return count($vectors) === count($inputs)
            ? ['vectors' => $vectors, 'tokens' => (int) $response->json('usage.prompt_tokens', 0)]
            : null;
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
