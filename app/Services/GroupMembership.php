<?php

namespace App\Services;

use App\Enums\PaymentPurpose;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Credits\Funding;

/**
 * Mengaktifkan grup baru dan member baru setelah pembayaran Normkredit-nya diterima.
 */
class GroupMembership
{
    public function __construct(private readonly NormkreditLedger $ledger)
    {
    }

    public function activateGroup(Group $group, User $owner, Funding $funding): void
    {
        $group->update(['status' => 'active']);

        $this->addMember($group, $owner, Role::OWNER);

        Subscription::create([
            'group_id' => $group->id,
            'plan_name' => 'normchat-pro',
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'main_price' => $funding->paidAmount,
            'included_seats' => 2,
        ]);

        $this->ledger->credit($group, $owner, $funding, PaymentPurpose::GroupCreate->contributionSource());

        AuditLog::record('group.create', $owner->id, $group, $this->auditMetadata($funding));
    }

    public function activateJoin(Group $group, User $member, Funding $funding): void
    {
        $this->addMember($group, $member, Role::MEMBER);

        $group->subscription?->increment('included_seats');

        $this->ledger->credit($group, $member, $funding, PaymentPurpose::GroupJoin->contributionSource());

        AuditLog::record('group.member_joined', $member->id, $group, $this->auditMetadata($funding) + ['patungan' => true]);
    }

    private function addMember(Group $group, User $user, string $roleKey): void
    {
        GroupMember::updateOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['role_id' => Role::forKey($roleKey)->id, 'status' => 'active', 'joined_at' => now()]
        );
    }

    private function auditMetadata(Funding $funding): array
    {
        return [
            'paid_amount' => $funding->paidAmount,
            'payment_method' => $funding->paymentMethod,
            'transaction_id' => $funding->reference,
            'normkredits' => $funding->credits,
        ];
    }
}
