<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupToken;
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

        $group = Group::query()
            ->with(['groupToken'])
            ->find($message->group_id);
        if (! $group) {
            return;
        }

        $owner = User::find($group->owner_id);
        if (! $owner) {
            return;
        }

        // Use group's configured provider/model
        $provider = $group->ai_provider ?: $this->normalizeProvider($owner->auth_provider);
        $model = $group->ai_model ?: config("ai_models.defaults.{$provider}");
        $multiplier = $group->getModelMultiplier();
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

        // Check token balance before calling AI
        $groupToken = $group->groupToken;
        if (! $groupToken || $groupToken->remaining_tokens <= 0) {
            $this->sendSystemMessage($group, 'Saldo token grup habis. Minta owner atau member untuk top-up normkredit agar bisa menggunakan AI.');
            return;
        }

        // Estimate minimum needed (at least 1000 tokens * multiplier)
        $minEstimate = (int) ceil(1000 * $multiplier);
        if ($groupToken->remaining_tokens < $minEstimate) {
            $this->sendSystemMessage($group, 'Saldo token grup tidak cukup untuk request ini. Sisa: ' . $groupToken->formattedRemaining() . ' token. Top-up normkredit untuk melanjutkan.');
            return;
        }

        $recentMessages = Message::query()
            ->where('group_id', $group->id)
            ->orderByDesc('id')
            ->take(16)
            ->get(['sender_type', 'content'])
            ->reverse()
            ->values();

        $credentials = $this->resolveCredentials($owner, $provider);

        $responseText = null;
        $usageTokens = 0;

        if ($credentials !== null) {
            [$responseText, $usageTokens] = $this->generateProviderResponse(
                $provider, $model, $credentials, $message->content, $recentMessages->all(), $group
            );
        }

        if (! $responseText) {
            $responseText = sprintf(
                'Provider %s aktif, tapi respons AI belum tersedia saat ini. Coba lagi beberapa saat.',
                strtoupper($provider)
            );
            // Don't charge tokens if response failed
            $usageTokens = 0;
        }

        // Consume tokens with multiplier
        if ($usageTokens > 0 && $groupToken) {
            $effectiveTokens = $groupToken->consumeTokens($usageTokens, $multiplier);

            if ($effectiveTokens === false) {
                // Not enough tokens - still send the response but warn
                $remaining = $groupToken->formattedRemaining();
                $needed = number_format((int) ceil($usageTokens * $multiplier));
                $responseText .= "\n\n---\nSaldo token grup tidak mencukupi. Sisa: {$remaining} token, dibutuhkan: {$needed} token. Top-up normkredit untuk melanjutkan penggunaan AI.";

                // Force consume whatever is left
                if ($groupToken->remaining_tokens > 0) {
                    $groupToken->increment('used_tokens', $groupToken->remaining_tokens);
                    $groupToken->update(['remaining_tokens' => 0]);
                }
            }
        }

        $aiMessage = Message::create([
            'group_id' => $message->group_id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'content' => $responseText,
        ]);

        event(new MessageSent($message->group_id, [
            'id' => $aiMessage->id,
            'message_type' => $aiMessage->message_type ?? 'text',
            'sender_type' => $aiMessage->sender_type,
            'sender_id' => $aiMessage->sender_id,
            'sender_name' => 'NormAI',
            'content' => $aiMessage->content,
            'attachment_url' => null,
            'attachment_mime' => null,
            'attachment_original_name' => null,
            'created_at' => optional($aiMessage->created_at)->toIso8601String(),
        ]));
    }

    private function sendSystemMessage(Group $group, string $text): void
    {
        $msg = Message::create([
            'group_id' => $group->id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'content' => $text,
        ]);

        event(new MessageSent($group->id, [
            'id' => $msg->id,
            'message_type' => 'text',
            'sender_type' => 'ai',
            'sender_id' => null,
            'sender_name' => 'NormAI',
            'content' => $msg->content,
            'attachment_url' => null,
            'attachment_mime' => null,
            'attachment_original_name' => null,
            'created_at' => optional($msg->created_at)->toIso8601String(),
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

    private function resolveCredentials(User $owner, string $provider = 'openai'): ?string
    {
        // 1. Try user-level credentials first (OAuth token or user-provided API key)
        if ($owner->hasValidCredentials()) {
            $credential = $owner->getAccessToken() ?? $owner->getApiKey();
            if ($credential) {
                return $credential;
            }
        }

        // 2. Fallback to server-side API key from .env
        $envKey = match ($provider) {
            'openai' => config('services.openai.api_key'),
            'claude' => config('services.anthropic.api_key'),
            'gemini' => config('services.gemini.api_key'),
            default => null,
        };

        return $envKey ?: null;
    }

    /**
     * Returns [responseText, actualTokensUsed]
     */
    private function generateProviderResponse(string $provider, string $model, string $credential, string $latestPrompt, array $recentMessages, ?Group $group = null): array
    {
        try {
            return match ($provider) {
                'openai' => $this->callOpenAi($credential, $model, $latestPrompt, $recentMessages, $group),
                'claude' => $this->callClaude($credential, $model, $latestPrompt, $recentMessages, $group),
                'gemini' => $this->callGemini($credential, $model, $latestPrompt, $recentMessages, $group),
                default => [null, 0],
            };
        } catch (Throwable $e) {
            Log::error('AI provider call failed.', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [null, 0];
        }
    }

    private function callOpenAi(string $token, string $model, string $latestPrompt, array $recentMessages, ?Group $group = null): array
    {
        $messages = $this->toChatMessages($recentMessages, $latestPrompt, $group);

        $response = Http::withToken($token)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.4,
            ]);

        if ($response->failed()) {
            Log::warning('OpenAI response failed.', ['status' => $response->status(), 'body' => $response->body()]);
            return [null, 0];
        }

        $text = $response->json('choices.0.message.content');
        $usage = $response->json('usage', []);
        $totalTokens = (int) ($usage['total_tokens'] ?? 0);

        return [$text, $totalTokens];
    }

    private function callClaude(string $token, string $model, string $latestPrompt, array $recentMessages, ?Group $group = null): array
    {
        $prompt = $this->toPlainTranscriptPrompt($recentMessages, $latestPrompt, $group);

        $response = Http::timeout(30)
            ->withHeaders([
                'x-api-key' => $token,
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 700,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Anthropic response failed.', ['status' => $response->status(), 'body' => $response->body()]);
            return [null, 0];
        }

        $parts = $response->json('content', []);
        $text = null;
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (($part['type'] ?? null) === 'text' && ! empty($part['text'])) {
                    $text = (string) $part['text'];
                    break;
                }
            }
        }

        $usage = $response->json('usage', []);
        $totalTokens = (int) (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0));

        return [$text, $totalTokens];
    }

    private function callGemini(string $token, string $model, string $latestPrompt, array $recentMessages, ?Group $group = null): array
    {
        $prompt = $this->toPlainTranscriptPrompt($recentMessages, $latestPrompt, $group);

        $response = Http::withToken($token)
            ->timeout(30)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
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
            return [null, 0];
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        $usageMetadata = $response->json('usageMetadata', []);
        $totalTokens = (int) (($usageMetadata['promptTokenCount'] ?? 0) + ($usageMetadata['candidatesTokenCount'] ?? 0));

        return [$text, $totalTokens];
    }

    private function toChatMessages(array $recentMessages, string $latestPrompt, ?Group $group = null): array
    {
        $systemPrompt = 'Kamu adalah AI participant untuk group chat Normchat. Jawab singkat, jelas, relevan konteks grup, dan gunakan Bahasa Indonesia.';

        if ($group) {
            if ($group->ai_persona_style) {
                $systemPrompt .= "\n\nPersona style: " . $group->ai_persona_style;
            }
            if ($group->ai_persona_guardrails) {
                $systemPrompt .= "\n\nGuardrails: " . $group->ai_persona_guardrails;
            }
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
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

    private function toPlainTranscriptPrompt(array $recentMessages, string $latestPrompt, ?Group $group = null): string
    {
        $lines = [
            'Kamu adalah AI participant untuk group chat Normchat.',
            'Jawab dalam Bahasa Indonesia, ringkas dan actionable.',
        ];

        if ($group?->ai_persona_style) {
            $lines[] = 'Persona style: ' . $group->ai_persona_style;
        }
        if ($group?->ai_persona_guardrails) {
            $lines[] = 'Guardrails: ' . $group->ai_persona_guardrails;
        }

        $lines[] = 'Konteks percakapan terbaru:';

        foreach ($recentMessages as $row) {
            $speaker = strtoupper((string) ($row['sender_type'] ?? 'user'));
            $lines[] = sprintf('%s: %s', $speaker, (string) ($row['content'] ?? ''));
        }

        $lines[] = 'Pertanyaan terakhir user:';
        $lines[] = $latestPrompt;

        return implode("\n", $lines);
    }
}
