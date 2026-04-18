<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class ProductDataController extends Controller
{
    /**
     * GET /api/product/data
     *
     * Returns Normchat user data for Interdotz product profile page.
     */
    public function show(Request $request): JsonResponse
    {
        $interdotzUserId = $request->query('userId', $request->query('user_id', ''));
        $app = $this->appMeta();
        $topups = $this->getTopupPackages();

        if (! $interdotzUserId) {
            return response()->json($this->buildSnapshotResponse(
                $app,
                [
                    'id' => null,
                    'name' => null,
                    'email' => null,
                    'avatar_url' => null,
                    'avatarUrl' => null,
                    'app_logo_url' => $app['logo_url'],
                    'appLogoUrl' => $app['logo_url'],
                    'joined_at' => null,
                    'joinedAt' => null,
                    'total_groups_owned' => 0,
                    'totalGroupsOwned' => 0,
                    'total_groups_member' => 0,
                    'totalGroupsMember' => 0,
                ],
                ['groups' => []],
                $topups,
                [],
                'success',
                'ok'
            ));
        }

        if (! $this->isAuthorizedClientRequest($request)) {
            return response()->json([
                'message' => 'unauthorized interdotz request',
                'hint' => 'Provide valid X-Client-Id and X-Client-Secret headers, or a bearer token from Interdotz.',
            ], 401);
        }

        $user = User::query()
            ->where(function ($query) use ($interdotzUserId) {
                $query->where('provider_user_id', (string) $interdotzUserId)
                    ->orWhere('interdotz_id', (string) $interdotzUserId)
                    ->orWhere('id', (int) $interdotzUserId);
            })
            ->first();

        if (! $user) {
            return response()->json($this->buildSnapshotResponse(
                $app,
                [
                    'id' => (string) $interdotzUserId,
                    'name' => null,
                    'email' => null,
                    'avatar_url' => null,
                    'avatarUrl' => null,
                    'app_logo_url' => $app['logo_url'],
                    'appLogoUrl' => $app['logo_url'],
                    'joined_at' => null,
                    'joinedAt' => null,
                    'total_groups_owned' => 0,
                    'totalGroupsOwned' => 0,
                    'total_groups_member' => 0,
                    'totalGroupsMember' => 0,
                ],
                ['groups' => []],
                $topups,
                [],
                'success',
                'user not linked'
            ));
        }

        $ownedGroups = Group::where('owner_id', $user->id)
            ->where('status', 'active')
            ->with('groupToken')
            ->get();

        $memberGroups = Group::whereHas('members', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('status', 'active');
        })->where('status', 'active')->with('groupToken')->get();

        $allGroups = $ownedGroups->merge($memberGroups)->unique('id');

        $profile = [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'avatarUrl' => $user->avatar_url,
            'app_logo_url' => $app['logo_url'],
            'appLogoUrl' => $app['logo_url'],
            'joined_at' => optional($user->created_at)->toIso8601String(),
            'joinedAt' => optional($user->created_at)->toIso8601String(),
            'total_groups_owned' => $ownedGroups->count(),
            'totalGroupsOwned' => $ownedGroups->count(),
            'total_groups_member' => $memberGroups->count(),
            'totalGroupsMember' => $memberGroups->count(),
        ];

        $sections = [
            'groups' => $allGroups->map(function ($group) use ($user) {
                $isOwner = (int) $group->owner_id === (int) $user->id;
                $credits = $group->groupToken
                    ? round($group->groupToken->remaining_tokens / 2500, 1)
                    : 0;

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'role' => $isOwner ? 'owner' : 'member',
                    'normkredit_remaining' => $credits,
                    'normkreditRemaining' => $credits,
                    'created_at' => optional($group->created_at)->toIso8601String(),
                    'createdAt' => optional($group->created_at)->toIso8601String(),
                ];
            })->values(),
        ];

        $transactions = GroupTokenContribution::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'group_id' => $c->group_id,
                'groupId' => $c->group_id,
                'source' => $c->source,
                'normkredits' => round($c->token_amount / 2500, 1),
                'du_paid' => $c->price_paid,
                'duPaid' => $c->price_paid,
                'reference' => $c->payment_reference,
                'created_at' => optional($c->created_at)->toIso8601String(),
                'createdAt' => optional($c->created_at)->toIso8601String(),
            ]);

        return response()->json($this->buildSnapshotResponse(
            $app,
            $profile,
            $sections,
            $topups,
            $transactions->all(),
            'success',
            'ok'
        ));
    }

    /**
     * GET /api/product/topup-packages
     */
    public function topupPackages(): JsonResponse
    {
        $packages = $this->getTopupPackages();

        return response()->json([
            'packages' => $packages,
            'app' => $this->appMeta(),
            'message' => 'ok',
            'payload' => ['packages' => $packages],
        ]);
    }

    private function appMeta(): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $logoUrl = trim((string) config('normchat.product_logo_url', ''));
        $iconUrl = trim((string) config('normchat.product_icon_url', ''));

        if ($logoUrl === '') {
            $logoUrl = $baseUrl.'/normchat-logo.png';
        } elseif (Str::startsWith($logoUrl, '/')) {
            $logoUrl = $baseUrl.$logoUrl;
        }

        if ($iconUrl === '') {
            $iconUrl = $baseUrl.'/icons/icon-192.png';
        } elseif (Str::startsWith($iconUrl, '/')) {
            $iconUrl = $baseUrl.$iconUrl;
        }
        $marketingPath = '/'.trim((string) config('app.normchat_marketing_path', '/normchat'), '/');

        return [
            'name' => 'Normchat',
            'base_url' => $baseUrl,
            'landing_url' => rtrim((string) config('app.normchat_app_url', $baseUrl), '/').$marketingPath,
            'logo_url' => $logoUrl,
            'icon_url' => $iconUrl,
        ];
    }

    private function buildSnapshotResponse(
        array $app,
        array $profile,
        array $sections,
        array $topups,
        array $transactions,
        string $fetchStatus,
        string $message
    ): array {
        $snapshot = [
            'client_id' => (string) config('services.interdotz.client_id', 'normchat'),
            'client_name' => $app['name'],
            'base_url' => $app['base_url'],
            'logo_url' => $app['logo_url'],
            'icon_url' => $app['icon_url'],
            'last_fetched_at' => now()->toIso8601String(),
            'fetch_status' => $fetchStatus,
            'app' => $app,
            'profile' => $profile,
            'sections' => $sections,
            'topups' => $topups,
            'transactions' => $transactions,
        ];

        return [
            ...$snapshot,
            'message' => $message,
            'payload' => $snapshot,
        ];
    }

    private function isAuthorizedClientRequest(Request $request): bool
    {
        $expectedClientId = trim((string) Config::get('services.interdotz.client_id'));
        $expectedClientSecret = trim((string) Config::get('services.interdotz.client_secret'));
        $allowBearerOnly = (bool) Config::get('normchat.allow_interdotz_bearer_only', true);

        if ($expectedClientId === '' && $expectedClientSecret === '') {
            return true;
        }

        $incomingClientId = trim((string) (
            $request->header('X-Client-Id')
            ?? $request->query('clientId')
            ?? $request->query('client_id')
            ?? ''
        ));
        $incomingClientSecret = trim((string) (
            $request->header('X-Client-Secret')
            ?? $request->query('clientSecret')
            ?? $request->query('client_secret')
            ?? ''
        ));
        $bearerToken = trim((string) $request->bearerToken());

        $clientIdMatches = $expectedClientId !== ''
            && $incomingClientId !== ''
            && hash_equals($expectedClientId, $incomingClientId);

        $clientSecretMatches = $expectedClientSecret !== ''
            && $incomingClientSecret !== ''
            && hash_equals($expectedClientSecret, $incomingClientSecret);

        if ($clientIdMatches && $clientSecretMatches) {
            return true;
        }

        if ($clientIdMatches && $bearerToken !== '') {
            return true;
        }

        return $allowBearerOnly && $bearerToken !== '';
    }

    private function getTopupPackages(): array
    {
        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);
        $idr12 = (int) config('normchat.idr_topup_12nk', 35000);
        $idr24 = (int) config('normchat.idr_topup_24nk', 70000);
        $idr48 = (int) config('normchat.idr_topup_48nk', 140000);
        $idr100 = (int) config('normchat.idr_topup_100nk', 280000);

        return [
            [
                'id' => 'nk_12',
                'name' => '12 Normkredit',
                'normkredits' => 12,
                'du_price' => $duPer12Nk,
                'idr_price' => $idr12,
                'description' => '12 normkredit',
            ],
            [
                'id' => 'nk_24',
                'name' => '24 Normkredit',
                'normkredits' => 24,
                'du_price' => $duPer12Nk * 2,
                'idr_price' => $idr24,
                'description' => '24 normkredit',
            ],
            [
                'id' => 'nk_48',
                'name' => '48 Normkredit',
                'normkredits' => 48,
                'du_price' => $duPer12Nk * 4,
                'idr_price' => $idr48,
                'description' => '48 normkredit',
            ],
            [
                'id' => 'nk_100',
                'name' => '100 Normkredit',
                'normkredits' => 100,
                'du_price' => (int) ceil(($duPer12Nk / 12) * 100),
                'idr_price' => $idr100,
                'description' => '100 normkredit',
            ],
        ];
    }
}
