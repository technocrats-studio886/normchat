<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_marketing_page_renders_with_nav_and_sign_in_button(): void
    {
        foreach (['/home' => 'One AI for the whole group.', '/about' => 'We build AI that a whole group can use.', '/pricing' => 'Rp30,000', '/contact' => 'Send message'] as $path => $text) {
            $this->get($path)
                ->assertOk()
                ->assertSee($text)
                ->assertSee('Sign in')
                ->assertSee('Prices')
                ->assertSee('Contact');
        }
    }

    public function test_legal_pages_are_linked_from_the_footer_and_the_sign_in_page(): void
    {
        $this->get('/terms')->assertOk()->assertSee('Terms of Service')->assertSee('Republic of Indonesia')->assertSee('Twenti Studio');
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy')->assertSee('OpenAI')->assertSee('30 days');
        foreach (['/home', '/pricing', '/about', '/contact', '/terms'] as $path) {
            $this->get($path)->assertSee('Terms of Service')->assertSee('Privacy Policy')->assertSee('brand/logo-mark.svg', false);
        }
        $this->get('/')->assertSee(route('terms'), false)->assertSee(route('privacy'), false)->assertSee('data-live-demo', false);
    }

    public function test_pricing_page_reads_numbers_from_config(): void
    {
        // Biaya layanan QRIS dipotong pihak ketiga: tidak disebut di situs pemasaran.
        $this->get('/pricing')->assertSee('250 Normkredit')->assertSee('Rp15,000')->assertDontSee('0.7%');
    }

    /** docs/rancangan-copy-marketing.md: manfaat dulu, angka dari setelan, klaim gratis hanya selama grup gratis aktif. */
    public function test_copy_follows_the_free_group_settings_and_compares_ai_levels(): void
    {
        $this->get('/home')->assertSee('Start your free group')->assertSee('70 Normkredit included, and your first 2 friends join free')->assertSee('data-live-demo', false)
            ->assertSee('One balance for the whole group')->assertSee('before joining')->assertSee('Start free')->assertSee(route('home'), false);
        // Empat mode Normi dari menu + (App\Enums\AiMode), masing-masing dengan kegunaannya.
        $this->get('/home')->assertSeeInOrder(['Guided Learning', 'Learn a topic step by step', 'Use it when', 'Create image', 'Turn an idea into a picture', 'Use it for', 'Canvas', 'Get a finished document or web page', 'Use it when', 'Deep Research', 'Research with sources you can check', 'Use it before'])
            ->assertSee('#i-book-open', false)->assertSee('id="i-magnifying-glass-plus"', false);
        $this->get('/pricing')->assertSee('Pay for Normi together, with no monthly subscription.')->assertSee('Free groups (your 1st and 2nd)')->assertSee('70 / 50 Normkredit')
            ->assertSeeInOrder(['Fast', 'Smart', 'Advanced'])->assertSee('Our most capable level')->assertDontSee('On par')
            ->assertSee('When the group balance reaches 500 Normkredit')->assertDontSee('gpt-6');
        $this->get('/about')->assertDontSee('normies')->assertSee('Anyone in the group can ask.')->assertSee('Twenti Studio')->assertDontSee('Sysnavia');
        // Normi Chat dikembangkan Twenti Studio: nama perusahaan lama tidak muncul di halaman mana pun.
        foreach (['/', '/home', '/pricing', '/about', '/contact', '/terms', '/privacy'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('Sysnavia')->assertDontSee('Uplift');
        }
        $this->get('/')->assertSee('Beranda')->assertSee(route('home'), false);

        config()->set('normchat.pricing.free_groups_enabled', false);
        foreach (['/home', '/pricing', '/about'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('Start your free group')->assertDontSee('Start free')->assertSee('Create your group');
        }
        $this->get('/pricing')->assertSee('Each group')->assertDontSee('Free groups');
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

    public function test_contact_messages_go_to_hello_and_partnerships_to_business(): void
    {
        config()->set('mail.default', 'array');
        config()->set('normchat.support_email', 'hello@normi.chat');
        config()->set('normchat.business_email', 'business@normi.chat');
        $send = fn (string $topic) => $this->post('/contact', ['name' => 'Rina', 'email' => 'rina@example.com', 'topic' => $topic, 'message' => 'We would like to work with you on a campus program.'])
            ->assertSessionHas('contact_status', 'sent');

        $send('Prices and payment');
        $send('Business partnership');

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(2, $sent);
        $this->assertSame('hello@normi.chat', $sent[0]->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertSame('business@normi.chat', $sent[1]->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertSame('rina@example.com', $sent[1]->getOriginalMessage()->getReplyTo()[0]->getAddress());

        $this->get('/contact')->assertSee('Business partnership')->assertSee('hello@normi.chat')->assertSee('business@normi.chat');
    }

    public function test_contact_honeypot_is_swallowed_without_sending(): void
    {
        $this->post('/contact', ['website' => 'spam.example'])->assertSessionHas('contact_status', 'sent');
    }
}
