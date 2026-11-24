<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatRequestStatusController extends Controller
{
    public function index(Request $request, Group $group): JsonResponse
    {
        $this->authorize('chat', $group);
        $ids = $request->validate(['ids' => 'required|array|min:1|max:80', 'ids.*' => 'integer|min:1'])['ids'];
        $messages = $this->ownMessages($group, $request->user())->whereKey($ids)->select('id');
        $items = ChatMessageQueue::whereIn('message_id', $messages)->orderByDesc('id')->get()->unique('message_id');

        return response()->json(['requests' => $items->map(fn ($item) => $this->state($item, $group))->values()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function retry(Request $request, Group $group, int $message): JsonResponse
    {
        $this->authorize('chat', $group);
        $data = $request->validate(['attempt' => 'required|integer|min:1', 'queue_id' => 'required|integer|min:1']);
        $result = DB::transaction(function () use ($request, $group, $message, $data) {
            $source = $this->ownMessages($group, $request->user())->whereKey($message)->lockForUpdate()->firstOrFail();
            $item = ChatMessageQueue::where('message_id', $message)->latest('id')->lockForUpdate()->firstOrFail();
            $state = $this->state($item, $group);
            if (! $state['can_retry'] || $item->id !== (int) $data['queue_id'] || $item->attempt !== (int) $data['attempt']) {
                return $state;
            }
            abort_unless($source->isAddressedToAi(), 409, 'Pesan ini sudah diubah.');
            $item->update([
                'status' => 'queued', 'attempt' => $item->attempt + 1, 'activity' => null, 'retry_allowed' => false,
                'queued_at' => now(), 'processed_at' => null, 'error_message' => null,
            ]);
            ProcessGroupChatQueueJob::dispatch($group->id)->afterCommit();

            return $this->state($item, $group);
        });

        return response()->json(['request' => $result]);
    }

    private function ownMessages(Group $group, User $user): Builder
    {
        return Message::where('group_id', $group->id)->where('sender_type', 'user')->where('sender_id', $user->id)
            ->visibleTo($user)->whereNotIn('id', MessageHide::where('user_id', $user->id)->select('message_id'));
    }

    private function state(ChatMessageQueue $item, Group $group): array
    {
        return ['message_id' => $item->message_id, 'queue_id' => $item->id] + $item->publicState($group->status === 'active');
    }
}
