<?php

namespace App\Services\Push;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Services\Chat\MentionNotifier;
use App\Services\Chat\PollService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Notifikasi push untuk satu pesan grup (keputusan user 11 Nov 2026):
 * - semua pesan grup, kecuali grup yang dibisukan; mention, balasan ke pesanku, dan jawaban Normi untuk
 *   pertanyaanku tetap dikirim walau dibisukan (bila setelan "Saat disebut" menyala);
 * - hanya bila pesan itu belum dibaca di perangkat mana pun (group_members.last_read_message_id) dan user tidak
 *   sedang membuka Normi Chat di perangkat lain (PushPresence). Dicek setelah jeda singkat
 *   (normchat.push.delay_seconds), jadi pesan yang langsung terbaca di laptop tidak berbunyi di HP.
 */
class MessagePush
{
    private const MAX_FAILURES = 10;

    public function __construct(
        private readonly MentionNotifier $mentions,
        private readonly PollService $polls,
    ) {
    }

    /** @return int jumlah notifikasi yang terkirim */
    public function send(Message $message): int
    {
        $push = WebPush::fromConfig();
        if ($push === null || $message->trashed() || ! in_array($message->sender_type, ['user', 'ai'], true)) {
            return 0;
        }
        // Pesan terusan dari grup lain tetap pesan baru di grup ini; pemberitahuan pribadi Normi hanya ke penerimanya.
        $group = Group::with(['owner', 'members' => fn ($query) => $query->active()])->find($message->group_id);
        if (! $group || $group->status !== 'active') {
            return 0;
        }

        $recipients = $this->recipients($group, $message);
        if ($recipients->isEmpty()) {
            return 0;
        }

        $subscriptions = PushSubscription::query()->whereIn('user_id', $recipients->keys())->get()->groupBy('user_id');
        $mentioned = $message->sender_type === 'user' ? $this->mentions->mentionedUserIds($group, (string) $message->content) : [];
        $message->loadMissing('sender:id,name', 'replyToMessage:id,sender_id,sender_type');

        $sent = 0;
        foreach ($subscriptions as $userId => $devices) {
            $userId = (int) $userId;
            /** @var GroupMember|null $member */
            $member = $recipients->get($userId);
            if (! $this->shouldNotify($message, $userId, $member, in_array($userId, $mentioned, true))) {
                continue;
            }

            $payload = $this->payload($group, $message, $userId, $member);
            foreach ($devices as $device) {
                $sent += $this->deliver($push, $device, $payload, $group) ? 1 : 0;
            }
        }

        return $sent;
    }

    /** @return Collection<int, GroupMember|null> penerima: user id → keanggotaan (owner lama tanpa baris anggota: null) */
    private function recipients(Group $group, Message $message): Collection
    {
        $members = $group->members->keyBy(fn (GroupMember $member) => (int) $member->user_id);
        $all = $members->map(fn ($member) => $member);
        if ($group->owner_id && ! $all->has((int) $group->owner_id)) {
            $all->put((int) $group->owner_id, null);
        }

        return $all->filter(function (?GroupMember $member, int $userId) use ($message) {
            if ($message->visible_to_user_id !== null) {
                return $userId === (int) $message->visible_to_user_id;
            }
            if ($message->sender_type === 'user' && $userId === (int) $message->sender_id) {
                return false;
            }
            // Pesan sebelum anggota bergabung tertutup sampai riwayatnya dikirim.
            $historyFrom = (int) ($member?->history_from_message_id ?? 0);

            return $historyFrom === 0 || (int) $message->id >= $historyFrom;
        });
    }

    private function shouldNotify(Message $message, int $userId, ?GroupMember $member, bool $isMentioned): bool
    {
        // Sudah dibaca di perangkat mana pun.
        if ($member !== null && (int) $member->last_read_message_id >= (int) $message->id) {
            return false;
        }
        // Sedang membuka Normi Chat di salah satu perangkat: pesannya terlihat di sana.
        if (PushPresence::isActive($userId)) {
            return false;
        }
        if ($member === null || ! $member->isMuted()) {
            return true;
        }

        // Grup dibisukan: hanya yang ditujukan langsung ke user ini.
        $user = $member->user ?? \App\Models\User::find($userId);
        if ($user === null || ! $user->notify_mentions) {
            return false;
        }
        $repliesToMe = (int) ($message->replyToMessage?->sender_id ?? 0) === $userId && $message->replyToMessage?->sender_type === 'user';

        return $isMentioned || $repliesToMe || $message->visible_to_user_id !== null;
    }

    /** @return array<string, mixed> */
    private function payload(Group $group, Message $message, int $userId, ?GroupMember $member): array
    {
        $sender = $message->sender_type === 'ai' ? 'Normi AI' : ($message->sender?->name ?? 'Anggota');
        $unread = $this->unreadCount($group, $userId, (int) ($member?->last_read_message_id ?? 0));

        return [
            'type' => 'message',
            'group_id' => (int) $group->id,
            'message_id' => (int) $message->id,
            'title' => $unread > 1 ? "{$group->name} ({$unread} pesan baru)" : (string) $group->name,
            'body' => Str::limit($sender . ': ' . $this->preview($message), 180),
            'url' => route('chat.show', $group, false) . '#message-' . $message->id,
            'tag' => 'group-' . $group->id,
            'unread' => $unread,
            'badge_total' => $this->totalUnread($userId),
            'sent_at' => now()->getTimestampMs(),
        ];
    }

    private function preview(Message $message): string
    {
        $content = trim((string) $message->content);
        $poll = $content !== '' ? $this->polls->parse($content) : null;
        if ($poll !== null) {
            return 'Polling: ' . $poll['question'];
        }

        $type = strtolower((string) $message->message_type);
        $label = match (true) {
            $type === 'image' => 'Foto',
            $type === 'voice' => 'Pesan suara',
            $type === 'video' => 'Video',
            $type === 'canvas' => 'Canvas',
            (bool) $message->attachment_path => 'File ' . ($message->attachment_original_name ?: ''),
            default => '',
        };
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) preg_replace('/[*_`#>|]+/u', '', $content))));

        return trim($label !== '' && $text !== '' ? "{$label} · {$text}" : ($label ?: $text)) ?: 'Pesan baru';
    }

    private function unreadCount(Group $group, int $userId, int $lastRead): int
    {
        return (int) Message::query()
            ->where('group_id', $group->id)
            ->where('sender_type', '!=', Message::SENDER_SYSTEM)
            ->where(fn ($query) => $query->whereNull('visible_to_user_id')->orWhere('visible_to_user_id', $userId))
            ->where(fn ($query) => $query->whereNull('sender_id')->orWhere('sender_id', '!=', $userId))
            ->when($lastRead > 0, fn ($query) => $query->where('id', '>', $lastRead))
            ->limit(1000)
            ->count();
    }

    /** Total pesan belum dibaca di semua grup aktif (angka lencana ikon aplikasi). */
    private function totalUnread(int $userId): int
    {
        return (int) GroupMember::query()
            ->active()
            ->where('group_members.user_id', $userId)
            ->join('messages', 'messages.group_id', '=', 'group_members.group_id')
            ->whereNull('messages.deleted_at')
            ->whereColumn('messages.id', '>', \Illuminate\Support\Facades\DB::raw('coalesce(group_members.last_read_message_id, 0)'))
            ->where('messages.sender_type', '!=', Message::SENDER_SYSTEM)
            ->where(fn ($query) => $query->whereNull('messages.sender_id')->orWhere('messages.sender_id', '!=', $userId))
            ->where(fn ($query) => $query->whereNull('messages.visible_to_user_id')->orWhere('messages.visible_to_user_id', $userId))
            ->count();
    }

    private function deliver(WebPush $push, PushSubscription $device, array $payload, Group $group): bool
    {
        // Topik per grup: notifikasi yang belum sampai (HP mati/luring) diganti yang terbaru, tidak menumpuk.
        $result = $push->send($device, $payload, 86400, 'g' . $group->id);
        if ($result === WebPush::GONE) {
            $device->delete();

            return false;
        }
        if ($result === WebPush::FAILED) {
            $device->increment('failures');
            if ($device->failures >= self::MAX_FAILURES) {
                $device->delete();
            }

            return false;
        }

        $device->forceFill(['last_success_at' => now(), 'failures' => 0])->save();

        return true;
    }
}
