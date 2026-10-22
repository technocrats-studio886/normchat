<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingPayment;
use App\Services\Payments\GatewayStatus;
use App\Services\Payments\GatewayTransaction;
use App\Services\Payments\PaymentFulfillment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InterdotzWebhookController extends Controller
{
    /**
     * Webhook: Payment callback (Midtrans via Interdotz).
     *
     * POST /api/webhooks/interdotz/payment
     */
    public function paymentCallback(Request $request, PaymentFulfillment $fulfillment): JsonResponse
    {
        Log::info('Interdotz payment webhook received.', ['payload' => $request->all()]);

        $referenceId = (string) $this->field($request, ['reference_id', 'referenceId'], '');
        if ($referenceId === '') {
            return response()->json(['message' => 'missing reference_id'], 400);
        }

        $pending = PendingPayment::where('order_id', $referenceId)->first();
        if (! $pending) {
            Log::warning('Interdotz payment webhook: payment not found.', ['ref' => $referenceId]);

            return response()->json(['message' => 'payment not found'], 404);
        }

        $status = strtolower((string) $this->field($request, ['status', 'transaction_status'], ''));
        $paymentMethod = (string) $this->field($request, ['payment_method'], 'midtrans');
        $gatewayTxId = (string) $this->field($request, ['gateway_transaction_id', 'transaction_id'], '');

        $state = GatewayStatus::state($status);

        if ($state !== GatewayTransaction::PENDING) {
            $fulfillment->apply($pending, new GatewayTransaction(
                $state,
                $status,
                (int) round((float) $this->field($request, ['gross_amount', 'amount'], 0)),
                $gatewayTxId !== '' ? $gatewayTxId : $referenceId,
                $paymentMethod,
                $this->field($request, ['paid_at'], null),
            ));

            return response()->json(['message' => 'processed', 'status' => $state]);
        }

        return response()->json(['message' => 'ignored', 'status' => $status]);
    }

    /**
     * Interdotz mengirim field di root atau di dalam `data`; ambil kunci pertama yang ada.
     */
    private function field(Request $request, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            foreach ([$key, 'data.' . $key] as $path) {
                $value = data_get($request->all(), $path);
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return $default;
    }
}
