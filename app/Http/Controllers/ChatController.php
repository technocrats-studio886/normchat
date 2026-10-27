<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Http\Requests\SendMessageRequest;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\MessageVersion;
use App\Models\PollVote;
use App\Services\Ai\Responders\CanvasResponder;
use App\Services\Chat\AttachmentStorage;
use App\Services\Chat\MentionNotifier;
use App\Services\Chat\MessagePresenter;
use App\Services\Credits\ModelAccess;
use App\Services\Files\StoredFiles;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    private const VISIBLE_MESSAGES = 80;

    public function __construct(
        private readonly MessagePresenter $presenter,
        private readonly MentionNotifier $mentions,
    ) {
    }

    /** Buka chat terakhir yang dikunjungi, atau grup yang paling baru aktif. */
    public function openLast(Request $request): RedirectResponse
    {
        $groups = Group::query()->visibleTo($request->user());
        $group = (clone $groups)->find((int) session('last_chat_group_id', 0))
            ?? $groups->latest('updated_at')->first();

        return $group ? redirect()->route('chat.show', $group) : redirect()->route('groups.index');
    }

    public function show(Request $request, Group $group, ModelAccess $access): View
    {
        $this->authorize('chat', $group);
        session(['last_chat_group_id' => $group->id]);

        $group->load(['owner', 'members.user', 'members.role', 'groupToken']);

        $messages = Message::query()
            ->where('group_id', $group->id)
            ->with(['sender:id,name', 'replyToMessage.sender:id,name'])
            ->withCount('versions')
            ->orderByDesc('id')
            ->take(self::VISIBLE_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        $lastReadMessageId = (int) ($this->membership($group, $request)?->last_read_message_id ?? 0);
        $latestMessageId = (int) Message::where('group_id', $group->id)->max('id');
        $aiModels = AiModel::active()->get(['model_id', 'label', 'description', 'unlock_multiple', 'lock_multiple']);
        $token = GroupToken::forGroup((int) $group->id);

        return view('chat.show', [
            'group' => $group,
            'messages' => $messages,
            'mentionSuggestions' => $this->mentions->suggestionsFor($group, $request->user()),
            'lastReadMessageId' => $lastReadMessageId,
            'latestMessageId' => $latestMessageId,
            'unreadCount' => $latestMessageId > 0 ? $this->unreadCount($group, $lastReadMessageId) : 0,
            'aiModels' => $aiModels,
            // Kategori yang dikunci saldo grup: status sekarang dan ambang buka/tutupnya.
            'modelGates' => $aiModels->filter->isGated()->mapWithKeys(fn (AiModel $aiModel) => [$aiModel->model_id => [
                'locked' => ! $access->isUnlocked($token, $aiModel),
                'open_at' => $access->openAt($aiModel),
                'open_at_label' => $access->format($access->openAt($aiModel)),
                'floor' => $access->floor($aiModel),
            ]]),
            'defaultAiModel' => $group->resolvedAiModel()?->model_id,
        ]);
    }

    public function markRead(Request $request, Group $group): JsonResponse
    {
        $this->authorize('chat', $group);
        $requestedId = (int) ($request->validate(['last_read_message_id' => ['nullable', 'integer']])['last_read_message_id'] ?? 0);

        $member = $this->membership($group, $request);
        if (! $member) {
            return response()->json(['ok' => false, 'message' => 'membership_not_found'], 404);
        }

        $latestMessageId = (int) Message::where('group_id', $group->id)->max('id');
        if ($latestMessageId <= 0) {
            return response()->json(['ok' => true, 'last_read_message_id' => (int) $member->last_read_message_id, 'unread_count' => 0]);
        }

        $targetReadId = $requestedId > 0 ? min($requestedId, $latestMessageId) : $latestMessageId;
        if ($targetReadId > (int) $member->last_read_message_id) {
            $member->update(['last_read_message_id' => $targetReadId, 'last_read_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'last_read_message_id' => (int) $member->last_read_message_id,
            'unread_count' => $this->unreadCount($group, (int) $member->last_read_message_id),
        ]);
    }

    public function store(SendMessageRequest $request, Group $group, AttachmentStorage $attachments): RedirectResponse|JsonResponse
    {
        $this->authorize('chat', $group);
        $user = $request->user();
        $content = $request->content();

        try {
            // Dikunci per grup agar urutan pesan dan antrean AI tetap berurutan.
            $message = Cache::lock('group-chat-submit:' . $group->id, 10)->block(3, function () use ($request, $group, $user, $content, $attachments) {
                $replyTarget = Message::where('group_id', $group->id)->find((int) $request->input('reply_to_message_id'));
                $quote = $request->replyQuoteText();

                $message = Message::create([
                    'group_id' => $group->id,
                    'message_type' => 'text',
                    'sender_type' => 'user',
                    'sender_id' => $user->id,
                    'reply_to_message_id' => $replyTarget?->id,
                    'reply_quote_text' => $replyTarget && $quote !== '' ? $quote : null,
                    'content' => $content !== '' ? $content : null,
                    ...($request->hasFile('attachment') ? $attachments->store($request->file('attachment'), $group) : []),
                ]);

                if (Message::isAiPrompt($content, $replyTarget)) {
                    // Pilihan composer hanya berarti bagi pesan yang memang ditujukan ke AI.
                    $message->update(['ai_model' => $request->aiModel(), 'ai_mode' => $request->aiMode()]);
                    ChatMessageQueue::enqueue($message);
                }

                AuditLog::record('chat.send_message', $user->id, $message, [], $group->id);

                return $message;
            });
        } catch (LockTimeoutException) {
            return back()->withErrors(['content' => 'Gagal mengirim. Coba lagi.']);
        }

        $message->loadCount('versions');
        if ($message->isAddressedToAi()) {
            ProcessGroupChatQueueJob::dispatch($group->id);
        }

        $payload = $this->presenter->present($message, $user->name, ['group_credits_remaining' => $group->groupToken?->credits ?? 0]);
        event(new MessageSent($group->id, $payload));

        if ($content !== '') {
            $this->mentions->notify($group, $message, $user, $content);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $payload], 201)
            : redirect()->route('chat.show', $group);
    }

    public function update(Request $request, Group $group, Message $message): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);

        $user = $request->user();
        abort_unless($message->sender_type === 'user' && (int) $message->sender_id === (int) $user->id, 403);

        $nextContent = trim((string) $request->validate(['content' => ['required', 'string', 'max:3000']])['content']);
        if ($nextContent === '') {
            return response()->json(['ok' => false, 'message' => 'content_required'], 422);
        }

        $previousContent = (string) ($message->content ?? '');
        if ($previousContent !== $nextContent) {
            MessageVersion::create([
                'message_id' => $message->id,
                'version_number' => max(((int) $message->versions()->max('version_number')) + 1, 1),
                'content_snapshot' => $previousContent,
                'edited_by' => $user->id,
                'edited_at' => now(),
            ]);
            $message->update(['content' => $nextContent]);

            // Hanya mention yang baru ditambahkan yang diberi notifikasi.
            $newHandles = array_values(array_diff($this->mentions->handlesIn($nextContent), $this->mentions->handlesIn($previousContent)));
            $this->mentions->notify($group, $message, $user, $nextContent, $newHandles);

            if ($message->isAddressedToAi()) {
                ChatMessageQueue::enqueue($message);
                ProcessGroupChatQueueJob::dispatch($group->id);
            }

            AuditLog::record('chat.edit_message', $user->id, $message, [
                'old_length' => mb_strlen($previousContent),
                'new_length' => mb_strlen($nextContent),
            ], $group->id);
        }

        $message->loadCount('versions');
        $payload = $this->presenter->present($message, $user->name, ['group_credits_remaining' => $group->groupToken?->credits ?? 0]);
        event(new MessageUpdated((int) $group->id, $payload));

        return response()->json(['ok' => true, 'message' => $payload]);
    }

    public function destroy(Request $request, Group $group, Message $message): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);

        $user = $request->user();
        abort_unless($group->isModerator($user), 403);

        PollVote::where('poll_message_id', $message->id)->delete();
        $message->delete();

        event(new MessageDeleted((int) $group->id, (int) $message->id, (int) $user->id, (string) $user->name));
        AuditLog::record('chat.delete_message', $user->id, $message, [
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'message_type' => $message->message_type,
        ], $group->id);

        return response()->json(['ok' => true, 'message_id' => (int) $message->id]);
    }

    /**
     * Halaman web canvas untuk iframe pratinjau. Dokumen srcdoc mewarisi CSP normi.chat (skrip
     * canvas akan diblokir), jadi canvas disajikan sendiri: sandbox tanpa allow-same-origin membuat
     * skripnya berjalan di origin terpisah yang tidak bisa menyentuh sesi atau halaman normi.chat.
     */
    public function canvas(Group $group, Message $message, StoredFiles $files): Response
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_unless($message->message_type === CanvasResponder::MESSAGE_TYPE
            && $message->attachment_mime === CanvasResponder::MIME_WEB
            && $message->attachment_path && $message->attachment_disk, 404);

        $html = $files->get($message->attachment_disk, $message->attachment_path);
        abort_unless(is_string($html), 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => 'sandbox allow-scripts allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-downloads; '
                . "default-src * data: blob: 'unsafe-inline' 'unsafe-eval'; frame-ancestors 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function attachment(Group $group, Message $message, StoredFiles $files): StreamedResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_if(! $message->attachment_path || ! $message->attachment_disk, 404);

        // File kecil dari cache Redis, file besar langsung dari S3.
        $size = $files->size($message->attachment_disk, $message->attachment_path);
        $stream = $size === null ? null : $files->stream($message->attachment_disk, $message->attachment_path);
        abort_unless(is_resource($stream), 404);

        $mime = $message->attachment_mime ?: 'application/octet-stream';
        $filename = str_replace(['/', '\\'], '_', (string) ($message->attachment_original_name ?: basename($message->attachment_path)));
        $inline = preg_match('#^(image|audio|video)/#', $mime) === 1
            || in_array($message->message_type, ['image', 'voice', 'video'], true)
            || $mime === 'application/pdf';

        $headers = [
            'Content-Type' => $mime,
            // Nama berkas dari user: di-escape & diberi versi ASCII agar tidak bisa merusak header.
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $filename,
                (string) preg_replace('/[^\x20-\x7E]|[%"\\\\\/]/', '_', Str::ascii($filename)) ?: 'lampiran'
            ),
            'Content-Length' => (string) $size,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];
        // Lampiran tidak boleh berjalan sebagai halaman di asal normi.chat. PDF dikecualikan:
        // viewer PDF Chrome menolak dokumen ber-sandbox, dan PDF tidak menjalankan skrip halaman.
        if ($mime !== 'application/pdf') {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    private function membership(Group $group, Request $request): ?GroupMember
    {
        return GroupMember::query()->active()->where('group_id', $group->id)->where('user_id', $request->user()->id)->first();
    }

    private function unreadCount(Group $group, int $lastReadMessageId): int
    {
        return (int) Message::query()
            ->where('group_id', $group->id)
            ->when($lastReadMessageId > 0, fn ($query) => $query->where('id', '>', $lastReadMessageId))
            ->count();
    }
}
