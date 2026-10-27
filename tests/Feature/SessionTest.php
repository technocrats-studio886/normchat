<?php

namespace Tests\Feature;

use App\Http\Middleware\EndIdleSession;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

/** Sesi: langsung ke /groups bila masih login, dan berakhir setelah 5 hari tanpa aksi user. */
class SessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->user->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->user->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    public function test_logged_in_user_skips_landing_and_login_pages(): void
    {
        foreach (['/', '/normchat', '/login'] as $path) {
            $this->actingAs($this->user)->get($path)->assertRedirect(route('groups.index'));
        }
    }

    public function test_guest_still_sees_landing(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_login_starts_a_session_without_remember_cookie(): void
    {
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        $googleUser = (new SocialiteUser())->setRaw(['email_verified' => true])
            ->map(['id' => 'g-1', 'name' => 'Noel', 'email' => 'noel@example.com', 'avatar' => null]);
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback')
            ->assertRedirect(route('groups.index'))
            ->assertCookieMissing(Auth::guard('web')->getRecallerName())
            ->assertSessionHas(EndIdleSession::LAST_ACTIVITY);
        $this->assertNull(User::where('email', 'noel@example.com')->value('remember_token'));
    }

    public function test_session_lasts_five_days(): void
    {
        $this->assertSame(7200, config('session.lifetime'));
        $this->assertSame(5, config('normchat.session_idle_days'));
    }

    public function test_activity_on_day_four_restarts_the_five_days(): void
    {
        $fourDaysAgo = now()->subDays(4)->getTimestamp();

        $this->actingAs($this->user)
            ->withSession([EndIdleSession::LAST_ACTIVITY => $fourDaysAgo])
            ->get(route('groups.index'))
            ->assertOk()
            ->assertSessionHas(EndIdleSession::LAST_ACTIVITY, now()->getTimestamp());
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_five_idle_days_end_the_session(): void
    {
        $this->actingAs($this->user)
            ->withSession([EndIdleSession::LAST_ACTIVITY => now()->subDays(5)->getTimestamp()])
            ->get(route('groups.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['auth' => 'Sesi berakhir karena 5 hari tidak ada aktivitas. Masuk lagi untuk lanjut.']);
        $this->assertGuest();
    }

    public function test_expired_session_answers_background_requests_with_401(): void
    {
        $this->actingAs($this->user)
            ->withSession([EndIdleSession::LAST_ACTIVITY => now()->subDays(6)->getTimestamp()])
            ->postJson(route('chat.read', $this->group))
            ->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_automatic_requests_from_an_open_tab_do_not_extend_the_session(): void
    {
        $fourDaysAgo = now()->subDays(4)->getTimestamp();

        // Tab terbuka menandai pesan terbaca & memuat statistik polling secara otomatis.
        $this->actingAs($this->user)
            ->withSession([EndIdleSession::LAST_ACTIVITY => $fourDaysAgo])
            ->postJson(route('chat.read', $this->group))
            ->assertOk()
            ->assertSessionHas(EndIdleSession::LAST_ACTIVITY, $fourDaysAgo);
        $this->actingAs($this->user)
            ->withSession([EndIdleSession::LAST_ACTIVITY => $fourDaysAgo])
            ->getJson(route('chat.polls.stats', $this->group))
            ->assertOk()
            ->assertSessionHas(EndIdleSession::LAST_ACTIVITY, $fourDaysAgo);
    }
}
