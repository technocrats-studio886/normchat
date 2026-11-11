<?php

namespace App\Services\Groups;

use App\Models\Group;
use App\Models\Message;
use App\Models\MessageHide;
use App\Models\LinkPreview;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use App\Support\UserTime;
use App\Services\Links\LinkPreviews;
use App\Services\Links\LinkExtractor;
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

    public function __construct(private readonly CreditWallet $wallet, private readonly LinkPreviews $links)
    {
    }

    /** @return array<string, mixed> */
    public function for(Group $group, User $user): array
    {
        $group->loadMissing(['groupToken', 'members.user', 'members.role']);
        $members = $group->members->where('status', 'active')
            ->sortBy(fn ($member) => [(int) $member->user_id === (int) $group->owner_id ? 0 : (($member->role->key ?? '') === 'admin' ? 1 : 2), mb_strtolower((string) $member->user?->name)])
            ->values();
        $links = $this->linkEntries($group, $user);
        $view = session('group_info');

        return [
            'group' => $group,
            'members' => $members,
            'memberCount' => $members->count() + ($members->contains('user_id', $group->owner_id) ? 0 : 1),
            'mediaMessages' => $this->archive($group, $user)->where('message_type', 'image')->get(),
            'fileMessages' => $this->archive($group, $user)->whereNotIn('message_type', ['image', 'voice'])->get(),
            // Tab Tautan: kartu pratinjau + potongan chat, dikelompokkan per waktu (docs/rancangan-pratinjau-tautan.md).
            'linkGroups' => $links->groupBy('period')->all(),
            'mediaCount' => $this->archive($group, $user, false)->where('message_type', 'image')->count(),
            'fileCount' => $this->archive($group, $user, false)->whereNotIn('message_type', ['image', 'voice'])->count(),
            'linkCount' => $links->count(),
            'isOwner' => $group->isOwnedBy($user),
            'canEditProfile' => $user->can('editGroupProfile', $group),
            'canManageBilling' => $user->can('manageBilling', $group),
            'canManageAiPersona' => $user->can('manageAiPersona', $group),
            'canManageMembers' => $user->can('manageMembers', $group),
            'canBackup' => $user->can('createBackup', $group),
            'creditBalance' => $this->wallet->balanceFor($group, $user),
            'aiModelLabel' => $group->resolvedAiModel()?->label,
            'infoView' => in_array($view, self::VIEWS, true) ? $view : null,
            // Bisu notifikasi grup untuk user ini (App\Http\Controllers\PushController::mute).
            'viewerMember' => $members->firstWhere('user_id', $user->id),
        ];
    }

    /** Pesan berlampiran terbaru grup. */
    private function archive(Group $group, User $user, bool $limit = true): Builder
    {
        // Anggota baru yang riwayatnya belum dikirim hanya melihat media sesudah ia bergabung.
        $query = Message::query()->where('group_id', $group->id)->visibleTo($user)->whereNotNull('attachment_path');

        return $limit ? $query->latest('created_at')->take(self::ARCHIVE_LIMIT) : $query;
    }

    /**
     * Satu entri per tautan di pesan terbaru yang boleh dilihat user (terbaru dulu), dengan pratinjaunya, potongan
     * teks chat selain tautan, dan kelompok waktu: Terbaru (7 hari), Minggu lalu, Bulan lalu, lalu per bulan.
     * Tautan yang belum punya pratinjau diantrekan untuk dibaca.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function linkEntries(Group $group, User $user): Collection
    {
        $messages = Message::query()
            ->with('sender:id,name')
            ->where('group_id', $group->id)
            ->visibleTo($user)
            ->whereNotIn('id', MessageHide::where('user_id', $user->id)->select('message_id'))
            ->where('sender_type', '!=', Message::SENDER_SYSTEM)
            ->where('content', 'like', '%http%')
            ->latest('id')
            ->take(self::ARCHIVE_LIMIT)
            ->get();

        $entries = $messages->flatMap(fn (Message $message) => collect(LinkExtractor::urls($message->content))
            ->map(fn (string $url) => ['message' => $message, 'url' => $url]));
        $previews = LinkPreview::forUrls($entries->pluck('url')->unique()->values()->all());
        $missing = $entries->pluck('url')->unique()->reject(fn ($url) => $previews->has($url) && ! $previews->get($url)->needsFetch())->take(30)->values()->all();
        if ($missing !== []) {
            $this->links->ensure($missing);
        }

        $zone = UserTime::zone();
        $today = now($zone)->startOfDay();

        return $entries->map(function (array $entry) use ($previews, $zone, $today) {
            $at = $entry['message']->created_at->copy()->setTimezone($zone);
            $days = (int) $at->copy()->startOfDay()->diffInDays($today);
            $preview = $previews->get($entry['url']);

            return [
                'url' => $entry['url'],
                'message_id' => (int) $entry['message']->id,
                'domain' => preg_replace('/^www\./i', '', (string) parse_url($entry['url'], PHP_URL_HOST)),
                'title' => $preview?->status === LinkPreview::OK ? $preview->title : null,
                'image_url' => $preview?->status === LinkPreview::OK ? $preview->image_url : null,
                'text' => LinkExtractor::textWithout($entry['message']->content),
                'at' => $at,
                'period' => match (true) {
                    $days <= 7 => 'Terbaru',
                    $days <= 14 => 'Minggu lalu',
                    $days <= 30 => 'Bulan lalu',
                    default => $at->locale('id')->translatedFormat('F Y'),
                },
            ];
        })->values();
    }
}
