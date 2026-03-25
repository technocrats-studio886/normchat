<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TrakteerWebhookController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

// ── Trakteer Webhook (no CSRF, no auth) ─────────────────────
Route::post('/webhooks/trakteer', [TrakteerWebhookController::class, 'handle'])->name('webhooks.trakteer');

// ── Public / Auth ────────────────────────────────────────────
Route::get('/', [SubscriptionController::class, 'landing'])->name('landing');
Route::get('/normchat', [SubscriptionController::class, 'landing'])->name('landing.path');
Route::get('/pricing', [SubscriptionController::class, 'pricing'])->name('subscription.pricing');
Route::get('/login', [AuthController::class, 'landing'])->name('login');

// Google SSO
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');

// Join group via share ID
Route::get('/join/{shareId}', [GroupController::class, 'showJoin'])->middleware(['auth'])->name('groups.join');
Route::post('/join/{shareId}', [GroupController::class, 'joinViaShareId'])->middleware(['auth'])->name('groups.join.submit');

// ── Authenticated ────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Payment flow
    Route::get('/payment/detail', [SubscriptionController::class, 'paymentDetail'])->name('subscription.payment.detail');
    Route::post('/payment/detail', [SubscriptionController::class, 'pay'])->name('subscription.pay');
    Route::get('/payment/waiting', [SubscriptionController::class, 'paymentWaiting'])->name('subscription.payment.waiting');
    Route::get('/payment/status', [SubscriptionController::class, 'paymentStatus'])->name('subscription.payment.status');
    Route::get('/payment/success', [SubscriptionController::class, 'paymentSuccess'])->name('subscription.payment.success');

    // Token purchase
    Route::get('/tokens/buy', [SubscriptionController::class, 'buyTokensForm'])->name('subscription.tokens.buy');
    Route::post('/tokens/buy', [SubscriptionController::class, 'buyTokens'])->name('subscription.tokens.buy.process');
    Route::get('/tokens/buy/success', [SubscriptionController::class, 'buyTokensSuccess'])->name('subscription.tokens.buy.success');

    // Groups
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');

    // Chat
    Route::get('/chat/last', [ChatController::class, 'openLast'])->name('chat.last');
    Route::get('/groups/{group}/chat', [ChatController::class, 'show'])->middleware('group.permission')->name('chat.show');
    Route::post('/groups/{group}/messages', [ChatController::class, 'store'])->middleware('group.permission')->name('chat.store');
    Route::get('/groups/{group}/messages/{message}/attachment', [ChatController::class, 'attachment'])->middleware('group.permission')->name('chat.attachment');

    // Settings (owner/manage_billing only)
    Route::get('/groups/{group}/settings', [SettingsController::class, 'show'])->middleware('group.permission:manage_billing')->name('settings.show');
    Route::get('/groups/{group}/settings/history-export', [SettingsController::class, 'historyExport'])->middleware('group.permission:manage_billing')->name('settings.history');
    Route::get('/groups/{group}/settings/ai-persona', [SettingsController::class, 'aiPersonaEditor'])->middleware('group.permission:manage_billing')->name('settings.ai.persona');
    Route::post('/groups/{group}/settings/ai-persona', [SettingsController::class, 'saveAiPersona'])->middleware('group.permission:manage_billing')->name('settings.ai.persona.save');
    Route::get('/groups/{group}/settings/seat-management', [SettingsController::class, 'seatManagement'])->middleware('group.permission:manage_billing')->name('settings.seats');
    Route::post('/groups/{group}/settings/ai', [SettingsController::class, 'createAiConnection'])->middleware('group.permission:manage_billing')->name('settings.ai');
    Route::post('/groups/{group}/settings/export', [SettingsController::class, 'createExport'])->middleware('group.permission:export_chat')->name('settings.export');
    Route::post('/groups/{group}/settings/backup', [SettingsController::class, 'createBackup'])->middleware('group.permission:recover_history')->name('settings.backup');
    Route::post('/groups/{group}/settings/backup/{backup}/restore', [SettingsController::class, 'restoreBackup'])->middleware('group.permission:recover_history')->name('settings.backup.restore');

    // Members
    Route::post('/groups/{group}/members/{member}/promote', [GroupController::class, 'promoteMember'])->middleware('group.permission:add_member')->name('groups.members.promote');
    Route::post('/groups/{group}/members/{member}/remove', [GroupController::class, 'removeMember'])->middleware('group.permission:remove_member')->name('groups.members.remove');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');

    // Seat payment flow
    Route::get('/subscribe/add-seat/{group}', [SubscriptionController::class, 'addSeatForm'])->name('subscription.add-seat');
    Route::post('/subscribe/add-seat/{group}', [SubscriptionController::class, 'processAddSeat'])->name('subscription.add-seat.process');
    Route::get('/subscribe/add-seat/{group}/success', [SubscriptionController::class, 'addSeatSuccess'])->name('subscription.add-seat.success');
    Route::get('/subscribe/add-seat/{group}/payments', [SubscriptionController::class, 'addSeatPaymentHistory'])->name('subscription.add-seat.payments');

    Route::redirect('/app', '/groups');
});

// ── PWA ──────────────────────────────────────────────────────
Route::get('/manifest.webmanifest', function () {
    return Response::json([
        'name' => 'Normchat',
        'short_name' => 'Normchat',
        'start_url' => '/groups',
        'display' => 'standalone',
        'background_color' => '#f5f8ff',
        'theme_color' => '#1d4ed8',
        'icons' => [
            ['src' => '/normchat-logo.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    return response()->view('pwa.sw')->header('Content-Type', 'application/javascript');
})->name('pwa.sw');
