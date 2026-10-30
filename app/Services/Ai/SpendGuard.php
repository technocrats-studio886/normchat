<?php

namespace App\Services\Ai;

use App\Exceptions\SpendLimitReachedException;
use App\Models\AiSpendGuard;
use App\Models\AiSpendHold;
use App\Models\AiSpendPeriod;
use App\Models\AuditLog;
use App\Services\Admin\AdminAlerts;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Penjaga saldo akun OpenAI Normi Chat (bukan Normkredit grup). Setiap panggilan OpenAI — jawaban
 * yang ditagih ke grup maupun pemeliharaan ingatan yang tidak ditagih — menahan estimasi biayanya
 * di sini dulu. Panggilan ditolak bila melewati salah satu batas:
 *
 * 1. anggaran bulanan (bulan kalender WIB), dan
 * 2. saldo prabayar OpenAI yang diisi superadmin, dikurangi cadangan yang harus tersisa.
 *
 * Batas yang belum diisi superadmin tidak membatasi. Semua perubahan mengunci baris ai_spend_guard.
 */
class SpendGuard
{
    public function __construct(private readonly AdminAlerts $alerts)
    {
    }

    /**
     * Tahan $usd untuk satu panggilan.
     *
     * @throws SpendLimitReachedException
     */
    public function reserve(string $kind, float $usd, ?int $groupId = null, ?int $creditHoldId = null): AiSpendHold
    {
        $usd = max(0.0, $usd);
        $blocked = null;

        $hold = DB::transaction(function () use ($kind, $usd, $groupId, $creditHoldId, &$blocked) {
            $guard = $this->lockGuard();
            $period = $this->lockPeriod();
            $blocked = $this->limitHit($guard, $period, $usd);
            if ($blocked !== null) {
                return null;
            }

            $guard->update(['reserved_usd' => $guard->reserved_usd + $usd]);

            return AiSpendHold::create([
                'kind' => $kind,
                'group_id' => $groupId,
                'credit_hold_id' => $creditHoldId,
                'reserved_usd' => $usd,
                'status' => AiSpendHold::HELD,
            ]);
        });

        if ($hold === null) {
            $this->raiseStop($blocked);

            throw new SpendLimitReachedException($blocked);
        }

        return $hold;
    }

    /** Apakah panggilan sekecil apa pun masih boleh (dicek sebelum pekerjaan yang memanggil OpenAI dimulai). */
    public function isOpen(): bool
    {
        $guard = AiSpendGuard::current();
        $blocked = $this->limitHit($guard, $this->period(), 0.0);
        if ($blocked !== null) {
            $this->raiseStop($blocked);
        }

        return $blocked === null;
    }

    /** Selesaikan tahanan dengan biaya nyata dari usage API. */
    public function settle(AiSpendHold $hold, float $actualUsd): void
    {
        DB::transaction(function () use ($hold, $actualUsd) {
            $guard = $this->lockGuard();
            $hold = AiSpendHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($hold->status !== AiSpendHold::HELD) {
                return;
            }

            $actualUsd = max(0.0, $actualUsd);
            $period = $this->lockPeriod();
            $period->update(['spent_usd' => $period->spent_usd + $actualUsd]);
            $guard->update([
                'reserved_usd' => max(0.0, $guard->reserved_usd - $hold->reserved_usd),
                'spent_since_set_usd' => $guard->spent_since_set_usd + $actualUsd,
            ]);
            $hold->update(['status' => AiSpendHold::SETTLED, 'actual_usd' => $actualUsd]);
        });

        $this->checkThresholds();
    }

    /**
     * Biaya yang sudah terjadi tanpa tahanan, mis. transkripsi voice note sebelum permintaan ditolak karena
     * saldo NC atau terlalu panjang. Langsung dicatat sebagai pemakaian (tanpa cek batas: biayanya sudah keluar).
     */
    public function charge(string $kind, float $usd, ?int $groupId = null): void
    {
        if ($usd <= 0) {
            return;
        }

        DB::transaction(function () use ($kind, $usd, $groupId) {
            $guard = $this->lockGuard();
            $period = $this->lockPeriod();
            $period->update(['spent_usd' => $period->spent_usd + $usd]);
            $guard->update(['spent_since_set_usd' => $guard->spent_since_set_usd + $usd]);
            AiSpendHold::create([
                'kind' => $kind,
                'group_id' => $groupId,
                'reserved_usd' => 0,
                'actual_usd' => $usd,
                'status' => AiSpendHold::SETTLED,
            ]);
        });

        $this->checkThresholds();
    }

    /** Batalkan tahanan (panggilan gagal sebelum ada biaya). */
    public function release(AiSpendHold $hold): void
    {
        DB::transaction(function () use ($hold) {
            $guard = $this->lockGuard();
            $hold = AiSpendHold::query()->whereKey($hold->id)->lockForUpdate()->firstOrFail();
            if ($hold->status !== AiSpendHold::HELD) {
                return;
            }

            $guard->update(['reserved_usd' => max(0.0, $guard->reserved_usd - $hold->reserved_usd)]);
            $hold->update(['status' => AiSpendHold::RELEASED]);
        });
    }

    /** Lepas tahanan yang tidak pernah diselesaikan (job mati di tengah jalan). */
    public function releaseStale(): int
    {
        $before = now()->subMinutes((int) config('normchat.spend.stale_hold_minutes', 15));
        $stale = AiSpendHold::query()->where('status', AiSpendHold::HELD)->where('created_at', '<', $before)->get();
        $stale->each(fn (AiSpendHold $hold) => $this->release($hold));

        return $stale->count();
    }

    /**
     * Koreksi dari rekonsiliasi harian: biaya nyata OpenAI dikurangi hitungan sistem untuk hari $date (UTC).
     * Bulan yang dikoreksi adalah bulan (WIB) hari itu; saldo hanya dikoreksi bila hari itu setelah saldo diisi.
     */
    public function applyCorrection(CarbonImmutable $date, float $diffUsd): void
    {
        DB::transaction(function () use ($date, $diffUsd) {
            $guard = $this->lockGuard();
            $period = $this->lockPeriod($date->setTimezone(UserTime::DEFAULT_ZONE)->format('Y-m'));
            $period->update(['spent_usd' => max(0.0, $period->spent_usd + $diffUsd)]);
            if ($guard->balance_set_at !== null && $date->startOfDay()->greaterThanOrEqualTo($guard->balance_set_at->copy()->utc()->startOfDay())) {
                $guard->update(['spent_since_set_usd' => max(0.0, $guard->spent_since_set_usd + $diffUsd)]);
            }
        });

        $this->checkThresholds();
    }

    public function setMonthlyBudget(?float $usd, int $userId): void
    {
        AiSpendGuard::current()->update(['monthly_budget_usd' => $usd, 'budget_set_at' => now()]);
        AuditLog::record('admin.set_openai_budget', $userId, null, ['monthly_budget_usd' => $usd]);
        $this->alerts->resolve('budget:');
        $this->checkThresholds();
    }

    /** Saldo prabayar terbaru dari halaman billing OpenAI; hitungan pemakaian sejak saldo ini dimulai dari 0. */
    public function setBalance(float $balanceUsd, float $reserveUsd, int $userId): void
    {
        AiSpendGuard::current()->update([
            'balance_usd' => $balanceUsd,
            'reserve_usd' => $reserveUsd,
            'balance_set_at' => now(),
            'balance_set_by' => $userId,
            'spent_since_set_usd' => 0,
        ]);
        AuditLog::record('admin.set_openai_balance', $userId, null, ['balance_usd' => $balanceUsd, 'reserve_usd' => $reserveUsd]);
        $this->alerts->resolve('balance:');
        $this->checkThresholds();
    }

    /**
     * Ringkasan untuk halaman superadmin.
     *
     * @return array{period: string, budget: ?float, spent: float, budget_percent: ?float, balance: ?float, reserve: float,
     *     spent_since_set: float, reserved: float, balance_left: ?float, balance_percent: ?float, balance_set_at: mixed}
     */
    public function status(): array
    {
        $guard = AiSpendGuard::current();
        $period = $this->period();

        return [
            'period' => $period->period,
            'budget' => $guard->monthly_budget_usd,
            'spent' => $period->spent_usd,
            'budget_percent' => $this->budgetPercent($guard, $period),
            'balance' => $guard->balance_usd,
            'reserve' => $guard->reserve_usd,
            'spent_since_set' => $guard->spent_since_set_usd,
            'reserved' => $guard->reserved_usd,
            'balance_left' => $guard->balance_usd === null ? null : $guard->balance_usd - $guard->spent_since_set_usd,
            'balance_percent' => $this->balancePercent($guard),
            'balance_set_at' => $guard->balance_set_at,
        ];
    }

    /** @return 'budget'|'balance'|null batas yang terlewati bila $usd ditambahkan */
    private function limitHit(AiSpendGuard $guard, AiSpendPeriod $period, float $usd): ?string
    {
        $stop = (float) config('normchat.spend.stop_percent', 100) / 100;
        if ($guard->monthly_budget_usd !== null
            && $period->spent_usd + $guard->reserved_usd + $usd > $guard->monthly_budget_usd * $stop) {
            return 'budget';
        }

        if ($guard->balance_usd !== null) {
            $usable = ($guard->balance_usd - $guard->reserve_usd) * $stop;
            if ($guard->spent_since_set_usd + $guard->reserved_usd + $usd > $usable) {
                return 'balance';
            }
        }

        return null;
    }

    private function checkThresholds(): void
    {
        $guard = AiSpendGuard::current();
        $period = $this->period();
        $budgetPercent = $this->budgetPercent($guard, $period);
        $balancePercent = $this->balancePercent($guard);
        $fmt = fn (float $value) => '$' . number_format($value, 2, ',', '.');

        foreach ((array) config('normchat.spend.alert_percents', [50, 70, 85]) as $threshold) {
            if ($budgetPercent !== null && $budgetPercent >= $threshold) {
                $this->alerts->raise(
                    $this->budgetAlertKey($guard, $period, $threshold),
                    $threshold >= 85 ? AdminAlerts::DANGER : AdminAlerts::WARNING,
                    "Anggaran OpenAI bulan ini terpakai {$threshold}%",
                    'Terpakai ' . $fmt($period->spent_usd) . ' dari anggaran ' . $fmt((float) $guard->monthly_budget_usd)
                        . ' (' . number_format($budgetPercent, 1, ',', '.') . '%). Pada 100% Normi AI berhenti menerima permintaan baru.'
                );
            }
            if ($balancePercent !== null && $balancePercent >= $threshold) {
                $this->alerts->raise(
                    'balance:' . $guard->balance_set_at?->timestamp . ":{$threshold}",
                    $threshold >= 85 ? AdminAlerts::DANGER : AdminAlerts::WARNING,
                    "Saldo OpenAI di atas cadangan terpakai {$threshold}%",
                    'Perkiraan sisa saldo OpenAI ' . $fmt((float) $guard->balance_usd - $guard->spent_since_set_usd)
                        . ', cadangan ' . $fmt($guard->reserve_usd) . '. Top-up di OpenAI lalu isi saldo baru di halaman Biaya OpenAI.'
                );
            }
        }
    }

    private function raiseStop(string $limit): void
    {
        $guard = AiSpendGuard::current();
        if ($limit === 'budget') {
            $this->alerts->raise(
                $this->budgetAlertKey($guard, $this->period(), 100),
                AdminAlerts::DANGER,
                'Normi AI berhenti: anggaran OpenAI bulan ini habis',
                'Permintaan berbayar baru ditolak sampai anggaran bulanan dinaikkan atau bulan berganti.'
            );

            return;
        }

        $this->alerts->raise(
            'balance:' . $guard->balance_set_at?->timestamp . ':100',
            AdminAlerts::DANGER,
            'Normi AI berhenti: saldo OpenAI mencapai cadangan',
            'Permintaan berbayar baru ditolak. Top-up di OpenAI lalu isi saldo baru di halaman Biaya OpenAI.'
        );
    }

    /**
     * Sekali per ambang per bulan, dan sekali lagi setelah anggaran diubah: tanpa waktu ubah di key, anggaran yang
     * dinaikkan lalu habis lagi di bulan yang sama tidak pernah diperingatkan.
     */
    private function budgetAlertKey(AiSpendGuard $guard, AiSpendPeriod $period, int $threshold): string
    {
        return 'budget:' . $period->period . ':' . ($guard->budget_set_at?->timestamp ?? 0) . ':' . $threshold;
    }

    private function budgetPercent(AiSpendGuard $guard, AiSpendPeriod $period): ?float
    {
        return $guard->monthly_budget_usd > 0 ? $period->spent_usd / $guard->monthly_budget_usd * 100 : null;
    }

    private function balancePercent(AiSpendGuard $guard): ?float
    {
        if ($guard->balance_usd === null) {
            return null;
        }
        $usable = $guard->balance_usd - $guard->reserve_usd;

        return $usable > 0 ? $guard->spent_since_set_usd / $usable * 100 : 100.0;
    }

    private function lockGuard(): AiSpendGuard
    {
        $id = AiSpendGuard::current()->id;

        return AiSpendGuard::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private function lockPeriod(?string $key = null): AiSpendPeriod
    {
        $key ??= self::periodKey();
        $this->ensurePeriod($key);

        return AiSpendPeriod::query()->where('period', $key)->lockForUpdate()->firstOrFail();
    }

    private function period(): AiSpendPeriod
    {
        $key = self::periodKey();
        $this->ensurePeriod($key);

        return AiSpendPeriod::query()->where('period', $key)->firstOrFail();
    }

    /** Aman dipanggil paralel di pergantian bulan (insertOrIgnore, bukan firstOrCreate). */
    private function ensurePeriod(string $key): void
    {
        AiSpendPeriod::query()->insertOrIgnore(['period' => $key, 'spent_usd' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function periodKey(): string
    {
        return now(UserTime::DEFAULT_ZONE)->format('Y-m');
    }
}
