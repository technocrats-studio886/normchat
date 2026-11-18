<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\GroupTokenContribution;
use App\Services\GroupPromotion;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminPromotionController extends Controller
{
    public function index(NormkreditPricing $pricing, GroupPromotion $promotion): View
    {
        return view('superadmin.promotions', [
            'settings' => $pricing->settings(),
            'offer' => $promotion->offer(),
            'sentence' => $promotion->sentence(),
            'bonusGiven' => (float) GroupTokenContribution::where('source', GroupTokenContribution::SOURCE_BONUS)->sum('credit_amount'),
            'bonusCount' => GroupTokenContribution::where('source', GroupTokenContribution::SOURCE_BONUS)->count(),
        ]);
    }

    public function update(Request $request, NormkreditPricing $pricing, PricingVersions $versions): RedirectResponse
    {
        $data = $request->validate([
            'group_promo_enabled' => ['sometimes', 'boolean'],
            'group_promo_type' => ['required', 'in:fixed,percent'],
            'group_promo_value' => ['required', 'numeric', 'min:0', 'max:' . ($request->input('group_promo_type') === 'percent' ? 100 : 10000)],
            'group_promo_starts_at' => ['required_if:group_promo_enabled,1', 'nullable', 'date_format:Y-m-d\TH:i'],
            'group_promo_ends_at' => ['required_if:group_promo_enabled,1', 'nullable', 'date_format:Y-m-d\TH:i', 'after:group_promo_starts_at'],
            'paid_group_member_slots' => ['required', 'integer', 'between:0,50'],
        ]);
        $values = [
            'group_promo_enabled' => $request->boolean('group_promo_enabled'),
            'group_promo_type' => $data['group_promo_type'],
            'group_promo_value' => $data['group_promo_value'],
            'paid_group_member_slots' => $data['paid_group_member_slots'],
            'free_groups_enabled' => false,
        ];
        foreach (['group_promo_starts_at', 'group_promo_ends_at'] as $key) {
            $values[$key] = empty($data[$key]) ? '' : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data[$key], GroupPromotion::TIMEZONE)->utc()->toIso8601String();
        }
        DB::transaction(function () use ($pricing, $versions, $request, $values) {
            $pricing->update($values);
            $versions->record($request->user()->id, 'Promo pembuatan grup diubah');
            AuditLog::record('admin.update_group_promotion', $request->user()->id, null, $values);
        });

        return redirect()->route('superadmin.promotions')->with('success', 'Promo disimpan. Bonus diberikan setelah pembayaran berhasil.');
    }
}
