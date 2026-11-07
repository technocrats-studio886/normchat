<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\SocialLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Nama/foto yang diubah di Akun tidak tertimpa data Google saat login lagi (laporan user 7 Nov 2026). */
class SocialLoginProfileTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $name = 'Alexander Google', ?string $avatar = 'https://lh3.googleusercontent.com/a/baru'): User
    {
        return app(SocialLogin::class)->findOrCreate('google', 'g-77', 'alex@contoh.test', $name, $avatar);
    }

    public function test_new_users_start_with_the_google_name_and_photo(): void
    {
        $user = $this->login();

        $this->assertSame('Alexander Google', $user->name);
        $this->assertSame('https://lh3.googleusercontent.com/a/baru', $user->avatar_url);
    }

    public function test_a_name_changed_in_the_account_page_survives_later_logins(): void
    {
        $user = $this->login();
        $this->actingAs($user)->post(route('profile.account.update'), ['name' => 'Alex'])->assertRedirect();
        $this->assertSame('Alex', $user->fresh()->name);

        // Login lagi (perangkat/browser lain): Google masih mengirim nama lama.
        $this->login();
        $this->assertSame('Alex', $user->fresh()->name);
    }

    public function test_uploaded_photo_is_kept_but_a_google_photo_is_refreshed(): void
    {
        $user = $this->login(avatar: 'https://lh3.googleusercontent.com/a/lama');
        $this->login(avatar: 'https://lh3.googleusercontent.com/a/baru');
        $this->assertSame('https://lh3.googleusercontent.com/a/baru', $user->fresh()->avatar_url, 'foto Google disegarkan');

        $own = route('avatars.show', 'foto-sendiri.webp');
        $user->forceFill(['avatar_url' => $own])->save();
        $this->login(avatar: 'https://lh3.googleusercontent.com/a/lain');
        $this->assertSame($own, $user->fresh()->avatar_url, 'foto unggahan sendiri dipertahankan');
    }
}
