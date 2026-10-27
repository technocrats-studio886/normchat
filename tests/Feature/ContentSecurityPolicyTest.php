<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Content-Security-Policy halaman, isolasi canvas, dan laporan pelanggaran. */
class ContentSecurityPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('normchat_attachments');
        $this->owner = User::factory()->create();
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    public function test_pages_send_a_nonce_based_policy_and_every_script_carries_the_nonce(): void
    {
        foreach ([route('chat.show', $this->group), route('groups.index'), route('profile.show'), route('settings.show', $this->group)] as $url) {
            $response = $this->actingAs($this->owner)->get($url)->assertOk();
            $policy = (string) $response->headers->get('Content-Security-Policy');

            $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9]+)';/", $policy, $url);
            preg_match("/'nonce-([A-Za-z0-9]+)'/", $policy, $nonce);
            $this->assertStringNotContainsString("'unsafe-inline' https://fonts.gstatic", $policy);
            $this->assertStringNotContainsString('unsafe-eval', $policy);
            $this->assertStringContainsString("object-src 'none'", $policy);
            $this->assertStringContainsString("frame-ancestors 'self'", $policy);
            // form-action akan memutus redirect ke halaman pembayaran setelah submit.
            $this->assertStringNotContainsString('form-action', $policy);

            preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $tags);
            $this->assertNotEmpty($tags[0], $url);
            foreach ($tags[0] as $tag) {
                $this->assertStringContainsString('nonce="' . $nonce[1] . '"', $tag, "$url: $tag");
            }
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/i', $response->getContent(), "$url: handler inline");
        }
    }

    public function test_each_request_gets_a_fresh_nonce(): void
    {
        $first = $this->actingAs($this->owner)->get(route('groups.index'))->headers->get('Content-Security-Policy');
        $second = $this->actingAs($this->owner)->get(route('groups.index'))->headers->get('Content-Security-Policy');

        $this->assertNotSame($first, $second);
    }

    public function test_realtime_address_is_allowed_for_websocket(): void
    {
        config()->set('normchat.csp.realtime', ['wss://normi.chat:443', 'wss://normi.chat']);

        $this->assertStringContainsString(
            "connect-src 'self' wss://normi.chat:443 wss://normi.chat;",
            (string) $this->actingAs($this->owner)->get(route('groups.index'))->headers->get('Content-Security-Policy')
        );
    }

    public function test_attachments_keep_their_own_sandbox_policy(): void
    {
        $message = $this->canvasMessage('text/markdown', 'g/doc.md', '# Judul');

        $this->actingAs($this->owner)->get(route('chat.attachment', [$this->group, $message]))
            ->assertOk()->assertHeader('Content-Security-Policy', 'sandbox');
    }

    public function test_web_canvas_is_served_in_its_own_sandbox(): void
    {
        $message = $this->canvasMessage('text/html', 'g/kuis.html', '<script>document.title="kuis"</script>');

        $response = $this->actingAs($this->owner)->get(route('chat.canvas', [$this->group, $message]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('<script>document.title="kuis"</script>', $response->getContent());

        $policy = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringStartsWith('sandbox allow-scripts ', $policy);
        $this->assertStringNotContainsString('allow-same-origin', $policy);
        $this->assertStringContainsString("frame-ancestors 'self'", $policy);
    }

    public function test_canvas_route_serves_only_web_canvases_to_members(): void
    {
        $document = $this->canvasMessage('text/markdown', 'g/doc.md', '# Judul');
        $this->actingAs($this->owner)->get(route('chat.canvas', [$this->group, $document]))->assertNotFound();

        $html = $this->canvasMessage('text/html', 'g/kuis.html', '<p>kuis</p>');
        $this->actingAs(User::factory()->create())->get(route('chat.canvas', [$this->group, $html]))->assertForbidden();

        $other = Group::create(['name' => 'Lain', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1'), 'status' => 'active']);
        $this->actingAs($this->owner)->get(route('chat.canvas', [$other, $html]))->assertNotFound();
    }

    public function test_violation_reports_are_logged_without_a_session(): void
    {
        Log::spy();

        $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => [
            'document-uri' => 'https://normi.chat/groups', 'violated-directive' => 'script-src', 'blocked-uri' => 'inline', 'ignored' => 'x',
        ]]))->assertNoContent()->assertCookieMissing(config('session.cookie'));

        Log::shouldHaveReceived('warning')->once()->with('CSP violation', [
            'document-uri' => 'https://normi.chat/groups', 'violated-directive' => 'script-src', 'blocked-uri' => 'inline',
        ]);
    }

    private function canvasMessage(string $mime, string $path, string $content): Message
    {
        Storage::disk('normchat_attachments')->put($path, $content);

        return Message::create([
            'group_id' => $this->group->id, 'sender_type' => 'ai', 'message_type' => 'canvas', 'content' => 'Canvas',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => $path, 'attachment_mime' => $mime,
            'attachment_original_name' => basename($path), 'attachment_size' => strlen($content),
        ]);
    }
}
