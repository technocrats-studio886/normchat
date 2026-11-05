<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Http\Requests\SendMessageRequest;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiModel;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupDraft;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\User;
use App\Services\Chat\MessageSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Services\Groups\GroupInfo;
use App\Services\Links\LinkPreviews;
use App\Services\Ai\Responders\CanvasResponder;
use App\Services\Chat\AttachmentStorage;
use App\Services\Chat\MentionNotifier;
use App\Services\Chat\MessageActions;
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
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    private const VISIBLE_MESSAGES = 80;

    /** Pesan sebelum dan sesudah pesan target pada potongan "around". */
    private const AROUND_SPAN = 30;

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

    public function show(Request $request, Group $group, ModelAccess $access, GroupInfo $info): View
    {
        $this->authorize('chat', $group);
        session(['last_chat_group_id' => $group->id]);

        $group->load(['owner', 'members.user', 'members.role', 'groupToken']);

        $viewer = $request->user();
        $messages = $this->recentMessages($group, $viewer);
        // Pertanyaan yang masih menunggu jawaban Normi tidak bisa diedit dulu.
        $awaitingAi = ChatMessageQueue::whereIn('message_id', $messages->pluck('id'))->whereIn('status', ['queued', 'processing'])->pluck('message_id')->flip();

        $lastReadMessageId = (int) ($this->membership($group, $request)?->last_read_message_id ?? 0);
        $latestMessageId = (int) Message::where('group_id', $group->id)->max('id');
        $aiModels = AiModel::active()->get(['model_id', 'label', 'description', 'unlock_multiple', 'lock_multiple']);
        $token = GroupToken::forGroup((int) $group->id);

        return view('chat.show', [
            'group' => $group,
            // Info grup desktop (jendela melayang dan panel kanan), isinya sama dengan halaman Info grup HP.
            'info' => $info->for($group, $viewer),
            'draft' => GroupDraft::unexpired()->where(['group_id' => $group->id, 'user_id' => $viewer->id])->first(),
            'historyFromMessageId' => $historyFrom = (int) ($group->members->firstWhere('user_id', $viewer->id)?->history_from_message_id ?? 0),
            // Kartu "grup dibuat" di paling atas bila feed sudah sampai awal yang boleh dilihat user.
            'showCreatedCard' => $messages->count() < self::VISIBLE_MESSAGES || $historyFrom > 0,
            // Dirender di browser oleh resources/js/chat/messages.js, sama dengan pesan realtime.
            'messages' => $messages->map(fn (Message $message) => $this->presenter->present($message, null, ['awaiting_ai' => $awaitingAi->has($message->id)]))->all(),
            // Tujuan "Bagikan": grup lain tempat user bisa mengirim pesan.
            'forwardTargets' => Group::query()->visibleTo($viewer)->where('status', 'active')->whereKeyNot($group->id)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Group $target) => ['id' => $target->id, 'name' => $target->name])->all(),
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
            'groupCredits' => (float) ($token?->credits ?? 0),
            'defaultAiModel' => $group->resolvedAiModel()?->model_id,
        ]);
    }

    /** Pesan yang tampil di feed user ini: yang boleh dilihat, tidak disembunyikan sendiri, termasuk yang sudah dihapus. */
    private function feedQuery(Group $group, User $viewer): Builder
    {
        return Message::withTrashed()
            ->where('group_id', $group->id)
            ->visibleTo($viewer)
            ->whereNotIn('id', MessageHide::where('user_id', $viewer->id)->select('message_id'))
            ->with(['sender:id,name,avatar_url', 'replyToMessage.sender:id,name'])
            ->withCount('versions');
    }

    /** Pesan terakhir yang dimuat saat chat dibuka, urut lama ke baru. */
    private function recentMessages(Group $group, User $viewer): Collection
    {
        return $this->feedQuery($group, $viewer)
            ->orderByDesc('id')
            ->take(self::VISIBLE_MESSAGES)
            ->get()
            ->reverse()
            ->values();
    }

    /** Muat ulang pesan terakhir (tombol "Ke pesan terbaru" setelah melompat ke pesan lama). */
    public function recent(Request $request, Group $group): JsonResponse
    {
        $this->authorize('chat', $group);
        $viewer = $request->user();
        $messages = $this->recentMessages($group, $viewer);
        $awaitingAi = ChatMessageQueue::whereIn('message_id', $messages->pluck('id'))->whereIn('status', ['queued', 'processing'])->pluck('message_id')->flip();
        $historyFrom = (int) ($this->membership($group, $request)?->history_from_message_id ?? 0);

        return response()->json([
            'show_created_card' => $messages->count() < self::VISIBLE_MESSAGES || $historyFrom > 0,
            'messages' => $messages->map(fn (Message $message) => $this->presenter->present($message, null, ['awaiting_ai' => $awaitingAi->has($message->id)]))->all(),
        ]);
    }

    /** Cari pesan di seluruh riwayat grup yang boleh dilihat user (docs/rancangan-cari-pesan.md). */
    public function search(Request $request, Group $group, MessageSearch $search): JsonResponse
    {
        $this->authorize('chat', $group);
        $data = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'before_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json($search->run($group, $request->user(), (string) $data['q'], (int) ($data['before_id'] ?? 0)));
    }

    /** Potongan feed di sekitar satu pesan lama, untuk pesan yang tidak ada di pesan awal yang dimuat. */
    public function around(Request $request, Group $group, int $message): JsonResponse
    {
        $this->authorize('chat', $group);
        $viewer = $request->user();

        $target = $this->feedQuery($group, $viewer)->find($message);
        abort_if(! $target, 404);

        $older = $this->feedQuery($group, $viewer)->where('id', '<', $target->id)->orderByDesc('id')->limit(self::AROUND_SPAN + 1)->get();
        $newer = $this->feedQuery($group, $viewer)->where('id', '>', $target->id)->orderBy('id')->limit(self::AROUND_SPAN + 1)->get();
        $hasOlder = $older->count() > self::AROUND_SPAN;
        $hasNewer = $newer->count() > self::AROUND_SPAN;

        $chunk = $older->take(self::AROUND_SPAN)->reverse()->push($target)->merge($newer->take(self::AROUND_SPAN))->values();
        $awaitingAi = ChatMessageQueue::whereIn('message_id', $chunk->pluck('id'))->whereIn('status', ['queued', 'processing'])->pluck('message_id')->flip();

        return response()->json([
            'target_id' => $target->id,
            'has_older' => $hasOlder,
            'has_newer' => $hasNewer,
            'messages' => $chunk->map(fn (Message $item) => $this->presenter->present($item, null, ['awaiting_ai' => $awaitingAi->has($item->id)]))->all(),
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
        $this->ensureNotFrozen($group);
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
                // Pesan terkirim: draf grup ini selesai di semua perangkat.
                GroupDraft::where(['group_id' => $group->id, 'user_id' => $user->id])->delete();

                return $message;
            });
        } catch (LockTimeoutException) {
            return back()->withErrors(['content' => 'Gagal mengirim. Coba lagi.']);
        }

        $message->loadCount('versions');
        if ($message->isAddressedToAi()) {
            ProcessGroupChatQueueJob::dispatch($group->id);
        }

        $payload = $this->presenter->present($message, $user->name, [
            'group_credits_remaining' => $group->groupToken?->credits ?? 0,
            'awaiting_ai' => $message->isAddressedToAi(),
        ]);
        event(new MessageSent($group->id, $payload));
        // Pratinjau tautan dibaca di antrean; kartunya menyusul lewat event link.preview.ready.
        app(LinkPreviews::class)->forNewMessage($message);

        if ($content !== '') {
            $this->mentions->notify($group, $message, $user, $content);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $payload], 201)
            : redirect()->route('chat.show', $group);
    }

    /** Edit pesan sendiri. regenerate=1: pertanyaan untuk Normi dijawab ulang (memakai Normkredit). */
    public function update(Request $request, Group $group, Message $message, MessageActions $actions): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureNotFrozen($group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_if($message->isSystem(), 403);
        abort_unless($actions->canEdit($request->user(), $message), 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'regenerate' => ['sometimes', 'boolean'],
            'ai_model' => ['nullable', 'string', 'max:80'],
        ]);
        $content = trim($validated['content']);
        if ($content === '') {
            return response()->json(['ok' => false, 'message' => 'Pesan tidak boleh kosong.'], 422);
        }

        try {
            $message = $actions->edit($request->user(), $group, $message, $content, (bool) ($validated['regenerate'] ?? false), $validated['ai_model'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        $payload = $this->presenter->present($message, $request->user()->name, [
            'group_credits_remaining' => $group->groupToken?->credits ?? 0,
            'awaiting_ai' => $actions->isAwaitingAi($message),
        ]);
        event(new MessageUpdated((int) $group->id, $payload));
        app(LinkPreviews::class)->forNewMessage($message);

        return response()->json(['ok' => true, 'message' => $payload]);
    }

    /** Semua versi pesan beserta jawaban Normi di tiap versi (popup "Version"). */
    public function versions(Group $group, Message $message, MessageActions $actions): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);

        return response()->json(['ok' => true, 'versions' => $actions->versions($message)]);
    }

    /** Hapus untuk semua: diganti jejak "Pesan ini dihapus"; isinya tetap tersimpan untuk ekspor. */
    public function destroy(Request $request, Group $group, Message $message, MessageActions $actions): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_if($message->isSystem(), 403);
        abort_unless($actions->canDeleteForAll($request->user(), $group, $message), 403);
        $this->ensureNotFrozen($group);

        $actions->deleteForAll($request->user(), $group, $message);

        return response()->json(['ok' => true, 'message' => $this->presenter->present($message)]);
    }

    /** Hapus untuk saya: pesan disembunyikan dari tampilan user ini saja, di semua perangkatnya. all=1: semua pesan. */
    public function hide(Request $request, Group $group, MessageActions $actions): JsonResponse
    {
        $this->authorize('chat', $group);
        $validated = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'message_ids' => ['required_without:all', 'array', 'max:200'],
            'message_ids.*' => ['integer'],
        ]);
        $ids = ($validated['all'] ?? false) ? null : array_map('intval', $validated['message_ids']);

        return response()->json(['ok' => true, 'hidden' => $actions->hideFor($request->user(), $group, $ids)]);
    }

    /** Teruskan pesan ke grup lain milik user. */
    public function forward(Request $request, Group $group, Message $message, MessageActions $actions): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_if($message->isNotice() || $message->isSystem(), 403);

        $ids = $request->validate([
            'group_ids' => ['required', 'array', 'min:1', 'max:20'],
            'group_ids.*' => ['integer'],
        ])['group_ids'];
        $targets = Group::query()->visibleTo($request->user())->where('status', 'active')->whereKey($ids)->whereKeyNot($group->id)->get();
        abort_if($targets->isEmpty(), 422);

        return response()->json(['ok' => true, 'forwarded' => $actions->forward($request->user(), $message, $targets)]);
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
        // Lampiran sebelum anggota baru bergabung tertutup sampai pemilik mengirim riwayat pesan.
        abort_unless(Message::withTrashed()->whereKey($message->id)->visibleTo(request()->user())->exists(), 404);

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

    private function ensureNotFrozen(Group $group): void
    {
        abort_if($group->isFrozen(), 423, 'Grup ini dibekukan oleh admin Normi Chat.');
    }

    private function unreadCount(Group $group, int $lastReadMessageId): int
    {
        $viewer = request()->user();

        // Sama dengan lencana daftar grup: pesan orang lain saja, tanpa pemberitahuan sistem.
        return (int) Message::query()
            ->where('group_id', $group->id)
            ->visibleTo($viewer)
            ->where('sender_type', '!=', Message::SENDER_SYSTEM)
            ->where(fn ($query) => $query->whereNull('sender_id')->orWhere('sender_id', '!=', $viewer->id))
            ->when($lastReadMessageId > 0, fn ($query) => $query->where('id', '>', $lastReadMessageId))
            ->count();
    }
}
