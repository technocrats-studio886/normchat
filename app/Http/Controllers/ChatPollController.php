<?php

namespace App\Http\Controllers;

use App\Events\PollVoted;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Message;
use App\Services\Chat\PollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatPollController extends Controller
{
    private const MAX_POLLS_PER_REQUEST = 60;

    public function __construct(private readonly PollService $polls)
    {
    }

    public function stats(Request $request, Group $group): JsonResponse
    {
        $this->authorize('chat', $group);

        $pollIds = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(self::MAX_POLLS_PER_REQUEST)
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'polls' => $pollIds === [] ? [] : $this->polls->statsFor($group, $pollIds, (int) $request->user()->id),
        ]);
    }

    public function vote(Request $request, Group $group, Message $message): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);

        $optionNumber = (int) $request->validate(['option_number' => ['required', 'integer', 'min:1', 'max:20']])['option_number'];

        $poll = $this->polls->parse($message->content);
        if (! $poll) {
            return response()->json(['ok' => false, 'message' => 'poll_not_found'], 422);
        }
        if (! $this->polls->hasOption($poll, $optionNumber)) {
            return response()->json(['ok' => false, 'message' => 'poll_option_invalid'], 422);
        }

        $userId = (int) $request->user()->id;
        $this->polls->vote($group, $message, $userId, $optionNumber);
        AuditLog::record('chat.vote_poll', $userId, $message, ['option_number' => $optionNumber], $group->id);

        $stats = $this->polls->statsFor($group, [(int) $message->id], $userId)[(string) $message->id] ?? null;
        if ($stats !== null) {
            event(new PollVoted((int) $group->id, $stats));
        }

        return response()->json(['ok' => true, 'poll' => $stats]);
    }
}
