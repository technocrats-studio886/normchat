<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('interdotz:sync-client-profile {--token=} {--dry-run} {--logo-url=} {--icon-url=}', function () {
    $apiBase = rtrim((string) config('services.interdotz.api_base'), '/');
    $clientId = trim((string) config('services.interdotz.client_id'));
    $adminToken = trim((string) ($this->option('token') ?: config('services.interdotz.admin_bearer_token')));
    $appUrl = rtrim((string) config('app.url'), '/');

    if ($apiBase === '' || $clientId === '') {
        $this->error('Interdotz config belum lengkap (api_base/client_id).');

        return Command::FAILURE;
    }

    $logoUrl = trim((string) ($this->option('logo-url') ?: config('normchat.product_logo_url') ?: ($appUrl.'/normchat-logo.png')));
    $iconUrl = trim((string) ($this->option('icon-url') ?: config('normchat.product_icon_url') ?: ($appUrl.'/icons/icon-192.png')));

    $payload = [
        'name' => 'Normchat',
        'baseUrl' => $appUrl,
        'redirectUrl' => (string) config('services.interdotz.redirect_uri', $appUrl.'/sso/interdotz/callback'),
        'webhookUrl' => $appUrl.'/api/webhooks/interdotz/payment',
        'isActive' => true,
        'is_public' => true,
        // Extra metadata fields are sent for Interdotz DB enrichment.
        'logo_url' => $logoUrl,
        'icon_url' => $iconUrl,
        'logoUrl' => $logoUrl,
        'iconUrl' => $iconUrl,
    ];

    if ((bool) $this->option('dry-run')) {
        $this->info('Dry run payload (no request sent):');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    if ($adminToken === '') {
        $this->error('Admin token Interdotz belum tersedia. Set INTERDOTZ_ADMIN_BEARER_TOKEN atau gunakan --token=.');

        return Command::FAILURE;
    }

    $client = Http::withToken($adminToken)
        ->acceptJson()
        ->timeout(20);

    $response = $client->patch("{$apiBase}/api/clients/{$clientId}", $payload);

    if ($response->status() === 404) {
        $createPayload = array_merge($payload, [
            'id' => $clientId,
            'clientId' => $clientId,
        ]);

        $response = $client->post("{$apiBase}/api/clients", $createPayload);
    }

    if (! $response->successful()) {
        $this->error('Sync client profile gagal: HTTP '.$response->status());
        $this->line((string) $response->body());

        return Command::FAILURE;
    }

    $this->info('Normchat client profile berhasil disinkronkan ke Interdotz.');
    $this->line((string) json_encode($response->json(), JSON_UNESCAPED_SLASHES));

    return Command::SUCCESS;
})->purpose('Sync Normchat client profile and image metadata to Interdotz backend');
