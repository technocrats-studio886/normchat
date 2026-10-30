<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use App\Services\Credits\Funding;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat setiap Normkredit yang masuk ke grup: saldo (lewat CreditWallet) dan
 * catatan kontribusinya selalu bergerak bersama.
 */
class NormkreditLedger
{
    public function __construct(private readonly CreditWallet $wallet)
    {
    }

    public function credit(Group $group, User $user, Funding $funding, string $source): GroupTokenContribution
    {
        return DB::transaction(function () use ($group, $user, $funding, $source) {
            $contribution = GroupTokenContribution::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'source' => $source,
                'credit_amount' => $funding->credits,
                'price_paid' => $funding->paidAmount,
                'payment_reference' => $funding->reference,
            ]);
            $this->wallet->deposit($group, $funding->credits, ['user_id' => $user->id, 'contribution_id' => $contribution->id]);

            return $contribution;
        });
    }

    public function hasContribution(string $reference, string $source): bool
    {
        return GroupTokenContribution::query()
            ->where('payment_reference', $reference)
            ->where('source', $source)
            ->exists();
    }
}
