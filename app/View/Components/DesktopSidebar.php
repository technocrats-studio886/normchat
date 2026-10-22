<?php

namespace App\View\Components;

use App\Models\Group;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Kolom kiri tampilan desktop (ala Telegram): daftar grup dengan pesan terakhir dan
 * jumlah belum dibaca, profil serta pengaturan menempel di bawah. Disembunyikan di layar
 * kecil; di sana navigasi tetap lewat beranda dan bottom nav.
 */
class DesktopSidebar extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $groups = Group::query()
            ->visibleTo($user)
            ->addSelect(['last_message_id' => Message::query()
                ->select('id')
                ->whereColumn('group_id', 'groups.id')
                ->latest('id')
                ->limit(1)])
            ->get();

        $lastMessages = Message::query()
            ->with('sender:id,name')
            ->whereIn('id', $groups->pluck('last_message_id')->filter())
            ->get()
            ->keyBy('id');

        $unread = DB::table('messages')
            ->join('group_members as gm', fn ($join) => $join->on('gm.group_id', '=', 'messages.group_id')->where('gm.user_id', $user->id))
            ->whereIn('messages.group_id', $groups->pluck('id'))
            ->whereNull('messages.deleted_at')
            ->whereRaw('messages.id > coalesce(gm.last_read_message_id, 0)')
            ->where(fn ($query) => $query->whereNull('messages.sender_id')->orWhere('messages.sender_id', '!=', $user->id))
            ->groupBy('messages.group_id')
            ->selectRaw('messages.group_id as group_id, count(*) as total')
            ->pluck('total', 'group_id');

        $routeGroup = request()->route('group');
        $activeGroupId = $routeGroup instanceof Group ? (int) $routeGroup->id : 0;

        return view('components.desktop-sidebar', [
            'user' => $user,
            'activeGroupId' => $activeGroupId,
            'rows' => $groups
                ->map(fn (Group $group) => $this->row($group, $lastMessages->get($group->last_message_id), (int) ($unread[$group->id] ?? 0), $user->id))
                ->sortByDesc('sort_key')
                ->values(),
        ]);
    }

    private function row(Group $group, ?Message $last, int $unread, int $viewerId): array
    {
        $time = $last?->created_at?->timezone(config('app.display_timezone', config('app.timezone')));

        return [
            'group' => $group,
            'initial' => Str::upper(Str::substr($group->name, 0, 1)),
            'preview' => $last ? $this->preview($last, $viewerId) : 'Belum ada pesan',
            'time' => $time ? ($time->isToday() ? $time->format('H:i') : $time->format('d/m/y')) : '',
            'unread' => $unread,
            'sort_key' => $last?->id ?? 0,
        ];
    }

    private function preview(Message $message, int $viewerId): string
    {
        $sender = match (true) {
            $message->sender_type === 'ai' => 'Normi AI',
            (int) $message->sender_id === $viewerId => 'Kamu',
            default => Str::words((string) ($message->sender?->name ?? 'User'), 1, ''),
        };

        $body = trim((string) $message->content) ?: match ($message->message_type) {
            'image' => '🖼 Gambar',
            'voice' => '🎤 Voice note',
            'video' => '🎬 Video',
            'canvas' => '🧩 Canvas',
            default => '📎 Lampiran',
        };

        return $sender . ': ' . Str::limit(Str::squish($body), 60);
    }
}
