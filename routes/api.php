<?php

use App\Http\Controllers\Api\ProductDataController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// ── Webhooks (no auth — validated by secret/signature) ──────

Route::prefix('webhooks/interdotz')->group(function () {
    Route::post('/charge', [WebhookController::class, 'chargeCallback']);
    Route::post('/topup', [WebhookController::class, 'topupCallback']);
    Route::post('/payment', [WebhookController::class, 'paymentCallback']);
});

// ── Product Data (called by Interdotz with client token) ────

Route::prefix('product')->group(function () {
    Route::get('/data', [ProductDataController::class, 'show']);
    Route::get('/topup-packages', [ProductDataController::class, 'topupPackages']);
});

// ── Transactions ────────────────────────────────────────────

Route::get('/transactions', [TransactionController::class, 'index']);
Route::get('/transactions/{id}', [TransactionController::class, 'show']);
