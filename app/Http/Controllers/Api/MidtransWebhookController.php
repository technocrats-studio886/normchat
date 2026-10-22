<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingPayment;
use App\Services\Payments\MidtransGateway;
use App\Services\Payments\PaymentFulfillment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * HTTP notification Midtrans. Diverifikasi dengan signature_key; aman diterima
 * berulang karena PaymentFulfillment idempoten per order.
 *
 * POST /api/webhooks/midtrans
 */
class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransGateway $midtrans, PaymentFulfillment $fulfillment): JsonResponse
    {
        $payload = $request->all();

        if (! $midtrans->hasValidSignature($payload)) {
            Log::warning('Midtrans notification rejected: invalid signature.', ['order_id' => $payload['order_id'] ?? null]);

            return response()->json(['message' => 'invalid signature'], 403);
        }

        $pending = PendingPayment::where('order_id', (string) ($payload['order_id'] ?? ''))->first();
        if (! $pending) {
            return response()->json(['message' => 'payment not found'], 404);
        }

        $transaction = $midtrans->toTransaction($payload);
        $fulfillment->apply($pending, $transaction);

        Log::info('Midtrans notification processed.', [
            'order_id' => $pending->order_id,
            'transaction_status' => $transaction->gatewayStatus,
        ]);

        return response()->json(['message' => 'processed', 'status' => $transaction->state]);
    }
}
