<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\Message;
use App\Jobs\ProcessGroupChatQueueJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function openLast(): RedirectResponse
    {
        $userId = (int) Auth::id();
        $lastGroupId = (int) session('last_chat_group_id', 0);

        if ($lastGroupId > 0) {
            $lastGroup = Group::query()
                ->where('id', $lastGroupId)
                ->where(function ($query) use ($userId) {
                    $query->where('owner_id', $userId)
                        ->orWhereHas('members', fn ($q) => $q->where('user_id', $userId)->where('status', 'active'));
                })
                ->first();

            if ($lastGroup) {
                return redirect()->route('chat.show', $lastGroup);
            }
        }

        $fallbackGroup = Group::query()
            ->where('owner_id', $userId)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $userId)->where('status', 'active'))
            ->latest('updated_at')
            ->first();

        if ($fallbackGroup) {
            return redirect()->route('chat.show', $fallbackGroup);
        }

        return redirect()->route('groups.index');
    }

    public function show(Group $group): View
    {
        $this->authorize('chat', $group);

        session(['last_chat_group_id' => $group->id]);

        $group->load(['owner', 'members.user', 'groupToken']);

        $messages = Message::query()
            ->where('group_id', $group->id)
            ->with('sender:id,name')
            ->orderByDesc('id')
            ->take(80)
            ->get()
            ->reverse()
            ->values();

        $ownerProvider = $group->ai_provider ? ucfirst((string) $group->ai_provider) : null;
        $mentionSuggestions = $this->buildMentionSuggestions($group, $group->ai_provider);

        return view('chat.show', [
            'group' => $group,
            'messages' => $messages,
            'ownerProvider' => $ownerProvider,
            'activeAi' => $ownerProvider ? collect([$ownerProvider]) : collect(),
            'mentionSuggestions' => $mentionSuggestions,
        ]);
    }

    public function store(Request $request, Group $group): RedirectResponse|JsonResponse
    {
        $this->authorize('chat', $group);

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,gif,heic,heif,mp3,wav,ogg,webm,mp4,aac,m4a'],
        ]);

        $content = trim((string) ($validated['content'] ?? ''));
        $attachment = $request->file('attachment');

        if ($content === '' && ! $attachment instanceof UploadedFile) {
            return back()->withErrors(['content' => 'Kirim teks, gambar, atau voice note.'])->withInput();
        }

        try {
            $message = Cache::lock('group-chat-submit:'.$group->id, 10)->block(3, function () use ($group, $content, $attachment) {
                $messageType = $this->resolveMessageType($attachment);

                $attachmentPath = null;
                $attachmentDisk = null;
                $attachmentMime = null;
                $attachmentSize = null;
                $attachmentOriginalName = null;

                if ($attachment instanceof UploadedFile) {
                    $attachmentDisk = 'normchat_attachments';
                    $extension = strtolower((string) $attachment->getClientOriginalExtension());
                    $clientMime = strtolower((string) $attachment->getClientMimeType());
                    $safeExtension = $extension !== '' ? $extension : ($messageType === 'image' ? 'jpg' : 'webm');
                    $attachmentPath = sprintf(
                        'group-%d/%s/%s.%s',
                        $group->id,
                        now()->format('Y/m'),
                        (string) Str::uuid(),
                        $safeExtension
                    );

                    Storage::disk($attachmentDisk)->put($attachmentPath, file_get_contents($attachment->getRealPath()));

                    $attachmentMime = $this->normalizeAttachmentMime($messageType, $clientMime, $safeExtension);
                    $attachmentSize = (int) $attachment->getSize();
                    $attachmentOriginalName = (string) $attachment->getClientOriginalName();
                }

                $createdMessage = Message::create([
                    'group_id' => $group->id,
                    'message_type' => $messageType,
                    'sender_type' => 'user',
                    'sender_id' => Auth::id(),
                    'content' => $content !== '' ? $content : null,
                    'attachment_disk' => $attachmentDisk,
                    'attachment_path' => $attachmentPath,
                    'attachment_mime' => $attachmentMime,
                    'attachment_original_name' => $attachmentOriginalName,
                    'attachment_size' => $attachmentSize,
                ]);

                if ($messageType === 'text') {
                    ChatMessageQueue::create([
                        'group_id' => $group->id,
                        'message_id' => $createdMessage->id,
                        'status' => 'queued',
                        'queued_at' => now(),
                    ]);
                }

                AuditLog::create([
                    'group_id' => $group->id,
                    'actor_id' => Auth::id(),
                    'action' => 'chat.send_message',
                    'target_type' => Message::class,
                    'target_id' => $createdMessage->id,
                    'created_at' => now(),
                ]);

                return $createdMessage;
            });
        } catch (LockTimeoutException) {
            return back()->withErrors(['content' => 'Chat sedang sibuk. Coba kirim ulang dalam beberapa detik.']);
        }

        if ($message->message_type === 'text') {
            ProcessGroupChatQueueJob::dispatch($group->id);
        }

        $payload = [
            'id' => $message->id,
            'message_type' => $message->message_type,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => Auth::user()?->name,
            'content' => $message->content,
            'attachment_url' => $message->attachment_path
                ? route('chat.attachment', ['group' => $group->id, 'message' => $message->id])
                : null,
            'attachment_mime' => $message->attachment_mime,
            'attachment_original_name' => $message->attachment_original_name,
            'created_at' => optional($message->created_at)->toIso8601String(),
        ];

        event(new MessageSent($group->id, $payload));

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $payload,
            ], 201);
        }

        return redirect()->route('chat.show', $group);
    }

    public function attachment(Group $group, Message $message): StreamedResponse
    {
        $this->authorize('chat', $group);

        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_if(! $message->attachment_path || ! $message->attachment_disk, 404);

        return Storage::disk($message->attachment_disk)->response(
            $message->attachment_path,
            $message->attachment_original_name ?? basename($message->attachment_path),
            [
                'Content-Type' => $message->attachment_mime ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ]
        );
    }

    private function resolveMessageType(?UploadedFile $attachment): string
    {
        if (! $attachment instanceof UploadedFile) {
            return 'text';
        }

        $mime = strtolower((string) $attachment->getMimeType());
        $clientMime = strtolower((string) $attachment->getClientMimeType());
        $extension = strtolower((string) $attachment->getClientOriginalExtension());
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mime, 'audio/') || str_starts_with($clientMime, 'audio/')) {
            return 'voice';
        }

        // Some desktop/mobile browsers return video/webm for microphone recordings.
        if ($extension === 'webm' && (str_starts_with($mime, 'video/') || str_starts_with($clientMime, 'video/'))) {
            return 'voice';
        }

        if (in_array($extension, ['mp3', 'wav', 'ogg', 'aac', 'm4a'], true)) {
            return 'voice';
        }

        return 'file';
    }

    private function normalizeAttachmentMime(string $messageType, string $clientMime, string $extension): string
    {
        if ($messageType === 'voice') {
            if ($clientMime !== '' && str_starts_with($clientMime, 'audio/')) {
                return $clientMime;
            }

            return match ($extension) {
                'mp3' => 'audio/mpeg',
                'wav' => 'audio/wav',
                'ogg' => 'audio/ogg',
                'aac' => 'audio/aac',
                'm4a' => 'audio/m4a',
                default => 'audio/webm',
            };
        }

        return $clientMime !== '' ? $clientMime : 'application/octet-stream';
    }

    private function buildMentionSuggestions(Group $group, ?string $activeProvider): array
    {
        $users = collect([$group->owner])
            ->merge($group->members->pluck('user'))
            ->filter()
            ->unique('id')
            ->map(fn ($user) => [
                'type' => 'user',
                'label' => (string) $user->name,
                'insert' => '@'.strtolower(str_replace(' ', '_', (string) $user->name)),
            ])
            ->values();

        $providerKey = strtolower((string) $activeProvider);
        $aiMentions = match ($providerKey) {
            'openai' => [
                ['type' => 'ai', 'label' => 'AI (OpenAI)', 'insert' => '@ai'],
                ['type' => 'ai', 'label' => 'ChatGPT', 'insert' => '@chatgpt'],
            ],
            'claude' => [
                ['type' => 'ai', 'label' => 'AI (Claude)', 'insert' => '@ai'],
                ['type' => 'ai', 'label' => 'Claude', 'insert' => '@claude'],
            ],
            'gemini' => [
                ['type' => 'ai', 'label' => 'AI (Gemini)', 'insert' => '@ai'],
                ['type' => 'ai', 'label' => 'Gemini', 'insert' => '@gemini'],
            ],
            default => [
                ['type' => 'ai', 'label' => 'AI', 'insert' => '@ai'],
            ],
        };

        return collect($aiMentions)->merge($users)->values()->all();
    }
}
