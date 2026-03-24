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
Route::get('/pricing', [SubscriptionController::class, 'pricing'])->name('subscription.pricing');
Route::get('/login', [AuthController::class, 'landing'])->name('login');

// API key-based connect (ChatGPT & Claude)
Route::get('/connect/chatgpt', [AuthController::class, 'showApiKeyForm'])->defaults('provider', 'chatgpt')->name('auth.connect.chatgpt');
Route::post('/connect/chatgpt', [AuthController::class, 'handleApiKeyConnect'])->defaults('provider', 'chatgpt')->name('auth.connect.chatgpt.store');
Route::get('/connect/claude', [AuthController::class, 'showApiKeyForm'])->defaults('provider', 'claude')->name('auth.connect.claude');
Route::post('/connect/claude', [AuthController::class, 'handleApiKeyConnect'])->defaults('provider', 'claude')->name('auth.connect.claude.store');

// OAuth-based connect (Gemini/Google only)
Route::get('/connect/{provider}', [AuthController::class, 'connectProvider'])->name('auth.connect');
Route::get('/oauth/callback/{provider}', [AuthController::class, 'handleCallback'])->name('auth.callback');
// Join group via share ID — must connect LLM first
Route::get('/join/{shareId}', [GroupController::class, 'showJoin'])->middleware(['auth', 'llm.connected'])->name('groups.join');
Route::post('/join/{shareId}', [GroupController::class, 'joinViaShareId'])->middleware(['auth', 'llm.connected'])->name('groups.join.submit');

// ── Authenticated ────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Payment flow
    Route::get('/payment/detail', [SubscriptionController::class, 'paymentDetail'])->name('subscription.payment.detail');
    Route::post('/payment/detail', [SubscriptionController::class, 'pay'])->name('subscription.pay');
    Route::get('/payment/success', [SubscriptionController::class, 'paymentSuccess'])->name('subscription.payment.success');

    // Groups
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->middleware('user.llm.connected')->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->middleware('user.llm.connected')->name('groups.store');

    // Chat
    Route::get('/groups/{group}/chat', [ChatController::class, 'show'])->middleware('group.permission')->name('chat.show');
    Route::post('/groups/{group}/messages', [ChatController::class, 'store'])->middleware('group.permission')->name('chat.store');

    // Settings
    Route::get('/groups/{group}/settings', [SettingsController::class, 'show'])->middleware('group.permission')->name('settings.show');
    Route::get('/groups/{group}/settings/history-export', [SettingsController::class, 'historyExport'])->middleware('group.permission')->name('settings.history');
    Route::get('/groups/{group}/settings/ai-persona', [SettingsController::class, 'aiPersonaEditor'])->middleware('group.permission')->name('settings.ai.persona');
    Route::get('/groups/{group}/settings/seat-management', [SettingsController::class, 'seatManagement'])->middleware('group.permission')->name('settings.seats');
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
