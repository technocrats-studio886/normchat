<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\VerifyInterdotzClientRequest;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Data Normi Chat untuk halaman profil produk di Interdotz (nonaktif selama
 * NORMCHAT_INTERDOTZ_ENABLED=false). Setiap kunci snake_case punya kembaran camelCase.
 */
class ProductDataController extends Controller
{
    private const RECENT_TRANSACTIONS = 20;

    /** GET /api/product/data?userId=… */
    public function show(Request $request): JsonResponse
    {
        $interdotzUserId = (string) $request->query('userId', $request->query('user_id', ''));
        if ($interdotzUserId === '') {
            return $this->snapshot($this->profile(null), [], [], 'ok');
        }

        if (! VerifyInterdotzClientRequest::isAuthorized($request)) {
            return response()->json([
                'message' => 'unauthorized interdotz request',
                'hint' => 'Provide valid X-Client-Id and X-Client-Secret headers, or a bearer token from Interdotz.',
            ], 401);
        }

        $user = User::query()
            ->where(fn ($query) => $query
                ->where('provider_user_id', $interdotzUserId)
                ->orWhere('interdotz_id', $interdotzUserId)
                ->orWhere('id', (int) $interdotzUserId))
            ->first();

        if (! $user) {
            return $this->snapshot($this->profile(null, $interdotzUserId), [], [], 'user not linked');
        }

        $ownedGroups = Group::where('owner_id', $user->id)->where('status', 'active')->with('groupToken')->get();
        $memberGroups = Group::where('status', 'active')
            ->whereHas('members', fn ($query) => $query->active()->where('user_id', $user->id))
            ->with('groupToken')
            ->get();

        $groups = $ownedGroups->merge($memberGroups)->unique('id')->map(fn (Group $group) => $this->withCamelCase([
            'id' => $group->id,
            'name' => $group->name,
            'role' => $group->isOwnedBy($user) ? 'owner' : 'member',
            'normkredit_remaining' => $group->groupToken?->credits ?? 0,
            'created_at' => optional($group->created_at)->toIso8601String(),
        ]))->values()->all();

        $transactions = GroupTokenContribution::where('user_id', $user->id)
            ->latest('created_at')
            ->take(self::RECENT_TRANSACTIONS)
            ->get()
            ->map(fn (GroupTokenContribution $contribution) => $this->withCamelCase([
                'id' => $contribution->id,
                'group_id' => $contribution->group_id,
                'source' => $contribution->source,
                'normkredits' => round((float) $contribution->credit_amount, 2),
                'idr_paid' => $contribution->price_paid,
                'reference' => $contribution->payment_reference,
                'created_at' => optional($contribution->created_at)->toIso8601String(),
            ]))
            ->all();

        return $this->snapshot($this->profile($user, null, $ownedGroups->count(), $memberGroups->count()), $groups, $transactions, 'ok');
    }

    /** GET /api/product/topup-packages — Normi Chat tidak menjual paket lewat Interdotz. */
    public function topupPackages(): JsonResponse
    {
        return response()->json([
            'packages' => [],
            'app' => $this->appMeta(),
            'message' => 'ok',
            'payload' => ['packages' => []],
        ]);
    }

    private function profile(?User $user, ?string $unlinkedId = null, int $groupsOwned = 0, int $groupsMember = 0): array
    {
        return $this->withCamelCase([
            'id' => $user ? (string) $user->id : $unlinkedId,
            'name' => $user?->name,
            'email' => $user?->email,
            'avatar_url' => $user?->avatar_url,
            'app_logo_url' => $this->appMeta()['logo_url'],
            'joined_at' => optional($user?->created_at)->toIso8601String(),
            'total_groups_owned' => $groupsOwned,
            'total_groups_member' => $groupsMember,
        ]);
    }

    private function snapshot(array $profile, array $groups, array $transactions, string $message): JsonResponse
    {
        $app = $this->appMeta();
        $snapshot = [
            'client_id' => (string) config('services.interdotz.client_id', 'normchat'),
            'client_name' => $app['name'],
            'base_url' => $app['base_url'],
            'logo_url' => $app['logo_url'],
            'icon_url' => $app['icon_url'],
            'last_fetched_at' => now()->toIso8601String(),
            'fetch_status' => 'success',
            'app' => $app,
            'profile' => $profile,
            'sections' => ['groups' => $groups],
            'topups' => [],
            'transactions' => $transactions,
        ];

        return response()->json([...$snapshot, 'message' => $message, 'payload' => $snapshot]);
    }

    private function appMeta(): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $absolute = function (string $configured, string $default) use ($baseUrl) {
            $url = trim($configured) ?: $default;

            return Str::startsWith($url, '/') ? $baseUrl . $url : $url;
        };

        return [
            'name' => 'Normi Chat',
            'base_url' => $baseUrl,
            'landing_url' => rtrim((string) config('app.normchat_app_url', $baseUrl), '/') . '/' . trim((string) config('app.normchat_marketing_path', '/normchat'), '/'),
            'logo_url' => $absolute((string) config('normchat.product_logo_url', ''), '/normchat-logo.png'),
            'icon_url' => $absolute((string) config('normchat.product_icon_url', ''), '/icons/icon-192.png'),
        ];
    }

    /** Tambahkan kembaran camelCase untuk setiap kunci snake_case (format yang diharapkan Interdotz). */
    private function withCamelCase(array $data): array
    {
        foreach ($data as $key => $value) {
            $data[Str::camel($key)] ??= $value;
        }

        return $data;
    }
}
