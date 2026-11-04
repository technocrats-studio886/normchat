<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GroupTokenContribution;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Superadmin › Promo: bonus Normkredit untuk setiap grup baru selama periode tertentu. */
class AdminPromoController extends Controller
{
    public function index(NormkreditPricing $pricing): View
    {
        $settings = $pricing->settings();
        $starts = filled($settings['group_bonus_starts_at'] ?? null) ? CarbonImmutable::parse($settings['group_bonus_starts_at']) : null;
        $ends = filled($settings['group_bonus_ends_at'] ?? null) ? CarbonImmutable::parse($settings['group_bonus_ends_at']) : null;

        $state = match (true) {
            ! ($settings['group_bonus_enabled'] ?? false) || (float) ($settings['group_bonus_credits'] ?? 0) <= 0 => 'mati',
            $starts !== null && $starts->isFuture() => 'dijadwalkan',
            $ends !== null && $ends->isPast() => 'selesai',
            default => 'berjalan',
        };

        $given = GroupTokenContribution::query()->where('source', GroupTokenContribution::SOURCE_BONUS);
        $period = (clone $given)->when($starts, fn ($q) => $q->where('created_at', '>=', $starts))->when($ends, fn ($q) => $q->where('created_at', '<', $ends));

        return view('superadmin.promo', [
            'settings' => $settings,
            'starts' => $starts,
            'ends' => $ends,
            'state' => $state,
            'periodGroups' => (clone $period)->count(),
            'periodCredits' => (float) (clone $period)->sum('credit_amount'),
            'allCredits' => (float) (clone $given)->sum('credit_amount'),
            'zone' => UserTime::zone(auth()->user()),
        ]);
    }

    public function update(Request $request, NormkreditPricing $pricing, PricingVersions $versions): RedirectResponse
    {
        $data = $request->validate([
            'group_bonus_enabled' => ['nullable', 'boolean'],
            'group_bonus_credits' => ['required', 'numeric', 'min:0', 'max:10000'],
            'group_bonus_starts_at' => ['nullable', 'date'],
            'group_bonus_ends_at' => ['nullable', 'date'],
        ]);

        $zone = UserTime::zone($request->user());
        $toUtc = fn (?string $value) => filled($value) ? CarbonImmutable::parse($value, $zone)->utc() : null;
        $starts = $toUtc($data['group_bonus_starts_at'] ?? null);
        $ends = $toUtc($data['group_bonus_ends_at'] ?? null);
        if ($starts && $ends && $ends->lte($starts)) {
            throw ValidationException::withMessages(['group_bonus_ends_at' => 'Waktu selesai harus setelah waktu mulai.']);
        }

        $values = [
            'group_bonus_enabled' => $request->boolean('group_bonus_enabled'),
            'group_bonus_credits' => round((float) $data['group_bonus_credits'], 2),
            'group_bonus_starts_at' => $starts?->toIso8601String(),
            'group_bonus_ends_at' => $ends?->toIso8601String(),
        ];
        $pricing->update($values);
        $versions->record($request->user()->id, 'Promo bonus buat grup diubah');
        AuditLog::record('admin.update_promo', $request->user()->id, null, $values);

        return redirect()->route('superadmin.promo')->with('success', 'Promo disimpan.');
    }
}
