<?php

namespace App\Providers;

use App\Models\Group;
use App\Policies\GroupPolicy;
use App\Services\Payments\MidtransGateway;
use App\Services\NormkreditPricing;
use App\Services\Payments\PaymentGateway;
use Carbon\Carbon;
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

        // URL yang dipakai bersama seluruh halaman marketing (layout + isi halaman).
        View::composer('marketing.*', function ($view) {
            $marketingBase = rtrim((string) config('app.marketing_url', config('app.url')), '/');
            $marketingPath = '/'.trim((string) config('app.normchat_marketing_path', '/normchat'), '/');
            $appBase = rtrim((string) config('app.normchat_app_url', config('app.url')), '/');

            $landingUrl = $marketingBase.$marketingPath;
            $lang = $view->getData()['lang'] ?? 'id';

            $view->with([
                'landingUrl' => $landingUrl,
                'lang' => $lang,
                'base' => $lang === 'en' ? $landingUrl.'/en' : $landingUrl,
                'startUrl' => $appBase.'/login?next=groups.index',
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
