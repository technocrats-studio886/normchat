<?php

namespace App\Providers;

use App\Models\AiModel;
use App\Models\Group;
use App\Models\User;
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
            // Grup gratis dari Superadmin › Grup gratis: jumlah grup dan saldo tiap grup (App\Support\FreeOffer).
            $offer = new \App\Support\FreeOffer([], 0);
            $free = $offer->active();
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
                    'freeFirst' => $offer->first(),
                    'freeSlots' => app(\App\Services\GroupPromotion::class)->offer()['free_member_slots'],
                    'promotion' => app(\App\Services\GroupPromotion::class)->offer(),
                    'offer' => $offer,
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
