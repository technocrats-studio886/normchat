<?php

namespace App\Http\Controllers;

use App\Enums\PaymentPurpose;
use App\Models\PendingPayment;
use App\Services\Payments\PaymentFulfillment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Halaman tujuan setelah user kembali dari Midtrans. Bila webhook belum masuk,
 * status ditanyakan langsung ke gateway.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentFulfillment $fulfillment): RedirectResponse
    {
        $pending = PendingPayment::query()
            ->where('order_id', (string) $request->query('order', ''))
            ->where('user_id', (int) $request->user()?->id)
            ->first();

        if (! $pending) {
            return redirect()->route('groups.index')->with('info', 'Referensi pembayaran tidak ditemukan.');
        }

        if ($pending->status === 'pending') {
            $fulfillment->syncWithGateway($pending, $request->user());
            $pending->refresh();
        }

        return match ($pending->status) {
            'paid' => $this->redirectAfterPaid($pending),
            'failed', 'expired' => redirect()->route('groups.index')
                ->with('info', 'Pembayaran gagal atau kedaluwarsa. Silakan coba kembali.'),
            default => redirect()->route('groups.index')
                ->with('info', 'Pembayaran sedang diproses. Mohon tunggu beberapa saat.'),
        };
    }

    private function redirectAfterPaid(PendingPayment $pending): RedirectResponse
    {
        $meta = (array) ($pending->metadata_json ?? []);
        $group = $pending->group;

        return match (PaymentPurpose::tryFrom((string) $pending->payment_type)) {
            PaymentPurpose::GroupCreate, PaymentPurpose::GroupJoin => $group
                ? redirect()->route('chat.show', $group)->with('success', 'Pembayaran berhasil dikonfirmasi.')
                : redirect()->route('groups.index')->with('success', 'Pembayaran berhasil dikonfirmasi.'),
            PaymentPurpose::Topup => redirect()->route('subscription.tokens.buy.success')
                ->with('success', 'Top-up berhasil dikonfirmasi.')
                ->with('token_purchase', [
                    'group_name' => (string) ($meta['group_name'] ?? $group?->name ?? 'Grup'),
                    'normkredits' => (int) ($meta['credits'] ?? 0),
                    'paid_amount' => (int) ($meta['paid_amount'] ?? $pending->expected_amount),
                    'payment_method' => 'midtrans',
                    'payment_unit' => 'IDR',
                ]),
            default => redirect()->route('groups.index')->with('success', 'Pembayaran berhasil dikonfirmasi.'),
        };
    }
}
