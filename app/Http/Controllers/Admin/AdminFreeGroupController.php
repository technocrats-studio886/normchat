<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupTokenContribution;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin › Grup gratis (App\Services\FreeGroups): berapa grup gratis tiap user, saldo awal tiap grup, dan jumlah
 * anggota yang bergabung tanpa patungan. Nilai baru berlaku untuk grup yang dibuat sesudahnya dan langsung tampil
 * di halaman publik (App\Support\FreeOffer).
 */
class AdminFreeGroupController extends Controller
{
    public function index(NormkreditPricing $pricing): View
    {
        $given = GroupTokenContribution::query()->where('source', GroupTokenContribution::SOURCE_FREE_GROUP);

        return view('superadmin.free-groups', [
            'settings' => $pricing->settings(),
            'groupCount' => (clone $given)->count(),
            'givenCredits' => (float) (clone $given)->sum('credit_amount'),
            'freeJoins' => (int) Group::withTrashed()->sum('free_joins_used'),
            'paidCredits' => $pricing->groupCreationCredits(),
            'offerSentence' => $pricing->freeOffer()->idSentence(),
        ]);
    }

    public function update(Request $request, NormkreditPricing $pricing, PricingVersions $versions): RedirectResponse
    {
        $data = $request->validate([
            'free_groups_enabled' => ['nullable', 'boolean'],
            'free_group_credits' => ['nullable', 'array', 'max:' . NormkreditPricing::MAX_FREE_GROUPS],
            'free_group_credits.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'free_group_member_slots' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $values = [
            'free_groups_enabled' => $request->boolean('free_groups_enabled'),
            // Berhenti di isian kosong/0 pertama (NormkreditPricing::parseCreditList); kosong semua = tanpa grup gratis.
            'free_group_credits' => NormkreditPricing::parseCreditList($data['free_group_credits'] ?? []),
            'free_group_member_slots' => (int) $data['free_group_member_slots'],
        ];
        $pricing->update($values);
        $versions->record($request->user()->id, 'Grup gratis diubah');
        AuditLog::record('admin.update_free_groups', $request->user()->id, null, $values);

        return redirect()->route('superadmin.free-groups')->with('success', 'Pengaturan grup gratis disimpan.');
    }
}
