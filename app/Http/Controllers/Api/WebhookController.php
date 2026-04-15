<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\GroupController;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
    use App\Models\PendingPayment;
use App\Models\SubscriptionPayment;
use App\Models\User;
    use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Webhook: Interdotz charge callback.
     * Called when a charge request is confirmed/rejected by user on Interdotz.
     *
     * POST /api/webhooks/interdotz/charge
     */
    public function chargeCallback(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Interdotz charge webhook received.', ['payload' => $payload]);

        $status = strtoupper((string) ($payload['status'] ?? ''));
        $referenceId = $payload['referenceId'] ?? $payload['reference_id'] ?? '';
        $referenceType = (string) ($payload['referenceType'] ?? $payload['reference_type'] ?? '');
        $transactionId = $payload['transaction_id'] ?? $payload['transactionId'] ?? null;
        $amount = (int) round((float) ($payload['amount'] ?? $payload['amount_charged'] ?? $payload['amountCharged'] ?? 0));

        if ($referenceType === '' && is_string($referenceId) && $referenceId !== '') {
            if (str_starts_with($referenceId, 'group_create_')) {
                $referenceType = 'normchat_group_creation';
            } elseif (str_starts_with($referenceId, 'patungan_')) {
                $referenceType = 'normchat_patungan';
            } elseif (str_starts_with($referenceId, 'topup_')) {
                $referenceType = 'normchat_topup';
            }
        }

        if ($status !== 'CONFIRMED' && $status !== 'SUCCESS') {
            Log::info('Interdotz charge not confirmed.', ['status' => $status, 'ref' => $referenceId]);

            // If rejected, clean up pending group
            if ($referenceType === 'normchat_group_creation' && in_array($status, ['REJECTED', 'CANCELLED', 'EXPIRED'])) {
                $this->handleGroupCreationRejected($referenceId);
            }

            return response()->json(['message' => 'acknowledged', 'status' => $status]);
        }

        // Route by reference type
        if ($referenceType === 'normchat_group_creation') {
            $this->handleGroupCreationCharge($referenceId, $amount, $transactionId);
        } elseif ($referenceType === 'normchat_patungan') {
            $this->handlePatunganCharge($referenceId, $amount, $transactionId);
        } elseif ($referenceType === 'normchat_topup') {
            $this->handleNormkreditTopup($referenceId, $amount, $transactionId);
        }

        return response()->json(['message' => 'processed', 'reference_id' => $referenceId]);
    }

    /**
     * Webhook: Interdotz normkredit topup from product page.
     * Called when user buys normkredit from Interdotz product profile.
     *
     * POST /api/webhooks/interdotz/topup
     */
    public function topupCallback(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Interdotz topup webhook received.', ['payload' => $payload]);

        $transactionId = $payload['transaction_id'] ?? '';
        $userId = $payload['user_id'] ?? '';
        $topupId = $payload['topup_id'] ?? '';
        $packageId = $payload['package_id'] ?? '';
        $duCharged = (int) ($payload['du_charged'] ?? 0);
        $groupId = (int) ($payload['group_id'] ?? $payload['metadata']['group_id'] ?? 0);

        if (! $userId || ! $transactionId) {
            return response()->json(['message' => 'missing required fields'], 400);
        }

        $user = User::query()
            ->where(function ($query) use ($userId) {
                $query->where('provider_user_id', (string) $userId)
                    ->orWhere('interdotz_id', (string) $userId)
                    ->orWhere('id', (int) $userId);
            })
            ->first();

        if (! $user) {
            Log::warning('Interdotz topup webhook: user not found.', ['userId' => $userId]);

            return response()->json(['message' => 'user not found'], 404);
        }

        // Find group
        $group = $groupId > 0 ? Group::find($groupId) : null;
        if (! $group) {
            $group = Group::query()
                ->where('owner_id', $user->id)
                ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id)->where('status', 'active'))
                ->latest('updated_at')
                ->first();
        }

        if (! $group) {
            Log::warning('Interdotz topup webhook: no group found.', ['userId' => $userId]);

            return response()->json(['message' => 'no group found for user'], 404);
        }

        // Convert DU to normkredit/tokens
        // 150 DU = 12 normkredit = 30.000 token
        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);
        $tokensPerNormkredit = 2_500;
        $normkredits = $duPer12Nk > 0 ? round(($duCharged / $duPer12Nk) * 12, 1) : 0;
        $tokens = (int) ($normkredits * $tokensPerNormkredit);

        if ($tokens <= 0) {
            return response()->json(['message' => 'insufficient amount'], 400);
        }

        $alreadyProcessed = GroupTokenContribution::query()
            ->where('payment_reference', (string) $transactionId)
            ->exists();
        if ($alreadyProcessed) {
            return response()->json([
                'message' => 'topup already processed',
                'group_id' => $group->id,
            ]);
        }

        // Add tokens to group
        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $group->id],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );

        $groupToken->increment('total_tokens', $tokens);
        $groupToken->increment('remaining_tokens', $tokens);

        // Record contribution
        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => 'interdotz_topup',
            'token_amount' => $tokens,
            'price_paid' => $duCharged,
            'payment_reference' => $transactionId,
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'normkredit.topup.interdotz',
            'target_type' => Group::class,
            'target_id' => $group->id,
            'metadata_json' => [
                'transaction_id' => $transactionId,
                'du_charged' => $duCharged,
                'normkredits' => $normkredits,
                'tokens' => $tokens,
                'package_id' => $packageId,
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'topup processed',
            'normkredits' => $normkredits,
            'tokens' => $tokens,
            'group_id' => $group->id,
        ]);
    }

    /**
     * Webhook: Payment callback (Midtrans via Interdotz).
     *
     * POST /api/webhooks/interdotz/payment
     */
    public function paymentCallback(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Interdotz payment webhook received.', ['payload' => $payload]);

        $referenceId = (string) (
            $payload['reference_id']
            ?? $payload['referenceId']
            ?? data_get($payload, 'data.reference_id')
            ?? data_get($payload, 'data.referenceId')
            ?? ''
        );
        $status = strtolower((string) (
            $payload['status']
            ?? $payload['transaction_status']
            ?? data_get($payload, 'data.status')
            ?? data_get($payload, 'data.transaction_status')
            ?? ''
        ));
        $paymentMethod = (string) (
            $payload['payment_method']
            ?? data_get($payload, 'data.payment_method')
            ?? 'midtrans'
        );
        $gatewayTxId = (string) (
            $payload['gateway_transaction_id']
            ?? $payload['transaction_id']
            ?? data_get($payload, 'data.gateway_transaction_id')
            ?? data_get($payload, 'data.transaction_id')
            ?? ''
        );
        $paidAt = $payload['paid_at'] ?? data_get($payload, 'data.paid_at');
        $grossAmount = (int) round((float) (
            $payload['gross_amount']
            ?? $payload['amount']
            ?? data_get($payload, 'data.gross_amount')
            ?? data_get($payload, 'data.amount')
            ?? 0
        ));

        if (! $referenceId) {
            return response()->json(['message' => 'missing reference_id'], 400);
        }

        $pending = PendingPayment::where('order_id', $referenceId)->first();
        if ($pending) {
            if ($this->isPaidPaymentStatus($status)) {
                $this->fulfillPendingPayment(
                    $pending,
                    $grossAmount > 0 ? $grossAmount : (int) $pending->expected_amount,
                    $gatewayTxId !== '' ? $gatewayTxId : $referenceId,
                    $paymentMethod,
                    $paidAt
                );

                return response()->json(['message' => 'processed', 'status' => 'paid']);
            }

            if ($this->isFailedPaymentStatus($status)) {
                $pending->update([
                    'status' => 'failed',
                    'metadata_json' => array_merge((array) ($pending->metadata_json ?? []), [
                        'payment_method' => $paymentMethod,
                        'gateway_transaction_id' => $gatewayTxId !== '' ? $gatewayTxId : null,
                        'gateway_status' => $status,
                    ]),
                ]);

                return response()->json(['message' => 'processed', 'status' => 'failed']);
            }

            return response()->json(['message' => 'ignored', 'status' => $status]);
        }

        $payment = SubscriptionPayment::where('reference', $referenceId)->first();

        if (! $payment) {
            Log::warning('Interdotz payment webhook: payment not found.', ['ref' => $referenceId]);

            return response()->json(['message' => 'payment not found'], 404);
        }

        if ($this->isPaidPaymentStatus($status)) {
            $payment->update([
                'status' => 'paid',
                'metadata_json' => array_merge($payment->metadata_json ?? [], [
                    'interdotz_payment_method' => $paymentMethod,
                    'interdotz_gateway_tx_id' => $gatewayTxId,
                    'interdotz_paid_at' => $paidAt,
                ]),
            ]);
        } elseif ($this->isFailedPaymentStatus($status)) {
            $payment->update(['status' => 'failed']);
        }

        return response()->json(['message' => 'processed', 'status' => $payment->status]);
    }

    // ── Private Handlers ────────────────────────────────────────

    private function handleGroupCreationCharge(string $referenceId, int $amount, ?string $transactionId): void
    {
        // referenceId format: "group_create_{groupId}_{timestamp}"
        preg_match('/group_create_(\d+)/', $referenceId, $matches);
        $groupId = (int) ($matches[1] ?? 0);
        $group = Group::find($groupId);

        if (! $group) {
            Log::warning('Group creation charge: group not found.', ['ref' => $referenceId]);

            return;
        }

        // Already activated
        if (($group->status ?? 'active') === 'active') {
            return;
        }

        $user = User::find($group->owner_id);
        if (! $user) {
            return;
        }

        $groupController = app(GroupController::class);
        $groupController->activateGroup($group, $user, $amount, $transactionId ?? $referenceId);

        Log::info('Group activated via webhook.', [
            'group_id' => $group->id,
            'du_paid' => $amount,
            'transaction_id' => $transactionId,
        ]);
    }

    private function handleGroupCreationRejected(string $referenceId): void
    {
        preg_match('/group_create_(\d+)/', $referenceId, $matches);
        $groupId = (int) ($matches[1] ?? 0);
        $group = Group::find($groupId);

        if ($group && ($group->status ?? '') === 'pending_payment') {
            $group->forceDelete();
            Log::info('Pending group deleted after payment rejection.', ['group_id' => $groupId]);
        }
    }

    private function handlePatunganCharge(string $referenceId, int $amount, ?string $transactionId): void
    {
        // referenceId format: "patungan_{groupId}_{userId}_{timestamp}"
        preg_match('/patungan_(\d+)_(\d+)/', $referenceId, $matches);
        $groupId = (int) ($matches[1] ?? 0);
        $userId = (int) ($matches[2] ?? 0);

        $group = Group::find($groupId);
        $user = User::find($userId);

        if (! $group || ! $user) {
            Log::warning('Patungan charge: group or user not found.', ['ref' => $referenceId]);

            return;
        }

        $ref = (string) ($transactionId ?? $referenceId);
        $alreadyProcessed = GroupTokenContribution::query()
            ->where('payment_reference', $ref)
            ->where('source', 'patungan')
            ->exists();
        if ($alreadyProcessed) {
            return;
        }

        $effectiveAmount = $amount > 0 ? $amount : (int) config('normchat.du_patungan', 25);

        $groupController = app(GroupController::class);
        $groupController->activateJoin($group, $user, $effectiveAmount, $ref);

        Log::info('Member joined via patungan webhook.', [
            'group_id' => $group->id,
            'user_id' => $user->id,
            'du_paid' => $effectiveAmount,
            'du_paid_from_payload' => $amount,
        ]);
    }

    private function handleNormkreditTopup(string $referenceId, int $amount, ?string $transactionId): void
    {
        // referenceId format: "topup_{groupId}_{userId}_{timestamp}"
        preg_match('/topup_(\d+)_(\d+)/', $referenceId, $matches);
        $groupId = (int) ($matches[1] ?? 0);
        $userId = (int) ($matches[2] ?? 0);

        $group = Group::find($groupId);
        if (! $group) {
            return;
        }

        $duPer12Nk = (int) config('normchat.du_topup_12nk', 150);
        $tokensPerNormkredit = 2_500;
        $normkredits = $duPer12Nk > 0 ? round(($amount / $duPer12Nk) * 12, 1) : 0;
        $tokens = (int) ($normkredits * $tokensPerNormkredit);

        if ($tokens <= 0) {
            return;
        }

        $ref = (string) ($transactionId ?? $referenceId);
        $alreadyProcessed = GroupTokenContribution::query()
            ->where('payment_reference', $ref)
            ->exists();
        if ($alreadyProcessed) {
            return;
        }

        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $group->id],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );

        $groupToken->increment('total_tokens', $tokens);
        $groupToken->increment('remaining_tokens', $tokens);

        GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $userId,
            'source' => 'interdotz_charge_topup',
            'token_amount' => $tokens,
            'price_paid' => $amount,
            'payment_reference' => $ref,
        ]);
    }

    private function isPaidPaymentStatus(string $status): bool
    {
        return in_array($status, ['paid', 'settlement', 'capture', 'success', 'confirmed'], true);
    }

    private function isFailedPaymentStatus(string $status): bool
    {
        return in_array($status, ['expire', 'expired', 'cancel', 'cancelled', 'deny', 'denied', 'failure', 'failed'], true);
    }

    private function fulfillPendingPayment(
        PendingPayment $pending,
        int $paidAmount,
        string $paymentReference,
        string $paymentMethod,
        mixed $paidAt
    ): void {
        if ($pending->status === 'paid') {
            return;
        }

        $group = $pending->group_id ? Group::find($pending->group_id) : null;
        $user = User::find($pending->user_id);

        if (! $user) {
            Log::warning('Pending payment fulfillment failed: user not found.', ['order_id' => $pending->order_id]);

            return;
        }

        if ($pending->payment_type === 'group_create_midtrans' && $group) {
            $alreadyProcessed = GroupTokenContribution::query()
                ->where('payment_reference', $paymentReference)
                ->where('source', 'group_creation_midtrans')
                ->exists();

            if (! $alreadyProcessed && (string) $group->status !== 'active') {
                app(GroupController::class)->activateGroup($group, $user, $paidAmount, $paymentReference, 'midtrans');
            }
        }

        if ($pending->payment_type === 'group_join_midtrans' && $group) {
            $alreadyProcessed = GroupTokenContribution::query()
                ->where('payment_reference', $paymentReference)
                ->where('source', 'patungan_midtrans')
                ->exists();

            if (! $alreadyProcessed) {
                app(GroupController::class)->activateJoin($group, $user, $paidAmount, $paymentReference, 'midtrans');
            }
        }

        if ($pending->payment_type === 'topup_midtrans' && $group) {
            $meta = (array) ($pending->metadata_json ?? []);
            $tokenAmount = (int) ($meta['token_amount'] ?? 0);

            if ($tokenAmount > 0) {
                $alreadyProcessed = GroupTokenContribution::query()
                    ->where('payment_reference', $paymentReference)
                    ->where('source', 'topup_midtrans')
                    ->exists();

                if (! $alreadyProcessed) {
                    $groupToken = GroupToken::firstOrCreate(
                        ['group_id' => $group->id],
                        ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
                    );
                    $groupToken->addTokens($tokenAmount);

                    GroupTokenContribution::create([
                        'group_id' => $group->id,
                        'user_id' => $user->id,
                        'source' => 'topup_midtrans',
                        'token_amount' => $tokenAmount,
                        'price_paid' => $paidAmount,
                        'payment_reference' => $paymentReference,
                    ]);
                }
            }
        }

        $pending->update([
            'status' => 'paid',
            'paid_at' => $paidAt ? Carbon::parse((string) $paidAt) : now(),
            'metadata_json' => array_merge((array) ($pending->metadata_json ?? []), [
                'payment_method' => $paymentMethod,
                'gateway_transaction_id' => $paymentReference,
                'paid_amount' => $paidAmount,
            ]),
        ]);
    }
}
