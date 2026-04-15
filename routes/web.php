<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

// ── Public / Auth ────────────────────────────────────────────
Route::get('/', [SubscriptionController::class, 'landing'])->name('landing');
Route::get('/normchat', [SubscriptionController::class, 'landing'])->name('landing.path');
Route::get('/login', [AuthController::class, 'landing'])->name('login');

// Interdotz SSO
Route::get('/auth/interdotz/login', [AuthController::class, 'redirectToInterdotz'])->name('auth.interdotz.login');
Route::get('/auth/interdotz/register', [AuthController::class, 'registerAtInterdotz'])->name('auth.interdotz.register');
Route::get('/sso/interdotz/callback', [AuthController::class, 'handleInterdotzCallback'])->name('auth.interdotz.callback');

// Join group via share ID
Route::get('/join/{shareId}', [GroupController::class, 'showJoin'])->middleware(['auth'])->name('groups.join');
Route::post('/join/{shareId}', [GroupController::class, 'joinViaShareId'])->middleware(['auth'])->name('groups.join.submit');

// ── Authenticated ────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/pricing', [SubscriptionController::class, 'pricing'])->name('subscription.pricing');

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
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::get('/groups/payment-callback', [GroupController::class, 'paymentCallback'])->name('groups.payment.callback');

    // Chat
    Route::get('/chat/last', [ChatController::class, 'openLast'])->name('chat.last');
    Route::get('/groups/{group}/chat', [ChatController::class, 'show'])->middleware('group.permission')->name('chat.show');
    Route::post('/groups/{group}/messages', [ChatController::class, 'store'])->middleware('group.permission')->name('chat.store');
    Route::get('/groups/{group}/polls/stats', [ChatController::class, 'pollStats'])->middleware('group.permission')->name('chat.polls.stats');
    Route::post('/groups/{group}/polls/{message}/vote', [ChatController::class, 'votePoll'])->middleware('group.permission')->name('chat.polls.vote');
    Route::post('/groups/{group}/messages/read', [ChatController::class, 'markRead'])->middleware('group.permission')->name('chat.read');
    Route::patch('/groups/{group}/messages/{message}', [ChatController::class, 'update'])->middleware('group.permission')->name('chat.update');
    Route::delete('/groups/{group}/messages/{message}', [ChatController::class, 'destroy'])->middleware('group.permission')->name('chat.destroy');
    Route::get('/groups/{group}/messages/{message}/attachment', [ChatController::class, 'attachment'])->middleware('group.permission')->name('chat.attachment');

    // Settings (view for active members, write actions restricted by permission)
    Route::get('/groups/{group}/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::post('/groups/{group}/settings/profile', [SettingsController::class, 'updateGroupProfile'])->middleware('group.permission')->name('settings.profile.update');
    Route::get('/groups/{group}/settings/history-export', [SettingsController::class, 'historyExport'])->name('settings.history');
    Route::get('/groups/{group}/settings/ai-persona', [SettingsController::class, 'aiPersonaEditor'])->name('settings.ai.persona');
    Route::post('/groups/{group}/settings/ai-persona', [SettingsController::class, 'saveAiPersona'])->middleware('group.permission')->name('settings.ai.persona.save');
    Route::post('/groups/{group}/settings/ai', [SettingsController::class, 'createAiConnection'])->middleware('group.permission')->name('settings.ai');
    Route::post('/groups/{group}/settings/export', [SettingsController::class, 'createExport'])->middleware('group.permission')->name('settings.export');
    Route::get('/groups/{group}/settings/export/{export}/download', [SettingsController::class, 'downloadExport'])->name('settings.export.download');
    Route::get('/groups/{group}/settings/transactions', [SettingsController::class, 'transactionHistory'])->name('settings.transactions');
    Route::post('/groups/{group}/settings/backup', [SettingsController::class, 'createBackup'])->middleware('group.permission')->name('settings.backup');
    Route::post('/groups/{group}/settings/backup/{backup}/restore', [SettingsController::class, 'restoreBackup'])->middleware('group.permission')->name('settings.backup.restore');

    // Members
    Route::post('/groups/{group}/members/{member}/promote', [GroupController::class, 'promoteMember'])->middleware('group.permission')->name('groups.members.promote');
    Route::post('/groups/{group}/members/{member}/remove', [GroupController::class, 'removeMember'])->middleware('group.permission')->name('groups.members.remove');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/security', [ProfileController::class, 'security'])->name('profile.security');

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
