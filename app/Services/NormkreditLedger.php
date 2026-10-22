<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Models\User;
use App\Services\Credits\CreditWallet;
use App\Services\Credits\Funding;

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
        $this->wallet->deposit($group, $funding->credits);

        return GroupTokenContribution::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'source' => $source,
            'credit_amount' => $funding->credits,
            'price_paid' => $funding->paidAmount,
            'payment_reference' => $funding->reference,
        ]);
    }

    public function hasContribution(string $reference, string $source): bool
    {
        return GroupTokenContribution::query()
            ->where('payment_reference', $reference)
            ->where('source', $source)
            ->exists();
    }
}
