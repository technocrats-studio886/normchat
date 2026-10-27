<?php

use App\Http\Controllers\Api\CspReportController;
use App\Http\Controllers\Api\InterdotzWebhookController;
use App\Http\Controllers\Api\MidtransWebhookController;
use App\Http\Controllers\Api\ProductDataController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

// Laporan pelanggaran Content-Security-Policy dari browser (App\Http\Middleware\AddContentSecurityPolicy).
Route::post('/csp-report', CspReportController::class)->middleware('throttle:60,1')->name('csp.report');

// ── Midtrans (aktif) — diverifikasi lewat signature_key ─────

Route::post('/webhooks/midtrans', MidtransWebhookController::class)->name('webhooks.midtrans');

// ── Interdotz (nonaktif selama NORMCHAT_INTERDOTZ_ENABLED=false) ──

Route::middleware('interdotz.enabled')->group(function () {
    Route::prefix('webhooks/interdotz')->middleware('interdotz.webhook')->group(function () {
        Route::post('/payment', [InterdotzWebhookController::class, 'paymentCallback']);
    });

    // Product data (dipanggil Interdotz dengan client token)
    Route::prefix('product')->group(function () {
        Route::get('/data', [ProductDataController::class, 'show']);
        Route::get('/topup-packages', [ProductDataController::class, 'topupPackages']);
    });

    Route::middleware('interdotz.client')->group(function () {
        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    });
});
