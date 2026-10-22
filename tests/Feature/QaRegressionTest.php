<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_blackbox_public_pages_are_accessible(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_blackbox_guest_is_redirected_from_protected_page(): void
    {
        $this->get('/groups')->assertRedirectContains('/login');
    }

    public function test_whitebox_login_offers_google_only(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk dengan Google')
            ->assertDontSee('Masuk dengan Interdotz');
    }
}
