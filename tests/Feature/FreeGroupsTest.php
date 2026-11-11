<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CreditLedgerEntry;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupTokenContribution;
use App\Models\PendingPayment;
use App\Models\PricingVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\FreeGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** docs/rancangan-grup-gratis-login.md: login di normi.chat, grup gratis 70/50 NK, 2 anggota gratis per grup gratis. */
class FreeGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        config()->set('services.midtrans.server_key', 'SB-Mid-server-uji');
        config()->set('services.midtrans.is_production', false);
        Http::fake(['app.sandbox.midtrans.com/snap/v1/transactions' => Http::response(['token' => 't', 'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/t'], 201)]);
    }

    // ── Halaman masuk ─────────────────────────────────────

    /** Semua halaman publik menyampaikan hal yang sama: dua grup gratis (70, 50), lalu Rp30.000 / 250 NK mulai grup ke-3. */
    public function test_every_public_page_tells_the_same_free_group_and_price_story(): void
    {
        $this->get('/')->assertSee('Dua grup pertamamu gratis, dengan 70 dan 50 Normkredit.');
        $this->get('/home')->assertSee('Your first two groups are free.')->assertSee('Your first two groups are on us.')
            ->assertSee('Your first group starts with 70 Normkredit and your second with 50')
            ->assertSee('From your third group, a group costs Rp30,000 and starts with 250 Normkredit.')
            ->assertDontSee('Your first group is free.');
        $this->get('/pricing')->assertSee('Your first two groups are free, so you can try Normi')
            ->assertSee('After that, a group costs Rp30,000 and starts with 250 Normkredit.')
            ->assertSee('From your 3rd group')->assertDontSee('Groups after that');

        // Grup kedua tidak gratis lagi: semua halaman ikut menyebut satu grup gratis dan harga mulai grup ke-2.
        config()->set('normchat.pricing.free_group_credits', '70');
        $this->get('/')->assertSee('Grup pertamamu gratis dengan 70 Normkredit.')->assertDontSee('Dua grup');
        $this->get('/home')->assertSee('Your first group is on us.')->assertSee('From your second group, a group costs Rp30,000');
        $this->get('/pricing')->assertSee('From your 2nd group')->assertDontSee('first two groups');
    }

    public function test_normi_chat_opens_the_login_page_with_the_free_offer(): void
    {
        foreach (['/', '/login'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('Dari obrolan jadi rencana.')
                ->assertSee('Dua grup pertamamu gratis, dengan 70 dan 50 Normkredit. Di tiap grup gratis, 2 teman pertama ikut tanpa biaya.')
                ->assertSee('Masuk dengan Google')
                ->assertSee(route('about'), false)->assertSee(route('pricing'), false)->assertSee(route('contact'), false);
        }
        $this->get('/pricing')->assertSee('Start your free group');

        config()->set('normchat.pricing.free_groups_enabled', false);
        $this->get('/')->assertOk()->assertDontSee('grup pertamamu gratis')->assertDontSee('Gratis untuk mulai');
        $this->get('/pricing')->assertDontSee('Start your free group');
    }

    // ── Grup gratis ke-1 dan ke-2 ─────────────────────────

    public function test_first_login_creates_one_free_group_without_password_and_opens_its_chat(): void
    {
        $rina = User::factory()->create(['name' => 'Rina Ayu']);

        Auth::guard('web')->login($rina);
        Auth::guard('web')->login($rina);
        $group = Group::sole();
        $this->assertSame(['Grup Rina', 'active', null, 2, 0], [$group->name, $group->status, $group->password_hash, $group->free_member_slots, $group->free_joins_used]);
        $this->assertTrue($group->isOwnedBy($rina) && $group->hasActiveMember($rina));
        $this->assertSame(70.0, (float) $group->groupToken()->value('remaining_credits'));
        $this->assertSame(['bonus'], CreditLedgerEntry::where('group_id', $group->id)->pluck('type')->all());
        $gift = GroupTokenContribution::sole();
        $this->assertSame([GroupTokenContribution::SOURCE_FREE_GROUP, 70.0, 0], [$gift->source, (float) $gift->credit_amount, (int) $gift->price_paid]);
        $this->assertTrue(AuditLog::where('action', 'group.create_free')->exists());

        // User yang sesinya sudah ada (tanpa event login): /groups membuatkan grup lalu membuka chat-nya, sekali.
        $sari = User::factory()->create(['name' => 'Sari']);
        $this->actingAs($sari)->get(route('groups.index'))->assertRedirect(route('chat.show', Group::where('owner_id', $sari->id)->sole()));
        $this->actingAs($sari)->get(route('groups.index'))->assertOk();
        $this->assertSame(1, Group::where('owner_id', $sari->id)->count());
    }

    public function test_second_group_is_free_with_50_and_the_third_goes_to_payment(): void
    {
        $rina = User::factory()->create();
        app(FreeGroups::class)->ensureFirstGroup($rina);

        $this->actingAs($rina)->get(route('groups.create'))->assertOk()
            ->assertSee('50 Normkredit')->assertSee('Ini grup gratis terakhirmu.')->assertSee('Buat grup gratis')->assertDontSee('Biaya layanan');
        $response = $this->actingAs($rina)->post(route('groups.store'), ['name' => 'Panitia', 'password' => '1234']);
        $second = Group::where('name', 'Panitia')->sole();
        $response->assertRedirect(route('chat.show', $second))->assertSessionHas('success', 'Grup gratis dibuat dengan saldo 50 Normkredit.');
        $this->assertTrue(Hash::check('1234', $second->password_hash));
        $this->assertSame([50.0, 2], [(float) $second->groupToken()->value('remaining_credits'), $second->free_member_slots]);

        $this->actingAs($rina)->get(route('groups.create'))->assertSee('Buat Grup · Rp30.000')->assertDontSee('Buat grup gratis');
        $this->actingAs($rina)->post(route('groups.store'), ['name' => 'Bayar', 'password' => '1234']);
        $this->assertSame('pending_payment', Group::where('name', 'Bayar')->value('status'));
        $this->assertSame(1, PendingPayment::count());
    }

    public function test_quota_counts_every_group_ever_created_but_not_unpaid_checkouts(): void
    {
        $free = app(FreeGroups::class);
        $owner = User::factory()->create();
        Group::create(['name' => 'Belum bayar', 'owner_id' => $owner->id, 'status' => 'pending_payment']);
        $this->assertSame(70.0, $free->nextCredits($owner));

        $paid = Group::create(['name' => 'Lama', 'owner_id' => $owner->id, 'status' => 'active']);
        $paid->delete();
        $this->assertSame(50.0, $free->nextCredits($owner));
        $this->assertNull($free->ensureFirstGroup($owner), 'pernah punya grup: tidak dibuatkan grup otomatis');

        Group::create(['name' => 'Lama 2', 'owner_id' => $owner->id, 'status' => 'frozen']);
        $this->assertNull($free->nextCredits($owner));
        $this->assertNull($free->create($owner, 'Lagi'));

        // Grup pertama dicek ulang di dalam kunci: permintaan kedua yang berjalan bersamaan tidak membuat grup ke-2.
        $new = User::factory()->create();
        $this->assertNotNull($free->ensureFirstGroup($new));
        $this->assertNull($free->create($new, 'Grup kedua', firstOnly: true));
        $this->assertSame(1, Group::where('owner_id', $new->id)->count());
    }

    public function test_superadmin_never_gets_a_free_group(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        Auth::guard('web')->login($boss);

        $this->assertNull(app(FreeGroups::class)->nextCredits($boss));
        $this->assertSame(0, Group::count());
    }

    public function test_pricing_survives_a_cache_that_still_holds_the_removed_bonus_keys(): void
    {
        // Cache produksi dibuat sebelum promo bonus dicabut; tanpa penyaring, setiap halaman yang membaca harga error.
        // Kunci lama free_first_group_credits (sebelum daftar grup gratis, 12 Nov 2026) juga diabaikan.
        \Illuminate\Support\Facades\Cache::forever('normchat:pricing-settings', ['group_bonus_enabled' => '1', 'group_bonus_starts_at' => '2026-11-01T00:00:00Z', 'free_first_group_credits' => '20', 'free_group_credits' => '90,40']);

        $pricing = app(\App\Services\NormkreditPricing::class);
        $settings = $pricing->settings();
        $this->assertArrayNotHasKey('group_bonus_enabled', $settings);
        $this->assertArrayNotHasKey('free_first_group_credits', $settings);
        $this->assertSame([90.0, 40.0], $pricing->freeGroupCredits());
    }

    // ── Gabung ke grup gratis ─────────────────────────────

    public function test_two_members_join_free_with_the_code_only_then_others_chip_in_and_slots_do_not_return(): void
    {
        $owner = User::factory()->create();
        $group = app(FreeGroups::class)->ensureFirstGroup($owner);
        [$ani, $budi, $citra] = User::factory()->count(3)->create()->all();

        $this->actingAs($ani)->get(route('groups.join', $group->share_id))->assertOk()->assertSee('Bergabung')->assertSee(route('groups.join.continue', $group->share_id));
        // Grup tanpa sandi: Bergabung langsung ke langkah masuk gratis, tanpa kolom sandi dan tanpa tagihan.
        $this->actingAs($ani)->get(route('groups.join.continue', $group->share_id))->assertOk()
            ->assertSee('Gabung gratis')->assertSee('Masih ada 2 slot gratis')->assertDontSee('name="password"', false)->assertDontSee('Tagihan patungan');
        $this->actingAs($ani)->post(route('groups.join.submit', $group->share_id))->assertRedirect(route('chat.show', $group));
        $this->actingAs($budi)->post(route('groups.join.submit', $group->share_id))->assertRedirect(route('chat.show', $group));
        $this->assertTrue($group->fresh()->hasActiveMember($ani) && $group->fresh()->hasActiveMember($budi));
        $this->assertSame(2, $group->fresh()->free_joins_used);
        $this->assertSame(0, PendingPayment::count());
        $this->assertSame(70.0, (float) $group->groupToken()->value('remaining_credits'));

        // Anggota gratis keluar: slot tidak kembali.
        $this->actingAs($ani)->post(route('groups.leave', $group))->assertRedirect(route('groups.index'));
        $this->assertSame(0, $group->fresh()->freeSlotsLeft());

        $this->actingAs($citra)->get(route('groups.join.continue', $group->share_id))->assertSee('Tagihan patungan')->assertDontSee('Gabung gratis');
        $this->actingAs($citra)->post(route('groups.join.submit', $group->share_id))->assertSessionHasErrors('packages');
        $this->actingAs($citra)->post(route('groups.join.submit', $group->share_id), ['packages' => 1]);
        $this->assertFalse($group->fresh()->hasActiveMember($citra));
        $this->assertSame('group_join_midtrans', PendingPayment::sole()->payment_type);

        $this->actingAs($ani)->post(route('groups.join.submit', $group->share_id))->assertSessionHasErrors('packages');
    }

    public function test_password_step_comes_before_the_bill_and_cannot_be_skipped(): void
    {
        $owner = User::factory()->create();
        $group = app(FreeGroups::class)->ensureFirstGroup($owner);
        $group->update(['password_hash' => Hash::make('rahasia'), 'free_member_slots' => 0]);
        $friend = User::factory()->create();

        // Profil grup: tombol Bergabung, belum ada kolom sandi atau tagihan.
        $this->actingAs($friend)->get(route('groups.join', $group->share_id))->assertOk()
            ->assertSee('Bergabung')->assertDontSee('name="password"', false)->assertDontSee('Tagihan patungan');
        // Bergabung → diminta sandi dulu.
        $this->actingAs($friend)->get(route('groups.join.continue', $group->share_id))->assertSee('name="password"', false)->assertDontSee('Tagihan patungan');
        // Melompati langkah sandi dengan mengirim form bayar langsung ditolak.
        $this->actingAs($friend)->post(route('groups.join.submit', $group->share_id), ['packages' => 1])
            ->assertRedirect(route('groups.join.continue', $group->share_id))->assertSessionHasErrors('password');
        $this->assertSame(0, PendingPayment::count());

        $this->actingAs($friend)->post(route('groups.join.password', $group->share_id), ['password' => 'salah'])->assertSessionHasErrors('password');
        $this->actingAs($friend)->get(route('groups.join.continue', $group->share_id))->assertDontSee('Tagihan patungan');
        $this->actingAs($friend)->post(route('groups.join.password', $group->share_id), ['password' => 'rahasia'])
            ->assertRedirect(route('groups.join.continue', $group->share_id));
        $this->actingAs($friend)->get(route('groups.join.continue', $group->share_id))->assertSee('Tagihan patungan')->assertSee('Bayar dan gabung');

        // Sandi benar belum membuat jadi anggota: masuk grup hanya setelah pembayaran berhasil.
        $this->actingAs($friend)->post(route('groups.join.submit', $group->share_id), ['packages' => 1]);
        $this->assertFalse($group->fresh()->hasActiveMember($friend));
        $this->assertSame('group_join_midtrans', PendingPayment::sole()->payment_type);
        $this->actingAs($friend)->get(route('chat.show', $group))->assertForbidden();
    }

    public function test_password_verification_expires_and_resets_when_the_password_changes(): void
    {
        $owner = User::factory()->create();
        $group = app(FreeGroups::class)->ensureFirstGroup($owner);
        $group->update(['password_hash' => Hash::make('rahasia')]);
        $friend = User::factory()->create();

        $this->actingAs($friend)->post(route('groups.join.password', $group->share_id), ['password' => 'rahasia']);
        $this->actingAs($friend)->get(route('groups.join.continue', $group->share_id))->assertSee('Gabung gratis');

        $group->update(['password_hash' => Hash::make('baru')]);
        $this->actingAs($friend)->get(route('groups.join.continue', $group->share_id))->assertSee('name="password"', false);

        $this->actingAs($friend)->post(route('groups.join.password', $group->share_id), ['password' => 'baru']);
        $this->travel(31)->minutes();
        $this->actingAs($friend)->post(route('groups.join.submit', $group->share_id))->assertSessionHasErrors('password');
        $this->assertFalse($group->fresh()->hasActiveMember($friend));
    }

    public function test_unpaid_and_frozen_groups_do_not_take_new_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $friend = User::factory()->create();
        $pending = Group::create(['name' => 'Belum bayar', 'owner_id' => $owner->id, 'status' => 'pending_payment', 'free_member_slots' => 2]);
        $frozen = Group::create(['name' => 'Beku', 'owner_id' => $owner->id, 'status' => Group::STATUS_FROZEN, 'free_member_slots' => 2]);
        GroupMember::create(['group_id' => $frozen->id, 'user_id' => $member->id, 'role_id' => Role::forKey(Role::MEMBER)->id, 'status' => 'active', 'joined_at' => now()]);

        foreach ([$pending, $frozen] as $group) {
            $this->actingAs($friend)->get(route('groups.join', $group->share_id))->assertNotFound();
            $this->actingAs($friend)->post(route('groups.join.submit', $group->share_id))->assertNotFound();
        }
        $this->actingAs($member)->get(route('groups.join', $frozen->share_id))->assertOk()->assertSee('Kamu sudah bergabung di grup ini.');
        $this->assertSame(0, $pending->fresh()->free_joins_used + $frozen->fresh()->free_joins_used);
    }

    // ── Superadmin, keuangan, tampilan ────────────────────

    public function test_superadmin_settings_apply_to_groups_created_afterwards(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $first = app(FreeGroups::class)->ensureFirstGroup(User::factory()->create());

        $this->actingAs($boss)->post(route('superadmin.free-groups.update'), ['free_group_credits' => [70, 50], 'free_group_member_slots' => 2])
            ->assertRedirect(route('superadmin.free-groups'));
        $this->assertNull(app(FreeGroups::class)->ensureFirstGroup(User::factory()->create()), 'saklar mati');

        $this->actingAs($boss)->post(route('superadmin.free-groups.update'), ['free_groups_enabled' => '1', 'free_group_credits' => [100, 40], 'free_group_member_slots' => 3]);
        $next = app(FreeGroups::class)->ensureFirstGroup(User::factory()->create());
        $this->assertSame([100.0, 3], [(float) $next->groupToken()->value('remaining_credits'), $next->free_member_slots]);
        $this->assertSame(2, $first->fresh()->free_member_slots);
        $this->assertSame(2, AuditLog::where('action', 'admin.update_free_groups')->count());
        $this->assertSame(2, PricingVersion::count());

        $this->actingAs($boss)->get(route('superadmin.free-groups'))->assertOk()
            ->assertSee('Grup gratis dibuat')->assertSee('170 NK')->assertDontSee('Buat grup');
        $this->actingAs($boss)->get('/')->assertRedirect(route('groups.index'));
    }

    public function test_superadmin_sets_how_many_free_groups_and_every_public_page_follows(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $this->actingAs($boss)->post(route('superadmin.free-groups.update'), ['free_groups_enabled' => '1', 'free_group_credits' => [100, 60, 30, null, 20], 'free_group_member_slots' => 1])
            ->assertRedirect(route('superadmin.free-groups'));
        $this->actingAs($boss)->get(route('superadmin.free-groups'))
            ->assertSee('Tiga grup pertamamu gratis, dengan 100, 60, dan 30 Normkredit. Di tiap grup gratis, 1 teman pertama ikut tanpa biaya.')
            ->assertSee('Saldo grup gratis ke-4 (NK)');
        auth()->logout();

        $this->get('/')->assertSee('Tiga grup pertamamu gratis, dengan 100, 60, dan 30 Normkredit.')->assertDontSee('70 dan 50');
        $this->get('/home')->assertSee('Your first three groups are on us.')
            ->assertSee('Your first group starts with 100 Normkredit, your second with 60, and your third with 30')
            ->assertSee('From your fourth group, a group costs');
        $this->get('/pricing')->assertSee('From your 4th group')->assertSee('100 / 60 / 30 Normkredit')->assertSee('Free groups (your 1st to 3rd)');

        // Grup ke-1 s.d. ke-3 gratis dengan saldo masing-masing, ke-4 lewat pembayaran.
        $user = User::factory()->create();
        $free = app(FreeGroups::class);
        $credits = [];
        for ($i = 0; $i < 3; $i++) {
            $credits[] = (float) $free->create($user, "Grup {$i}")->groupToken()->value('remaining_credits');
        }
        $this->assertSame([100.0, 60.0, 30.0], $credits);
        $this->assertNull($free->create($user, 'Grup 4'));

        // Semua dikosongkan: tanpa grup gratis, halaman publik tidak menawarkannya lagi (bukan kembali ke 70/50).
        $this->actingAs($boss)->post(route('superadmin.free-groups.update'), ['free_groups_enabled' => '1', 'free_group_credits' => [null, null], 'free_group_member_slots' => 1]);
        auth()->logout();
        $this->assertSame([], app(\App\Services\NormkreditPricing::class)->freeGroupCredits());
        $this->get('/')->assertDontSee('grup pertamamu gratis')->assertDontSee('70 dan 50');
        $this->get('/pricing')->assertDontSee('Start your free group')->assertDontSee('Free groups (your');
        $this->assertNull(app(FreeGroups::class)->ensureFirstGroup(User::factory()->create()));
    }

    public function test_free_credits_are_shown_as_gifts_not_revenue(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $owner = User::factory()->create();
        $group = app(FreeGroups::class)->ensureFirstGroup($owner);

        $this->actingAs($boss)->get(route('superadmin.finance'))->assertSee('Saldo gratis 70,00 NK');
        $this->actingAs($boss)->get(route('superadmin.groups.show', $group))->assertSee('Gratis · anggota gratis 0/2');
        $this->actingAs($owner)->get(route('settings.transactions', $group))->assertSee('Grup gratis')->assertSee('Normi Chat')->assertDontSee('No. transaksi');
        $this->actingAs($owner)->get(route('settings.show', $group))
            ->assertSee('0 dari 2 slot anggota gratis sudah terpakai')->assertSee('teman bisa bergabung cukup dengan kode grup.');
    }

    public function test_owner_sees_the_invite_card_until_the_free_slots_are_used(): void
    {
        $owner = User::factory()->create();
        $group = app(FreeGroups::class)->ensureFirstGroup($owner);
        $friend = User::factory()->create();

        $this->actingAs($owner)->get(route('chat.show', $group))->assertSee('data-invite-card="' . $group->id . '"', false)->assertSee('Ajak 2 teman bergabung gratis ke grup ini dengan kode');
        $this->actingAs($friend)->post(route('groups.join.submit', $group->share_id));
        $this->actingAs($friend)->get(route('chat.show', $group))->assertDontSee('data-invite-card', false);
        $this->actingAs($owner)->get(route('chat.show', $group))->assertSee('Ajak 1 teman');

        $this->actingAs(User::factory()->create())->post(route('groups.join.submit', $group->share_id));
        $this->actingAs($owner)->get(route('chat.show', $group))->assertDontSee('data-invite-card', false);
    }
}
