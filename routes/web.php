<?php

use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\Admin\AdminGroupController;
use App\Http\Controllers\Admin\AdminOpenAiController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminUserController;
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
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\SuperadminController;
use App\Http\Controllers\TopupController;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

// ── Public / Auth ────────────────────────────────────────────
Route::get('/', LandingController::class)->name('landing');
Route::get('/normchat', LandingController::class)->name('landing.path');

// Halaman marketing (company profile): Indonesia di path marketing, Inggris di '<path>/en'.
$marketingPath = trim((string) config('app.normchat_marketing_path', '/normchat'), '/');
foreach (['id' => '', 'en' => '/en'] as $lang => $suffix) {
    Route::prefix($marketingPath.$suffix)
        ->name($lang === 'en' ? 'marketing.en.' : 'marketing.')
        ->group(function () use ($lang) {
            Route::get('/about', [MarketingController::class, 'about'])->defaults('lang', $lang)->name('about');
            Route::get('/pricing', [MarketingController::class, 'pricing'])->defaults('lang', $lang)->name('pricing');
            Route::get('/contact', [MarketingController::class, 'contact'])->defaults('lang', $lang)->name('contact');
            Route::post('/contact', [MarketingController::class, 'sendContact'])->defaults('lang', $lang)->middleware('throttle:5,10')->name('contact.send');
        });
}
Route::get($marketingPath.'/en', LandingController::class)->defaults('lang', 'en')->name('landing.en');
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
Route::get('/join/{shareId}', [GroupController::class, 'showJoin'])->middleware(['auth', 'not.superadmin'])->name('groups.join');
Route::post('/join/{shareId}', [GroupController::class, 'joinViaShareId'])->middleware(['auth', 'not.superadmin'])->name('groups.join.submit');

// ── Authenticated ────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Top-up Normkredit
    Route::get('/tokens/buy', [TopupController::class, 'create'])->name('subscription.tokens.buy');
    Route::post('/tokens/buy', [TopupController::class, 'store'])->name('subscription.tokens.buy.process');
    Route::get('/tokens/buy/success', [TopupController::class, 'success'])->name('subscription.tokens.buy.success');

    // Groups
    Route::get('/groups', [GroupController::class, 'index'])->middleware('not.superadmin')->name('groups.index');
    Route::get('/groups/create', [GroupController::class, 'create'])->middleware('not.superadmin')->name('groups.create');
    Route::post('/groups', [GroupController::class, 'store'])->middleware('not.superadmin')->name('groups.store');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');

    // Chat
    Route::get('/chat/last', [ChatController::class, 'openLast'])->middleware('not.superadmin')->name('chat.last');
    Route::get('/groups/{group}/chat', [ChatController::class, 'show'])->middleware('group.permission')->name('chat.show');
    Route::post('/groups/{group}/messages', [ChatController::class, 'store'])->middleware('group.permission')->name('chat.store');
    Route::get('/groups/{group}/polls/stats', [ChatPollController::class, 'stats'])->middleware('group.permission')->name('chat.polls.stats');
    Route::post('/groups/{group}/polls/{message}/vote', [ChatPollController::class, 'vote'])->middleware('group.permission')->name('chat.polls.vote');
    Route::post('/groups/{group}/messages/read', [ChatController::class, 'markRead'])->middleware('group.permission')->name('chat.read');
    Route::patch('/groups/{group}/messages/{message}', [ChatController::class, 'update'])->middleware('group.permission')->name('chat.update');
    Route::delete('/groups/{group}/messages/{message}', [ChatController::class, 'destroy'])->middleware('group.permission')->name('chat.destroy');
    Route::post('/groups/{group}/messages/hide', [ChatController::class, 'hide'])->middleware('group.permission')->name('chat.hide');
    Route::get('/groups/{group}/messages/{message}/versions', [ChatController::class, 'versions'])->middleware('group.permission')->name('chat.versions');
    Route::post('/groups/{group}/messages/{message}/forward', [ChatController::class, 'forward'])->middleware('group.permission')->name('chat.forward');
    Route::get('/groups/{group}/messages/{message}/attachment', [ChatController::class, 'attachment'])->middleware('group.permission')->name('chat.attachment');
    Route::get('/groups/{group}/messages/{message}/canvas', [ChatController::class, 'canvas'])->middleware('group.permission')->name('chat.canvas');

    // Settings (view for active members, write actions restricted by permission)
    Route::get('/groups/{group}/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::post('/groups/{group}/settings/profile', [SettingsController::class, 'updateGroupProfile'])->middleware('group.permission')->name('settings.profile.update');
    Route::get('/groups/{group}/settings/history-export', [GroupExportController::class, 'index'])->name('settings.history');
    Route::get('/groups/{group}/settings/ai-persona', [AiSettingsController::class, 'edit'])->name('settings.ai.persona');
    Route::post('/groups/{group}/settings/ai-persona', [AiSettingsController::class, 'updatePersona'])->middleware('group.permission')->name('settings.ai.persona.save');
    Route::post('/groups/{group}/settings/ai-model', [AiSettingsController::class, 'updateModel'])->middleware('group.permission')->name('settings.ai.model.save');

    // Batas harian kredit grup per anggota (owner)
    Route::post('/groups/{group}/credits/limit', [GroupCreditController::class, 'limit'])->middleware('group.permission')->name('groups.credits.limit');
    Route::post('/groups/{group}/settings/export', [GroupExportController::class, 'store'])->middleware('group.permission')->name('settings.export');
    Route::get('/groups/{group}/settings/exports', [GroupExportController::class, 'list'])->middleware('group.permission')->name('settings.exports');
    Route::get('/groups/{group}/settings/export/count', [GroupExportController::class, 'count'])->middleware('group.permission')->name('settings.export.count');
    Route::get('/groups/{group}/settings/export/{export}/download', [GroupExportController::class, 'show'])->name('settings.export.download');
    Route::get('/groups/{group}/settings/transactions', [SettingsController::class, 'transactionHistory'])->name('settings.transactions');
    Route::get('/groups/{group}/settings/backups', [GroupBackupController::class, 'index'])->middleware('group.permission')->name('settings.backups');
    Route::post('/groups/{group}/settings/backup', [GroupBackupController::class, 'store'])->middleware('group.permission')->name('settings.backup');
    Route::get('/groups/{group}/settings/backup/{backup}/preview', [GroupBackupController::class, 'preview'])->middleware('group.permission')->name('settings.backup.preview');
    Route::post('/groups/{group}/settings/backup/{backup}/restore', [GroupBackupController::class, 'restore'])->middleware('group.permission')->name('settings.backup.restore');

    // Members
    Route::post('/groups/{group}/members/{member}/promote', [GroupController::class, 'promoteMember'])->middleware('group.permission')->name('groups.members.promote');
    Route::post('/groups/{group}/members/{member}/remove', [GroupController::class, 'removeMember'])->middleware('group.permission')->name('groups.members.remove');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/account', [ProfileController::class, 'account'])->name('profile.account');
    Route::get('/avatars/{filename}', [ProfileController::class, 'avatar'])->where('filename', '[A-Za-z0-9]{20,64}\.(jpg|jpeg|png|webp)')->name('avatars.show');
    Route::post('/profile/account', [ProfileController::class, 'updateAccount'])->name('profile.account.update');
    Route::post('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme.update');
    Route::post('/profile/timezone', [ProfileController::class, 'updateTimezone'])->name('profile.timezone.update');
    // URL lama, dipertahankan untuk bookmark.
    Route::redirect('/profile/activity', '/profile/login-history')->name('profile.activity');
    Route::get('/profile/storage', [ProfileController::class, 'storage'])->name('profile.storage');
    Route::redirect('/profile/security', '/profile')->name('profile.security');
    Route::get('/profile/login-history', [ProfileHistoryController::class, 'logins'])->name('profile.login-history');
    Route::get('/profile/transactions', [ProfileHistoryController::class, 'transactions'])->name('profile.transactions');

    // Superadmin: ringkasan, grup, user, keuangan & AI, log audit, pengaturan (model AI & harga)
    Route::middleware('superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [AdminReportController::class, 'dashboard'])->name('index');
        Route::get('/grup', [AdminGroupController::class, 'index'])->name('groups.index');
        Route::get('/grup/{group}', [AdminGroupController::class, 'show'])->name('groups.show');
        Route::post('/grup/{group}/normkredit', [AdminGroupController::class, 'adjustCredits'])->name('groups.credits');
        Route::post('/grup/{group}/bekukan', [AdminGroupController::class, 'freeze'])->name('groups.freeze');
        Route::delete('/grup/{group}', [AdminGroupController::class, 'destroy'])->name('groups.destroy');
        Route::get('/user', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/user/{user}', [AdminUserController::class, 'show'])->withTrashed()->name('users.show');
        Route::post('/user/{user}/blokir', [AdminUserController::class, 'block'])->name('users.block');
        Route::delete('/user/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/user/{user}/pulihkan', [AdminUserController::class, 'restore'])->withTrashed()->name('users.restore');
        Route::get('/keuangan', [AdminReportController::class, 'finance'])->name('finance');
        Route::get('/csv/{type}', [AdminReportController::class, 'csv'])->whereIn('type', ['grup', 'user', 'transaksi', 'ai'])->name('csv');
        Route::get('/audit', [AdminReportController::class, 'audit'])->name('audit');
        Route::get('/biaya-openai', [AdminOpenAiController::class, 'index'])->name('openai');
        Route::post('/biaya-openai/kurs', [AdminOpenAiController::class, 'updateRate'])->name('openai.rate');
        Route::post('/biaya-openai/anggaran', [AdminOpenAiController::class, 'updateBudget'])->name('openai.budget');
        Route::post('/biaya-openai/saldo', [AdminOpenAiController::class, 'updateBalance'])->name('openai.balance');
        Route::post('/biaya-openai/email-uji', [AdminOpenAiController::class, 'testEmail'])->name('openai.test-email');
        Route::post('/peringatan/{alert}/tutup', [AdminOpenAiController::class, 'resolveAlert'])->name('alerts.resolve');
        Route::get('/pengaturan', [SuperadminController::class, 'index'])->name('settings');
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
        'name' => 'Normi Chat',
        'short_name' => 'Normi Chat',
        'start_url' => '/groups',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#ffffff',
        // Layar pembuka Android dibangun dari ikon 512 + background_color ini.
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-maskable-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json']);
})->name('pwa.manifest');

Route::get('/sw.js', function () {
    return response()->view('pwa.sw')->header('Content-Type', 'application/javascript');
})->name('pwa.sw');
