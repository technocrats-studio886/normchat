<?php

namespace Tests\Feature;

use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\GroupToken;
use App\Models\Message;
use App\Models\PendingPayment;
use App\Models\Role;
use App\Models\User;
use App\Services\NormkreditPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Bug krusial sisa audit 23 Nov 2026 (docs/rancangan-worker-dan-file-nov.md). */
class AuditFollowUpFixesTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.midtrans.server_key', self::SERVER_KEY);
        config()->set('services.midtrans.is_production', false);
        config()->set('normchat.pricing.free_groups_enabled', false);
        $this->owner = User::factory()->create();
        $this->group = Group::create(['name' => 'Kelas', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
        GroupToken::forGroup($this->group->id);
    }

    private function settle(PendingPayment $pending, string $settlementTime): void
    {
        $gross = number_format((int) $pending->expected_amount, 2, '.', '');
        $this->postJson('/api/webhooks/midtrans', [
            'order_id' => $pending->order_id, 'status_code' => '200', 'gross_amount' => $gross, 'transaction_status' => 'settlement',
            'transaction_id' => 'mt-'.$pending->id, 'payment_type' => 'bank_transfer', 'settlement_time' => $settlementTime,
            'signature_key' => hash('sha512', $pending->order_id.'200'.$gross.self::SERVER_KEY),
        ])->assertOk()->assertJsonPath('status', 'paid');
    }

    private function joinPayment(User $user): PendingPayment
    {
        return PendingPayment::create([
            'user_id' => $user->id, 'group_id' => $this->group->id, 'order_id' => 'NCJOIN-'.Str::random(8), 'payment_type' => 'group_join_midtrans',
            'expected_amount' => app(NormkreditPricing::class)->quote(125)->total(), 'status' => 'pending',
            'metadata_json' => ['credits' => 125], 'expires_at' => now()->addDay(),
        ]);
    }

    public function test_late_join_payment_keeps_an_existing_admin_role_and_open_history(): void
    {
        $admin = User::factory()->create();
        $pending = $this->joinPayment($admin);
        // Sementara VA belum dibayar, user masuk lewat jalur lain lalu dijadikan admin dan riwayatnya dibuka.
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $admin->id, 'role_id' => Role::forKey(Role::ADMIN)->id, 'status' => 'active', 'joined_at' => now(), 'history_from_message_id' => null]);
        $joinedBefore = Message::where('group_id', $this->group->id)->where('sender_type', Message::SENDER_SYSTEM)->count();

        $this->settle($pending, now()->toDateTimeString());

        $member = GroupMember::where('group_id', $this->group->id)->where('user_id', $admin->id)->sole();
        $this->assertSame(Role::forKey(Role::ADMIN)->id, $member->role_id);
        $this->assertNull($member->history_from_message_id);
        $this->assertSame($joinedBefore, Message::where('group_id', $this->group->id)->where('sender_type', Message::SENDER_SYSTEM)->count());
        $this->assertSame(125.0, (float) GroupToken::forGroup($this->group->id)->fresh()->credits, 'Patungannya tetap jadi saldo grup.');
    }

    public function test_midtrans_settlement_time_is_read_as_wib(): void
    {
        $pending = $this->joinPayment(User::factory()->create());

        $this->settle($pending, '2026-11-23 21:00:00');

        $this->assertSame('2026-11-23 14:00:00', $pending->fresh()->paid_at->utc()->toDateTimeString());
    }

    public function test_upload_happens_outside_the_group_lock_and_duplicate_or_busy_sends_leave_no_orphan_files(): void
    {
        Queue::fake();
        Storage::fake('normchat_attachments');
        $key = (string) Str::uuid();
        $send = fn () => $this->actingAs($this->owner)->post(route('chat.store', $this->group), [
            'content' => 'catatan', 'client_message_id' => $key, 'attachment' => UploadedFile::fake()->createWithContent('catatan.txt', 'isi catatan'),
        ], ['Accept' => 'application/json']);

        $send()->assertCreated();
        $send()->assertCreated();
        $this->assertSame(1, Message::where('client_message_id', $key)->count());
        $this->assertCount(1, Storage::disk('normchat_attachments')->allFiles(), 'Unggahan kiriman ulang dihapus.');

        $lock = Cache::lock('group-chat-submit:'.$this->group->id, 30);
        $lock->get();
        $this->actingAs($this->owner)->post(route('chat.store', $this->group), [
            'content' => 'lagi', 'attachment' => UploadedFile::fake()->createWithContent('lagi.txt', 'isi'),
        ], ['Accept' => 'application/json'])->assertStatus(503);
        $lock->release();
        $this->assertCount(1, Storage::disk('normchat_attachments')->allFiles(), 'Unggahan saat grup sibuk dihapus.');
    }

    public function test_failed_requests_explain_what_the_sender_can_do(): void
    {
        $question = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => '@normi jawab ulang']);
        $answer = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'message_type' => 'text', 'content' => 'lama', 'reply_to_message_id' => $question->id]);
        $answer->delete();
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $question->id, 'status' => 'failed', 'queued_at' => now(), 'attempt' => 1, 'replace_message_id' => $answer->id]);

        $this->actingAs($this->owner)->getJson(route('chat.requests', [$this->group, 'ids' => [$question->id]]))->assertOk()
            ->assertJsonPath('requests.0.can_retry', false)->assertJsonPath('requests.0.hint', 'resend');
    }
}
