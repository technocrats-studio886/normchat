<?php

namespace App\Services\Credits;

use App\Exceptions\InsufficientBudgetException;
use App\Models\AiModel;
use App\Models\CreditHold;
use App\Models\Group;
use App\Models\GroupToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu perubahan saldo Normkredit grup.
 *
 * - Normkredit grup satu kantong: semua anggota memakainya penuh (dibatasi kuota harian
 *   per anggota hanya bila owner mengaturnya).
 * - Request AI menahan estimasi (hold) lalu diselesaikan sesuai pemakaian aktual (settle).
 * - Kategori yang dikunci saldo (ModelAccess) tidak boleh menahan modal grup; status buka/tutupnya
 *   diperbarui setiap saldo berubah.
 *
 * Semua operasi mengunci baris group_tokens agar saldo tidak bocor saat paralel.
 */
class CreditWallet
{
    public function __construct(private readonly ModelAccess $access)
    {
    }

    /** Tambah kredit dari pembelian ke saldo grup. */
    public function deposit(Group $group, float $credits): void
    {
        DB::transaction(function () use ($group, $credits) {
            $token = $this->lockToken($group);
            $token->update([
                'total_credits' => $token->total_credits + $credits,
                'remaining_credits' => $token->remaining_credits + $credits,
            ]);
            $this->access->sync($token);
        });
    }

    /**
     * Penyesuaian saldo oleh superadmin (positif menambah, negatif mengurangi). Saldo yang sedang
     * ditahan untuk permintaan AI yang berjalan tidak bisa ditarik.
     *
     * @throws \RuntimeException pesan aman ditampilkan ke admin
     */
    public function adjust(Group $group, float $credits): void
    {
        DB::transaction(function () use ($group, $credits) {
            $token = $this->lockToken($group);
            if ($token->remaining_credits + $credits < 0) {
                throw new \RuntimeException('Saldo grup tidak cukup untuk dikurangi sebanyak itu.');
            }
            $token->update([
                'total_credits' => $token->total_credits + $credits,
                'remaining_credits' => $token->remaining_credits + $credits,
            ]);
            $this->access->sync($token);
        });
    }

    public function balanceFor(Group $group, User $user): CreditBalance
    {
        $token = GroupToken::forGroup((int) $group->id);

        return new CreditBalance(round($token->remaining_credits, 4), $this->dailyQuotaLeft($group, $user));
    }

    /**
     * Tahan $amount NK untuk request $user dengan $model.
     *
     * @throws InsufficientBudgetException bila kredit yang boleh dipakai tidak cukup
     */
    public function hold(Group $group, User $user, float $amount, ?AiModel $model = null): CreditHold
    {
        return DB::transaction(function () use ($group, $user, $amount, $model) {
            $token = $this->lockToken($group);
            $balance = new CreditBalance($token->remaining_credits, $this->dailyQuotaLeft($group, $user));

            if ($amount > $balance->usable() + 0.00001) {
                throw new InsufficientBudgetException($this->insufficientMessage($amount, $balance));
            }

            if ($model && $token->remaining_credits - $amount < $this->access->floor($model) - 0.00001) {
                throw new InsufficientBudgetException($this->access->floorMessage($model, (float) $token->remaining_credits));
            }

            $token->update([
                'remaining_credits' => max(0, round($token->remaining_credits - $amount, 4)),
                'reserved_credits' => round($token->reserved_credits + $amount, 4),
            ]);

            return CreditHold::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'amount' => round($amount, 4),
                'status' => CreditHold::HELD,
            ]);
        });
    }

    /**
     * Selesaikan penahanan dengan pemakaian aktual. Kelebihan tahanan dikembalikan;
     * kekurangan diambil dari kredit yang masih boleh dipakai, sebisanya.
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
            $token = $this->lockToken($group);
            $held = $hold->amount;

            if ($actual <= $held) {
                $charged = max(0.0, $actual);
                $remaining = $token->remaining_credits + ($held - $charged);
            } else {
                // Pemakaian melebihi estimasi: ambil selisihnya dari kredit yang masih boleh dipakai.
                $quota = $this->dailyQuotaLeft($group, User::findOrFail($hold->user_id));
                $usable = $quota === null ? $token->remaining_credits : min($token->remaining_credits, $quota);
                $extra = max(0.0, min($actual - $held, $usable));
                $charged = $held + $extra;
                $remaining = $token->remaining_credits - $extra;
            }

            $charged = round($charged, 4);
            $token->update([
                'remaining_credits' => max(0, round($remaining, 4)),
                'reserved_credits' => max(0, round($token->reserved_credits - $held, 4)),
                'used_credits' => round($token->used_credits + $charged, 4),
            ]);
            $hold->update(['amount' => $charged, 'status' => CreditHold::SETTLED]);
            $this->access->sync($token);

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

            $token = $this->lockToken(Group::findOrFail($hold->group_id));
            $token->update([
                'remaining_credits' => round($token->remaining_credits + $hold->amount, 4),
                'reserved_credits' => max(0, round($token->reserved_credits - $hold->amount, 4)),
            ]);
            $hold->update(['status' => CreditHold::RELEASED]);
            $this->access->sync($token);
        });
    }

    /** Sisa kuota harian anggota; null = tanpa batas (owner tidak pernah dibatasi). */
    private function dailyQuotaLeft(Group $group, User $user): ?float
    {
        $limit = (float) ($group->member_daily_credit_limit ?? 0);
        if ($limit <= 0 || $group->isOwnedBy($user)) {
            return null;
        }

        $usedToday = (float) CreditHold::query()
            ->where('group_id', $group->id)
            ->where('user_id', $user->id)
            ->whereIn('status', [CreditHold::HELD, CreditHold::SETTLED])
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('amount');

        return max(0.0, round($limit - $usedToday, 4));
    }

    private function insufficientMessage(float $amount, CreditBalance $balance): string
    {
        $fmt = fn (float $value) => number_format($value, 2, ',', '.');
        $limitedToday = $balance->dailyQuotaLeft !== null && $balance->dailyQuotaLeft < $balance->groupTotal;

        return ($limitedToday
            ? 'Batas harianmu tinggal ' . $fmt($balance->dailyQuotaLeft) . ' Normkredit, belum cukup untuk ini (butuh sekitar ' . $fmt($amount) . ').'
            : 'Normkredit grup tidak cukup (sisa ' . $fmt($balance->usable()) . ', butuh sekitar ' . $fmt($amount) . ').')
            . ' Isi Normkredit atau pilih model lain.';
    }

    private function lockToken(Group $group): GroupToken
    {
        GroupToken::forGroup((int) $group->id);

        return GroupToken::query()->where('group_id', $group->id)->lockForUpdate()->firstOrFail();
    }
}
