<?php

namespace App\Services\Credits;

use App\Exceptions\InsufficientBudgetException;
use App\Models\CreditHold;
use App\Models\Group;
use App\Models\GroupMemberCredit;
use App\Models\GroupToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya pintu perubahan saldo Normkredit grup.
 *
 * - Kredit bersama: boleh dipakai semua anggota (dibatasi kuota harian per anggota bila owner mengaturnya).
 * - Kredit terlindungi: milik satu orang, hanya ia yang bisa memakainya.
 * - Request AI menahan estimasi (hold) lalu diselesaikan sesuai pemakaian aktual (settle).
 *
 * Semua operasi mengunci baris group_tokens (lalu dompet member) agar saldo tidak bocor saat paralel.
 */
class CreditWallet
{
    /**
     * Tambah kredit dari pembelian. $sharePercent bagian masuk ke kredit bersama, sisanya
     * menjadi kredit terlindungi milik pembeli.
     *
     * @return array{shared: float, protected: float}
     */
    public function deposit(Group $group, User $user, float $credits, float $sharePercent): array
    {
        $sharePercent = max(0.0, min(100.0, $sharePercent));
        $shared = round($credits * $sharePercent / 100, 4);
        $protected = round($credits - $shared, 4);

        DB::transaction(function () use ($group, $user, $credits, $shared, $protected) {
            $token = $this->lockToken($group);
            $token->update([
                'total_credits' => $token->total_credits + $credits,
                'remaining_credits' => $token->remaining_credits + $credits,
                'shared_credits' => $token->shared_credits + $shared,
            ]);

            $wallet = $this->lockWallet($group, $user);
            $wallet->update(['protected_credits' => $wallet->protected_credits + $protected]);
        });

        return ['shared' => $shared, 'protected' => $protected];
    }

    /** Pindahkan kredit terlindungi milik $user ke kredit bersama (tidak bisa ditarik balik). */
    public function shareProtected(Group $group, User $user, float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Jumlah harus lebih dari 0.');
        }

        DB::transaction(function () use ($group, $user, $amount) {
            $token = $this->lockToken($group);
            $wallet = $this->lockWallet($group, $user);

            if ($amount > $wallet->protected_credits + 0.00001) {
                throw new InvalidArgumentException('Kredit terlindungi kamu tidak cukup.');
            }

            $wallet->update(['protected_credits' => round($wallet->protected_credits - $amount, 4)]);
            $token->update(['shared_credits' => round($token->shared_credits + $amount, 4)]);
        });
    }

    public function setSpendSharedFirst(Group $group, User $user, bool $sharedFirst): void
    {
        $this->walletFor($group, $user)->update(['spend_shared_first' => $sharedFirst]);
    }

    public function balanceFor(Group $group, User $user): CreditBalance
    {
        $token = GroupToken::forGroup((int) $group->id);
        $wallet = $this->walletFor($group, $user);

        return new CreditBalance(
            round($token->remaining_credits, 4),
            round($token->shared_credits, 4),
            round($wallet->protected_credits, 4),
            $this->sharedQuotaLeft($group, $user),
            $wallet->spend_shared_first,
        );
    }

    /**
     * Tahan $amount NK untuk request $user.
     *
     * @throws InsufficientBudgetException bila kredit yang boleh dipakai tidak cukup
     */
    public function hold(Group $group, User $user, float $amount): CreditHold
    {
        return DB::transaction(function () use ($group, $user, $amount) {
            $token = $this->lockToken($group);
            $wallet = $this->lockWallet($group, $user);
            $balance = new CreditBalance(
                $token->remaining_credits,
                $token->shared_credits,
                $wallet->protected_credits,
                $this->sharedQuotaLeft($group, $user),
                $wallet->spend_shared_first,
            );

            if ($amount > $balance->usable() + 0.00001) {
                throw new InsufficientBudgetException($this->insufficientMessage($amount, $balance));
            }

            [$fromShared, $fromProtected] = $this->split($amount, $balance->usableShared(), $balance->protected, $balance->sharedFirst);

            $this->take($token, $wallet, $fromShared, $fromProtected);
            $token->update(['reserved_credits' => round($token->reserved_credits + $amount, 4)]);

            return CreditHold::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'shared_amount' => $fromShared,
                'protected_amount' => $fromProtected,
                'shared_first' => $balance->sharedFirst,
                'status' => CreditHold::HELD,
            ]);
        });
    }

    /**
     * Selesaikan penahanan dengan pemakaian aktual. Kelebihan tahanan dikembalikan ke
     * kantong asalnya; kekurangan diambil dari kredit yang masih boleh dipakai, sebisanya.
     *
     * @return float NK yang benar-benar terpotong
     */
    public function settle(CreditHold $hold, float $actual): float
    {
        return DB::transaction(function () use ($hold, $actual) {
            $hold = CreditHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($hold->status !== CreditHold::HELD) {
                return 0.0;
            }

            $group = Group::findOrFail($hold->group_id);
            $user = User::findOrFail($hold->user_id);
            $token = $this->lockToken($group);
            $wallet = $this->lockWallet($group, $user);
            $held = $hold->total();

            if ($actual <= $held) {
                [$usedShared, $usedProtected] = $this->split($actual, $hold->shared_amount, $hold->protected_amount, $hold->shared_first);
                $this->give($token, $wallet, $hold->shared_amount - $usedShared, $hold->protected_amount - $usedProtected);
            } else {
                // Pemakaian melebihi estimasi: ambil selisihnya dari kredit yang masih boleh dipakai.
                $sharedQuota = $this->sharedQuotaLeft($group, $user);
                $usableShared = $sharedQuota === null ? $token->shared_credits : min($token->shared_credits, $sharedQuota);
                $extra = min($actual - $held, $usableShared + $wallet->protected_credits);
                [$extraShared, $extraProtected] = $this->split($extra, $usableShared, $wallet->protected_credits, $hold->shared_first);
                $this->take($token, $wallet, $extraShared, $extraProtected);
                $usedShared = $hold->shared_amount + $extraShared;
                $usedProtected = $hold->protected_amount + $extraProtected;
            }

            $charged = round($usedShared + $usedProtected, 4);
            $token->update([
                'reserved_credits' => max(0, round($token->reserved_credits - $held, 4)),
                'used_credits' => round($token->used_credits + $charged, 4),
            ]);
            $hold->update([
                'shared_amount' => round($usedShared, 4),
                'protected_amount' => round($usedProtected, 4),
                'status' => CreditHold::SETTLED,
            ]);

            return $charged;
        });
    }

    /** Batalkan penahanan (mis. request ke OpenAI gagal); seluruh NK kembali. */
    public function release(CreditHold $hold): void
    {
        DB::transaction(function () use ($hold) {
            $hold = CreditHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($hold->status !== CreditHold::HELD) {
                return;
            }

            $group = Group::findOrFail($hold->group_id);
            $token = $this->lockToken($group);
            $wallet = $this->lockWallet($group, User::findOrFail($hold->user_id));

            $this->give($token, $wallet, $hold->shared_amount, $hold->protected_amount);
            $token->update(['reserved_credits' => max(0, round($token->reserved_credits - $hold->total(), 4))]);
            $hold->update(['status' => CreditHold::RELEASED]);
        });
    }

    /** Sisa kuota harian kredit bersama; owner tidak dibatasi. */
    private function sharedQuotaLeft(Group $group, User $user): ?float
    {
        $limit = (float) ($group->member_daily_shared_limit ?? 0);
        if ($limit <= 0 || $group->isOwnedBy($user)) {
            return null;
        }

        $usedToday = (float) CreditHold::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [CreditHold::HELD, CreditHold::SETTLED])
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('shared_amount');

        return max(0.0, round($limit - $usedToday, 4));
    }

    /**
     * Bagi $amount ke dua kantong sesuai urutan, dibatasi isi masing-masing.
     *
     * @return array{0: float, 1: float} [dari bersama, dari terlindungi]
     */
    private function split(float $amount, float $shared, float $protected, bool $sharedFirst): array
    {
        if ($sharedFirst) {
            $fromShared = min($amount, max(0.0, $shared));

            return [round($fromShared, 4), round(min($amount - $fromShared, max(0.0, $protected)), 4)];
        }

        $fromProtected = min($amount, max(0.0, $protected));

        return [round(min($amount - $fromProtected, max(0.0, $shared)), 4), round($fromProtected, 4)];
    }

    private function take(GroupToken $token, GroupMemberCredit $wallet, float $shared, float $protected): void
    {
        $token->update([
            'shared_credits' => max(0, round($token->shared_credits - $shared, 4)),
            'remaining_credits' => max(0, round($token->remaining_credits - $shared - $protected, 4)),
        ]);
        $wallet->update(['protected_credits' => max(0, round($wallet->protected_credits - $protected, 4))]);
    }

    private function give(GroupToken $token, GroupMemberCredit $wallet, float $shared, float $protected): void
    {
        $token->update([
            'shared_credits' => round($token->shared_credits + $shared, 4),
            'remaining_credits' => round($token->remaining_credits + $shared + $protected, 4),
        ]);
        $wallet->update(['protected_credits' => round($wallet->protected_credits + $protected, 4)]);
    }

    private function insufficientMessage(float $amount, CreditBalance $balance): string
    {
        $fmt = fn (float $value) => number_format($value, 2, ',', '.');
        $message = 'Kredit yang bisa kamu pakai tidak cukup untuk permintaan ini (perkiraan hingga ' . $fmt($amount)
            . ' NK, tersedia ' . $fmt($balance->usable()) . ' NK: bersama ' . $fmt($balance->usableShared())
            . ' + terlindungi milikmu ' . $fmt($balance->protected) . ').';

        if ($balance->sharedQuotaLeft !== null && $balance->sharedQuotaLeft < $balance->shared) {
            $message .= ' Kuota harian kredit bersama kamu tersisa ' . $fmt($balance->sharedQuotaLeft) . ' NK.';
        }

        return $message . ' Top-up Normkredit atau pilih model yang lebih hemat.';
    }

    private function lockToken(Group $group): GroupToken
    {
        GroupToken::forGroup((int) $group->id);

        return GroupToken::query()->where('group_id', $group->id)->lockForUpdate()->firstOrFail();
    }

    private function lockWallet(Group $group, User $user): GroupMemberCredit
    {
        $this->walletFor($group, $user);

        return GroupMemberCredit::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function walletFor(Group $group, User $user): GroupMemberCredit
    {
        return GroupMemberCredit::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $user->id],
            ['protected_credits' => 0, 'spend_shared_first' => false]
        );
    }
}
