<?php

namespace Tests\Feature;

use App\Events\UserMentioned;
use App\Http\Middleware\TrackUserSession;
use App\Models\Announcement;
use App\Models\CreditLedgerEntry;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\PendingPayment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Chat\MentionNotifier;
use App\Services\NormkreditPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * docs/rancangan-ux-admin-pengumuman-bonus.md: superadmin tanpa UI grup, Akun > Pengaturan, perangkat & sesi,
 * pengumuman, dan bonus buat grup.
 */
class UxAdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-uji';

    private User $boss;

    private User $noel;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        config()->set('services.midtrans.server_key', self::SERVER_KEY);
        config()->set('services.midtrans.is_production', false);
        // User uji belum punya grup; grup gratis (FreeGroupsTest) akan membuatkannya dan mengalihkan /groups ke chat.
        config()->set('normchat.pricing.free_groups_enabled', false);
        $this->boss = User::factory()->create(['email' => 'boss@normchat.test', 'name' => 'Boss']);
        $this->noel = User::factory()->create(['name' => 'Noel']);
    }

    // ── Superadmin (U1, A1) ────────────────────────────────

    public function test_superadmin_pages_use_the_admin_console_without_any_group_ui(): void
    {
        $pages = ['superadmin.index', 'superadmin.groups.index', 'superadmin.users.index', 'superadmin.finance', 'superadmin.openai',
            'superadmin.free-groups', 'superadmin.announcements.index', 'superadmin.announcements.create', 'superadmin.audit', 'superadmin.settings', 'profile.show'];

        foreach ($pages as $route) {
            $html = $this->actingAs($this->boss)->get(route($route))->assertOk()->getContent();
            $this->assertStringContainsString('aria-label="Menu admin"', $html, $route);
            foreach (['data-join-by-id-form', 'data-group-search-input', 'Buat grup', 'data-group-card'] as $groupUi) {
                $this->assertStringNotContainsString($groupUi, $html, "{$route} memuat UI grup: {$groupUi}");
            }
        }
    }

    public function test_superadmin_account_page_has_no_storage_payments_or_notifications(): void
    {
        $this->actingAs($this->boss)->get(route('profile.show'))
            ->assertOk()->assertSee('Zona waktu')->assertSee('Perangkat & sesi')
            ->assertDontSee('Penyimpanan')->assertDontSee('Riwayat Pembayaran')->assertDontSee('Notifikasi');
        $this->actingAs($this->boss)->get(route('profile.notifications'))->assertNotFound();
    }

    // ── Akun > Pengaturan (U2, U3) ─────────────────────────

    public function test_account_page_groups_settings_for_a_member(): void
    {
        $this->actingAs($this->noel)->get(route('profile.show'))
            ->assertOk()
            ->assertSeeInOrder(['Pengaturan', 'Tampilan', 'Zona waktu', 'Notifikasi', 'Perangkat & sesi', 'Data & riwayat', 'Penyimpanan', 'Bantuan', 'Keluar']);
    }

    public function test_manual_timezone_is_not_overwritten_by_the_device(): void
    {
        $this->actingAs($this->noel)->post(route('profile.timezone.save'), ['timezone_mode' => 'manual', 'timezone' => 'Asia/Makassar'])
            ->assertRedirect(route('profile.show'));
        $this->actingAs($this->noel)->postJson(route('profile.timezone.update'), ['timezone' => 'Europe/Berlin'])->assertOk();
        $this->assertSame('Asia/Makassar', $this->noel->fresh()->timezone);

        $this->actingAs($this->noel)->post(route('profile.timezone.save'), ['timezone_mode' => 'auto']);
        $this->actingAs($this->noel)->postJson(route('profile.timezone.update'), ['timezone' => 'Europe/Berlin'])->assertOk();
        $this->assertSame('Europe/Berlin', $this->noel->fresh()->timezone);

        $this->actingAs($this->noel)->post(route('profile.timezone.save'), ['timezone_mode' => 'manual', 'timezone' => 'Bukan/Zona'])
            ->assertSessionHasErrors('timezone');
    }

    public function test_muted_mentions_are_not_sent(): void
    {
        $sari = User::factory()->create(['name' => 'Sari', 'notify_mentions' => false]);
        $group = $this->group($this->noel, [$sari]);
        $message = Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $this->noel->id, 'content' => '@sari cek ya']);
        Event::fake([UserMentioned::class]);

        app(MentionNotifier::class)->notify($group, $message, $this->noel, '@sari cek ya');
        Event::assertNotDispatched(UserMentioned::class);

        $sari->update(['notify_mentions' => true]);
        app(MentionNotifier::class)->notify($group->fresh(), $message, $this->noel, '@sari cek ya');
        Event::assertDispatched(UserMentioned::class);
    }

    public function test_notification_settings_are_saved(): void
    {
        $this->actingAs($this->noel)->post(route('profile.notifications.save'), ['notify_sound' => '1'])->assertRedirect(route('profile.show'));
        $this->assertFalse($this->noel->fresh()->notify_mentions);
        $this->assertTrue($this->noel->fresh()->notify_sound);
        $this->actingAs($this->noel)->get(route('groups.index'))->assertSee('name="auth-user-notify-sound" content="1"', false);
    }

    // ── Perangkat & sesi ──────────────────────────────────

    public function test_session_is_recorded_and_ends_after_being_revoked_or_after_logout_of_other_devices(): void
    {
        [$request, $session] = $this->sessionRequest($this->noel, 'sesihp');
        $this->assertSame(200, $this->track($request)->getStatusCode());
        $row = UserSession::query()->where('session_hash', UserSession::hashFor($session->getId()))->firstOrFail();
        $this->assertSame($this->noel->id, $row->user_id);

        // Satu perangkat dikeluarkan.
        $row->update(['revoked_at' => now()]);
        $this->assertTrue($this->track($request)->isRedirect(route('login')));

        // "Keluar dari semua perangkat lain": versi sesi lain tertinggal.
        [$laptop] = $this->sessionRequest($this->noel, 'sesilaptop', version: 0);
        $this->noel->increment('session_version');
        $this->assertTrue($this->track($laptop)->isRedirect(route('login')));

        // Sesi dari sebelum fitur ini (tanpa versi) juga berakhir setelah user mengeluarkan perangkat lain.
        [$legacy] = $this->sessionRequest($this->noel, 'sesilama', version: null);
        $this->assertTrue($this->track($legacy)->isRedirect(route('login')));
    }

    public function test_logout_other_devices_keeps_this_device(): void
    {
        UserSession::create(['user_id' => $this->noel->id, 'session_hash' => UserSession::hashFor('lain'), 'device' => 'Android · Chrome', 'last_active_at' => now()]);

        $this->actingAs($this->noel)->post(route('profile.sessions.logout-others'))->assertRedirect(route('profile.sessions'));

        $this->assertSame(1, $this->noel->fresh()->session_version);
        $this->assertNotNull(UserSession::where('session_hash', UserSession::hashFor('lain'))->value('revoked_at'));
        $this->actingAs($this->noel)->get(route('profile.sessions'))->assertOk();
    }

    // ── Pengumuman (P1, P2) ───────────────────────────────

    public function test_announcement_flow_popup_banner_schedule_and_dismiss_per_session(): void
    {
        $this->actingAs($this->boss)->post(route('superadmin.announcements.store'), [
            'title' => 'Pemeliharaan malam ini', 'body' => "Normi istirahat 23.00–23.30.\nLihat https://normi.chat/status <script>x</script>",
            'display' => 'popup', 'tone' => 'penting', 'cta_label' => 'Detail', 'cta_url' => '/groups', 'publish' => '1',
        ])->assertRedirect(route('superadmin.announcements.index'));
        $popup = Announcement::firstOrFail();
        Announcement::create(['title' => 'Fitur baru', 'body' => 'Ada tema baru.', 'display' => 'banner', 'tone' => 'info', 'is_active' => true]);
        Announcement::create(['title' => 'Nanti', 'body' => 'x', 'display' => 'banner', 'tone' => 'info', 'is_active' => true, 'starts_at' => now()->addDay()]);
        Announcement::create(['title' => 'Draf', 'body' => 'x', 'display' => 'popup', 'tone' => 'info', 'is_active' => false]);

        $html = $this->actingAs($this->noel)->get(route('groups.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-announcement-dialog', $html);
        $this->assertStringContainsString('Pemeliharaan malam ini', $html);
        $this->assertStringContainsString('Fitur baru', $html);
        $this->assertStringNotContainsString('Nanti', $html);
        $this->assertStringNotContainsString('<script>x</script>', $html);
        $this->assertStringContainsString('<a href="https://normi.chat/status"', $html);

        // Ditutup: hilang selama sesi ini; superadmin tidak melihatnya sama sekali.
        $this->actingAs($this->noel)->post(route('announcements.dismiss', $popup))->assertNoContent();
        $this->actingAs($this->noel)->get(route('groups.index'))->assertDontSee('Pemeliharaan malam ini')->assertSee('Fitur baru');
        $this->actingAs($this->boss)->get(route('profile.show'))->assertDontSee('Fitur baru');

        // Masuk ulang (sesi baru): muncul lagi.
        $this->flushSession();
        $this->actingAs($this->noel)->get(route('groups.index'))->assertSee('Pemeliharaan malam ini');
    }

    public function test_announcement_rejects_unsafe_links_and_bad_schedule(): void
    {
        $base = ['title' => 'A', 'body' => 'B', 'display' => 'banner', 'tone' => 'info', 'cta_label' => 'Buka'];
        foreach (['javascript:alert(1)', 'http://normi.chat', '//evil.test'] as $url) {
            $this->actingAs($this->boss)->post(route('superadmin.announcements.store'), $base + ['cta_url' => $url])->assertSessionHasErrors('cta_url');
        }
        $this->actingAs($this->boss)->post(route('superadmin.announcements.store'), $base + ['cta_url' => '/groups', 'starts_at' => '2026-11-10T10:00', 'ends_at' => '2026-11-09T10:00'])
            ->assertSessionHasErrors('ends_at');
        $this->actingAs($this->noel)->get(route('superadmin.announcements.index'))->assertForbidden();
        $this->assertSame(0, Announcement::count());
    }

    // ── Bonus buat grup (B1, dicabut 4 Nov 2026: diganti grup gratis) ──

    public function test_checkouts_no_longer_carry_a_bonus_but_one_started_before_the_change_is_still_honored(): void
    {
        app(NormkreditPricing::class)->update(['free_groups_enabled' => false]);
        $this->actingAs($this->noel)->get(route('groups.create'))->assertDontSee('Bonus', false);

        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 't', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/t'], 201)]);
        $this->actingAs($this->noel)->post('/groups', ['name' => 'Panitia', 'password' => '1234']);
        $pending = PendingPayment::firstOrFail();
        $this->assertArrayNotHasKey('bonus_credits', $pending->metadata_json);

        // Checkout yang dimulai sebelum promo dicabut membawa bonusnya sendiri.
        $pending->update(['metadata_json' => $pending->metadata_json + ['bonus_credits' => 20]]);
        $this->postJson('/api/webhooks/midtrans', $this->notification($pending))->assertOk();

        $group = Group::firstOrFail();
        $this->assertSame(270.0, (float) $group->groupToken()->value('remaining_credits'));
        $this->assertSame(['deposit', 'bonus'], CreditLedgerEntry::where('group_id', $group->id)->orderBy('id')->pluck('type')->all());
        $this->postJson('/api/webhooks/midtrans', $this->notification($pending))->assertOk();
        $this->assertSame(270.0, (float) $group->groupToken()->value('remaining_credits'));
        $this->actingAs($this->boss)->get(route('superadmin.finance'))->assertSee('Saldo gratis 20,00 NK');
    }

    private function group(User $owner, array $members = []): Group
    {
        $group = Group::create(['name' => 'Panitia', 'owner_id' => $owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
        foreach ($members as $member) {
            GroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role_id' => Role::forKey(Role::MEMBER)->id, 'status' => 'active', 'joined_at' => now()]);
        }

        return $group;
    }

    /** @return array{0: Request, 1: Store} permintaan dengan sesi berid tetap milik $user */
    private function sessionRequest(User $user, string $id, ?int $version = 0): array
    {
        $session = new Store('uji', new ArraySessionHandler(10), str_pad($id, 40, 'x'));
        if ($version !== null) {
            $session->put('session_version', $version);
        }
        $request = Request::create('/groups');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user->fresh());
        $this->app['auth']->guard('web')->setUser($user->fresh());

        return [$request, $session];
    }

    private function track(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->app['auth']->guard('web')->setUser($request->user());

        return app(TrackUserSession::class)->handle($request, fn () => response('ok'));
    }

    private function notification(PendingPayment $pending): array
    {
        $gross = number_format((int) $pending->expected_amount, 2, '.', '');

        return [
            'order_id' => $pending->order_id,
            'status_code' => '200',
            'gross_amount' => $gross,
            'transaction_status' => 'settlement',
            'transaction_id' => 'mt-' . $pending->id,
            'payment_type' => 'qris',
            'signature_key' => hash('sha512', $pending->order_id . '200' . $gross . self::SERVER_KEY),
        ];
    }
}
