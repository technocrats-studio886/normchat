<?php

namespace App\Services\Profile;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Support\Bytes;

/** Total ukuran lampiran yang dikirim seorang user, per jenis dan per grup. */
class StorageUsage
{
    public function summaryFor(User $user): array
    {
        $buckets = ['image' => ['bytes' => 0, 'count' => 0], 'video' => ['bytes' => 0, 'count' => 0], 'file' => ['bytes' => 0, 'count' => 0]];
        foreach ($this->rows($user, ['attachment_mime']) as $row) {
            $kind = self::kindOf((string) $row->attachment_mime);
            $buckets[$kind]['bytes'] += (int) $row->bytes;
            $buckets[$kind]['count'] += (int) $row->count;
        }

        $total = array_sum(array_column($buckets, 'bytes'));
        $labels = ['image' => 'Gambar', 'video' => 'Video', 'file' => 'File'];

        return [
            'total_bytes' => $total,
            'total_human' => Bytes::human($total),
            'breakdown' => array_values(array_map(fn (string $kind) => [
                'label' => $labels[$kind],
                'bytes' => $buckets[$kind]['bytes'],
                'human' => Bytes::human($buckets[$kind]['bytes']),
                'count' => $buckets[$kind]['count'],
            ], array_keys($buckets))),
        ];
    }

    /** Per grup, diurutkan dari yang paling besar. */
    public function perGroupFor(User $user): array
    {
        $rows = $this->rows($user, ['group_id', 'attachment_mime']);
        $groupNames = Group::query()->whereIn('id', $rows->pluck('group_id')->unique()->filter())->pluck('name', 'id');

        $perGroup = [];
        foreach ($rows as $row) {
            $groupId = (int) $row->group_id;
            $perGroup[$groupId] ??= [
                'id' => $groupId,
                'name' => $groupNames[$groupId] ?? 'Grup telah dihapus',
                'image_bytes' => 0,
                'video_bytes' => 0,
                'file_bytes' => 0,
                'total_bytes' => 0,
                'total_count' => 0,
            ];

            $perGroup[$groupId][self::kindOf((string) $row->attachment_mime) . '_bytes'] += (int) $row->bytes;
            $perGroup[$groupId]['total_bytes'] += (int) $row->bytes;
            $perGroup[$groupId]['total_count'] += (int) $row->count;
        }

        usort($perGroup, fn ($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        return array_map(fn (array $group) => $group + [
            'total_human' => Bytes::human($group['total_bytes']),
            'image_human' => Bytes::human($group['image_bytes']),
            'video_human' => Bytes::human($group['video_bytes']),
            'file_human' => Bytes::human($group['file_bytes']),
        ], $perGroup);
    }

    private function rows(User $user, array $groupBy): \Illuminate\Support\Collection
    {
        return Message::query()
            ->where('sender_id', $user->id)
            ->whereNotNull('attachment_size')
            ->selectRaw(implode(', ', $groupBy) . ', SUM(attachment_size) as bytes, COUNT(*) as count')
            ->groupBy($groupBy)
            ->get();
    }

    private static function kindOf(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            default => 'file',
        };
    }
}
