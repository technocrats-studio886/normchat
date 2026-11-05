<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\GroupDraft;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\User;
use App\Services\Chat\MessageSummary;
use App\Support\UserTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Baris daftar grup untuk sidebar desktop dan daftar grup HP (docs/rancangan-alur-chat-wa.md):
 * grup berdraf (≤ 24 jam) paling atas, lalu pesan terakhir terbaru ke terlama (grup tanpa pesan: waktu dibuat).
 * Pratinjau, jam, dan jumlah belum dibaca hanya menghitung pesan yang boleh dilihat user.
 */
class GroupListRows
{
    /** @return Collection<int, array<string, mixed>> */
    public function for(User $user): Collection
    {
        $groups = Group::query()
            ->visibleTo($user)
            ->with(['members' => fn ($query) => $query->where('status', 'active')])
            ->addSelect(['last_message_id' => Message::withTrashed()
                ->select('id')
                ->whereColumn('group_id', 'groups.id')
                ->visibleTo($user)
                ->whereNotIn('id', MessageHide::where('user_id', $user->id)->select('message_id'))
                ->latest('id')
                ->limit(1)])
            ->get();

        $lastMessages = Message::withTrashed()
            ->with('sender:id,name')
            ->whereIn('id', $groups->pluck('last_message_id')->filter())
            ->get()
            ->keyBy('id');

        $unread = DB::table('messages')
            ->join('group_members as gm', fn ($join) => $join->on('gm.group_id', '=', 'messages.group_id')->where('gm.user_id', $user->id))
            ->whereIn('messages.group_id', $groups->pluck('id'))
            ->whereNull('messages.deleted_at')
            ->where(fn ($query) => $query->whereNull('messages.visible_to_user_id')->orWhere('messages.visible_to_user_id', $user->id))
            ->whereNotIn('messages.id', MessageHide::where('user_id', $user->id)->select('message_id'))
            ->whereRaw('messages.id > coalesce(gm.last_read_message_id, 0)')
            ->whereRaw('messages.id > coalesce(gm.history_from_message_id, 0)')
            ->where('messages.sender_type', '!=', Message::SENDER_SYSTEM)
            ->where(fn ($query) => $query->whereNull('messages.sender_id')->orWhere('messages.sender_id', '!=', $user->id))
            ->groupBy('messages.group_id')
            ->selectRaw('messages.group_id as group_id, count(*) as total')
            ->pluck('total', 'group_id');

        $drafts = GroupDraft::unexpired()->where('user_id', $user->id)->whereIn('group_id', $groups->pluck('id'))->get()->keyBy('group_id');

        return $groups
            ->map(fn (Group $group) => $this->row($group, $lastMessages->get($group->last_message_id), (int) ($unread[$group->id] ?? 0), $drafts->get($group->id), (int) $user->id))
            ->sort(fn (array $a, array $b) => [$b['draft_at'] ?? '', $b['activity_at']] <=> [$a['draft_at'] ?? '', $a['activity_at']])
            ->values();
    }

    private function row(Group $group, ?Message $last, int $unread, ?GroupDraft $draft, int $viewerId): array
    {
        $time = $last?->created_at?->copy()->setTimezone(UserTime::zone());
        $activity = $last?->created_at ?? $group->created_at;

        return [
            'group' => $group,
            'initial' => $group->initial(),
            'preview' => $last ? $this->preview($last, $viewerId) : 'Belum ada pesan',
            'time' => $time ? ($time->isToday() ? $time->format('H.i') : $time->format('d/m/y')) : '',
            'unread' => $unread,
            'draft' => $draft ? Str::limit(preg_replace('/\s+/u', ' ', $draft->content), 80) : null,
            'draft_at' => $draft?->updated_at?->toIso8601String(),
            'activity_at' => $activity?->toIso8601String() ?? '',
            'last_message_id' => (int) ($last?->id ?? 0),
            'member_count' => $group->members->count() + ($group->members->contains('user_id', $group->owner_id) ? 0 : 1),
        ];
    }

    public function preview(Message $message, int $viewerId): string
    {
        if ($message->sender_type === Message::SENDER_SYSTEM) {
            return Str::limit((string) $message->content, 70);
        }
        $sender = match (true) {
            $message->sender_type === 'ai' => 'Normi AI',
            (int) $message->sender_id === $viewerId => 'Kamu',
            default => Str::words((string) ($message->sender?->name ?? 'Anggota'), 1, ''),
        };

        return $sender . ': ' . Str::limit(MessageSummary::of($message), 60);
    }
}
