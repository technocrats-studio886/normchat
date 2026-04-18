<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = Auth::user();
        $storage = $this->computeStorageUsage((int) $user->id);

        $loginCount = AuditLog::query()
            ->where('actor_id', $user->id)
            ->whereIn('action', ['auth.connect', 'auth.logout'])
            ->count();

        $transactionCount = GroupTokenContribution::query()
            ->where('user_id', $user->id)
            ->count();

        return view('profile.show', [
            'user' => $user,
            'storage' => $storage,
            'username' => $this->resolveUsername($user),
            'loginCount' => $loginCount,
            'transactionCount' => $transactionCount,
        ]);
    }

    public function loginHistory(Request $request): View
    {
        $user = Auth::user();

        $loginRange = (string) $request->query('range', 'week');
        if (! in_array($loginRange, ['week', 'month', '3month', 'all'], true)) {
            $loginRange = 'week';
        }

        $sortDir = (string) $request->query('sort', 'desc');
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = AuditLog::query()
            ->where('actor_id', $user->id)
            ->whereIn('action', ['auth.connect', 'auth.logout']);

        if ($loginRange === 'week') {
            $query->where('created_at', '>=', now()->subWeek());
        } elseif ($loginRange === 'month') {
            $query->where('created_at', '>=', now()->subMonth());
        } elseif ($loginRange === '3month') {
            $query->where('created_at', '>=', now()->subMonths(3));
        }

        $totalCount = (clone $query)->count();

        $loginLogs = $query
            ->orderBy('created_at', $sortDir)
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        // Stats
        $totalAllTime = AuditLog::query()
            ->where('actor_id', $user->id)
            ->where('action', 'auth.connect')
            ->count();

        $lastLogin = AuditLog::query()
            ->where('actor_id', $user->id)
            ->where('action', 'auth.connect')
            ->latest('created_at')
            ->first();

        return view('profile.login-history', [
            'loginLogs' => $loginLogs,
            'loginRange' => $loginRange,
            'sortDir' => $sortDir,
            'totalCount' => $totalCount,
            'totalAllTime' => $totalAllTime,
            'lastLogin' => $lastLogin,
        ]);
    }

    public function transactionHistoryPage(Request $request): View
    {
        $user = Auth::user();

        $range = (string) $request->query('range', 'all');
        if (! in_array($range, ['week', 'month', '3month', 'all'], true)) {
            $range = 'all';
        }

        $source = (string) $request->query('source', 'all');
        $sortDir = (string) $request->query('sort', 'desc');
        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $query = GroupTokenContribution::query()
            ->where('user_id', $user->id)
            ->with('group:id,name');

        if ($range === 'week') {
            $query->where('created_at', '>=', now()->subWeek());
        } elseif ($range === 'month') {
            $query->where('created_at', '>=', now()->subMonth());
        } elseif ($range === '3month') {
            $query->where('created_at', '>=', now()->subMonths(3));
        }

        if ($source !== 'all') {
            $sourceGroups = match($source) {
                'patungan' => ['patungan', 'patungan_midtrans'],
                'topup' => ['topup', 'topup_midtrans', 'interdotz_topup', 'interdotz_charge_topup'],
                'group_creation' => ['group_creation', 'group_creation_midtrans'],
                default => [],
            };
            if (! empty($sourceGroups)) {
                $query->whereIn('source', $sourceGroups);
            }
        }

        $totalCount = (clone $query)->count();

        $transactions = $query
            ->orderBy('created_at', $sortDir)
            ->paginate(15, ['*'], 'page')
            ->withQueryString();

        // Summary stats
        $allUserTx = GroupTokenContribution::query()->where('user_id', $user->id);
        $totalSpentDu = (clone $allUserTx)
            ->whereNotIn('source', ['patungan_midtrans', 'topup_midtrans', 'group_creation_midtrans'])
            ->sum('price_paid');
        $totalSpentIdr = (clone $allUserTx)
            ->whereIn('source', ['patungan_midtrans', 'topup_midtrans', 'group_creation_midtrans'])
            ->sum('price_paid');
        $totalTokensEarned = (clone $allUserTx)->sum('token_amount');
        $totalAllTime = $allUserTx->count();

        return view('profile.transactions', [
            'transactions' => $transactions,
            'range' => $range,
            'source' => $source,
            'sortDir' => $sortDir,
            'totalCount' => $totalCount,
            'totalAllTime' => $totalAllTime,
            'totalSpentDu' => (int) $totalSpentDu,
            'totalSpentIdr' => (int) $totalSpentIdr,
            'totalTokensEarned' => (int) $totalTokensEarned,
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

    public function security(): RedirectResponse
    {
        return redirect()->route('profile.show');
    }

    public function account(): View
    {
        $user = Auth::user();

        return view('profile.account', [
            'user' => $user,
            'username' => $this->resolveUsername($user),
        ]);
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $updatePayload = [
            'name' => trim((string) $validated['name']),
        ];

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $newAvatarUrl = Storage::disk('public')->url($path);

            $oldAvatar = (string) ($user->avatar_url ?? '');
            $oldPath = $this->resolveLocalAvatarPath($oldAvatar);
            if ($oldPath !== null && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $updatePayload['avatar_url'] = $newAvatarUrl;
        }

        $user->update($updatePayload);

        return redirect()->route('profile.account')
            ->with('success', 'Akun berhasil diperbarui.');
    }

    public function storage(): View
    {
        $user = Auth::user();
        $storage = $this->computeStorageUsage((int) $user->id);

        $perGroupRows = Message::query()
            ->where('sender_id', $user->id)
            ->whereNotNull('attachment_size')
            ->selectRaw('group_id, attachment_mime, SUM(attachment_size) as bytes, COUNT(*) as count')
            ->groupBy('group_id', 'attachment_mime')
            ->get();

        $groupIds = $perGroupRows->pluck('group_id')->unique()->filter()->values();
        $groups = Group::query()->whereIn('id', $groupIds)->get(['id', 'name'])->keyBy('id');

        $perGroup = [];
        foreach ($perGroupRows as $row) {
            $gid = (int) $row->group_id;
            if (! isset($perGroup[$gid])) {
                $perGroup[$gid] = [
                    'id' => $gid,
                    'name' => $groups[$gid]->name ?? 'Grup telah dihapus',
                    'image_bytes' => 0,
                    'video_bytes' => 0,
                    'file_bytes' => 0,
                    'total_bytes' => 0,
                    'total_count' => 0,
                ];
            }
            $mime = (string) ($row->attachment_mime ?? '');
            $bytes = (int) $row->bytes;
            $count = (int) $row->count;

            if (str_starts_with($mime, 'image/')) {
                $perGroup[$gid]['image_bytes'] += $bytes;
            } elseif (str_starts_with($mime, 'video/')) {
                $perGroup[$gid]['video_bytes'] += $bytes;
            } else {
                $perGroup[$gid]['file_bytes'] += $bytes;
            }
            $perGroup[$gid]['total_bytes'] += $bytes;
            $perGroup[$gid]['total_count'] += $count;
        }

        usort($perGroup, fn ($a, $b) => $b['total_bytes'] <=> $a['total_bytes']);

        foreach ($perGroup as &$row) {
            $row['total_human'] = $this->humanBytes($row['total_bytes']);
            $row['image_human'] = $this->humanBytes($row['image_bytes']);
            $row['video_human'] = $this->humanBytes($row['video_bytes']);
            $row['file_human'] = $this->humanBytes($row['file_bytes']);
        }
        unset($row);

        return view('profile.storage', [
            'user' => $user,
            'storage' => $storage,
            'perGroup' => $perGroup,
        ]);
    }

    public function activity(): RedirectResponse
    {
        return redirect()->route('profile.login-history');
    }

    private function resolveUsername($user): string
    {
        $username = (string) ($user->username ?? '');
        if ($username !== '') {
            return $username;
        }

        $providerId = (string) ($user->provider_user_id ?? $user->interdotz_id ?? '');
        if ($providerId !== '') {
            $cleaned = preg_replace('/[^A-Za-z0-9._-]/', '', $providerId);

            return strtolower(substr((string) $cleaned, 0, 40));
        }

        $email = (string) ($user->email ?? '');
        $emailPrefix = strstr($email, '@', true);
        if (is_string($emailPrefix) && $emailPrefix !== '') {
            $cleaned = preg_replace('/[^A-Za-z0-9._-]/', '', $emailPrefix);

            return strtolower(substr((string) $cleaned, 0, 40));
        }

        return 'user' . (string) ($user->id ?? '');
    }

    private function resolveLocalAvatarPath(string $avatarUrl): ?string
    {
        $path = parse_url($avatarUrl, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $prefix = '/storage/';
        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        return ltrim(substr($path, strlen($prefix)), '/');
    }

}
