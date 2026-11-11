<?php

namespace Tests\Feature;

use App\Jobs\SendMessagePushJob;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\PushSubscription;
use App\Models\Role;
use App\Models\User;
use App\Services\Push\MessagePush;
use App\Services\Push\PushPresence;
use App\Services\Push\WebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Notifikasi push (11 Nov 2026): izin per perangkat, hanya untuk pesan yang belum dibaca di perangkat mana pun,
 * tidak saat Normi Chat sedang terbuka, dan bisu per grup.
 */
class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $member;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $keys = WebPush::generateKeys();
        config()->set('normchat.push.public_key', $keys['public']);
        config()->set('normchat.push.private_key', $keys['private']);
        config()->set('normchat.push.subject', 'mailto:support@normi.chat');
        // Job push dari pesan baru tidak langsung jalan (antrean test sync); tiap test memanggil MessagePush sendiri.
        Queue::fake([SendMessagePushJob::class]);

        $this->owner = User::factory()->create(['name' => 'Budi Santoso']);
        $this->member = User::factory()->create(['name' => 'Sari Wulandari']);
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        foreach ([[$this->owner, Role::OWNER], [$this->member, Role::MEMBER]] as [$user, $role]) {
            GroupMember::create(['group_id' => $this->group->id, 'user_id' => $user->id, 'role_id' => Role::forKey($role)->id, 'status' => 'active', 'joined_at' => now()]);
        }
    }

    public function test_encryption_matches_rfc_8291_test_vector(): void
    {
        $d = fn (string $value) => WebPush::base64UrlDecode($value);
        $body = WebPush::encrypt(
            'When I grow up, I want to be a watermelon',
            $d('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            $d('BTBZMqHH6r4Tts7J_aSIgg'),
            $d('DGv6ra1nlYgDCS1FRnbzlw'),
            ['private' => $d('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'), 'public' => $d('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8')],
        );

        $this->assertSame(
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN',
            WebPush::base64UrlEncode($body)
        );
    }

    public function test_device_subscribes_only_to_known_push_services(): void
    {
        $keys = $this->browserKeys();

        $this->actingAs($this->member)->postJson('/push/subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => $keys, 'device' => 'Chrome di Android'])->assertOk();
        $this->actingAs($this->member)->postJson('/push/subscriptions', ['endpoint' => 'https://web.push.apple.com/QGu', 'keys' => $keys])->assertOk();
        $this->actingAs($this->member)->postJson('/push/subscriptions', ['endpoint' => 'https://169.254.169.254/latest', 'keys' => $keys])->assertStatus(422);
        $this->actingAs($this->member)->postJson('/push/subscriptions', ['endpoint' => 'https://evil.example/fcm.googleapis.com', 'keys' => $keys])->assertStatus(422);
        $this->assertSame(2, PushSubscription::where('user_id', $this->member->id)->count());

        // Browser yang sama dipakai akun lain: langganannya pindah, tidak dobel.
        $this->actingAs($this->owner)->postJson('/push/subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => $keys])->assertOk();
        $this->assertSame(1, PushSubscription::where('user_id', $this->member->id)->count());

        $this->actingAs($this->owner)->deleteJson('/push/subscriptions', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertOk();
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_new_message_queues_a_delayed_push(): void
    {

        $this->actingAs($this->owner)->postJson("/groups/{$this->group->id}/messages", ['content' => 'Halo semua'])->assertSuccessful();

        Queue::assertPushed(SendMessagePushJob::class, fn (SendMessagePushJob $job) => $job->delay !== null);
    }

    public function test_unread_message_is_pushed_with_vapid_and_encrypted_body(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response('', 201)]);
        $this->subscribe($this->member, 'https://fcm.googleapis.com/fcm/send/sari');
        $this->subscribe($this->owner, 'https://fcm.googleapis.com/fcm/send/budi');

        $message = $this->say($this->owner, 'Besok kumpul jam 7 ya');
        $sent = app(MessagePush::class)->send($message);

        $this->assertSame(1, $sent);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://fcm.googleapis.com/fcm/send/sari'
            && str_starts_with($request->header('Authorization')[0], 'vapid t=')
            && $request->header('Content-Encoding')[0] === 'aes128gcm'
            && $request->header('Topic')[0] === 'g' . $this->group->id
            && strlen($request->body()) > 86);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/budi'));
    }

    public function test_message_read_on_another_device_is_not_pushed(): void
    {
        Http::fake();
        $this->subscribe($this->member, 'https://fcm.googleapis.com/fcm/send/sari-hp');
        $message = $this->say($this->owner, 'Sudah baca di laptop');

        // Dibaca dari laptop sebelum jeda push habis.
        $this->actingAs($this->member)->postJson("/groups/{$this->group->id}/messages/read", ['last_read_message_id' => $message->id])->assertOk();

        $this->assertSame(0, app(MessagePush::class)->send($message));
        Http::assertNothingSent();
    }

    public function test_no_push_while_the_app_is_open_on_any_device(): void
    {
        Http::fake();
        $this->subscribe($this->member, 'https://fcm.googleapis.com/fcm/send/sari-hp');
        PushPresence::touch($this->member->id, 'laptop-session', true);

        $this->assertSame(0, app(MessagePush::class)->send($this->say($this->owner, 'Halo')));

        // Tab laptop disembunyikan: HP kembali menerima notifikasi.
        PushPresence::touch($this->member->id, 'laptop-session', false);
        Http::fake(['*' => Http::response('', 201)]);
        $this->assertSame(1, app(MessagePush::class)->send($this->say($this->owner, 'Halo lagi')));
    }

    public function test_muted_group_only_pushes_mentions_and_replies_to_me(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $this->subscribe($this->member, 'https://fcm.googleapis.com/fcm/send/sari');
        $this->actingAs($this->member)->putJson("/groups/{$this->group->id}/notifications", ['duration' => '8h'])
            ->assertOk()->assertJsonPath('muted', true);

        $this->assertSame(0, app(MessagePush::class)->send($this->say($this->owner, 'Info umum')));
        $this->assertSame(1, app(MessagePush::class)->send($this->say($this->owner, '@sari_wulandari cek ini ya')));

        $mine = $this->say($this->member, 'Pertanyaanku');
        $this->assertSame(1, app(MessagePush::class)->send($this->say($this->owner, 'Jawabannya begini', ['reply_to_message_id' => $mine->id])));

        $this->actingAs($this->member)->putJson("/groups/{$this->group->id}/notifications", ['duration' => 'off'])->assertJsonPath('muted', false);
        $this->assertSame(1, app(MessagePush::class)->send($this->say($this->owner, 'Info umum lagi')));
    }

    public function test_expired_subscription_is_removed(): void
    {
        Http::fake(['*' => Http::response('', 410)]);
        $this->subscribe($this->member, 'https://fcm.googleapis.com/fcm/send/lama');

        app(MessagePush::class)->send($this->say($this->owner, 'Halo'));

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_presence_endpoint_and_test_push(): void
    {
        Http::fake(['*' => Http::response('', 201)]);
        $this->subscribe($this->member, 'https://fcm.googleapis.com/fcm/send/sari');

        $this->actingAs($this->member)->postJson('/push/presence', ['active' => true])->assertOk();
        $this->assertTrue(PushPresence::isActive($this->member->id));

        $this->actingAs($this->member)->postJson('/push/test', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/sari'])
            ->assertOk()->assertJsonPath('ok', true);
    }

    /** @return array{p256dh: string, auth: string} kunci langganan seperti dari browser */
    private function browserKeys(): array
    {
        return ['p256dh' => WebPush::generateKeys()['public'], 'auth' => WebPush::base64UrlEncode(random_bytes(16))];
    }

    private function subscribe(User $user, string $endpoint): void
    {
        $keys = $this->browserKeys();
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => $keys['p256dh'],
            'auth_token' => $keys['auth'],
        ]);
    }

    private function say(User $user, string $content, array $extra = []): Message
    {
        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $user->id, 'message_type' => 'text', 'content' => $content, ...$extra]);
    }
}
