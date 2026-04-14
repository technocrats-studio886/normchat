<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use App\Events\UserMentioned;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\MessageVersion;
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
            ->with(['sender:id,name', 'replyToMessage.sender:id,name'])
            ->withCount('versions')
            ->orderByDesc('id')
            ->take(80)
            ->get()
            ->reverse()
            ->values();

        $latestMessageId = (int) Message::query()
            ->where('group_id', $group->id)
            ->max('id');

        $viewerMember = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        $lastReadMessageId = (int) ($viewerMember?->last_read_message_id ?? 0);

        $unreadCount = 0;
        if ($latestMessageId > 0) {
            $unreadCount = (int) Message::query()
                ->where('group_id', $group->id)
                ->when($lastReadMessageId > 0, fn ($query) => $query->where('id', '>', $lastReadMessageId))
                ->count();
        }

        $mentionSuggestions = $this->buildMentionSuggestions($group);

        return view('chat.show', [
            'group' => $group,
            'messages' => $messages,
            'mentionSuggestions' => $mentionSuggestions,
            'lastReadMessageId' => $lastReadMessageId,
            'latestMessageId' => $latestMessageId,
            'unreadCount' => $unreadCount,
        ]);
    }

    public function markRead(Request $request, Group $group): JsonResponse
    {
        $this->authorize('chat', $group);

        $validated = $request->validate([
            'last_read_message_id' => ['nullable', 'integer'],
        ]);

        $member = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', Auth::id())
            ->where('status', 'active')
            ->first();

        if (! $member) {
            return response()->json([
                'ok' => false,
                'message' => 'membership_not_found',
            ], 404);
        }

        $latestMessageId = (int) Message::query()
            ->where('group_id', $group->id)
            ->max('id');

        if ($latestMessageId <= 0) {
            return response()->json([
                'ok' => true,
                'last_read_message_id' => (int) ($member->last_read_message_id ?? 0),
                'unread_count' => 0,
            ]);
        }

        $requestedId = (int) ($validated['last_read_message_id'] ?? 0);
        $targetReadId = $requestedId > 0
            ? min($requestedId, $latestMessageId)
            : $latestMessageId;

        $currentReadId = (int) ($member->last_read_message_id ?? 0);
        if ($targetReadId > $currentReadId) {
            $member->last_read_message_id = $targetReadId;
            $member->last_read_at = now();
            $member->save();
        }

        return response()->json([
            'ok' => true,
            'last_read_message_id' => (int) ($member->last_read_message_id ?? 0),
            'unread_count' => (int) Message::query()
                ->where('group_id', $group->id)
                ->where('id', '>', (int) ($member->last_read_message_id ?? 0))
                ->count(),
        ]);
    }

    public function store(Request $request, Group $group): RedirectResponse|JsonResponse
    {
        $this->authorize('chat', $group);

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:3000'],
            'attachment' => ['nullable', 'file', 'max:15360', 'mimes:jpg,jpeg,png,webp,gif,heic,heif,mp3,wav,ogg,webm,mp4,aac,m4a,pdf,txt,csv,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z'],
            'reply_to_message_id' => ['nullable', 'integer'],
        ]);

        $content = trim((string) ($validated['content'] ?? ''));
        $attachment = $request->file('attachment');
        $replyToMessageId = (int) ($validated['reply_to_message_id'] ?? 0);

        if ($content === '' && ! $attachment instanceof UploadedFile) {
            return back()->withErrors(['content' => 'Kirim teks, gambar, atau voice note.'])->withInput();
        }

        try {
            $message = Cache::lock('group-chat-submit:'.$group->id, 10)->block(3, function () use ($group, $content, $attachment, $replyToMessageId) {
                $messageType = $this->resolveMessageType($attachment);

                $attachmentPath = null;
                $attachmentDisk = null;
                $attachmentMime = null;
                $attachmentSize = null;
                $attachmentOriginalName = null;
                $replyTarget = null;

                if ($replyToMessageId > 0) {
                    $replyTarget = Message::query()
                        ->where('id', $replyToMessageId)
                        ->where('group_id', $group->id)
                        ->first();
                }

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

                    $targetDirectory = dirname($attachmentPath);
                    if ($targetDirectory !== '.') {
                        Storage::disk($attachmentDisk)->makeDirectory($targetDirectory);
                    }

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
                    'reply_to_message_id' => $replyTarget?->id,
                    'content' => $content !== '' ? $content : null,
                    'attachment_disk' => $attachmentDisk,
                    'attachment_path' => $attachmentPath,
                    'attachment_mime' => $attachmentMime,
                    'attachment_original_name' => $attachmentOriginalName,
                    'attachment_size' => $attachmentSize,
                ]);

                if ($this->shouldQueueAi($content, $replyTarget)) {
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

        $message->loadMissing('replyToMessage.sender:id,name')->loadCount('versions');

        if ($this->shouldQueueAi($content, $message->replyToMessage)) {
            ProcessGroupChatQueueJob::dispatch($group->id);
        }

        $payload = $this->buildMessagePayload($message, $group, Auth::user()?->name);

        event(new MessageSent($group->id, $payload));

        if ($content !== '') {
            $this->dispatchMentionNotifications($group, $message, $content);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $payload,
            ], 201);
        }

        return redirect()->route('chat.show', $group);
    }

    public function update(Request $request, Group $group, Message $message): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);

        $userId = (int) Auth::id();
        $isSender = $message->sender_type === 'user' && (int) $message->sender_id === $userId;
        abort_unless($isSender, 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
        ]);

        $nextContent = trim((string) $validated['content']);
        if ($nextContent === '') {
            return response()->json([
                'ok' => false,
                'message' => 'content_required',
            ], 422);
        }

        $previousContent = (string) ($message->content ?? '');
        if ($previousContent !== $nextContent) {
            $nextVersion = ((int) $message->versions()->max('version_number')) + 1;

            MessageVersion::create([
                'message_id' => $message->id,
                'version_number' => max($nextVersion, 1),
                'content_snapshot' => $previousContent,
                'edited_by' => $userId,
                'edited_at' => now(),
            ]);

            $message->content = $nextContent;
            $message->save();

            AuditLog::create([
                'group_id' => $group->id,
                'actor_id' => $userId,
                'action' => 'chat.edit_message',
                'target_type' => Message::class,
                'target_id' => $message->id,
                'metadata_json' => [
                    'old_length' => mb_strlen($previousContent),
                    'new_length' => mb_strlen($nextContent),
                ],
                'created_at' => now(),
            ]);
        }

        $message->loadMissing('replyToMessage.sender:id,name')->loadCount('versions');
        $payload = $this->buildMessagePayload($message, $group, Auth::user()?->name);

        event(new MessageUpdated((int) $group->id, $payload));

        return response()->json([
            'ok' => true,
            'message' => $payload,
        ]);
    }

    public function destroy(Request $request, Group $group, Message $message): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);

        $userId = (int) Auth::id();
        $isSender = $message->sender_type === 'user' && (int) $message->sender_id === $userId;
        $isModerator = $this->isGroupModerator($group, $userId);
        abort_unless($isSender || $isModerator, 403);

        $messageId = (int) $message->id;
        $message->delete();

        event(new MessageDeleted(
            (int) $group->id,
            $messageId,
            $userId,
            (string) (Auth::user()?->name ?? 'User')
        ));

        AuditLog::create([
            'group_id' => $group->id,
            'actor_id' => $userId,
            'action' => 'chat.delete_message',
            'target_type' => Message::class,
            'target_id' => $message->id,
            'metadata_json' => [
                'sender_type' => $message->sender_type,
                'sender_id' => $message->sender_id,
                'message_type' => $message->message_type,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message_id' => $messageId,
        ]);
    }

    private function dispatchMentionNotifications(Group $group, Message $message, string $content): void
    {
        $mentionedHandles = $this->extractMentionHandles($content);
        if ($mentionedHandles === []) {
            return;
        }

        $group->loadMissing([
            'owner:id,name',
            'members' => fn ($query) => $query
                ->where('status', 'active')
                ->with('user:id,name'),
        ]);

        $handleToUserId = [];
        collect([$group->owner])
            ->merge($group->members->pluck('user'))
            ->filter()
            ->unique('id')
            ->each(function ($user) use (&$handleToUserId) {
                foreach ($this->buildUserMentionHandles((string) $user->name) as $handle) {
                    $handleToUserId[$handle] = (int) $user->id;
                }
            });

        $senderId = (int) $message->sender_id;
        $targetUserIds = collect($mentionedHandles)
            ->map(fn ($handle) => $handleToUserId[$handle] ?? null)
            ->filter(fn ($userId) => is_int($userId) && $userId > 0 && $userId !== $senderId)
            ->unique()
            ->values();

        if ($targetUserIds->isEmpty()) {
            return;
        }

        $senderName = (string) (Auth::user()?->name ?? 'User');
        $chatUrl = route('chat.show', $group).'#message-'.$message->id;

        foreach ($targetUserIds as $targetUserId) {
            event(new UserMentioned((int) $targetUserId, [
                'group_id' => (int) $group->id,
                'group_name' => (string) $group->name,
                'message_id' => (int) $message->id,
                'sender_id' => $senderId,
                'sender_name' => $senderName,
                'content' => mb_substr((string) $content, 0, 240),
                'chat_url' => $chatUrl,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ]));
        }
    }

    /**
     * @return array<int, string>
     */
    private function extractMentionHandles(string $content): array
    {
        if (! preg_match_all('/@([\\p{L}\\p{N}_.]+)/u', $content, $matches)) {
            return [];
        }

        return collect($matches[1] ?? [])
            ->map(fn ($handle) => strtolower(trim((string) $handle)))
            ->filter(fn ($handle) => $handle !== '' && $handle !== 'ai' && $handle !== 'openai' && $handle !== 'chatgpt')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function buildUserMentionHandles(string $name): array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return [];
        }

        $base = strtolower($trimmed);
        $base = preg_replace('/[^\\p{L}\\p{N} ]+/u', '', $base) ?? '';
        $base = trim(preg_replace('/\\s+/u', ' ', $base) ?? '');

        if ($base === '') {
            return [];
        }

        $handles = [
            str_replace(' ', '_', $base),
            str_replace(' ', '.', $base),
            str_replace(' ', '', $base),
            $base,
        ];

        return collect($handles)
            ->filter(fn ($handle) => $handle !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function attachment(Group $group, Message $message): StreamedResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('chat', $group);

        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_if(! $message->attachment_path || ! $message->attachment_disk, 404);

        $disk = Storage::disk($message->attachment_disk);
        $mime = $message->attachment_mime ?: 'application/octet-stream';
        $filename = $message->attachment_original_name ?? basename($message->attachment_path);

        return response()->stream(function () use ($disk, $message) {
            $stream = $disk->readStream($message->attachment_path);
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
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

    private function buildMentionSuggestions(Group $group): array
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

        $aiMentions = [
            ['type' => 'ai', 'label' => 'AI Assistant', 'insert' => '@ai'],
        ];

        return collect($aiMentions)->merge($users)->values()->all();
    }

    private function shouldQueueAi(string $content, ?Message $replyTarget = null): bool
    {
        $normalized = strtolower($content);
        if (str_contains($normalized, '@ai') || str_contains($normalized, '@openai') || str_contains($normalized, '@chatgpt')) {
            return true;
        }

        return $replyTarget?->sender_type === 'ai';
    }

    private function buildMessagePayload(Message $message, Group $group, ?string $senderName = null, ?GroupToken $groupToken = null): array
    {
        $groupToken ??= $group->groupToken;
        $replyTarget = $message->replyToMessage;
        $versionsCount = isset($message->versions_count)
            ? (int) $message->versions_count
            : (int) $message->versions()->count();
        $isEdited = $versionsCount > 0;

        return [
            'id' => $message->id,
            'message_type' => $message->message_type,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => $senderName ?: ($message->sender?->name ?? ($message->sender_type === 'ai' ? 'NormAI' : 'User')),
            'content' => $message->content,
            'attachment_url' => $message->attachment_path
                ? route('chat.attachment', ['group' => $group->id, 'message' => $message->id])
                : null,
            'attachment_mime' => $message->attachment_mime,
            'attachment_original_name' => $message->attachment_original_name,
            'created_at' => optional($message->created_at)->toIso8601String(),
            'is_edited' => $isEdited,
            'edited_at' => $isEdited ? optional($message->updated_at)->toIso8601String() : null,
            'reply_to' => $replyTarget ? [
                'id' => (int) $replyTarget->id,
                'sender_name' => $replyTarget->sender_type === 'ai'
                    ? 'NormAI'
                    : ($replyTarget->sender?->name ?? 'User'),
                'message_type' => (string) $replyTarget->message_type,
                'content' => (string) ($replyTarget->content ?? ''),
                'attachment_original_name' => (string) ($replyTarget->attachment_original_name ?? ''),
            ] : null,
            'group_tokens_remaining' => (int) ($groupToken?->remaining_tokens ?? 0),
            'group_credits_remaining' => round(((int) ($groupToken?->remaining_tokens ?? 0)) / 2500, 1),
        ];
    }

    private function isGroupModerator(Group $group, int $userId): bool
    {
        if ((int) $group->owner_id === $userId) {
            return true;
        }

        return GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->whereIn('key', ['owner', 'admin']))
            ->exists();
    }
}
