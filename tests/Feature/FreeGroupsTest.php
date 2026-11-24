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
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Hak, saldo, dan alur gabung grup gratis lama tetap berjalan setelah pembuatan grup beralih ke pembayaran. */
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


    /** Salinan data grup lama untuk memastikan hak dan saldonya tetap berjalan sesudah rilis. */
    private function legacyGroup(User $owner): Group
    {
        $group = Group::create(['name' => 'Grup lama', 'owner_id' => $owner->id, 'status' => 'active',
            'free_member_slots' => 2, 'created_free' => true]);
        app(\App\Services\GroupMembership::class)->activateFreeGroup($group, $owner, 70);

        return $group;
    }

    public function test_two_members_join_free_with_the_code_only_then_others_chip_in_and_slots_do_not_return(): void
    {
        $owner = User::factory()->create();
        $group = $this->legacyGroup($owner);
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

    public function test_concurrent_submission_of_the_same_member_cannot_consume_two_slots(): void
    {
        $group = $this->legacyGroup(User::factory()->create());
        $friend = User::factory()->create();
        $joinedByFirstRequest = false;
        DB::listen(function (QueryExecuted $query) use ($group, $friend, &$joinedByFirstRequest) {
            if ($joinedByFirstRequest || ! str_contains($query->sql, 'from "groups"') || ! str_contains($query->sql, 'for update')) {
                return;
            }

            // Simulasikan kiriman pertama selesai setelah cek keanggotaan awal pada kiriman kedua.
            $joinedByFirstRequest = true;
            $group->increment('free_joins_used');
            app(\App\Services\GroupMembership::class)->joinFree($group, $friend);
        });

        $this->actingAs($friend)->post(route('groups.join.submit', $group->share_id))->assertRedirect(route('chat.show', $group));

        $this->assertTrue($joinedByFirstRequest);
        $this->assertSame(1, $group->fresh()->free_joins_used);
        $this->assertSame(1, AuditLog::where('group_id', $group->id)->where('action', 'group.member_joined')->count());
        $this->assertDatabaseCount('pending_payments', 0);
    }

    public function test_password_step_comes_before_the_bill_and_cannot_be_skipped(): void
    {
        $owner = User::factory()->create();
        $group = $this->legacyGroup($owner);
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
        $group = $this->legacyGroup($owner);
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

    public function test_free_credits_are_shown_as_gifts_not_revenue(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $owner = User::factory()->create();
        $group = $this->legacyGroup($owner);

        $this->actingAs($boss)->get(route('superadmin.finance'))->assertSee('Saldo gratis 70,00 NK');
        $this->actingAs($boss)->get(route('superadmin.groups.show', $group))->assertSee('Gratis · anggota gratis 0/2');
        $this->actingAs($owner)->get(route('settings.transactions', $group))->assertSee('Grup gratis')->assertSee('Normi Chat')->assertDontSee('No. transaksi');
        $this->actingAs($owner)->get(route('settings.show', $group))
            ->assertSee('0 dari 2 slot anggota gratis sudah terpakai')->assertSee('teman bisa bergabung cukup dengan kode grup.');
    }

    public function test_owner_sees_the_invite_card_until_the_free_slots_are_used(): void
    {
        $owner = User::factory()->create();
        $group = $this->legacyGroup($owner);
        $friend = User::factory()->create();

        $this->actingAs($owner)->get(route('chat.show', $group))->assertSee('data-invite-card="' . $group->id . '"', false)->assertSee('Ajak 2 teman bergabung gratis ke grup ini dengan kode');
        $this->actingAs($friend)->post(route('groups.join.submit', $group->share_id));
        $this->actingAs($friend)->get(route('chat.show', $group))->assertDontSee('data-invite-card', false);
        $this->actingAs($owner)->get(route('chat.show', $group))->assertSee('Ajak 1 teman');

        $this->actingAs(User::factory()->create())->post(route('groups.join.submit', $group->share_id));
        $this->actingAs($owner)->get(route('chat.show', $group))->assertDontSee('data-invite-card', false);
    }
}
