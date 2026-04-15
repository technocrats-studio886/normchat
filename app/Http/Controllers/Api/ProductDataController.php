<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        if (! $interdotzUserId) {
            return response()->json(['message' => 'userId required'], 400);
        }

        $user = User::where('provider_user_id', (string) $interdotzUserId)
            ->where('auth_provider', 'interdotz')
            ->first();

        if (! $user) {
            return response()->json(['message' => 'user not found'], 404);
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
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'joined_at' => optional($user->created_at)->toIso8601String(),
            'total_groups_owned' => $ownedGroups->count(),
            'total_groups_member' => $memberGroups->count(),
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
                    'created_at' => optional($group->created_at)->toIso8601String(),
                ];
            })->values(),
        ];

        $topups = $this->getTopupPackages();

        $transactions = GroupTokenContribution::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->take(20)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'group_id' => $c->group_id,
                'source' => $c->source,
                'normkredits' => round($c->token_amount / 2500, 1),
                'du_paid' => $c->price_paid,
                'reference' => $c->payment_reference,
                'created_at' => optional($c->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'profile' => $profile,
            'sections' => $sections,
            'topups' => $topups,
            'transactions' => $transactions,
        ]);
    }

    /**
     * GET /api/product/topup-packages
     */
    public function topupPackages(): JsonResponse
    {
        return response()->json([
            'packages' => $this->getTopupPackages(),
        ]);
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
