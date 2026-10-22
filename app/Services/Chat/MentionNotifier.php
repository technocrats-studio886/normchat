<?php

namespace App\Services\Chat;

use App\Events\UserMentioned;
use App\Models\Group;
use App\Models\Message;
use App\Models\User;

/**
 * Mention @nama di chat. Satu nama punya beberapa bentuk handle
 * ("Budi Santoso" → @budi_santoso, @budi.santoso, @budisantoso, @budi santoso).
 */
class MentionNotifier
{
    /** @return list<string> handle yang disebut (tanpa mention AI) */
    public function handlesIn(string $content): array
    {
        if (! preg_match_all('/@([\p{L}\p{N}_.]+)/u', $content, $matches)) {
            return [];
        }

        $aiHandles = array_map(fn (string $mention) => ltrim($mention, '@'), Message::AI_MENTIONS);

        return array_values(array_unique(array_filter(
            array_map(fn ($handle) => strtolower(trim($handle)), $matches[1]),
            fn ($handle) => $handle !== '' && ! in_array($handle, $aiHandles, true)
        )));
    }

    /**
     * Kirim notifikasi ke anggota yang disebut (bukan pengirim). $onlyHandles membatasi ke
     * handle tertentu, mis. hanya mention baru setelah pesan diedit.
     */
    public function notify(Group $group, Message $message, User $sender, string $content, ?array $onlyHandles = null): void
    {
        $handles = $this->handlesIn($content);
        if ($onlyHandles !== null) {
            $handles = array_values(array_intersect($handles, $onlyHandles));
        }
        if ($handles === []) {
            return;
        }

        $userIdsByHandle = [];
        foreach ($this->participants($group) as $user) {
            foreach ($this->handlesFor((string) $user->name) as $handle) {
                $userIdsByHandle[$handle] = (int) $user->id;
            }
        }

        $targets = array_unique(array_filter(
            array_map(fn ($handle) => $userIdsByHandle[$handle] ?? null, $handles),
            fn ($userId) => $userId !== null && $userId !== (int) $sender->id
        ));

        foreach ($targets as $userId) {
            event(new UserMentioned($userId, [
                'group_id' => (int) $group->id,
                'group_name' => (string) $group->name,
                'message_id' => (int) $message->id,
                'sender_id' => (int) $sender->id,
                'sender_name' => (string) $sender->name,
                'content' => mb_substr($content, 0, 240),
                'chat_url' => route('chat.show', $group) . '#message-' . $message->id,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ]));
        }
    }

    /** Saran mention untuk composer: Normi AI lalu anggota lain. */
    public function suggestionsFor(Group $group, User $viewer): array
    {
        $users = $this->participants($group)
            ->reject(fn (User $user) => (int) $user->id === (int) $viewer->id)
            ->map(fn (User $user) => [
                'type' => 'user',
                'user_id' => (int) $user->id,
                'label' => (string) $user->name,
                'insert' => '@' . strtolower(str_replace(' ', '_', (string) $user->name)),
            ])
            ->values()
            ->all();

        return [['type' => 'ai', 'label' => 'Normi AI', 'insert' => '@normi'], ...$users];
    }

    /** @return list<string> */
    private function handlesFor(string $name): array
    {
        $base = strtolower(trim($name));
        $base = trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[^\p{L}\p{N} ]+/u', '', $base)));
        if ($base === '') {
            return [];
        }

        return array_values(array_unique([
            str_replace(' ', '_', $base),
            str_replace(' ', '.', $base),
            str_replace(' ', '', $base),
            $base,
        ]));
    }

    /** @return \Illuminate\Support\Collection<int, User> owner + anggota aktif */
    private function participants(Group $group): \Illuminate\Support\Collection
    {
        $group->loadMissing(['owner', 'members' => fn ($query) => $query->active()->with('user')]);

        return collect([$group->owner])
            ->merge($group->members->where('status', 'active')->pluck('user'))
            ->filter()
            ->unique('id')
            ->values();
    }
}
