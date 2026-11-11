<?php

namespace App\Services\Chat;

use App\Events\GroupDataChanged;
use App\Events\MessageSent;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Reminder;
use App\Models\User;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pengingat grup dan pribadi (docs/rancangan-fitur-produktivitas-nov.md bagian 2). Dibuat lewat menu atau lewat
 * "@normi ingatkan …" (ReminderResponder), dikirim normchat:send-reminders sebagai pesan Normi.
 */
class Reminders
{
    /** Pengingat aktif per grup, supaya tidak dipakai untuk spam. */
    public const MAX_PENDING = 50;

    public function __construct(
        private readonly MessagePresenter $presenter,
        private readonly SystemMessages $system,
    ) {
    }

    /**
     * @param  array{text: string, remind_at: CarbonInterface, repeat?: string, audience?: string, source_message_id?: ?int}  $data
     */
    public function create(Group $group, User $user, array $data, bool $announce = true): Reminder
    {
        $text = Str::limit(trim((string) preg_replace('/[ \t]+/u', ' ', $data['text'])), 497, '…');
        abort_if($text === '', 422, 'Isi pengingat masih kosong.');
        $at = CarbonImmutable::instance($data['remind_at'])->setTimezone(config('app.timezone'))->startOfMinute();
        abort_if($at->lessThanOrEqualTo(now()), 422, 'Waktu pengingat sudah lewat.');
        abort_if($at->greaterThan(now()->addYear()), 422, 'Pengingat paling jauh setahun ke depan.');
        abort_if(Reminder::query()->where('group_id', $group->id)->where('status', Reminder::PENDING)->count() >= self::MAX_PENDING, 422, 'Pengingat aktif di grup ini sudah terlalu banyak.');

        $reminder = Reminder::create([
            'group_id' => $group->id,
            'created_by' => $user->id,
            'source_message_id' => $data['source_message_id'] ?? null,
            'text' => $text,
            'remind_at' => $at,
            'repeat' => in_array($data['repeat'] ?? 'none', Reminder::REPEATS, true) ? $data['repeat'] : 'none',
            'audience' => in_array($data['audience'] ?? 'group', Reminder::AUDIENCES, true) ? $data['audience'] : 'group',
            'status' => Reminder::PENDING,
        ]);

        if ($announce && ! $reminder->isPersonal()) {
            $this->system->record($group, SystemMessages::REMINDER_CREATED, $user, null, [
                'reminder_id' => (int) $reminder->id,
                'when' => self::whenLabel($reminder, $user),
            ]);
        }
        $this->changed($reminder);

        return $reminder;
    }

    public function canCancel(Group $group, Reminder $reminder, User $user): bool
    {
        return (int) $reminder->created_by === (int) $user->id || (! $reminder->isPersonal() && $group->isModerator($user));
    }

    public function cancel(Reminder $reminder): void
    {
        if ($reminder->status !== Reminder::PENDING) {
            return;
        }
        $reminder->update(['status' => Reminder::CANCELLED]);
        $this->changed($reminder);
        $this->refreshAnswer($reminder);
    }

    /** @return list<array<string, mixed>> pengingat yang akan datang dan terlihat oleh $viewer */
    public function listFor(Group $group, User $viewer): array
    {
        return Reminder::query()
            ->where('group_id', $group->id)
            ->where('status', Reminder::PENDING)
            ->visibleTo($viewer)
            ->with('creator:id,name')
            ->orderBy('remind_at')
            ->limit(self::MAX_PENDING)
            ->get()
            ->map(fn (Reminder $reminder) => $this->present($group, $reminder, $viewer))
            ->all();
    }

    public function present(Group $group, Reminder $reminder, User $viewer): array
    {
        return [
            'id' => (int) $reminder->id,
            'text' => (string) $reminder->text,
            'remind_at' => $reminder->remind_at->toIso8601String(),
            'when' => self::whenLabel($reminder, $viewer),
            'repeat' => (string) $reminder->repeat,
            'audience' => (string) $reminder->audience,
            'status' => (string) $reminder->status,
            'creator_name' => $reminder->creator?->name,
            'can_cancel' => $reminder->status === Reminder::PENDING && $this->canCancel($group, $reminder, $viewer),
        ];
    }

    /** "Jumat, 14 Nov pukul 19.00" (+ ", tiap minggu") menurut zona waktu $viewer. */
    public static function whenLabel(Reminder $reminder, ?User $viewer = null): string
    {
        $local = UserTime::local($reminder->remind_at, $viewer);
        $today = CarbonImmutable::now(UserTime::zone($viewer))->startOfDay();
        $day = CarbonImmutable::parse($local->toDateString(), $local->getTimezone())->startOfDay();
        $days = (int) $today->diffInDays($day, false);
        $date = match (true) {
            $days === 0 => 'hari ini',
            $days === 1 => 'besok',
            default => $local->translatedFormat($local->year === $today->year ? 'l, j M' : 'l, j M Y'),
        };
        $repeat = match ($reminder->repeat) {
            'daily' => ', tiap hari',
            'weekly' => ', tiap minggu',
            default => '',
        };

        return $date . ' pukul ' . $local->format('H.i') . $repeat;
    }

    /** Kirim semua pengingat yang sudah waktunya. @return int jumlah yang dikirim */
    public function sendDue(int $limit = 100): int
    {
        $sent = 0;
        $due = Reminder::query()->where('status', Reminder::PENDING)->where('remind_at', '<=', now())
            ->orderBy('remind_at')->limit($limit)->pluck('id');
        foreach ($due as $id) {
            try {
                $sent += $this->deliver((int) $id) ? 1 : 0;
            } catch (Throwable $e) {
                Log::error('Reminder delivery failed.', ['reminder_id' => $id, 'error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    private function deliver(int $id): bool
    {
        // Diklaim dulu (status pending → sending di baris yang sama) supaya dua proses tidak mengirim dua kali.
        $claimed = Reminder::query()->whereKey($id)->where('status', Reminder::PENDING)->where('remind_at', '<=', now())
            ->update(['status' => 'sending']);
        if ($claimed === 0) {
            return false;
        }
        $reminder = Reminder::with(['group', 'creator'])->findOrFail($id);
        $group = $reminder->group;
        $creator = $reminder->creator;
        $creatorActive = $creator && $group && ((int) $group->owner_id === (int) $creator->id
            || GroupMember::query()->active()->where('group_id', $group->id)->where('user_id', $creator->id)->exists());
        if (! $group || $group->status !== 'active' || $group->isFrozen() || ($reminder->isPersonal() && ! $creatorActive)) {
            $reminder->update(['status' => Reminder::FAILED]);
            $this->changed($reminder);

            return false;
        }

        $content = '⏰ **' . $reminder->text . '**'
            . "\n\nPengingat dari " . ($creator?->name ?? 'anggota grup')
            . ($reminder->repeat === 'none' ? '.' : ' (' . ($reminder->repeat === 'daily' ? 'tiap hari' : 'tiap minggu') . ').');
        $message = Message::create([
            'group_id' => $group->id,
            'sender_type' => 'ai',
            'sender_id' => null,
            'reply_to_message_id' => $reminder->source_message_id && Message::query()->whereKey($reminder->source_message_id)->where('group_id', $group->id)->exists()
                ? $reminder->source_message_id : null,
            'visible_to_user_id' => $reminder->isPersonal() ? $creator->id : null,
            'content' => $content,
            'system_meta' => ['reminder_id' => (int) $reminder->id, 'reminder_fired' => true],
        ]);

        $next = $this->nextOccurrence($reminder);
        $reminder->update([
            'status' => $next ? Reminder::PENDING : Reminder::SENT,
            'remind_at' => $next ?? $reminder->remind_at,
            'last_message_id' => $message->id,
            'sent_count' => $reminder->sent_count + 1,
        ]);

        event(new MessageSent((int) $group->id, $this->presenter->present($message, 'Normi AI'), $reminder->isPersonal() ? (int) $creator->id : null));
        $this->changed($reminder);
        $this->refreshAnswer($reminder);

        return true;
    }

    /** Jadwal berikutnya untuk pengingat berulang, melewati jadwal yang terlewat (mis. server sempat mati). */
    private function nextOccurrence(Reminder $reminder): ?CarbonImmutable
    {
        if ($reminder->repeat === 'none') {
            return null;
        }
        $zone = UserTime::zone($reminder->creator);
        $next = CarbonImmutable::instance($reminder->remind_at)->setTimezone($zone);
        do {
            $next = $reminder->repeat === 'daily' ? $next->addDay() : $next->addWeek();
        } while ($next->lessThanOrEqualTo(now()));

        return $next->setTimezone(config('app.timezone'));
    }

    private function changed(Reminder $reminder): void
    {
        event(new GroupDataChanged((int) $reminder->group_id, GroupDataChanged::REMINDERS, $reminder->isPersonal() ? (int) $reminder->created_by : null));
    }

    /** Jawaban Normi yang membuat pengingat ini ikut berubah (tombol "Batalkan" hilang setelah dibatalkan/terkirim). */
    private function refreshAnswer(Reminder $reminder): void
    {
        $answer = Message::query()->where('group_id', $reminder->group_id)->where('sender_type', 'ai')
            ->where('system_meta->reminder_id', $reminder->id)->whereNull('system_meta->reminder_fired')->first();
        if ($answer) {
            event(new \App\Events\MessageUpdated((int) $answer->group_id, $this->presenter->present($answer, 'Normi AI')));
        }
    }
}
