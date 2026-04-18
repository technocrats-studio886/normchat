<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = Auth::user();

        $subscriptions = Subscription::query()
            ->whereHas('group.members', fn ($query) => $query->where('user_id', $user->id))
            ->orWhereHas('group', fn ($query) => $query->where('owner_id', $user->id))
            ->with('group')
            ->latest()
            ->get();

        $storage = $this->computeStorageUsage((int) $user->id);

        return view('profile.show', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'storage' => $storage,
        ]);
    }

    private function computeStorageUsage(int $userId): array
    {
        $rows = Message::query()
            ->where('sender_id', $userId)
            ->whereNotNull('attachment_size')
            ->selectRaw('attachment_mime, SUM(attachment_size) as bytes, COUNT(*) as count')
            ->groupBy('attachment_mime')
            ->get();

        $buckets = ['image' => 0, 'video' => 0, 'file' => 0];
        $counts = ['image' => 0, 'video' => 0, 'file' => 0];

        foreach ($rows as $row) {
            $mime = (string) ($row->attachment_mime ?? '');
            $bytes = (int) $row->bytes;
            $count = (int) $row->count;

            if (str_starts_with($mime, 'image/')) {
                $buckets['image'] += $bytes;
                $counts['image'] += $count;
            } elseif (str_starts_with($mime, 'video/')) {
                $buckets['video'] += $bytes;
                $counts['video'] += $count;
            } else {
                $buckets['file'] += $bytes;
                $counts['file'] += $count;
            }
        }

        $total = array_sum($buckets);

        return [
            'total_bytes' => $total,
            'total_human' => $this->humanBytes($total),
            'breakdown' => [
                ['label' => 'Gambar', 'bytes' => $buckets['image'], 'human' => $this->humanBytes($buckets['image']), 'count' => $counts['image']],
                ['label' => 'Video', 'bytes' => $buckets['video'], 'human' => $this->humanBytes($buckets['video']), 'count' => $counts['video']],
                ['label' => 'File', 'bytes' => $buckets['file'], 'human' => $this->humanBytes($buckets['file']), 'count' => $counts['file']],
            ],
        ];
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min(count($units) - 1, (int) floor(log($bytes, 1024)));
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 1, ',', '.').' '.$units[$power];
    }

    public function security(): View
    {
        $user = Auth::user();

        return view('profile.security', [
            'user' => $user,
        ]);
    }

    public function activity(): View
    {
        $user = Auth::user();

        $loginLogs = AuditLog::query()
            ->where('actor_id', $user->id)
            ->whereIn('action', ['auth.connect', 'auth.login'])
            ->orderByDesc('created_at')
            ->simplePaginate(10, ['*'], 'login_page')
            ->withQueryString();

        $paymentHistory = GroupTokenContribution::query()
            ->where('user_id', $user->id)
            ->with(['group:id,name'])
            ->orderByDesc('created_at')
            ->simplePaginate(10, ['*'], 'payment_page')
            ->withQueryString();

        return view('profile.activity', [
            'user' => $user,
            'loginLogs' => $loginLogs,
            'paymentHistory' => $paymentHistory,
        ]);
    }
}
