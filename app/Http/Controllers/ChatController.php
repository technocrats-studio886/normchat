<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\Message;
use App\Jobs\ProcessGroupChatQueueJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class ChatController extends Controller
{
    public function show(Group $group): View
    {
        $this->authorize('chat', $group);

        $group->load(['members.user', 'aiConnections']);

        $messages = Message::query()
            ->where('group_id', $group->id)
            ->with('sender:id,name')
            ->orderByDesc('id')
            ->take(80)
            ->get()
            ->reverse()
            ->values();

        $activeGroupAi = $group->aiConnections->first();
        $ownerProvider = $activeGroupAi ? ucfirst((string) $activeGroupAi->provider) : null;

        return view('chat.show', [
            'group' => $group,
            'messages' => $messages,
            'ownerProvider' => $ownerProvider,
            'activeAi' => $ownerProvider ? collect([$ownerProvider]) : collect(),
            'activeGroupAi' => $activeGroupAi,
        ]);
    }

    public function store(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('chat', $group);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
        ]);

        $content = trim($validated['content']);

        try {
            $message = Cache::lock('group-chat-submit:'.$group->id, 10)->block(3, function () use ($group, $content) {
                $createdMessage = Message::create([
                    'group_id' => $group->id,
                    'sender_type' => 'user',
                    'sender_id' => Auth::id(),
                    'content' => $content,
                ]);

                ChatMessageQueue::create([
                    'group_id' => $group->id,
                    'message_id' => $createdMessage->id,
                    'status' => 'queued',
                    'queued_at' => now(),
                ]);

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

        ProcessGroupChatQueueJob::dispatch($group->id);

        event(new MessageSent($group->id, [
            'id' => $message->id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
            'sender_name' => Auth::user()?->name,
            'content' => $message->content,
            'created_at' => optional($message->created_at)->toIso8601String(),
        ]));

        return redirect()->route('chat.show', $group);
    }
}
