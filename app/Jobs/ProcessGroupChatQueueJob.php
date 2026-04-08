<?php

namespace App\Jobs;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Models\AiConnection;
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
use Illuminate\Support\Facades\Storage;
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
        $message = Message::query()
            ->with(['replyToMessage.sender:id,name'])
            ->whereKey($queueItem->message_id)
            ->first();
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

        // AI stack is fixed to OpenAI for now (provider/model hidden from end users).
        $provider = 'openai';
        $model = (string) config('ai_models.defaults.openai', 'gpt-5');
        $multiplier = $group->getModelMultiplier();
        $content = strtolower((string) $message->content);
        $replyTarget = $message->replyToMessage;

        // Check if message mentions AI
        $triggers = $this->buildMentionTriggers($provider);
        $isAiRequest = false;
        foreach ($triggers as $trigger) {
            if (str_contains($content, $trigger)) {
                $isAiRequest = true;
                break;
            }
        }

        if (! $isAiRequest && $replyTarget?->sender_type === 'ai') {
            $isAiRequest = true;
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
            ->get([
                'id',
                'sender_type',
                'sender_id',
                'message_type',
                'content',
                'attachment_disk',
                'attachment_path',
                'attachment_mime',
                'attachment_original_name',
                'reply_to_message_id',
            ])
            ->reverse()
            ->values();

        $credentials = $this->resolveCredentials($owner, $provider);

        if ($credentials === null) {
            $this->sendSystemMessage($group, 'AI belum dikonfigurasi. Hubungi admin untuk mengisi OPENAI_API_KEY agar fitur @ai aktif.');
            return;
        }

        $responseText = null;
        $usageTokens = 0;

        event(new TypingStatus($group->id, 'ai', null, 'NormAI', true));
        try {
            [$responseText, $usageTokens] = $this->generateProviderResponse(
                $provider,
                $model,
                $credentials,
                $message,
                $recentMessages->all(),
                $group
            );
        } finally {
            event(new TypingStatus($group->id, 'ai', null, 'NormAI', false));
        }

        if (! $responseText) {
            $responseText = 'AI sedang sibuk dan belum bisa merespons saat ini. Coba lagi beberapa saat.';
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

        $groupToken?->refresh();

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
            'reply_to' => null,
            'group_tokens_remaining' => (int) ($groupToken?->remaining_tokens ?? 0),
            'group_credits_remaining' => round(((int) ($groupToken?->remaining_tokens ?? 0)) / 1000, 1),
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

    private function buildMentionTriggers(string $provider): array
    {
        return ['@ai', '@openai', '@chatgpt'];
    }

    private function resolveCredentials(User $owner, string $provider = 'openai'): ?string
    {
        // 1) User-level OpenAI token from explicit AI connection
        $aiConnection = AiConnection::query()
            ->where('user_id', $owner->id)
            ->where('provider', 'openai')
            ->first();

        $connectionToken = $aiConnection?->decryptedAccessToken();
        if (is_string($connectionToken) && $connectionToken !== '') {
            return $connectionToken;
        }

        // 2) User-level API key
        $apiKey = $owner->getApiKey();
        if (is_string($apiKey) && $apiKey !== '') {
            return $apiKey;
        }

        // 3) Server-side API key
        $envKey = config('services.openai.api_key');

        return (is_string($envKey) && $envKey !== '') ? $envKey : null;
    }

    /**
     * Returns [responseText, actualTokensUsed]
     */
    private function generateProviderResponse(string $provider, string $model, string $credential, Message $requestMessage, array $recentMessages, ?Group $group = null): array
    {
        try {
            return $this->callOpenAi($credential, $model, $requestMessage, $recentMessages, $group);
        } catch (Throwable $e) {
            Log::error('AI provider call failed.', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            return [null, 0];
        }
    }

    private function callOpenAi(string $token, string $model, Message $requestMessage, array $recentMessages, ?Group $group = null): array
    {
        $messages = $this->toChatMessages($recentMessages, $requestMessage, $token, $group);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.4,
        ];

        $response = Http::withToken($token)
            ->timeout(30)
            ->post('https://api.openai.com/v1/chat/completions', $payload);

        if ($response->failed()) {
            $body = (string) $response->body();
            $mightBeModelIssue = str_contains(strtolower($body), 'model') || $response->status() === 404;
            if ($mightBeModelIssue && $model !== 'gpt-4.1') {
                $payload['model'] = 'gpt-4.1';
                $response = Http::withToken($token)
                    ->timeout(30)
                    ->post('https://api.openai.com/v1/chat/completions', $payload);
            }
        }

        if ($response->failed()) {
            Log::warning('OpenAI response failed.', ['status' => $response->status(), 'body' => $response->body()]);
            return [null, 0];
        }

        $text = $response->json('choices.0.message.content');
        $usage = $response->json('usage', []);
        $totalTokens = (int) ($usage['total_tokens'] ?? 0);

        return [$text, $totalTokens];
    }

    private function toChatMessages(array $recentMessages, Message $requestMessage, string $token, ?Group $group = null): array
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
            if ((int) ($row['id'] ?? 0) === (int) $requestMessage->id) {
                continue;
            }

            $rowText = $this->messageToContextLine($row, $token);
            $messages[] = [
                'role' => (($row['sender_type'] ?? 'user') === 'ai') ? 'assistant' : 'user',
                'content' => $rowText,
            ];
        }

        $latestPrompt = $this->buildLatestPromptText($requestMessage, $token);
        $latestParts = [
            ['type' => 'text', 'text' => $latestPrompt],
        ];

        $latestImageDataUri = $this->attachmentImageDataUri($requestMessage);
        if ($latestImageDataUri !== null) {
            $latestParts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $latestImageDataUri],
            ];
        }

        $replyImageDataUri = $this->attachmentImageDataUri($requestMessage->replyToMessage);
        if ($replyImageDataUri !== null) {
            $latestParts[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $replyImageDataUri],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $latestParts];

        return $messages;
    }

    private function buildLatestPromptText(Message $requestMessage, string $token): string
    {
        $parts = [];

        $baseContent = trim((string) $requestMessage->content);
        if ($baseContent !== '') {
            $parts[] = $baseContent;
        }

        if ($this->isAudioMessage($requestMessage)) {
            $transcript = $this->transcribeAudioAttachment($requestMessage, $token);
            if ($transcript !== null && $transcript !== '') {
                $parts[] = 'Transkrip audio dari pesan ini: ' . $transcript;
            } else {
                $parts[] = 'Pesan ini memiliki lampiran audio namun transkrip tidak tersedia.';
            }
        } elseif ($this->isImageMessage($requestMessage)) {
            $parts[] = 'Pesan ini menyertakan gambar. Gunakan gambar tersebut untuk menjawab.';
        }

        $replyTarget = $requestMessage->replyToMessage;
        if ($replyTarget) {
            $replySender = $replyTarget->sender_type === 'ai'
                ? 'NormAI'
                : ($replyTarget->sender?->name ?? 'User');

            $replySummary = trim((string) $replyTarget->content);
            if ($replySummary === '') {
                $replySummary = $this->isImageMessage($replyTarget)
                    ? '[lampiran gambar]'
                    : ($this->isAudioMessage($replyTarget)
                        ? '[lampiran audio]'
                        : '[lampiran file]');
            }

            $parts[] = "Kamu sedang membalas (reply) pesan dari {$replySender}: {$replySummary}";

            if ($this->isAudioMessage($replyTarget)) {
                $replyTranscript = $this->transcribeAudioAttachment($replyTarget, $token);
                if ($replyTranscript !== null && $replyTranscript !== '') {
                    $parts[] = 'Transkrip audio dari pesan yang direply: ' . $replyTranscript;
                }
            }

            if ($this->isImageMessage($replyTarget)) {
                $parts[] = 'Ada gambar pada pesan yang direply. Perhatikan gambar tersebut dalam jawabanmu.';
            }
        }

        if ($parts === []) {
            return 'Bantu user berdasarkan konteks percakapan terbaru.';
        }

        return implode("\n\n", $parts);
    }

    private function messageToContextLine($row, string $token): string
    {
        $content = trim((string) ($row['content'] ?? ''));
        if ($content !== '') {
            return $content;
        }

        $messageType = strtolower((string) ($row['message_type'] ?? 'text'));
        if ($messageType === 'image') {
            return '[Pesan sebelumnya berisi gambar]';
        }

        if ($messageType === 'voice') {
            $audioMessage = new Message([
                'message_type' => $row['message_type'] ?? 'voice',
                'attachment_disk' => $row['attachment_disk'] ?? null,
                'attachment_path' => $row['attachment_path'] ?? null,
                'attachment_mime' => $row['attachment_mime'] ?? null,
                'attachment_original_name' => $row['attachment_original_name'] ?? null,
            ]);
            $audioMessage->id = (int) ($row['id'] ?? 0);

            $transcript = $this->transcribeAudioAttachment($audioMessage, $token);
            if ($transcript !== null && $transcript !== '') {
                return '[Transkrip audio] ' . $transcript;
            }

            return '[Pesan sebelumnya berisi audio]';
        }

        return '[Pesan sebelumnya berisi lampiran]';
    }

    private function isImageMessage(?Message $message): bool
    {
        if (! $message) {
            return false;
        }

        $mime = strtolower((string) $message->attachment_mime);
        return strtolower((string) $message->message_type) === 'image' || str_starts_with($mime, 'image/');
    }

    private function isAudioMessage(?Message $message): bool
    {
        if (! $message) {
            return false;
        }

        $mime = strtolower((string) $message->attachment_mime);
        return strtolower((string) $message->message_type) === 'voice'
            || str_starts_with($mime, 'audio/')
            || $mime === 'video/webm';
    }

    private function attachmentImageDataUri(?Message $message): ?string
    {
        if (! $this->isImageMessage($message) || ! $message?->attachment_disk || ! $message->attachment_path) {
            return null;
        }

        try {
            $disk = Storage::disk($message->attachment_disk);
            if (! $disk->exists($message->attachment_path)) {
                return null;
            }

            $size = (int) $disk->size($message->attachment_path);
            if ($size <= 0 || $size > 4 * 1024 * 1024) {
                return null;
            }

            $binary = $disk->get($message->attachment_path);
            $mime = $message->attachment_mime ?: 'image/jpeg';

            return 'data:'.$mime.';base64,'.base64_encode($binary);
        } catch (Throwable $e) {
            Log::warning('Failed to load image attachment for AI context.', [
                'message_id' => $message?->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function transcribeAudioAttachment(?Message $message, string $token): ?string
    {
        if (! $this->isAudioMessage($message) || ! $message?->attachment_disk || ! $message->attachment_path) {
            return null;
        }

        $cacheKey = 'msg-audio-transcript:'.$message->id;
        return Cache::remember($cacheKey, now()->addHours(12), function () use ($message, $token) {
            $tmpPath = null;
            $stream = null;

            try {
                $disk = Storage::disk($message->attachment_disk);
                if (! $disk->exists($message->attachment_path)) {
                    return null;
                }

                $binary = $disk->get($message->attachment_path);
                if ($binary === '' || $binary === null) {
                    return null;
                }

                $tmpPath = tempnam(sys_get_temp_dir(), 'normchat-audio-');
                if (! $tmpPath) {
                    return null;
                }

                file_put_contents($tmpPath, $binary);
                $stream = fopen($tmpPath, 'r');
                if ($stream === false) {
                    return null;
                }

                $filename = $message->attachment_original_name ?: basename($message->attachment_path);
                $response = Http::withToken($token)
                    ->timeout(90)
                    ->attach('file', $stream, $filename)
                    ->post('https://api.openai.com/v1/audio/transcriptions', [
                        'model' => 'whisper-1',
                        'response_format' => 'json',
                        'language' => 'id',
                    ]);

                if ($response->failed()) {
                    Log::warning('Audio transcription failed.', [
                        'message_id' => $message->id,
                        'status' => $response->status(),
                    ]);

                    return null;
                }

                $text = trim((string) ($response->json('text') ?? ''));
                return $text !== '' ? $text : null;
            } catch (Throwable $e) {
                Log::warning('Audio transcription exception.', [
                    'message_id' => $message?->id,
                    'error' => $e->getMessage(),
                ]);

                return null;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                if ($tmpPath && file_exists($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        });
    }

}
