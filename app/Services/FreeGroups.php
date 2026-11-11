<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Grup gratis (docs/rancangan-grup-gratis-login.md): jumlah grup tanpa bayar per user dan saldo awal tiap grup
 * diatur di Superadmin › Grup gratis (NormkreditPricing::freeGroupCredits, mis. "70,50" = dua grup). Grup ke-1
 * dibuat otomatis saat login pertama, berikutnya lewat "Buat grup"; semuanya punya slot anggota yang bergabung tanpa
 * patungan. Jatah dihitung dari semua grup yang pernah dibuat user (berbayar maupun yang sudah dihapus), jadi
 * menghapus grup tidak mengembalikan jatah. Grup sesudah jatah habis memakai alur bayar.
 */
class FreeGroups
{

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

    /** Jumlah grup gratis per user menurut setelan sekarang (0 = fitur mati). */
    public function quota(): int
    {
        return count($this->pricing->freeGroupCredits());
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
        return $this->pricing->freeGroupCredits()[$used] ?? null;
    }
}
