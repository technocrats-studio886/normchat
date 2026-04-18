<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupTokenContribution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * GET /api/transactions
     *
     * Returns transaction history for a user (called by Interdotz or internal).
     */
    public function index(Request $request): JsonResponse
    {
        $interdotzUserId = $request->query('userId', $request->query('user_id', ''));
        $page = max(0, (int) $request->query('page', 0));
        $size = min(50, max(1, (int) $request->query('size', $request->query('limit', 20))));

        if (! $interdotzUserId) {
            return response()->json(['message' => 'userId required'], 400);
        }

        $user = User::query()
            ->where(function ($query) use ($interdotzUserId) {
                $query->where('provider_user_id', (string) $interdotzUserId)
                    ->orWhere('interdotz_id', (string) $interdotzUserId)
                    ->orWhere('id', (int) $interdotzUserId);
            })
            ->first();

        if (! $user) {
            return response()->json(['message' => 'user not found'], 404);
        }

        $query = GroupTokenContribution::where('user_id', $user->id)
            ->orderByDesc('created_at');

        $total = $query->count();
        $items = $query->skip($page * $size)->take($size)->get();

        $transactions = $items->map(fn ($c) => [
                'id' => (string) $c->id,
                'type' => 'CHARGE',
                'status' => 'SUCCESS',
                'reference_type' => $c->source,
                'referenceType' => $c->source,
                'reference_id' => $c->payment_reference ?? "contrib_{$c->id}",
                'referenceId' => $c->payment_reference ?? "contrib_{$c->id}",
                'amount' => $c->price_paid ?? 0,
                'normkredits' => round($c->token_amount / 2500, 1),
                'tokens' => $c->token_amount,
                'group_id' => $c->group_id,
                'groupId' => $c->group_id,
                'created_at' => optional($c->created_at)->toIso8601String(),
                'createdAt' => optional($c->created_at)->toIso8601String(),
            ]);

        $data = [
            'transactions' => $transactions,
            'current_page' => $page,
            'currentPage' => $page,
            'total_pages' => (int) ceil($total / $size),
            'totalPages' => (int) ceil($total / $size),
            'total_items' => $total,
            'totalItems' => $total,
        ];

        return response()->json([
            ...$data,
            'message' => 'ok',
            'payload' => $data,
        ]);
    }

    /**
     * GET /api/transactions/{id}
     */
    public function show(string $id): JsonResponse
    {
        $contribution = GroupTokenContribution::find($id);

        if (! $contribution) {
            return response()->json(['message' => 'transaction not found'], 404);
        }

        $data = [
            'id' => (string) $contribution->id,
            'type' => 'CHARGE',
            'status' => 'SUCCESS',
            'reference_type' => $contribution->source,
            'referenceType' => $contribution->source,
            'reference_id' => $contribution->payment_reference ?? "contrib_{$contribution->id}",
            'referenceId' => $contribution->payment_reference ?? "contrib_{$contribution->id}",
            'amount' => $contribution->price_paid ?? 0,
            'normkredits' => round($contribution->token_amount / 2500, 1),
            'tokens' => $contribution->token_amount,
            'group_id' => $contribution->group_id,
            'groupId' => $contribution->group_id,
            'user_id' => $contribution->user_id,
            'userId' => $contribution->user_id,
            'created_at' => optional($contribution->created_at)->toIso8601String(),
            'createdAt' => optional($contribution->created_at)->toIso8601String(),
        ];

        return response()->json([
            ...$data,
            'message' => 'ok',
            'payload' => $data,
        ]);
    }
}
