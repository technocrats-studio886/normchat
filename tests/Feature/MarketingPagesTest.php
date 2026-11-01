<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_marketing_page_renders_with_nav_and_sign_in_button(): void
    {
        foreach (['/' => 'Chat together, ask Normi.', '/about' => 'About Normi Chat', '/pricing' => 'Rp30,000', '/contact' => 'Send message'] as $path => $text) {
            $this->get($path)
                ->assertOk()
                ->assertSee($text)
                ->assertSee('Sign in')
                ->assertSee('Prices')
                ->assertSee('Contact');
        }
    }

    public function test_pricing_page_reads_numbers_from_config(): void
    {
        $this->get('/pricing')->assertSee('250 Normkredit')->assertSee('Rp15,000')->assertSee('0.7%');
    }

    public function test_contact_form_validates_and_accepts_a_message(): void
    {
        $this->post('/contact', ['name' => '', 'email' => 'nope'])->assertSessionHasErrors(['name', 'email', 'topic', 'message']);

        $this->post('/contact', [
            'name' => 'Rina',
            'email' => 'rina@example.com',
            'topic' => 'Prices and payment',
            'message' => 'How do I top up the group balance?',
        ])->assertRedirect(route('contact'))->assertSessionHas('contact_status', 'sent');
    }

    public function test_contact_honeypot_is_swallowed_without_sending(): void
    {
        $this->post('/contact', ['website' => 'spam.example'])->assertSessionHas('contact_status', 'sent');
    }
}
