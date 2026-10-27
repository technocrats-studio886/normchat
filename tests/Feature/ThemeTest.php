<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Tema tampilan per akun: dipilih di Profil, dipasang di <html data-theme> semua halaman. */
class ThemeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->group = Group::create([
            'name' => 'Kelas BD',
            'owner_id' => $this->user->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
        ]);
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
            'role_id' => Role::forKey(Role::OWNER)->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_pages_start_with_default_theme(): void
    {
        $this->actingAs($this->user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('<html lang="id" data-theme="default">', false)
            ->assertSee('<meta name="theme-color" content="#ffffff">', false);
    }

    public function test_saved_theme_applies_to_every_page(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('profile.theme.update'), ['theme' => 'navy'])
            ->assertOk()
            ->assertJson(['ok' => true, 'theme' => 'navy']);

        $this->assertSame('navy', $this->user->fresh()->theme);

        foreach ([route('profile.show'), route('groups.index'), route('chat.show', $this->group), route('settings.show', $this->group)] as $url) {
            $this->actingAs($this->user)->get($url)
                ->assertOk()
                ->assertSee('data-theme="navy"', false)
                ->assertSee('<meta name="theme-color" content="#121C29">', false);
        }
    }

    public function test_theme_is_per_account(): void
    {
        $this->user->update(['theme' => 'coral']);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('profile.show'))
            ->assertSee('data-theme="default"', false);
    }

    public function test_choosing_default_clears_the_saved_theme(): void
    {
        $this->user->update(['theme' => 'coral']);

        $this->actingAs($this->user)
            ->postJson(route('profile.theme.update'), ['theme' => 'default'])
            ->assertOk();

        $this->assertNull($this->user->fresh()->theme);
    }

    public function test_unknown_theme_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('profile.theme.update'), ['theme' => 'pink'])
            ->assertUnprocessable();

        $this->assertNull($this->user->fresh()->theme);
    }

    public function test_guest_cannot_save_a_theme(): void
    {
        $this->postJson(route('profile.theme.update'), ['theme' => 'navy'])->assertUnauthorized();
    }

    public function test_removed_theme_falls_back_to_default(): void
    {
        $this->user->forceFill(['theme' => 'tema-lama'])->save();

        $this->actingAs($this->user)->get(route('profile.show'))
            ->assertSee('data-theme="default"', false);
    }

    public function test_profile_shows_current_theme_and_picker(): void
    {
        $this->user->update(['theme' => 'coral']);

        $this->actingAs($this->user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('data-theme-open="1"', false)
            ->assertSee('data-theme-url="' . route('profile.theme.update') . '"', false)
            ->assertSee('data-theme-option="coral"', false)
            ->assertSee('aria-checked="true"', false)
            ->assertSeeInOrder(['data-theme-current-label="1">', 'Coral'], false);
    }

    public function test_theme_is_only_changed_from_profile(): void
    {
        $this->actingAs($this->user)->get(route('chat.show', $this->group))
            ->assertOk()
            ->assertDontSee('data-open-theme-picker', false)
            ->assertDontSee('data-theme-panel', false);

        $this->actingAs($this->user)->get(route('settings.show', $this->group))
            ->assertOk()
            ->assertDontSee('Tema Chat');
    }
}
