<?php

namespace App\Services\Credits;

use App\Exceptions\InsufficientBudgetException;
use App\Models\AiModel;
use App\Models\CreditHold;
use App\Models\CreditLedgerEntry;
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
 * Semua operasi mengunci baris group_tokens agar saldo tidak bocor saat paralel, dan setiap
 * perubahan tercatat di credit_ledger dalam transaksi yang sama. Kolom amount di ledger = perubahan
 * total dana grup (tersedia + ditahan): setoran +, penyesuaian ±, settle − NC terpakai; hold dan
 * release bernilai 0 karena hanya memindahkan saldo tersedia ↔ ditahan (terlihat di kolom reserved).
 */
class CreditWallet
{
    public function __construct(private readonly ModelAccess $access)
    {
    }

    /**
     * Tambah kredit dari pembelian ke saldo grup.
     *
     * @param  array{user_id?: int|null, contribution_id?: int|null}  $refs
     */
    public function deposit(Group $group, float $credits, array $refs = []): void
    {
        DB::transaction(function () use ($group, $credits, $refs) {
            $token = $this->lockToken($group);
            $before = $this->snapshot($token);
            $token->update([
                'total_credits' => $token->total_credits + $credits,
                'remaining_credits' => $token->remaining_credits + $credits,
            ]);
            $this->record($token, $before, CreditLedgerEntry::DEPOSIT, $credits, $refs);
            $this->access->sync($token);
        });
    }

    /**
     * Penyesuaian saldo oleh superadmin (positif menambah, negatif mengurangi). Saldo yang sedang
     * ditahan untuk permintaan AI yang berjalan tidak bisa ditarik.
     *
     * @param  array{user_id?: int|null, contribution_id?: int|null}  $refs
     *
     * @throws \RuntimeException pesan aman ditampilkan ke admin
     */
    public function adjust(Group $group, float $credits, array $refs = []): void
    {
        DB::transaction(function () use ($group, $credits, $refs) {
            $token = $this->lockToken($group);
            if ($token->remaining_credits + $credits < 0) {
                throw new \RuntimeException('Saldo grup tidak cukup untuk dikurangi sebanyak itu.');
            }
            $before = $this->snapshot($token);
            $token->update([
                'total_credits' => $token->total_credits + $credits,
                'remaining_credits' => $token->remaining_credits + $credits,
            ]);
            $this->record($token, $before, CreditLedgerEntry::ADMIN_ADJUST, $credits, $refs + ['admin_action' => 'admin.adjust_credits']);
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
     * @param  array{pricing_version_id?: int|null, exchange_rate_id?: int|null}  $refs
     *
     * @throws InsufficientBudgetException bila kredit yang boleh dipakai tidak cukup
     */
    public function hold(Group $group, User $user, float $amount, ?AiModel $model = null, array $refs = []): CreditHold
    {
        return DB::transaction(function () use ($group, $user, $amount, $model, $refs) {
            $token = $this->lockToken($group);
            $balance = new CreditBalance($token->remaining_credits, $this->dailyQuotaLeft($group, $user));

            if ($amount > $balance->usable() + 0.00001) {
                throw new InsufficientBudgetException($this->insufficientMessage($amount, $balance));
            }

            if ($model && $token->remaining_credits - $amount < $this->access->floor($model) - 0.00001) {
                throw new InsufficientBudgetException($this->access->floorMessage($model, (float) $token->remaining_credits));
            }

            $before = $this->snapshot($token);
            $token->update([
                'remaining_credits' => max(0, round($token->remaining_credits - $amount, 4)),
                'reserved_credits' => round($token->reserved_credits + $amount, 4),
            ]);

            $hold = CreditHold::create([
                'group_id' => $group->id,
                'user_id' => $user->id,
                'amount' => round($amount, 4),
                'status' => CreditHold::HELD,
                'pricing_version_id' => $refs['pricing_version_id'] ?? null,
                'exchange_rate_id' => $refs['exchange_rate_id'] ?? null,
            ]);
            $this->record($token, $before, CreditLedgerEntry::HOLD, 0.0, ['user_id' => $user->id, 'credit_hold_id' => $hold->id] + $refs);

            return $hold;
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
            $before = $this->snapshot($token);
            $token->update([
                'remaining_credits' => max(0, round($remaining, 4)),
                'reserved_credits' => max(0, round($token->reserved_credits - $held, 4)),
                'used_credits' => round($token->used_credits + $charged, 4),
            ]);
            $hold->update(['amount' => $charged, 'status' => CreditHold::SETTLED]);
            $this->record($token, $before, CreditLedgerEntry::SETTLE, -$charged, $this->holdRefs($hold));
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
            $before = $this->snapshot($token);
            $token->update([
                'remaining_credits' => round($token->remaining_credits + $hold->amount, 4),
                'reserved_credits' => max(0, round($token->reserved_credits - $hold->amount, 4)),
            ]);
            $hold->update(['status' => CreditHold::RELEASED]);
            $this->record($token, $before, CreditLedgerEntry::RELEASE, 0.0, $this->holdRefs($hold));
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

    /** @return array{0: float, 1: float} saldo tersedia dan ditahan sebelum perubahan */
    private function snapshot(GroupToken $token): array
    {
        return [(float) $token->remaining_credits, (float) $token->reserved_credits];
    }

    private function holdRefs(CreditHold $hold): array
    {
        return [
            'user_id' => $hold->user_id,
            'credit_hold_id' => $hold->id,
            'pricing_version_id' => $hold->pricing_version_id,
            'exchange_rate_id' => $hold->exchange_rate_id,
        ];
    }

    private function record(GroupToken $token, array $before, string $type, float $amount, array $refs): void
    {
        CreditLedgerEntry::create([
            'group_id' => $token->group_id,
            'type' => $type,
            'amount' => round($amount, 4),
            'balance_before' => round($before[0], 4),
            'balance_after' => round((float) $token->remaining_credits, 4),
            'reserved_before' => round($before[1], 4),
            'reserved_after' => round((float) $token->reserved_credits, 4),
            ...array_intersect_key($refs, array_flip([
                'user_id', 'credit_hold_id', 'ai_usage_log_id', 'contribution_id', 'admin_action', 'pricing_version_id', 'exchange_rate_id',
            ])),
        ]);
    }

    private function lockToken(Group $group): GroupToken
    {
        GroupToken::forGroup((int) $group->id);

        return GroupToken::query()->where('group_id', $group->id)->lockForUpdate()->firstOrFail();
    }
}
