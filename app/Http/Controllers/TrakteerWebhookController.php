<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupToken;
use App\Models\GroupTokenContribution;
use App\Models\PendingPayment;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrakteerWebhookController extends Controller
{
    /**
     * Handle incoming Trakteer webhook.
     *
     * Trakteer sends POST with JSON body and header X-Webhook-Token for verification.
     * Webhook payload fields:
     *   - id, created_at, type, is_paid, is_test
     *   - supporter_name, supporter_email, supporter_message
     *   - quantity, unit_price, price, net_amount
     *   - order_id, payment_method, media
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Verify webhook token
        $webhookToken = $request->header('X-Webhook-Token');
        $expectedToken = config('services.trakteer.webhook_token');

        if (! $expectedToken || ! hash_equals($expectedToken, $webhookToken ?? '')) {
            Log::warning('Trakteer webhook: invalid token', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        Log::info('Trakteer webhook received', [
            'id' => $payload['id'] ?? null,
            'order_id' => $payload['order_id'] ?? null,
            'price' => $payload['price'] ?? null,
            'supporter_message' => $payload['supporter_message'] ?? null,
        ]);

        // 2. Skip if not paid
        if (empty($payload['is_paid'])) {
            return response()->json(['message' => 'Skipped: not paid']);
        }

        // 3. Extract order_id from supporter_message
        //    User is instructed to include their order code in the message.
        //    Format: NC-SUB-XXXXXX or NC-TOKEN-XXXXXX or NC-SEAT-XXXXXX
        $message = trim($payload['supporter_message'] ?? '');
        $orderId = $this->extractOrderId($message);

        if (! $orderId) {
            Log::info('Trakteer webhook: no order ID found in message', [
                'message' => $message,
                'trakteer_id' => $payload['id'] ?? null,
            ]);
            return response()->json(['message' => 'No matching order ID in message']);
        }

        // 4. Find matching pending payment
        $pending = PendingPayment::where('order_id', $orderId)
            ->where('status', 'pending')
            ->first();

        if (! $pending) {
            Log::warning('Trakteer webhook: pending payment not found', [
                'order_id' => $orderId,
            ]);
            return response()->json(['message' => 'Pending payment not found'], 404);
        }

        // 5. Verify amount matches (Trakteer price is in Rupiah)
        $paidAmount = (int) ($payload['price'] ?? 0);

        if ($paidAmount < $pending->expected_amount) {
            Log::warning('Trakteer webhook: amount mismatch', [
                'order_id' => $orderId,
                'expected' => $pending->expected_amount,
                'received' => $paidAmount,
            ]);
            return response()->json(['message' => 'Amount mismatch'], 422);
        }

        // 6. Fulfill payment based on type
        try {
            DB::transaction(function () use ($pending, $payload, $paidAmount) {
                $pending->markPaid();

                match ($pending->payment_type) {
                    'subscription' => $this->fulfillSubscription($pending, $payload, $paidAmount),
                    'topup' => $this->fulfillTopup($pending, $payload, $paidAmount),
                    'add_seat' => $this->fulfillAddSeat($pending, $payload, $paidAmount),
                    default => Log::error('Trakteer webhook: unknown payment type', [
                        'type' => $pending->payment_type,
                    ]),
                };
            });
        } catch (\Throwable $e) {
            Log::error('Trakteer webhook: fulfillment failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Fulfillment error'], 500);
        }

        return response()->json(['message' => 'OK']);
    }

    private function extractOrderId(string $message): ?string
    {
        // Match NC-SUB-XXXXXX, NC-TOKEN-XXXXXX, NC-SEAT-XXXXXX (case-insensitive)
        if (preg_match('/\b(NC-(?:SUB|TOKEN|SEAT)-[A-Z0-9]{8})\b/i', $message, $matches)) {
            return strtoupper($matches[1]);
        }
        return null;
    }

    private function fulfillSubscription(PendingPayment $pending, array $payload, int $paidAmount): void
    {
        // Mark session-equivalent: the user can now create a group
        // We store the paid status on the pending payment itself.
        // The SubscriptionController::paymentStatus endpoint will check this.
        Log::info('Trakteer: subscription fulfilled', [
            'order_id' => $pending->order_id,
            'user_id' => $pending->user_id,
            'amount' => $paidAmount,
        ]);
    }

    private function fulfillTopup(PendingPayment $pending, array $payload, int $paidAmount): void
    {
        $meta = $pending->metadata_json ?? [];
        $groupId = $pending->group_id;
        $tokenAmount = (int) ($meta['token_amount'] ?? 0);

        if (! $groupId || $tokenAmount < 1) {
            Log::error('Trakteer topup: invalid metadata', ['pending_id' => $pending->id]);
            return;
        }

        $groupToken = GroupToken::firstOrCreate(
            ['group_id' => $groupId],
            ['total_tokens' => 0, 'used_tokens' => 0, 'remaining_tokens' => 0]
        );
        $groupToken->addTokens($tokenAmount);

        GroupTokenContribution::create([
            'group_id' => $groupId,
            'user_id' => $pending->user_id,
            'source' => 'topup',
            'token_amount' => $tokenAmount,
            'price_paid' => $paidAmount,
            'payment_reference' => $pending->order_id,
        ]);
    }

    private function fulfillAddSeat(PendingPayment $pending, array $payload, int $paidAmount): void
    {
        $meta = $pending->metadata_json ?? [];
        $groupId = $pending->group_id;
        $seatCount = (int) ($meta['seat_count'] ?? 0);

        if (! $groupId || $seatCount < 1) {
            Log::error('Trakteer add_seat: invalid metadata', ['pending_id' => $pending->id]);
            return;
        }

        $group = Group::find($groupId);
        if (! $group) {
            return;
        }

        $subscription = Subscription::where('group_id', $groupId)->first();
        if (! $subscription) {
            return;
        }

        $subscription->included_seats = (int) $subscription->included_seats + $seatCount;
        $subscription->save();

        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'group_id' => $groupId,
            'created_by' => $pending->user_id,
            'payment_type' => 'add_seat_trakteer',
            'reference' => $pending->order_id,
            'seat_count' => $seatCount,
            'unit_price' => (int) ($meta['unit_price'] ?? 4000),
            'total_amount' => $paidAmount,
            'status' => 'paid',
            'metadata_json' => [
                'payment_method' => 'trakteer',
                'trakteer_id' => $payload['id'] ?? null,
                'supporter_name' => $payload['supporter_name'] ?? null,
            ],
        ]);
    }
}
