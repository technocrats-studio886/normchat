<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'group.permission' => \App\Http\Middleware\EnsureGroupPermission::class,
            'interdotz.client' => \App\Http\Middleware\VerifyInterdotzClientRequest::class,
            'interdotz.webhook' => \App\Http\Middleware\VerifyInterdotzWebhookRequest::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperadmin::class,
            'interdotz.enabled' => \App\Http\Middleware\EnsureInterdotzEnabled::class,
        ]);

        // Trust Nginx reverse proxy
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
