<?php

namespace App\Enums;

/**
 * Tujuan pembayaran Midtrans. Nilainya disimpan di pending_payments.payment_type
 * (dan metadata_json.action untuk kompatibilitas data lama).
 */
enum PaymentPurpose: string
{
    case GroupCreate = 'group_create_midtrans';
    case GroupJoin = 'group_join_midtrans';
    case Topup = 'topup_midtrans';

    public function orderPrefix(): string
    {
        return match ($this) {
            self::GroupCreate => 'NCGRP',
            self::GroupJoin => 'NCJOIN',
            self::Topup => 'NCTOPUP',
        };
    }

    /** Sumber kontribusi Normkredit yang tercatat saat pembayaran lunas. */
    public function contributionSource(): string
    {
        return match ($this) {
            self::GroupCreate => 'group_creation_midtrans',
            self::GroupJoin => 'patungan_midtrans',
            self::Topup => 'topup_midtrans',
        };
    }

    public function itemId(int $groupId): string
    {
        return match ($this) {
            self::GroupCreate => 'group-' . $groupId,
            self::GroupJoin => 'join-' . $groupId,
            self::Topup => 'topup-' . $groupId,
        };
    }

    public function itemName(string $groupName, int $credits): string
    {
        return match ($this) {
            self::GroupCreate => "Pembuatan grup {$groupName} ({$credits} Normkredit)",
            self::GroupJoin => "Patungan grup {$groupName} ({$credits} Normkredit)",
            self::Topup => "Top-up {$credits} Normkredit",
        };
    }
}
