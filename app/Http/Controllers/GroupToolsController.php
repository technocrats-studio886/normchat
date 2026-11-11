<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AuditLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupTask;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\ScheduledMessage;
use App\Services\Chat\GroupPins;
use App\Services\Chat\GroupTasks;
use App\Services\Chat\MessageSender;
use App\Services\Chat\MessageTranslator;
use App\Services\Chat\Reminders;
use App\Services\Chat\ScheduledMessages;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Fitur produktivitas grup (docs/rancangan-fitur-produktivitas-nov.md): ringkasan pesan belum dibaca, sematan,
 * tugas, pengingat, pesan terjadwal, terjemahan, dan rekap mingguan. Semua rute memakai middleware group.permission
 * (anggota aktif atau pemilik); grup yang dibekukan hanya bisa dibaca.
 */
class GroupToolsController extends Controller
{
    /** Minimal pesan belum dibaca agar ringkasan bermakna. */
    public const CATCH_UP_MIN = 5;

    // ── Ringkasan pesan belum dibaca ──────────────────────────────

    /** "Ringkas dengan Normi" di pembatas pesan belum dibaca: pertanyaan @normi biasa dengan rentang pesannya. */
    public function catchUp(Request $request, Group $group, MessageSender $sender): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        $user = $request->user();
        $from = (int) $request->validate(['from_id' => ['required', 'integer', 'min:0']])['from_id'];
        $to = (int) Message::where('group_id', $group->id)->max('id');
        $unread = Message::query()->where('group_id', $group->id)->whereBetween('id', [$from + 1, $to])->visibleTo($user)
            ->whereNull('visible_to_user_id')->where('sender_type', '!=', Message::SENDER_SYSTEM)
            ->where(fn ($query) => $query->whereNull('sender_id')->orWhere('sender_id', '!=', $user->id))->count();
        abort_if($unread < self::CATCH_UP_MIN, 422, 'Pesan yang belum dibaca terlalu sedikit untuk diringkas.');

        $message = Cache::lock('group-chat-submit:' . $group->id, 10)->block(3, function () use ($group, $user, $from, $to, $unread, $request) {
            $message = Message::create([
                'group_id' => $group->id,
                'message_type' => 'text',
                'sender_type' => 'user',
                'sender_id' => $user->id,
                'content' => "@normi ringkas {$unread} pesan yang belum kubaca",
                'system_meta' => ['catch_up' => ['from' => $from, 'to' => $to]],
                'ai_model' => $request->input('ai_model') ?: null,
            ]);
            ChatMessageQueue::enqueue($message);
            AuditLog::record('chat.catch_up', $user->id, $message, ['unread' => $unread], $group->id);

            return $message;
        });

        return response()->json(['ok' => true, 'message' => $sender->afterCreate($group, $message, $user)], 201);
    }

    // ── Sematan ───────────────────────────────────────────────────

    public function pins(Request $request, Group $group, GroupPins $pins): JsonResponse
    {
        $this->authorize('chat', $group);

        return response()->json(['ok' => true, 'pins' => $pins->listFor($group, $request->user())]);
    }

    public function pin(Request $request, Group $group, Message $message, GroupPins $pins): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        abort_unless(Message::query()->whereKey($message->id)->visibleTo($request->user())->exists(), 404);
        $pins->pin($group, $message, $request->user());

        return response()->json(['ok' => true, 'pins' => $pins->listFor($group, $request->user())]);
    }

    public function unpin(Request $request, Group $group, int $message, GroupPins $pins): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        $pins->unpin($group, $message);

        return response()->json(['ok' => true, 'pins' => $pins->listFor($group, $request->user())]);
    }

    // ── Tugas ─────────────────────────────────────────────────────

    public function tasks(Request $request, Group $group, GroupTasks $tasks): JsonResponse
    {
        $this->authorize('chat', $group);

        return response()->json(['ok' => true] + $tasks->listFor($group, $request->user()));
    }

    public function storeTask(Request $request, Group $group, GroupTasks $tasks): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:' . GroupTask::TITLE_MAX],
            'assignee_id' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'source_message_id' => ['nullable', 'integer'],
        ]);
        $task = $tasks->create($group, $request->user(), $data);

        return response()->json(['ok' => true, 'task_id' => $task->id] + $tasks->listFor($group, $request->user()), 201);
    }

    public function updateTask(Request $request, Group $group, GroupTask $task, GroupTasks $tasks): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        abort_unless((int) $task->group_id === (int) $group->id, 404);
        $data = $request->validate([
            'done' => ['sometimes', 'boolean'],
            'title' => ['sometimes', 'string', 'max:' . GroupTask::TITLE_MAX],
            'assignee_id' => ['sometimes', 'nullable', 'integer'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ]);
        $tasks->update($group, $task, $request->user(), $data);

        return response()->json(['ok' => true] + $tasks->listFor($group, $request->user()));
    }

    public function destroyTask(Request $request, Group $group, GroupTask $task, GroupTasks $tasks): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        abort_unless((int) $task->group_id === (int) $group->id, 404);
        abort_unless($tasks->canDelete($group, $task, $request->user()), 403, 'Hanya pembuat tugas atau moderator yang bisa menghapusnya.');
        $tasks->delete($group, $task);

        return response()->json(['ok' => true] + $tasks->listFor($group, $request->user()));
    }

    // ── Pengingat ─────────────────────────────────────────────────

    public function reminders(Request $request, Group $group, Reminders $reminders): JsonResponse
    {
        $this->authorize('chat', $group);

        return response()->json(['ok' => true, 'reminders' => $reminders->listFor($group, $request->user())]);
    }

    public function storeReminder(Request $request, Group $group, Reminders $reminders): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        $data = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'remind_at' => ['required', 'date'],
            'repeat' => ['nullable', 'in:' . implode(',', Reminder::REPEATS)],
            'audience' => ['nullable', 'in:' . implode(',', Reminder::AUDIENCES)],
            'source_message_id' => ['nullable', 'integer'],
        ]);
        $source = (int) ($data['source_message_id'] ?? 0);
        $reminders->create($group, $request->user(), [
            'text' => $data['text'],
            'remind_at' => CarbonImmutable::parse($data['remind_at']),
            'repeat' => $data['repeat'] ?? 'none',
            'audience' => $data['audience'] ?? 'group',
            'source_message_id' => $source > 0 && Message::query()->where('group_id', $group->id)->whereKey($source)->visibleTo($request->user())->exists() ? $source : null,
        ]);

        return response()->json(['ok' => true, 'reminders' => $reminders->listFor($group, $request->user())], 201);
    }

    public function cancelReminder(Request $request, Group $group, Reminder $reminder, Reminders $reminders): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $reminder->group_id === (int) $group->id, 404);
        abort_unless($reminders->canCancel($group, $reminder, $request->user()), 403, 'Hanya pembuat pengingat atau moderator yang bisa membatalkannya.');
        $reminders->cancel($reminder);

        return response()->json(['ok' => true, 'reminders' => $reminders->listFor($group, $request->user())]);
    }

    // ── Pesan terjadwal ───────────────────────────────────────────

    public function scheduled(Request $request, Group $group, ScheduledMessages $scheduled): JsonResponse
    {
        $this->authorize('chat', $group);

        return response()->json(['ok' => true, 'scheduled' => $scheduled->listFor($group, $request->user())]);
    }

    public function storeScheduled(Request $request, Group $group, ScheduledMessages $scheduled): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'send_at' => ['required', 'date'],
            'reply_to_message_id' => ['nullable', 'integer'],
            'ai_mode' => ['nullable', 'string', 'max:30'],
            'ai_model' => ['nullable', 'string', 'max:80'],
        ]);
        $scheduled->create($group, $request->user(), ['send_at' => CarbonImmutable::parse($data['send_at'])] + $data);

        return response()->json(['ok' => true, 'scheduled' => $scheduled->listFor($group, $request->user())], 201);
    }

    public function cancelScheduled(Request $request, Group $group, ScheduledMessage $scheduledMessage, ScheduledMessages $scheduled): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ownScheduled($request, $group, $scheduledMessage);
        $scheduled->cancel($scheduledMessage);

        return response()->json(['ok' => true, 'scheduled' => $scheduled->listFor($group, $request->user())]);
    }

    public function sendScheduledNow(Request $request, Group $group, ScheduledMessage $scheduledMessage, ScheduledMessages $scheduled): JsonResponse
    {
        $this->authorize('chat', $group);
        $this->ensureWritable($group);
        $this->ownScheduled($request, $group, $scheduledMessage);
        $message = $scheduled->sendNow($scheduledMessage);
        abort_if($message === null, 409, 'Pesan ini tidak bisa dikirim. Coba lagi.');

        return response()->json(['ok' => true, 'scheduled' => $scheduled->listFor($group, $request->user())]);
    }

    // ── Terjemahan ────────────────────────────────────────────────

    public function translate(Request $request, Group $group, Message $message, MessageTranslator $translator): JsonResponse
    {
        $this->authorize('chat', $group);
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_unless(Message::query()->whereKey($message->id)->visibleTo($request->user())->exists(), 404);
        abort_if($message->isSystem() || trim((string) $message->content) === '', 422, 'Pesan ini tidak berisi teks.');
        $result = $translator->translate($group, $message);
        abort_if($result === null, 503, 'Terjemahan belum bisa dibuat. Coba lagi sebentar lagi.');

        return response()->json(['ok' => true, 'translation' => $result]);
    }

    // ── Rekap mingguan ────────────────────────────────────────────

    public function weeklyRecap(Request $request, Group $group): JsonResponse
    {
        abort_unless($group->isModerator($request->user()), 403, 'Hanya pemilik atau admin grup yang bisa mengatur rekap mingguan.');
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'day' => ['required', 'integer', 'between:1,7'],
            'hour' => ['required', 'integer', 'between:0,23'],
        ]);
        $group->update(['weekly_recap_enabled' => $data['enabled'], 'weekly_recap_day' => $data['day'], 'weekly_recap_hour' => $data['hour']]);
        AuditLog::record('group.weekly_recap', $request->user()->id, $group, $data, $group->id);

        return response()->json(['ok' => true]);
    }

    private function ownScheduled(Request $request, Group $group, ScheduledMessage $scheduled): void
    {
        abort_unless((int) $scheduled->group_id === (int) $group->id && (int) $scheduled->user_id === (int) $request->user()->id, 404);
    }

    private function ensureWritable(Group $group): void
    {
        abort_if($group->isFrozen(), 423, 'Grup ini dibekukan oleh admin Normi Chat.');
    }
}
