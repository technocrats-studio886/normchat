<?php

namespace App\Services\Payments;

use App\Enums\PaymentPurpose;
use App\Models\Group;
use App\Models\PendingPayment;
use App\Models\User;
use App\Services\Credits\Funding;
use App\Services\GroupMembership;
use App\Services\NormkreditLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menuntaskan PendingPayment: dari webhook Interdotz, atau dari halaman callback
 * yang menanyakan status ke gateway. Aman dipanggil berulang (idempoten).
 */
class PaymentFulfillment
{
    public function __construct(
        private readonly GroupMembership $membership,
        private readonly NormkreditLedger $ledger,
        private readonly PaymentGateway $gateway,
    ) {
    }

    public function fulfill(PendingPayment $pending, int $paidAmount, string $reference, string $paymentMethod, mixed $paidAt): void
    {
        DB::transaction(function () use ($pending, $paidAmount, $reference, $paymentMethod, $paidAt) {
            // Webhook dan halaman callback bisa datang bersamaan; kunci baris agar saldo tidak tertambah dua kali.
            $locked = PendingPayment::query()->whereKey($pending->id)->lockForUpdate()->first();
            if (! $locked || $locked->status === 'paid') {
                return;
            }

            $user = User::find($locked->user_id);
            if (! $user) {
                Log::warning('Pending payment fulfillment failed: user not found.', ['order_id' => $locked->order_id]);

                return;
            }

            // Nominal yang dibayar (sudah termasuk biaya layanan) harus sama dengan tagihan.
            if ($paidAmount < (int) $locked->expected_amount) {
                Log::warning('Payment amount below expected; Normkredit not granted.', [
                    'order_id' => $locked->order_id,
                    'paid_amount' => $paidAmount,
                    'expected_amount' => (int) $locked->expected_amount,
                ]);
                $this->markFailed($locked, 'amount_mismatch', ['paid_amount' => $paidAmount]);

                return;
            }

            $meta = (array) ($locked->metadata_json ?? []);
            $group = Group::withTrashed()->whereKey($locked->group_id)->lockForUpdate()->first();
            if ($group?->trashed() && ($meta['archived_unpaid_group'] ?? false) && $group->status === 'pending_payment') {
                $group->restore();
            }
            $purpose = PaymentPurpose::tryFrom((string) $locked->payment_type);
            if ($purpose && (! $group || $group->trashed())) {
                Log::error('Confirmed payment requires reconciliation: destination group unavailable.', ['order_id' => $locked->order_id]);
                $locked->update(['status' => 'needs_review', 'metadata_json' => array_merge($meta, [
                    'gateway_transaction_id' => $reference, 'paid_amount' => $paidAmount,
                ])]);

                return;
            }
            if ($group && $purpose) {
                $this->grant($purpose, $group, $user, new Funding(
                    (float) ($meta['credits'] ?? 0),
                    $paidAmount,
                    $reference,
                    $paymentMethod,
                ), (float) ($meta['bonus_credits'] ?? 0));
            }

            unset($meta['archived_unpaid_group']);
            $locked->update([
                'status' => 'paid',
                'paid_at' => $paidAt ? Carbon::parse((string) $paidAt) : now(),
                'metadata_json' => array_merge($meta, [
                    'gateway_status' => 'paid',
                    'payment_method' => $paymentMethod,
                    'gateway_transaction_id' => $reference,
                    'paid_amount' => $paidAmount,
                ]),
            ]);
        });

        $pending->refresh();
    }

    public function markFailed(PendingPayment $pending, string $gatewayStatus, array $gatewayMetadata = []): void
    {
        DB::transaction(function () use ($pending, $gatewayStatus, $gatewayMetadata) {
            $locked = PendingPayment::whereKey($pending->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['paid', 'needs_review'], true)) {
                return;
            }

            $metadata = array_merge((array) $locked->metadata_json, $gatewayMetadata, ['gateway_status' => $gatewayStatus]);
            if ($locked->payment_type === PaymentPurpose::GroupCreate->value) {
                $group = Group::whereKey($locked->group_id)->lockForUpdate()->first();
                if ($group?->status === 'pending_payment') {
                    // Simpan tujuan pembayaran jika konfirmasi lunas datang belakangan.
                    $group->delete();
                    $metadata['archived_unpaid_group'] = true;
                }
            }

            $locked->update(['status' => 'failed', 'metadata_json' => $metadata]);
        });

        $pending->refresh();
    }

    /** Tanyakan status ke gateway untuk pembayaran yang masih pending, lalu tuntaskan. */
    public function syncWithGateway(PendingPayment $pending, User $user): void
    {
        $transaction = $this->gateway->fetchStatus($pending, $user);
        if (! $transaction) {
            return;
        }

        Log::info('Payment callback: synced gateway status.', [
            'order_id' => $pending->order_id,
            'gateway' => $this->gateway->name(),
            'gateway_status' => $transaction->gatewayStatus,
        ]);

        $this->apply($pending, $transaction);
    }

    /** Terapkan status gateway (dari webhook atau status API) ke PendingPayment. */
    public function apply(PendingPayment $pending, GatewayTransaction $transaction): void
    {
        if ($transaction->isPaid()) {
            $this->fulfill(
                $pending,
                $transaction->amount > 0 ? $transaction->amount : (int) $pending->expected_amount,
                $transaction->reference !== '' ? $transaction->reference : $pending->order_id,
                $transaction->paymentMethod,
                $transaction->paidAt
            );
        } elseif ($transaction->isFailed()) {
            $this->markFailed($pending, $transaction->gatewayStatus, [
                'payment_method' => $transaction->paymentMethod,
                'gateway_transaction_id' => $transaction->reference !== '' ? $transaction->reference : null,
            ]);
        }
    }

    /**
     * @param float $bonusCredits bonus promo buat grup yang dikunci saat checkout. Promo dicabut 4 Nov 2026 (diganti
     *        grup gratis); hanya checkout yang dimulai sebelum itu yang masih membawanya. Boleh dihapus setelahnya.
     */
    private function grant(PaymentPurpose $purpose, Group $group, User $user, Funding $funding, float $bonusCredits = 0.0): void
    {
        if ($this->ledger->hasContribution((string) $funding->reference, $purpose->contributionSource())) {
            return;
        }

        if ($purpose === PaymentPurpose::GroupCreate && $group->status !== 'active') {
            $this->membership->activateGroup($group, $user, $funding);
            if ($bonusCredits > 0) {
                $this->ledger->bonus($group, $user, $bonusCredits, (string) $funding->reference);
            }

            return;
        }

        match ($purpose) {
            PaymentPurpose::GroupCreate => null,
            PaymentPurpose::GroupJoin => $this->membership->activateJoin($group, $user, $funding),
            PaymentPurpose::Topup => $funding->credits > 0
                ? $this->ledger->credit($group, $user, $funding, $purpose->contributionSource())
                : null,
        };
    }
}
