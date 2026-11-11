<?php

namespace App\Services\Chat;

use App\Events\GroupDataChanged;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\ScheduledMessage;
use App\Models\User;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pesan terjadwal (docs/rancangan-fitur-produktivitas-nov.md bagian 4): teks yang dikirim otomatis atas nama
 * pengirimnya lewat MessageSender, persis seperti diketik saat itu (termasuk memanggil Normi).
 */
class ScheduledMessages
{
    public const MAX_PENDING = 20;

    public function __construct(private readonly MessageSender $sender)
    {
    }

    /** @param  array{content: string, send_at: CarbonInterface, reply_to_message_id?: ?int, ai_mode?: ?string, ai_model?: ?string}  $data */
    public function create(Group $group, User $user, array $data): ScheduledMessage
    {
        $content = trim($data['content']);
        abort_if($content === '', 422, 'Pesan masih kosong.');
        $at = CarbonImmutable::instance($data['send_at'])->setTimezone(config('app.timezone'))->startOfMinute();
        abort_if($at->lessThan(now()->addMinute()->startOfMinute()), 422, 'Pilih waktu minimal 1 menit dari sekarang.');
        abort_if($at->greaterThan(now()->addYear()), 422, 'Pesan bisa dijadwalkan paling jauh setahun ke depan.');
        abort_if($this->pendingQuery($group, $user)->count() >= self::MAX_PENDING, 422, 'Pesan terjadwal di grup ini sudah ' . self::MAX_PENDING . '. Kirim atau batalkan sebagian dulu.');

        $replyTo = (int) ($data['reply_to_message_id'] ?? 0);
        $scheduled = ScheduledMessage::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'content' => $content,
            'reply_to_message_id' => $replyTo > 0 && Message::query()->where('group_id', $group->id)->whereKey($replyTo)->visibleTo($user)->exists() ? $replyTo : null,
            'ai_mode' => $data['ai_mode'] ?? null,
            'ai_model' => $data['ai_model'] ?? null,
            'send_at' => $at,
            'status' => ScheduledMessage::PENDING,
        ]);
        $this->changed($scheduled);

        return $scheduled;
    }

    public function cancel(ScheduledMessage $scheduled): void
    {
        if ($scheduled->status === ScheduledMessage::PENDING) {
            $scheduled->update(['status' => ScheduledMessage::CANCELLED]);
            $this->changed($scheduled);
        }
    }

    /** "Kirim sekarang" dari daftar. */
    public function sendNow(ScheduledMessage $scheduled): ?Message
    {
        return $this->deliver((int) $scheduled->id, force: true);
    }

    /** @return list<array<string, mixed>> */
    public function listFor(Group $group, User $user): array
    {
        return $this->pendingQuery($group, $user)->orderBy('send_at')->get()
            ->map(fn (ScheduledMessage $scheduled) => [
                'id' => (int) $scheduled->id,
                'content' => (string) $scheduled->content,
                'send_at' => $scheduled->send_at->toIso8601String(),
                'when' => self::whenLabel($scheduled->send_at, $user),
                'reply_to_message_id' => $scheduled->reply_to_message_id ? (int) $scheduled->reply_to_message_id : null,
            ])->all();
    }

    public function pendingCount(Group $group, User $user): int
    {
        return $this->pendingQuery($group, $user)->count();
    }

    public static function whenLabel(CarbonInterface $time, ?User $viewer = null): string
    {
        $local = UserTime::local($time, $viewer);
        $today = CarbonImmutable::now(UserTime::zone($viewer))->startOfDay();
        $days = (int) $today->diffInDays(CarbonImmutable::parse($local->toDateString(), $local->getTimezone()), false);
        $date = match (true) {
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            default => $local->translatedFormat($local->year === $today->year ? 'l, j M' : 'l, j M Y'),
        };

        return $date . ' pukul ' . $local->format('H.i');
    }

    /** @return int jumlah pesan yang terkirim */
    public function sendDue(int $limit = 100): int
    {
        $sent = 0;
        $due = ScheduledMessage::query()->where('status', ScheduledMessage::PENDING)->where('send_at', '<=', now())
            ->orderBy('send_at')->limit($limit)->pluck('id');
        foreach ($due as $id) {
            $sent += $this->deliver((int) $id) ? 1 : 0;
        }

        return $sent;
    }

    private function deliver(int $id, bool $force = false): ?Message
    {
        $claimed = ScheduledMessage::query()->whereKey($id)->where('status', ScheduledMessage::PENDING)
            ->when(! $force, fn ($query) => $query->where('send_at', '<=', now()))
            ->update(['status' => 'sending']);
        if ($claimed === 0) {
            return null;
        }
        $scheduled = ScheduledMessage::with(['group.groupToken', 'user'])->findOrFail($id);
        $group = $scheduled->group;
        $user = $scheduled->user;

        $error = match (true) {
            ! $group || $group->status !== 'active' => 'Grup tidak aktif lagi.',
            $group->isFrozen() => 'Grup dibekukan.',
            ! $user || ! ((int) $group->owner_id === (int) $user->id
                || GroupMember::query()->active()->where('group_id', $group->id)->where('user_id', $user->id)->exists()) => 'Pengirim sudah bukan anggota grup.',
            default => null,
        };
        if ($error !== null) {
            $scheduled->update(['status' => ScheduledMessage::FAILED, 'error' => $error]);
            $this->changed($scheduled);

            return null;
        }

        try {
            $message = $this->sender->sendText($group, $user, (string) $scheduled->content, $scheduled->reply_to_message_id, $scheduled->ai_mode, $scheduled->ai_model);
        } catch (Throwable $e) {
            // Mis. kunci grup sedang dipakai: dicoba lagi di putaran berikutnya.
            Log::warning('Scheduled message delivery failed.', ['id' => $id, 'error' => $e->getMessage()]);
            $scheduled->update(['status' => ScheduledMessage::PENDING]);

            return null;
        }
        $scheduled->update(['status' => ScheduledMessage::SENT, 'sent_message_id' => $message->id]);
        $this->changed($scheduled);

        return $message;
    }

    private function pendingQuery(Group $group, User $user)
    {
        return ScheduledMessage::query()->where('group_id', $group->id)->where('user_id', $user->id)->where('status', ScheduledMessage::PENDING);
    }

    private function changed(ScheduledMessage $scheduled): void
    {
        event(new GroupDataChanged((int) $scheduled->group_id, GroupDataChanged::SCHEDULED, (int) $scheduled->user_id));
    }
}
