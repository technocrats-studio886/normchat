<?php

namespace App\Providers;

use App\Models\Group;
use App\Policies\GroupPolicy;
use App\Services\Payments\InterdotzGateway;
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
        $this->app->bind(PaymentGateway::class, fn ($app) => config('normchat.payment_gateway') === 'interdotz'
            ? $app->make(InterdotzGateway::class)
            : $app->make(MidtransGateway::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Group::class, GroupPolicy::class);

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
