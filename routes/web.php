<?php

use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatPollController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\GroupBackupController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupCreditController;
use App\Http\Controllers\GroupExportController;
use App\Http\Controllers\InterdotzAuthController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileHistoryController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\SuperadminController;
use App\Http\Controllers\TopupController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

// ── Public / Auth ────────────────────────────────────────────
Route::get('/', LandingController::class)->name('landing');
Route::get('/normchat', LandingController::class)->name('landing.path');
Route::get('/login', [AuthController::class, 'landing'])->name('login');

// Google SSO (aktif)
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

// Interdotz SSO (nonaktif selama NORMCHAT_INTERDOTZ_ENABLED=false)
Route::middleware('interdotz.enabled')->group(function () {
    Route::get('/auth/interdotz/login', [InterdotzAuthController::class, 'redirectToLogin'])->name('auth.interdotz.login');
    Route::get('/auth/interdotz/register', [InterdotzAuthController::class, 'redirectToRegister'])->name('auth.interdotz.register');
    Route::get('/sso/interdotz/callback', [InterdotzAuthController::class, 'callback'])->name('auth.interdotz.callback');
});
Route::get('/groups/payment-callback', PaymentCallbackController::class)->name('groups.payment.callback');

// Join group via share ID
Route::get('/join/{shareId}', [GroupController::class, 'showJoin'])->middleware(['auth'])->name('groups.join');
Route::post('/join/{shareId}', [GroupController::class, 'joinViaShareId'])->middleware(['auth'])->name('groups.join.submit');

// ── Authenticated ────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Top-up Normkredit
    Route::get('/tokens/buy', [TopupController::class, 'create'])->name('subscription.tokens.buy');
    Route::post('/tokens/buy', [TopupController::class, 'store'])->name('subscription.tokens.buy.process');
    Route::get('/tokens/buy/success', [TopupController::class, 'success'])->name('subscription.tokens.buy.success');

    // Groups
    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

    // Chat
    Route::get('/chat/last', [ChatController::class, 'openLast'])->name('chat.last');
    Route::get('/groups/{group}/chat', [ChatController::class, 'show'])->middleware('group.permission')->name('chat.show');
    Route::post('/groups/{group}/messages', [ChatController::class, 'store'])->middleware('group.permission')->name('chat.store');
    Route::get('/groups/{group}/polls/stats', [ChatPollController::class, 'stats'])->middleware('group.permission')->name('chat.polls.stats');
    Route::post('/groups/{group}/polls/{message}/vote', [ChatPollController::class, 'vote'])->middleware('group.permission')->name('chat.polls.vote');
    Route::post('/groups/{group}/messages/read', [ChatController::class, 'markRead'])->middleware('group.permission')->name('chat.read');
    Route::patch('/groups/{group}/messages/{message}', [ChatController::class, 'update'])->middleware('group.permission')->name('chat.update');
    Route::delete('/groups/{group}/messages/{message}', [ChatController::class, 'destroy'])->middleware('group.permission')->name('chat.destroy');
    Route::get('/groups/{group}/messages/{message}/attachment', [ChatController::class, 'attachment'])->middleware('group.permission')->name('chat.attachment');

    // Settings (view for active members, write actions restricted by permission)
    Route::get('/groups/{group}/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::post('/groups/{group}/settings/profile', [SettingsController::class, 'updateGroupProfile'])->middleware('group.permission')->name('settings.profile.update');
    Route::get('/groups/{group}/settings/history-export', [GroupExportController::class, 'index'])->name('settings.history');
    Route::get('/groups/{group}/settings/ai-persona', [AiSettingsController::class, 'edit'])->name('settings.ai.persona');
    Route::post('/groups/{group}/settings/ai-persona', [AiSettingsController::class, 'updatePersona'])->middleware('group.permission')->name('settings.ai.persona.save');
    Route::post('/groups/{group}/settings/ai-model', [AiSettingsController::class, 'updateModel'])->middleware('group.permission')->name('settings.ai.model.save');

    // Pembagian Normkredit (bersama vs terlindungi)
    Route::post('/groups/{group}/credits/share', [GroupCreditController::class, 'share'])->middleware('group.permission')->name('groups.credits.share');
    Route::post('/groups/{group}/credits/order', [GroupCreditController::class, 'order'])->middleware('group.permission')->name('groups.credits.order');
    Route::post('/groups/{group}/credits/limit', [GroupCreditController::class, 'limit'])->middleware('group.permission')->name('groups.credits.limit');
    Route::post('/groups/{group}/settings/export', [GroupExportController::class, 'store'])->middleware('group.permission')->name('settings.export');
    Route::get('/groups/{group}/settings/export/{export}/download', [GroupExportController::class, 'show'])->name('settings.export.download');
    Route::get('/groups/{group}/settings/transactions', [SettingsController::class, 'transactionHistory'])->name('settings.transactions');
    Route::post('/groups/{group}/settings/backup', [GroupBackupController::class, 'store'])->middleware('group.permission')->name('settings.backup');
    Route::post('/groups/{group}/settings/backup/{backup}/restore', [GroupBackupController::class, 'restore'])->middleware('group.permission')->name('settings.backup.restore');

    // Members
    Route::post('/groups/{group}/members/{member}/promote', [GroupController::class, 'promoteMember'])->middleware('group.permission')->name('groups.members.promote');
    Route::post('/groups/{group}/members/{member}/remove', [GroupController::class, 'removeMember'])->middleware('group.permission')->name('groups.members.remove');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/account', [ProfileController::class, 'account'])->name('profile.account');
    Route::post('/profile/account', [ProfileController::class, 'updateAccount'])->name('profile.account.update');
    // URL lama, dipertahankan untuk bookmark.
    Route::redirect('/profile/activity', '/profile/login-history')->name('profile.activity');
    Route::get('/profile/storage', [ProfileController::class, 'storage'])->name('profile.storage');
    Route::redirect('/profile/security', '/profile')->name('profile.security');
    Route::get('/profile/login-history', [ProfileHistoryController::class, 'logins'])->name('profile.login-history');
    Route::get('/profile/transactions', [ProfileHistoryController::class, 'transactions'])->name('profile.transactions');

    // Superadmin: katalog model AI & harga Normkredit
    Route::middleware('superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [SuperadminController::class, 'index'])->name('index');
        Route::post('/settings', [SuperadminController::class, 'updateSettings'])->name('settings.update');
        Route::post('/models', [SuperadminController::class, 'storeModel'])->name('models.store');
        Route::get('/models/{aiModel}/edit', [SuperadminController::class, 'editModel'])->name('models.edit');
        Route::put('/models/{aiModel}', [SuperadminController::class, 'updateModel'])->name('models.update');
    });

    // Mailbox Interdotz (nonaktif selama NORMCHAT_INTERDOTZ_ENABLED=false)
    Route::middleware('interdotz.enabled')->group(function () {
        Route::get('/mailbox', [MailboxController::class, 'inbox'])->name('mailbox.inbox');
        Route::get('/mailbox/sent', [MailboxController::class, 'sent'])->name('mailbox.sent');
        Route::get('/mailbox/compose', [MailboxController::class, 'compose'])->name('mailbox.compose');
        Route::post('/mailbox/send', [MailboxController::class, 'send'])->name('mailbox.send');
        Route::put('/mailbox/read-all', [MailboxController::class, 'markAllRead'])->name('mailbox.read-all');
        Route::get('/mailbox/{mailId}', [MailboxController::class, 'show'])->name('mailbox.show');
        Route::delete('/mailbox/{mailId}', [MailboxController::class, 'destroy'])->name('mailbox.destroy');
    });

    Route::redirect('/app', '/groups');
});

// ── PWA ──────────────────────────────────────────────────────
Route::get('/manifest.webmanifest', function () {
    return Response::json([
        'name' => 'Normchat',
        'short_name' => 'Normchat',
        'start_url' => '/groups',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#ffffff',
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/apple-touch-icon.png', 'sizes' => '180x180', 'type' => 'image/png', 'purpose' => 'any'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    return response()->view('pwa.sw')->header('Content-Type', 'application/javascript');
})->name('pwa.sw');
