<?php

namespace App\Services\Chat;

use App\Events\MessageSent;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;

/**
 * Pemberitahuan di tengah chat (docs/rancangan-alur-chat-wa.md): bergabung, keluar, dikeluarkan, riwayat
 * dikirim, nama/foto/deskripsi grup diubah. Disimpan sebagai pesan `sender_type = system` dengan
 * `system_event` + `system_meta`; teksnya disusun per penonton di browser ("Kamu …") dan di ekspor
 * (orang ketiga) lewat text().
 */
class SystemMessages
{
    public const MEMBER_JOINED = 'member_joined';
    public const MEMBER_LEFT = 'member_left';
    public const MEMBER_REMOVED = 'member_removed';
    public const HISTORY_SHARED = 'history_shared';
    public const NAME_CHANGED = 'name_changed';
    public const PHOTO_CHANGED = 'photo_changed';
    public const PHOTO_REMOVED = 'photo_removed';
    public const DESCRIPTION_CHANGED = 'description_changed';
    public const MESSAGE_PINNED = 'message_pinned';
    public const REMINDER_CREATED = 'reminder_created';

    public function __construct(private readonly MessagePresenter $presenter)
    {
    }

    /** @param array<string, mixed> $extra mis. before/after untuk nama dan deskripsi */
    public function record(Group $group, string $event, User $actor, ?User $target = null, array $extra = []): Message
    {
        $message = Message::create([
            'group_id' => $group->id,
            'sender_type' => Message::SENDER_SYSTEM,
            'sender_id' => null,
            'message_type' => 'system',
            'system_event' => $event,
            'system_meta' => array_filter([
                'actor_id' => (int) $actor->id,
                'actor_name' => (string) $actor->name,
                'target_id' => $target ? (int) $target->id : null,
                'target_name' => $target?->name,
                ...$extra,
            ], fn ($value) => $value !== null),
            'content' => self::text($event, ['actor_name' => $actor->name, 'target_name' => $target?->name, ...$extra]),
        ]);

        event(new MessageSent((int) $group->id, $this->presenter->present($message)));

        return $message;
    }

    /** Teks orang ketiga (ekspor, pratinjau daftar grup, isi `content` untuk pencarian). */
    public static function text(string $event, array $meta): string
    {
        $actor = (string) ($meta['actor_name'] ?? 'Anggota');
        $target = (string) ($meta['target_name'] ?? 'anggota');

        return match ($event) {
            self::MEMBER_JOINED => "{$actor} bergabung lewat tautan undangan.",
            self::MEMBER_LEFT => "{$actor} keluar.",
            self::MEMBER_REMOVED => "{$actor} mengeluarkan {$target}.",
            self::HISTORY_SHARED => "{$actor} mengirim riwayat pesan ke {$target}.",
            self::NAME_CHANGED => "{$actor} mengganti nama grup dari «" . ($meta['before'] ?? '') . '» menjadi «' . ($meta['after'] ?? '') . '».',
            self::PHOTO_CHANGED => "{$actor} mengganti foto grup.",
            self::PHOTO_REMOVED => "{$actor} menghapus foto grup.",
            self::DESCRIPTION_CHANGED => filled($meta['after'] ?? null) ? "{$actor} mengubah deskripsi grup." : "{$actor} menghapus deskripsi grup.",
            self::MESSAGE_PINNED => "{$actor} menyematkan pesan.",
            self::REMINDER_CREATED => "{$actor} membuat pengingat untuk " . ($meta['when'] ?? 'nanti') . '.',
            default => '',
        };
    }
}
