<?php

namespace App\Providers;

use App\Models\Group;
use App\Policies\GroupPolicy;
use App\Services\Payments\MidtransGateway;
use App\Services\NormkreditPricing;
use App\Services\Payments\PaymentGateway;
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
        Gate::policy(Group::class, GroupPolicy::class);

        // Halaman marketing: URL antar halaman, tautan masuk, dan harga terkini (termasuk timpaan superadmin).
        View::composer('marketing.*', function ($view) {
            $marketingBase = rtrim((string) config('app.marketing_url', config('app.url')), '/');
            $appBase = rtrim((string) config('app.normchat_app_url', config('app.url')), '/');

            $view->with([
                'page' => fn (string $path) => $marketingBase.$path,
                'loginUrl' => $appBase.'/login?next=groups.index',
                'supportEmail' => config('normchat.support_email'),
                'pricing' => app(NormkreditPricing::class)->settings(),
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
