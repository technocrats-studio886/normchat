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
 * Superadmin › Grup gratis (App\Services\FreeGroups): saldo awal grup gratis ke-1 dan ke-2 tiap user dan jumlah
 * anggota yang bergabung tanpa patungan. Nilai baru berlaku untuk grup yang dibuat sesudahnya.
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
        ]);
    }

    public function update(Request $request, NormkreditPricing $pricing, PricingVersions $versions): RedirectResponse
    {
        $data = $request->validate([
            'free_groups_enabled' => ['nullable', 'boolean'],
            'free_first_group_credits' => ['required', 'integer', 'min:0', 'max:10000'],
            'free_second_group_credits' => ['required', 'integer', 'min:0', 'max:10000'],
            'free_group_member_slots' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        $values = [
            'free_groups_enabled' => $request->boolean('free_groups_enabled'),
            'free_first_group_credits' => (int) $data['free_first_group_credits'],
            'free_second_group_credits' => (int) $data['free_second_group_credits'],
            'free_group_member_slots' => (int) $data['free_group_member_slots'],
        ];
        $pricing->update($values);
        $versions->record($request->user()->id, 'Grup gratis diubah');
        AuditLog::record('admin.update_free_groups', $request->user()->id, null, $values);

        return redirect()->route('superadmin.free-groups')->with('success', 'Pengaturan grup gratis disimpan.');
    }
}
