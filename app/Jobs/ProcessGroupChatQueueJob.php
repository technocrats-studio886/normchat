<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessGroupChatQueueJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $groupId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $lock = Cache::lock('group-chat-queue:'.$this->groupId, 30);

        if (! $lock->get()) {
            $this->release(2);
            return;
        }

        try {
            while (true) {
                $queueItem = ChatMessageQueue::query()
                    ->where('group_id', $this->groupId)
                    ->where('status', 'queued')
                    ->orderBy('id')
                    ->first();

                if (! $queueItem) {
                    break;
                }

                $queueItem->status = 'processing';
                $queueItem->save();

                try {
                    $this->processQueueItem($queueItem);

                    $queueItem->status = 'processed';
                    $queueItem->processed_at = now();
                    $queueItem->error_message = null;
                    $queueItem->save();
                } catch (Throwable $e) {
                    $queueItem->status = 'failed';
                    $queueItem->error_message = mb_substr($e->getMessage(), 0, 250);
                    $queueItem->processed_at = now();
                    $queueItem->save();
                }
            }
        } finally {
            $lock->release();
        }
    }

    private function processQueueItem(ChatMessageQueue $queueItem): void
    {
        $message = Message::query()->whereKey($queueItem->message_id)->first();
        if (! $message) {
            return;
        }

        $group = Group::query()->with(['aiConnections' => fn ($q) => $q->latest('created_at')])->find($message->group_id);
        if (! $group) {
            return;
        }

        // Resolve owner and provider source (group-level connection preferred)
        $owner = User::find($group->owner_id);
        if (! $owner) {
            return;
        }

        $activeConnection = $group->aiConnections->first();
        $provider = $this->normalizeProvider($activeConnection?->provider ?: $owner->auth_provider);
        $content = strtolower((string) $message->content);

        // Check if message mentions AI
        $triggers = $this->buildMentionTriggers($provider);

        $isAiRequest = false;
        foreach ($triggers as $trigger) {
            if (str_contains($content, $trigger)) {
                $isAiRequest = true;
                break;
            }
        }

        if (! $isAiRequest) {
            return;
        }

        $recentMessages = Message::query()
            ->where('group_id', $group->id)
            ->orderByDesc('id')
            ->take(16)
            ->get(['sender_type', 'content'])
            ->reverse()
            ->values();

        $credentials = $this->resolveCredentials($activeConnection, $owner);

        $responseText = null;
        if ($credentials !== null) {
            $responseText = $this->generateProviderResponse($provider, $credentials, $message->content, $recentMessages->all());
        }

        if (! $responseText) {
            $responseText = sprintf(
                'Provider %s aktif, tapi respons AI belum tersedia saat ini. Coba lagi beberapa saat atau perbarui koneksi AI owner.',
                strtoupper($provider)
            );
        }

        $aiMessage = Message::create([
            'group_id' => $message->group_id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'content' => $responseText,
        ]);

        event(new MessageSent($message->group_id, [
            'id' => $aiMessage->id,
            'sender_type' => $aiMessage->sender_type,
            'sender_id' => $aiMessage->sender_id,
            'sender_name' => 'NormAI',
            'content' => $aiMessage->content,
            'created_at' => optional($aiMessage->created_at)->toIso8601String(),
        ]));
    }

    private function normalizeProvider(?string $provider): string
    {
        $key = strtolower((string) $provider);

        return match ($key) {
            'chatgpt' => 'openai',
            'anthropic' => 'claude',
            'google' => 'gemini',
            default => in_array($key, ['openai', 'claude', 'gemini'], true) ? $key : 'openai',
        };
    }

    private function buildMentionTriggers(string $provider): array
    {
        $base = ['@ai'];

        return match ($provider) {
            'openai' => array_merge($base, ['@openai', '@chatgpt']),
            'claude' => array_merge($base, ['@claude', '@anthropic']),
            'gemini' => array_merge($base, ['@gemini', '@google']),
            default => $base,
        };
    }

    private function resolveCredentials($activeConnection, User $owner): ?string
    {
        if ($activeConnection) {
            $token = $activeConnection->decryptedAccessToken();
            if ($token !== null && $token !== '') {
                return $token;
            }
        }

        if (! $owner->hasValidCredentials()) {
            return null;
        }

        return $owner->getAccessToken() ?? $owner->getApiKey();
    }

    private function generateProviderResponse(string $provider, string $credential, string $latestPrompt, array $recentMessages): ?string
    {
        try {
            return match ($provider) {
                'openai' => $this->callOpenAi($credential, $latestPrompt, $recentMessages),
                'claude' => $this->callClaude($credential, $latestPrompt, $recentMessages),
                'gemini' => $this->callGemini($credential, $latestPrompt, $recentMessages),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error('AI provider call failed.', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function callOpenAi(string $token, string $latestPrompt, array $recentMessages): ?string
    {
        $messages = $this->toChatMessages($recentMessages, $latestPrompt);

        $response = Http::withToken($token)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => env('OPENAI_CHAT_MODEL', 'gpt-4o-mini'),
                'messages' => $messages,
                'temperature' => 0.4,
            ]);

        if ($response->failed()) {
            Log::warning('OpenAI response failed.', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    private function callClaude(string $token, string $latestPrompt, array $recentMessages): ?string
    {
        $prompt = $this->toPlainTranscriptPrompt($recentMessages, $latestPrompt);

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $token,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => env('ANTHROPIC_CHAT_MODEL', 'claude-3-5-sonnet-latest'),
                'max_tokens' => 700,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Anthropic response failed.', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $parts = $response->json('content', []);
        if (! is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (($part['type'] ?? null) === 'text' && ! empty($part['text'])) {
                return (string) $part['text'];
            }
        }

        return null;
    }

    private function callGemini(string $token, string $latestPrompt, array $recentMessages): ?string
    {
        $prompt = $this->toPlainTranscriptPrompt($recentMessages, $latestPrompt);

        $response = Http::withToken($token)
            ->timeout(30)
            ->post('https://generativelanguage.googleapis.com/v1beta/models/'.env('GEMINI_CHAT_MODEL', 'gemini-2.0-flash').':generateContent', [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Gemini response failed.', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        return $response->json('candidates.0.content.parts.0.text');
    }

    private function toChatMessages(array $recentMessages, string $latestPrompt): array
    {
        $messages = [
            [
                'role' => 'system',
                'content' => 'Kamu adalah AI participant untuk group chat Normchat. Jawab singkat, jelas, relevan konteks grup, dan gunakan Bahasa Indonesia.',
            ],
        ];

        foreach ($recentMessages as $row) {
            $messages[] = [
                'role' => (($row['sender_type'] ?? 'user') === 'ai') ? 'assistant' : 'user',
                'content' => (string) ($row['content'] ?? ''),
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $latestPrompt];

        return $messages;
    }

    private function toPlainTranscriptPrompt(array $recentMessages, string $latestPrompt): string
    {
        $lines = [
            'Kamu adalah AI participant untuk group chat Normchat.',
            'Jawab dalam Bahasa Indonesia, ringkas dan actionable.',
            'Konteks percakapan terbaru:',
        ];

        foreach ($recentMessages as $row) {
            $speaker = strtoupper((string) ($row['sender_type'] ?? 'user'));
            $lines[] = sprintf('%s: %s', $speaker, (string) ($row['content'] ?? ''));
        }

        $lines[] = 'Pertanyaan terakhir user:';
        $lines[] = $latestPrompt;

        return implode("\n", $lines);
    }
}
