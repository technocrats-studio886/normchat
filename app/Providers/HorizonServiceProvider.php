<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon mengelola worker antrean (config/horizon.php). Dasbornya ditutup: skrip inline-nya ditolak CSP
 * bernonce normi.chat, dan status antrean sudah ada di /superadmin/antrean.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn ($user = null) => false);
    }
}
