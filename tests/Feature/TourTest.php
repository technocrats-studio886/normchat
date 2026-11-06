<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** Tur pengenalan (docs/rancangan-tur-dan-blok-kode.md). */
class TourTest extends TestCase
{
    use RefreshDatabase;

    private function memberOfGroup(User $user): Group
    {
        $group = Group::create(['name' => 'Kelas', 'owner_id' => $user->id, 'status' => 'active']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $user->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);

        return $group;
    }

    /** Jalankan listener Login di dalam request bersesi, lalu kembalikan sesi itu. */
    private function login(User $user): \Illuminate\Contracts\Session\Session
    {
        config()->set('normchat.pricing.free_groups_enabled', false);
        $request = Request::create('/auth/google/callback');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        app()->instance('request', $request);
        event(new Login('web', $user, false));

        return $session;
    }

    public function test_new_and_existing_users_see_the_tour_once(): void
    {
        $user = User::factory()->create();
        $group = $this->memberOfGroup($user);

        // Belum pernah melihat tur (pengguna baru, atau pengguna lama sebelum fitur ini): tampil.
        $this->actingAs($user)->get(route('chat.show', $group))->assertOk()
            ->assertSee('data-tour-due', false)->assertSee(route('tour.seen'), false);

        $this->actingAs($user)->postJson(route('tour.seen'))->assertOk();
        $this->assertNotNull($user->fresh()->tour_seen_at);
        $this->actingAs($user)->get(route('chat.show', $group))->assertDontSee('data-tour-due', false);

        // Login biasa (aktif kemarin) tidak memunculkan tur lagi.
        $user->forceFill(['last_active_at' => now()->subDay()])->save();
        $this->assertFalse($this->login($user->fresh())->get('tour_due', false));
    }

    public function test_tour_returns_after_three_weeks_away(): void
    {
        $user = User::factory()->create(['tour_seen_at' => now()->subMonths(2), 'last_active_at' => now()->subDays(22)]);
        $group = $this->memberOfGroup($user);

        $this->assertTrue($user->tourDue());
        $this->assertTrue($this->login($user)->get('tour_due'));
        $this->actingAs($user)->withSession(['tour_due' => true])->get(route('chat.show', $group))->assertSee('data-tour-due', false);

        $user->forceFill(['last_active_at' => now()->subDays(20)])->save();
        $this->assertFalse($user->fresh()->tourDue(), '20 hari belum 3 minggu.');
    }

    public function test_activity_is_recorded_and_tour_can_be_replayed_from_the_account_menu(): void
    {
        $user = User::factory()->create(['tour_seen_at' => now()]);
        $group = $this->memberOfGroup($user);

        $this->actingAs($user)->get(route('chat.show', $group))->assertDontSee('data-tour-due', false);
        $this->assertNotNull($user->fresh()->last_active_at);

        $this->actingAs($user)->get(route('profile.show'))->assertSee('Lihat tur lagi')->assertSee(route('tour.again'), false);
        $this->actingAs($user)->get(route('tour.again'))->assertRedirect(route('chat.last'))->assertSessionHas('tour_due', true);
        $this->actingAs($user)->withSession(['tour_due' => true])->get(route('chat.show', $group))->assertSee('data-tour-due', false);
    }

    public function test_superadmins_never_get_the_tour(): void
    {
        $boss = User::factory()->create(['email' => 'boss@normi.test']);
        config()->set('normchat.superadmin_emails', ['boss@normi.test']);

        $this->assertFalse($boss->tourDue());
        $this->actingAs($boss)->get(route('profile.show'))->assertDontSee('Lihat tur lagi');
    }
}
