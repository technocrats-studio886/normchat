<?php

namespace App\Services\Chat;

use App\Events\GroupDataChanged;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupTask;
use App\Models\Message;
use App\Models\User;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Tugas dan checklist bersama (docs/rancangan-fitur-produktivitas-nov.md bagian 3). Semua anggota aktif boleh
 * menambah dan mencentang; menghapus hanya pembuat tugas atau moderator.
 */
class GroupTasks
{
    /** Tugas selesai yang masih ditampilkan di daftar. */
    private const DONE_SHOWN = 30;

    private const OPEN_MAX = 200;

    /**
     * @param  array{title: string, assignee_id?: ?int, due_date?: ?string, source_message_id?: ?int}  $data
     */
    public function create(Group $group, User $user, array $data, bool $broadcast = true): GroupTask
    {
        $title = Str::limit(trim((string) preg_replace('/\s+/u', ' ', $data['title'])), GroupTask::TITLE_MAX - 3, '…');
        abort_if($title === '', 422, 'Judul tugas masih kosong.');
        abort_if(GroupTask::query()->where('group_id', $group->id)->whereNull('done_at')->count() >= self::OPEN_MAX, 422, 'Tugas yang belum selesai sudah terlalu banyak. Selesaikan atau hapus beberapa dulu.');

        $task = GroupTask::create([
            'group_id' => $group->id,
            'title' => $title,
            'created_by' => $user->id,
            'assignee_id' => $this->validAssignee($group, $data['assignee_id'] ?? null),
            'due_date' => $data['due_date'] ?? null,
            'source_message_id' => $this->validSource($group, $user, $data['source_message_id'] ?? null),
        ]);
        if ($broadcast) {
            $this->changed($group);
        }

        return $task;
    }

    /** @param  array{done?: bool, title?: string, assignee_id?: ?int, due_date?: ?string}  $data */
    public function update(Group $group, GroupTask $task, User $user, array $data): GroupTask
    {
        $changes = [];
        if (array_key_exists('done', $data)) {
            $changes += $data['done']
                ? ($task->isDone() ? [] : ['done_at' => now(), 'done_by' => $user->id])
                : ['done_at' => null, 'done_by' => null];
        }
        if (array_key_exists('title', $data)) {
            $title = Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) $data['title'])), GroupTask::TITLE_MAX - 3, '…');
            abort_if($title === '', 422, 'Judul tugas masih kosong.');
            $changes['title'] = $title;
        }
        if (array_key_exists('assignee_id', $data)) {
            $changes['assignee_id'] = $this->validAssignee($group, $data['assignee_id']);
        }
        if (array_key_exists('due_date', $data)) {
            $changes['due_date'] = $data['due_date'];
        }
        if ($changes !== []) {
            $task->update($changes);
            $this->changed($group);
        }

        return $task->refresh();
    }

    public function canDelete(Group $group, GroupTask $task, User $user): bool
    {
        return (int) $task->created_by === (int) $user->id || $group->isModerator($user);
    }

    public function delete(Group $group, GroupTask $task): void
    {
        $task->delete();
        $this->changed($group);
    }

    public function changed(Group $group): void
    {
        event(new GroupDataChanged((int) $group->id, GroupDataChanged::TASKS));
    }

    /** @return array{open: list<array>, done: list<array>, done_total: int, members: list<array{id: int, name: string}>} */
    public function listFor(Group $group, User $viewer): array
    {
        $open = GroupTask::query()->where('group_id', $group->id)->whereNull('done_at')
            ->with(['assignee:id,name', 'creator:id,name'])
            ->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('id')
            ->limit(self::OPEN_MAX)->get();
        $doneQuery = GroupTask::query()->where('group_id', $group->id)->whereNotNull('done_at');
        $done = (clone $doneQuery)->with(['assignee:id,name', 'creator:id,name', 'finisher:id,name'])
            ->orderByDesc('done_at')->limit(self::DONE_SHOWN)->get();
        $visibleSources = $this->visibleSourceIds($group, $viewer, $open->merge($done));

        return [
            'open' => $open->map(fn (GroupTask $task) => $this->present($group, $task, $viewer, $visibleSources))->all(),
            'done' => $done->map(fn (GroupTask $task) => $this->present($group, $task, $viewer, $visibleSources))->all(),
            'done_total' => (clone $doneQuery)->count(),
            'members' => $this->members($group),
        ];
    }

    /** Teks daftar tugas terbuka untuk Normi (rekap mingguan). */
    public function openSummary(Group $group, int $limit = 30): string
    {
        return GroupTask::query()->where('group_id', $group->id)->whereNull('done_at')
            ->with('assignee:id,name')->orderByRaw('due_date is null')->orderBy('due_date')->limit($limit)->get()
            ->map(fn (GroupTask $task) => '- ' . $task->title
                . ($task->assignee ? ' (' . $task->assignee->name . ')' : '')
                . ($task->due_date ? ', tenggat ' . $task->due_date->translatedFormat('l j M') : ''))
            ->implode("\n");
    }

    /** @return list<array{id: int, name: string}> anggota aktif (dan pemilik) yang bisa diberi tugas */
    public function members(Group $group): array
    {
        $ids = GroupMember::query()->active()->where('group_id', $group->id)->pluck('user_id')->push($group->owner_id)->filter()->unique();

        return User::query()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => (int) $user->id, 'name' => (string) $user->name])->values()->all();
    }

    /** Cocokkan nama dari Normi ("Budi", "budi santoso", "@budi_s") ke anggota; null bila tidak yakin. */
    public function matchMember(Group $group, ?string $name): ?int
    {
        $needle = Str::lower(trim(ltrim((string) $name, '@')));
        $needle = str_replace('_', ' ', $needle);
        if ($needle === '' || in_array($needle, ['semua', 'semua anggota', 'grup', 'kita'], true)) {
            return null;
        }
        $members = collect($this->members($group));
        $exact = $members->first(fn (array $member) => Str::lower($member['name']) === $needle);
        if ($exact) {
            return $exact['id'];
        }
        $partial = $members->filter(fn (array $member) => Str::startsWith(Str::lower($member['name']), $needle)
            || in_array($needle, explode(' ', Str::lower($member['name'])), true));

        return $partial->count() === 1 ? $partial->first()['id'] : null;
    }

    private function validAssignee(Group $group, mixed $id): ?int
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return in_array($id, array_column($this->members($group), 'id'), true) ? $id : null;
    }

    private function validSource(Group $group, User $user, mixed $id): ?int
    {
        $id = (int) $id;

        return $id > 0 && Message::query()->where('group_id', $group->id)->whereKey($id)->visibleTo($user)->exists() ? $id : null;
    }

    /** @param  Collection<int, GroupTask>  $tasks  @return array<int, true> */
    private function visibleSourceIds(Group $group, User $viewer, Collection $tasks): array
    {
        $ids = $tasks->pluck('source_message_id')->filter()->unique();
        if ($ids->isEmpty()) {
            return [];
        }

        return Message::query()->where('group_id', $group->id)->whereIn('id', $ids)->visibleTo($viewer)->pluck('id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /** @param  array<int, true>  $visibleSources */
    private function present(Group $group, GroupTask $task, User $viewer, array $visibleSources): array
    {
        $due = $task->due_date ? CarbonImmutable::parse($task->due_date->toDateString(), UserTime::zone($viewer)) : null;
        $today = CarbonImmutable::now(UserTime::zone($viewer))->startOfDay();

        return [
            'id' => (int) $task->id,
            'title' => (string) $task->title,
            'done' => $task->isDone(),
            'assignee_id' => $task->assignee_id ? (int) $task->assignee_id : null,
            'assignee_name' => $task->assignee?->name,
            'due_date' => $task->due_date?->toDateString(),
            'due_label' => $due ? $this->dueLabel($due, $today) : null,
            'overdue' => $due !== null && ! $task->isDone() && $due < $today,
            'done_by_name' => $task->finisher?->name,
            'creator_name' => $task->creator?->name,
            'source_message_id' => isset($visibleSources[(int) $task->source_message_id]) ? (int) $task->source_message_id : null,
            'can_delete' => $this->canDelete($group, $task, $viewer),
        ];
    }

    private function dueLabel(CarbonImmutable $due, CarbonImmutable $today): string
    {
        $days = (int) $today->diffInDays($due, false);

        return match (true) {
            $days === 0 => 'Hari ini',
            $days === 1 => 'Besok',
            $days === -1 => 'Kemarin',
            $days > 1 && $days < 7 => $due->translatedFormat('l'),
            default => $due->translatedFormat($due->year === $today->year ? 'j M' : 'j M Y'),
        };
    }
}
