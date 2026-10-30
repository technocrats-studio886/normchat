<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAdminAlertMail;
use App\Models\AdminAlert;
use App\Models\AiCostReconciliation;
use App\Models\AuditLog;
use App\Models\ExchangeRate;
use App\Models\PricingVersion;
use App\Services\Admin\AdminAlerts;
use App\Services\Ai\CostReconciliation;
use App\Services\Ai\SpendGuard;
use App\Services\NormkreditPricing;
use App\Services\Pricing\PricingVersions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Superadmin › Biaya OpenAI: kurs harian, anggaran bulanan, saldo OpenAI + cadangan, rekonsiliasi,
 * riwayat versi harga, dan peringatan. Yang dijaga di sini saldo akun OpenAI, bukan Normkredit grup.
 */
class AdminOpenAiController extends Controller
{
    /** Isian kurs yang beda lebih dari ini dari kurs terakhir harus dikonfirmasi (cegah salah ketik). */
    private const RATE_JUMP_PERCENT = 10;

    public function index(SpendGuard $spend, NormkreditPricing $pricing, PricingVersions $versions, CostReconciliation $reconciliation): View
    {
        return view('superadmin.openai', [
            'status' => $spend->status(),
            'rate' => $pricing->rate(),
            'usdToIdr' => $pricing->usdToIdr(),
            'cardMargin' => $pricing->cardMarginPercent(),
            'rates' => ExchangeRate::query()->with('setter:id,name')->where('created_at', '>=', now()->subDays(30))->latest('id')->get(),
            'reconciliations' => AiCostReconciliation::query()->latest('date')->limit(14)->get(),
            'reconcileConfigured' => $reconciliation->isConfigured(),
            'mailer' => (string) config('mail.default'),
            'alertRecipients' => (array) config('normchat.superadmin_emails', []),
            'pricingVersions' => PricingVersion::query()->with('creator:id,name')->latest('id')->limit(10)->get()
                ->map(fn (PricingVersion $version) => ['version' => $version, 'changes' => $versions->changes($version)]),
        ]);
    }

    public function updateRate(Request $request, NormkreditPricing $pricing): RedirectResponse
    {
        $data = $request->validate([
            'market_rate' => ['required', 'numeric', 'between:5000,50000'],
            'note' => ['nullable', 'string', 'max:200'],
            'confirm_jump' => ['nullable', 'boolean'],
        ]);
        $market = round((float) $data['market_rate'], 2);

        $last = $pricing->rate();
        if ($last !== null && ! $request->boolean('confirm_jump')) {
            $change = abs($market - $last['market_rate']) / $last['market_rate'] * 100;
            if ($change > self::RATE_JUMP_PERCENT) {
                throw ValidationException::withMessages(['market_rate' => 'Kurs ini beda ' . number_format($change, 1, ',', '.')
                    . '% dari kurs terakhir (Rp' . number_format($last['market_rate'], 0, ',', '.') . '). Centang konfirmasi bila memang benar.'])
                    ->redirectTo(url()->previous() . '#kurs');
            }
        }

        $rate = $pricing->setExchangeRate($market, $request->user()->id, $data['note'] ?? null);
        AuditLog::record('admin.set_exchange_rate', $request->user()->id, $rate, [
            'market_rate' => $rate->market_rate,
            'card_margin_percent' => $rate->card_margin_percent,
            'applied_rate' => $rate->applied_rate,
        ]);
        app(AdminAlerts::class)->resolve('rate:');

        return redirect()->route('superadmin.openai')->with('success', 'Kurs disimpan. Tagihan baru memakai Rp'
            . number_format($rate->applied_rate, 2, ',', '.') . ' per USD.');
    }

    public function updateBudget(Request $request, SpendGuard $spend): RedirectResponse
    {
        $data = $request->validate(['monthly_budget_usd' => ['nullable', 'numeric', 'min:1', 'max:1000000']]);
        $budget = isset($data['monthly_budget_usd']) ? round((float) $data['monthly_budget_usd'], 2) : null;
        $spend->setMonthlyBudget($budget, $request->user()->id);

        return redirect()->route('superadmin.openai')->with('success', $budget === null ? 'Anggaran bulanan dikosongkan (tanpa batas).' : 'Anggaran bulanan disimpan.');
    }

    public function updateBalance(Request $request, SpendGuard $spend): RedirectResponse
    {
        $data = $request->validate([
            'balance_usd' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'reserve_usd' => ['required', 'numeric', 'min:0', 'lt:balance_usd'],
        ], ['reserve_usd.lt' => 'Cadangan harus lebih kecil dari saldo.']);
        $spend->setBalance(round((float) $data['balance_usd'], 2), round((float) $data['reserve_usd'], 2), $request->user()->id);

        return redirect()->route('superadmin.openai')->with('success', 'Saldo OpenAI disimpan. Pemakaian dihitung mulai sekarang.');
    }

    public function testEmail(Request $request): RedirectResponse
    {
        SendAdminAlertMail::dispatch('Email uji', 'Email uji dari halaman Biaya OpenAI oleh ' . $request->user()->name
            . '. Bila email ini sampai, peringatan biaya OpenAI juga akan sampai ke alamat ini.');
        AuditLog::record('admin.test_alert_email', $request->user()->id);

        return back()->with('success', config('mail.default') === 'log'
            ? 'Email uji diantrekan, tapi MAIL_MAILER masih "log": email hanya tercatat di log server.'
            : 'Email uji diantrekan ke ' . implode(', ', (array) config('normchat.superadmin_emails', [])) . '.');
    }

    public function resolveAlert(Request $request, AdminAlert $alert): RedirectResponse
    {
        $alert->update(['resolved_at' => now()]);
        AuditLog::record('admin.resolve_alert', $request->user()->id, $alert, ['title' => $alert->title]);

        return back()->with('success', 'Peringatan ditutup.');
    }
}
