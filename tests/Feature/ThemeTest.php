<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Tema per akun (mode + aksen): dipilih di Profil, dipasang di <html> semua halaman. */
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

    public function test_new_account_follows_system_with_navy_accent(): void
    {
        $this->actingAs($this->user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('<html lang="id" data-theme-mode="system" data-mode="light" data-accent="navy">', false)
            ->assertSee('<meta name="theme-color" content="#ffffff">', false);
    }

    public function test_saved_theme_applies_to_every_page(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('profile.theme.update'), ['mode' => 'dark', 'accent' => 'toska'])
            ->assertOk()
            ->assertJson(['ok' => true, 'mode' => 'dark', 'accent' => 'toska']);

        $this->assertSame(['dark', 'toska'], [$this->user->fresh()->theme_mode, $this->user->fresh()->theme_accent]);

        foreach ([route('profile.show'), route('groups.index'), route('chat.show', $this->group), route('settings.show', $this->group)] as $url) {
            $this->actingAs($this->user)->get($url)
                ->assertOk()
                ->assertSee('data-theme-mode="dark" data-mode="dark" data-accent="toska"', false)
                ->assertSee('<meta name="theme-color" content="#121C29">', false);
        }
    }

    public function test_theme_is_per_account(): void
    {
        $this->user->update(['theme_mode' => 'dark', 'theme_accent' => 'bata']);
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('profile.show'))
            ->assertSee('data-theme-mode="system" data-mode="light" data-accent="navy"', false);
    }

    public function test_defaults_are_stored_empty(): void
    {
        $this->user->update(['theme_mode' => 'dark', 'theme_accent' => 'bata']);

        $this->actingAs($this->user)
            ->postJson(route('profile.theme.update'), ['mode' => 'system', 'accent' => 'navy'])
            ->assertOk();

        $this->assertNull($this->user->fresh()->theme_mode);
        $this->assertNull($this->user->fresh()->theme_accent);
    }

    public function test_unknown_mode_or_accent_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('profile.theme.update'), ['mode' => 'sepia', 'accent' => 'navy'])
            ->assertUnprocessable();
        $this->actingAs($this->user)
            ->postJson(route('profile.theme.update'), ['mode' => 'dark', 'accent' => 'pink'])
            ->assertUnprocessable();

        $this->assertNull($this->user->fresh()->theme_mode);
    }

    public function test_guest_cannot_save_a_theme(): void
    {
        $this->postJson(route('profile.theme.update'), ['mode' => 'dark', 'accent' => 'navy'])->assertUnauthorized();
    }

    public function test_unknown_stored_values_fall_back_to_defaults(): void
    {
        $this->user->forceFill(['theme_mode' => 'sepia', 'theme_accent' => 'ungu'])->save();

        $this->actingAs($this->user)->get(route('profile.show'))
            ->assertSee('data-theme-mode="system" data-mode="light" data-accent="navy"', false);
    }

    public function test_profile_shows_current_theme_and_picker(): void
    {
        $this->user->update(['theme_mode' => 'light', 'theme_accent' => 'hijau']);

        $this->actingAs($this->user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('data-theme-open="1"', false)
            ->assertSee('data-theme-url="' . route('profile.theme.update') . '" data-theme-mode="light" data-theme-accent="hijau"', false)
            ->assertSee('aria-checked="true" data-theme-mode-option="light"', false)
            ->assertSee('aria-checked="true" aria-label="Hijau daun"', false)
            ->assertSeeInOrder(['data-theme-current-label="1">', 'Terang · Hijau daun'], false);
    }

    public function test_preview_has_its_own_mode_so_the_page_does_not_change(): void
    {
        $this->actingAs($this->user)->get(route('profile.show'))
            ->assertSee('data-theme-preview="1" data-mode="light" data-accent="navy"', false);
    }

    public function test_theme_is_only_changed_from_profile(): void
    {
        $this->actingAs($this->user)->get(route('chat.show', $this->group))
            ->assertOk()
            ->assertDontSee('data-theme-panel', false);
    }

    public function test_old_theme_values_are_migrated(): void
    {
        $migration = require database_path('migrations/2026_10_27_120000_split_theme_into_mode_and_accent.php');
        $migration->down();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $this->user->id)->update(['theme' => 'coral']);
        $other = User::factory()->create();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $other->id)->update(['theme' => 'navy']);

        $migration->up();

        $this->assertSame(['light', 'bata'], [$this->user->fresh()->theme_mode, $this->user->fresh()->theme_accent]);
        $this->assertSame(['dark', null], [$other->fresh()->theme_mode, $other->fresh()->theme_accent]);
    }
}
