<?php

namespace App\Providers;

use App\Models\AiModel;
use App\Models\Group;
use App\Models\User;
use App\Services\FreeGroups;
use App\Policies\GroupPolicy;
use App\Services\Payments\MidtransGateway;
use App\Services\NormkreditPricing;
use App\Services\Payments\PaymentGateway;
use Carbon\Carbon;
use App\Models\UserSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, MidtransGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nama hari & bulan dari server (translatedFormat) ikut bahasa aplikasi.
        Carbon::setLocale(app()->getLocale());
        Gate::policy(Group::class, GroupPolicy::class);

        // Akun > Perangkat & sesi (App\Http\Middleware\TrackUserSession): versi sesi dipasang saat masuk,
        // dan perangkat yang keluar dihapus dari daftar.
        Event::listen(Login::class, function (Login $event) {
            if (request()->hasSession()) {
                request()->session()->put('session_version', (int) ($event->user->session_version ?? 0));
                // Tur pengenalan: dicek sebelum aktivitas login ini tercatat (docs/rancangan-tur-dan-blok-kode.md).
                if ($event->user instanceof User && $event->user->tourDue()) {
                    request()->session()->put('tour_due', true);
                }
            }
            // Grup gratis pertama (App\Services\FreeGroups); /groups lalu membuka chat-nya, kecuali user datang
            // untuk tujuan lain (mis. tautan gabung grup teman).
            // Gagal membuat grup tidak boleh menggagalkan login; /groups mencoba lagi.
            try {
                $first = $event->user instanceof User ? app(FreeGroups::class)->ensureFirstGroup($event->user) : null;
            } catch (\Throwable $e) {
                report($e);
                $first = null;
            }
            if ($first && request()->hasSession()) {
                $intended = request()->session()->get('url.intended');
                if (! $intended || $intended === route('groups.index')) {
                    request()->session()->put('welcome_group_id', $first->id);
                }
            }
        });
        Event::listen(Logout::class, function () {
            if (request()->hasSession()) {
                UserSession::query()->where('session_hash', UserSession::hashFor(request()->session()->getId()))->delete();
            }
        });

        // Halaman marketing: URL antar halaman, tautan masuk, dan harga terkini (termasuk timpaan superadmin).
        View::composer('marketing.*', function ($view) {
            $marketingBase = rtrim((string) config('app.marketing_url', config('app.url')), '/');
            $appBase = rtrim((string) config('app.normchat_app_url', config('app.url')), '/');

            $pricing = app(NormkreditPricing::class)->settings();
            $idr = fn (int|float $n) => 'Rp'.number_format($n, 0, '.', ',');
            $count = fn (int|float $n) => number_format($n, 0, '.', ',');
            $free = (bool) ($pricing['free_groups_enabled'] ?? false) && (float) ($pricing['free_first_group_credits'] ?? 0) > 0;
            // Kategori terkunci (Advanced): terbuka saat saldo grup mencapai ambang ini (App\Services\Credits\ModelAccess).
            $advancedAt = AiModel::query()->where('is_active', true)->where('unlock_multiple', '>', 0)->min('unlock_multiple');

            $view->with([
                'page' => fn (string $path) => $marketingBase.$path,
                'loginUrl' => $appBase.'/',
                'supportEmail' => config('normchat.support_email'),
                'businessEmail' => config('normchat.business_email'),
                'pricing' => $pricing,
                'idr' => $idr,
                // Angka yang dipakai copy pemasaran (docs/rancangan-copy-marketing.md), semuanya dari setelan.
                'n' => [
                    'perCredit' => $idr($pricing['rupiah_per_credit']),
                    'groupPrice' => $idr($pricing['group_creation_credits'] * $pricing['rupiah_per_credit']),
                    'groupCredits' => $count($pricing['group_creation_credits']),
                    'packPrice' => $idr($pricing['package_credits'] * $pricing['rupiah_per_credit']),
                    'packCredits' => $count($pricing['package_credits']),
                    'maxPacks' => (int) $pricing['max_packages_per_payment'],
                    'free' => $free,
                    'freeFirst' => $count((float) ($pricing['free_first_group_credits'] ?? 0)),
                    'freeSecond' => (float) ($pricing['free_second_group_credits'] ?? 0) > 0 ? $count((float) $pricing['free_second_group_credits']) : null,
                    'freeSlots' => (int) ($pricing['free_group_member_slots'] ?? 0),
                    'advancedAt' => $advancedAt ? $count((float) $advancedAt * $pricing['group_creation_credits']) : null,
                ],
            ]);
        });

        View::composer(['partials.package-picker', 'groups.create'], function ($view) {
            $pricing = app(NormkreditPricing::class);
            $view->with([
                'packageQuote' => $pricing->quotePackages(1),
                'maxPackages' => $pricing->maxPackagesPerPayment(),
                'feePercent' => (float) $pricing->get('qris_fee_percent'),
            ]);
        });
    }
}
