<?php

namespace App\Services\Chat;

use App\Events\GroupDataChanged;
use App\Models\Group;
use App\Models\GroupPin;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sematan pesan (docs/rancangan-fitur-produktivitas-nov.md bagian 5): semua anggota aktif boleh menyematkan dan
 * melepas; maksimal MAX, sematan baru menggeser yang paling lama. Pesan yang dihapus untuk semua tidak ikut tampil.
 */
class GroupPins
{
    public const MAX = 5;

    public function __construct(private readonly SystemMessages $system)
    {
    }

    public function pin(Group $group, Message $message, User $user): void
    {
        abort_unless((int) $message->group_id === (int) $group->id, 404);
        abort_if($message->trashed() || $message->isNotice() || $message->isSystem(), 422, 'Pesan ini tidak bisa disematkan.');

        $created = DB::transaction(function () use ($group, $message, $user) {
            $pin = GroupPin::firstOrCreate(['group_id' => $group->id, 'message_id' => $message->id], ['pinned_by' => $user->id]);
            if (! $pin->wasRecentlyCreated) {
                return false;
            }
            $extra = GroupPin::query()->where('group_id', $group->id)->orderByDesc('id')->skip(self::MAX)->take(100)->pluck('id');
            if ($extra->isNotEmpty()) {
                GroupPin::query()->whereIn('id', $extra)->delete();
            }

            return true;
        });

        if ($created) {
            // Tanpa cuplikan isi: anggota yang riwayatnya belum dikirim tidak boleh membaca pesan lama lewat sini.
            $this->system->record($group, SystemMessages::MESSAGE_PINNED, $user, null, ['message_id' => (int) $message->id]);
            event(new GroupDataChanged((int) $group->id, GroupDataChanged::PINS));
        }
    }

    public function unpin(Group $group, int $messageId): void
    {
        if (GroupPin::query()->where('group_id', $group->id)->where('message_id', $messageId)->delete() > 0) {
            event(new GroupDataChanged((int) $group->id, GroupDataChanged::PINS));
        }
    }

    /** Pesan dihapus untuk semua: sematannya ikut lepas. */
    public function forgetMessage(Message $message): void
    {
        $this->unpin(Group::findOrFail($message->group_id), (int) $message->id);
    }

    /**
     * Sematan yang boleh dilihat $viewer, terbaru dulu.
     *
     * @return list<array{message_id: int, sender_name: string, preview: string, type: string, pinned_by: string}>
     */
    public function listFor(Group $group, User $viewer): array
    {
        $pins = GroupPin::query()->where('group_id', $group->id)->with('pinner:id,name')->orderByDesc('id')->get();
        if ($pins->isEmpty()) {
            return [];
        }
        $messages = Message::query()
            ->where('group_id', $group->id)
            ->whereIn('id', $pins->pluck('message_id'))
            ->visibleTo($viewer)
            ->with('sender:id,name')
            ->get()
            ->keyBy('id');

        return $pins
            ->filter(fn (GroupPin $pin) => $messages->has($pin->message_id))
            ->map(function (GroupPin $pin) use ($messages) {
                /** @var Message $message */
                $message = $messages->get($pin->message_id);

                return [
                    'message_id' => (int) $message->id,
                    'sender_name' => $message->sender_type === 'ai' ? 'Normi AI' : ($message->sender?->name ?? 'Anggota'),
                    'preview' => Str::limit(MessageSummary::of($message), 160),
                    'type' => (string) ($message->message_type ?? 'text'),
                    'pinned_by' => (string) ($pin->pinner?->name ?? 'Anggota'),
                ];
            })
            ->values()
            ->all();
    }
}
