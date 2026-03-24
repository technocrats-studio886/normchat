<?php

namespace Tests\Feature;

use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeConnectionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_claude_route_shows_api_key_form(): void
    {
        $this->get('/connect/claude')
            ->assertOk()
            ->assertSee('Connect Claude')
            ->assertSee('API Key');
    }

    public function test_connect_chatgpt_route_shows_api_key_form(): void
    {
        $this->get('/connect/chatgpt')
            ->assertOk()
            ->assertSee('Connect ChatGPT')
            ->assertSee('API Key');
    }

    public function test_api_key_connect_validates_and_creates_user(): void
    {
        // Mock the Anthropic API validation call
        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'hi']]], 200),
        ]);

        $response = $this->post('/connect/claude', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'api_key' => 'sk-ant-api03-test-key-1234567890',
        ]);

        $response->assertRedirect();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('claude', $user->auth_provider);

        $connection = AiConnection::where('user_id', $user->id)->first();
        $this->assertNotNull($connection);
        $this->assertSame('claude', $connection->provider);
    }

    public function test_group_create_requires_llm_connection(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/groups/create')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['llm' => 'You must connect an LLM before creating a group']);
    }
}
