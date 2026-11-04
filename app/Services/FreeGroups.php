<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Grup gratis (docs/rancangan-grup-gratis-login.md): setiap user mendapat dua grup tanpa bayar. Grup ke-1 dibuat
 * otomatis saat login pertama, grup ke-2 lewat "Buat grup"; keduanya punya slot anggota yang bergabung tanpa
 * patungan. Jatah dihitung dari semua grup yang pernah dibuat user (berbayar maupun yang sudah dihapus), jadi
 * menghapus grup tidak mengembalikan jatah. Grup ke-3 dst memakai alur bayar.
 */
class FreeGroups
{
    public const QUOTA = 2;

    public function __construct(
        private readonly NormkreditPricing $pricing,
        private readonly GroupMembership $membership,
    ) {
    }

    /** Saldo awal grup gratis berikutnya untuk user ini, atau null bila jatahnya habis / fitur mati. */
    public function nextCredits(User $user): ?float
    {
        if ($user->isSuperadmin()) {
            return null;
        }

        return $this->creditsFor($this->used($user));
    }

    /** Slot anggota gratis untuk grup gratis yang dibuat sekarang. */
    public function memberSlots(): int
    {
        return max(0, (int) ($this->pricing->settings()['free_group_member_slots'] ?? 0));
    }

    /** Grup yang pernah dibuat user: termasuk yang dihapus; checkout yang tidak dibayar tidak dihitung. */
    public function used(User $user): int
    {
        return Group::withTrashed()
            ->where('owner_id', $user->id)
            ->where('status', '!=', 'pending_payment')
            ->count();
    }

    /**
     * Membuat grup gratis berikutnya; null bila jatahnya habis. $firstOnly: hanya bila user belum pernah membuat
     * grup (dicek ulang di dalam kunci, supaya login dan halaman grup yang berjalan bersamaan tidak membuat dua).
     */
    public function create(User $user, string $name, ?string $password = null, ?string $description = null, bool $firstOnly = false): ?Group
    {
        return DB::transaction(function () use ($user, $name, $password, $description, $firstOnly) {
            // Kunci baris user: dua permintaan bersamaan tidak bisa memakai jatah yang sama.
            $owner = User::query()->lockForUpdate()->find($user->id);
            $credits = $owner && ! ($firstOnly && $this->used($owner) > 0) ? $this->nextCredits($owner) : null;
            if ($credits === null) {
                return null;
            }

            $group = Group::create([
                'name' => $name,
                'description' => $description,
                'owner_id' => $owner->id,
                'password_hash' => filled($password) ? Hash::make($password) : null,
                'ai_provider' => 'openai',
                'status' => 'active',
                'free_member_slots' => $this->memberSlots(),
            ]);
            $this->membership->activateFreeGroup($group, $owner, $credits);

            return $group;
        });
    }

    /** Grup pertama tanpa kata sandi untuk user yang belum pernah membuat grup (login pertama). */
    public function ensureFirstGroup(User $user): ?Group
    {
        if ($user->isSuperadmin() || $this->used($user) > 0) {
            return null;
        }

        $firstName = trim(strtok(trim((string) $user->name), ' ') ?: '');

        return $this->create($user, $firstName !== '' ? mb_substr('Grup ' . $firstName, 0, 120) : 'Grup pertamaku', firstOnly: true);
    }

    private function creditsFor(int $used): ?float
    {
        $settings = $this->pricing->settings();
        if (! ($settings['free_groups_enabled'] ?? false)) {
            return null;
        }

        $credits = match ($used) {
            0 => (float) ($settings['free_first_group_credits'] ?? 0),
            1 => (float) ($settings['free_second_group_credits'] ?? 0),
            default => 0.0,
        };

        return $credits > 0 ? $credits : null;
    }
}
