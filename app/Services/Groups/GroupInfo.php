<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data panel Info grup (resources/views/groups/info/panel.blade.php). Dipakai halaman Info grup HP
 * (settings.show) dan jendela/panel kanan desktop di ruang chat, jadi isinya selalu sama.
 */
class GroupInfo
{
    private const ARCHIVE_LIMIT = 60;

    /** Tampilan panel yang boleh dibuka ulang lewat flash `group_info` setelah form disimpan. */
    public const VIEWS = ['main', 'manage', 'media', 'members'];

    public function __construct(private readonly CreditWallet $wallet)
    {
    }

    /** @return array<string, mixed> */
    public function for(Group $group, User $user): array
    {
        $group->loadMissing(['groupToken', 'members.user', 'members.role']);
        $members = $group->members->where('status', 'active')
            ->sortBy(fn ($member) => [(int) $member->user_id === (int) $group->owner_id ? 0 : (($member->role->key ?? '') === 'admin' ? 1 : 2), mb_strtolower((string) $member->user?->name)])
            ->values();
        $links = $this->linkMessages($group);
        $view = session('group_info');

        return [
            'group' => $group,
            'members' => $members,
            'memberCount' => $members->count() + ($members->contains('user_id', $group->owner_id) ? 0 : 1),
            'mediaMessages' => $this->archive($group)->where('message_type', 'image')->get(),
            'fileMessages' => $this->archive($group)->whereNotIn('message_type', ['image', 'voice'])->get(),
            'linkMessages' => $links,
            'mediaCount' => $this->archive($group, false)->where('message_type', 'image')->count(),
            'fileCount' => $this->archive($group, false)->whereNotIn('message_type', ['image', 'voice'])->count(),
            'linkCount' => $links->sum(fn (Message $message) => count($message->extracted_urls)),
            'isOwner' => $group->isOwnedBy($user),
            'canEditProfile' => $user->can('editGroupProfile', $group),
            'canManageBilling' => $user->can('manageBilling', $group),
            'canManageAiPersona' => $user->can('manageAiPersona', $group),
            'canManageMembers' => $user->can('manageMembers', $group),
            'canBackup' => $user->can('createBackup', $group),
            'creditBalance' => $this->wallet->balanceFor($group, $user),
            'aiModelLabel' => $group->resolvedAiModel()?->label,
            'infoView' => in_array($view, self::VIEWS, true) ? $view : null,
        ];
    }

    /** Pesan berlampiran terbaru grup. */
    private function archive(Group $group, bool $limit = true): Builder
    {
        $query = Message::query()->where('group_id', $group->id)->whereNotNull('attachment_path');

        return $limit ? $query->latest('created_at')->take(self::ARCHIVE_LIMIT) : $query;
    }

    /** Pesan terbaru yang berisi tautan, dengan daftar URL-nya di `extracted_urls`. */
    private function linkMessages(Group $group): Collection
    {
        return Message::query()
            ->where('group_id', $group->id)
            ->where('content', 'like', '%http%')
            ->latest('created_at')
            ->take(self::ARCHIVE_LIMIT)
            ->get()
            ->each(function (Message $message) {
                preg_match_all('/https?:\/\/[^\s<]+/i', (string) $message->content, $urls);
                $message->extracted_urls = $urls[0] ?? [];
            })
            ->filter(fn (Message $message) => $message->extracted_urls !== [])
            ->values();
    }
}
