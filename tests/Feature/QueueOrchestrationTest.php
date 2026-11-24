<?php

namespace Tests\Feature;

use App\Jobs\CreateBackupSnapshotJob;
use App\Jobs\EmbedGroupMessagesJob;
use App\Jobs\FetchLinkPreviewJob;
use App\Jobs\GenerateExportJob;
use App\Jobs\GenerateWeeklyRecapJob;
use App\Jobs\IndexDocumentJob;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Jobs\SendAdminAlertMail;
use App\Jobs\SendMessagePushJob;
use App\Jobs\SummarizeGroupContextJob;
use App\Models\AdminAlert;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\Events\LongWaitDetected;
use Mockery;
use Tests\TestCase;

/** Orkestrator antrean Horizon dan job Normi per permintaan (docs/rancangan-worker-dan-file-nov.md). */
class QueueOrchestrationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->group = Group::create(['name' => 'Grup Antrean', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    /** Pesan tanpa @normi: GroupAiAssistant langsung selesai, jadi yang diuji hanya alur antreannya. */
    private function queued(string $content = 'catatan'): ChatMessageQueue
    {
        $message = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => $content]);

        return ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now(), 'attempt' => 1]);
    }

    public function test_each_job_runs_on_its_own_queue(): void
    {
        Queue::fake();
        $expected = [
            'ai' => [new ProcessGroupChatQueueJob(1)],
            'notify' => [new SendMessagePushJob(1), new FetchLinkPreviewJob('https://contoh.id'), new SendAdminAlertMail('a', 'b')],
            'documents' => [new GenerateExportJob(1), new CreateBackupSnapshotJob(1), new IndexDocumentJob(1), new GenerateWeeklyRecapJob(1)],
            'maintenance' => [new EmbedGroupMessagesJob(1), new SummarizeGroupContextJob(1)],
        ];
        foreach ($expected as $queue => $jobs) {
            foreach ($jobs as $job) {
                $this->assertSame($queue, $job->queue, $job::class);
            }
        }
        $supervised = collect(config('horizon.defaults'))->pluck('queue')->flatten()->all();
        foreach ([...array_keys($expected), 'default'] as $queue) {
            $this->assertContains($queue, $supervised, "Antrean {$queue} tidak dijaga supervisor Horizon.");
        }
        $this->assertLessThanOrEqual(10, collect(config('horizon.defaults'))->sum(fn ($s) => $s['maxProcesses'] ?? $s['processes'] ?? 1));
        $this->assertGreaterThan(collect(config('horizon.defaults'))->max('timeout'), (int) config('queue.connections.redis.retry_after'));
    }

    public function test_one_request_per_job_then_the_next_job_is_dispatched(): void
    {
        $first = $this->queued('satu');
        $second = $this->queued('dua');
        Queue::fake();

        (new ProcessGroupChatQueueJob($this->group->id))->handle();

        $this->assertSame('processed', $first->fresh()->status);
        $this->assertSame('queued', $second->fresh()->status);
        Queue::assertPushedOn('ai', ProcessGroupChatQueueJob::class, fn ($job) => $job->groupId === $this->group->id);
        Queue::assertPushed(ProcessGroupChatQueueJob::class, 1);
    }

    public function test_sync_queue_still_drains_the_whole_group_in_order(): void
    {
        $items = [$this->queued('a'), $this->queued('b'), $this->queued('c')];

        (new ProcessGroupChatQueueJob($this->group->id))->handle();

        foreach ($items as $item) {
            $this->assertSame('processed', $item->fresh()->status);
        }
        $order = ChatMessageQueue::orderBy('processed_at')->orderBy('id')->pluck('id')->all();
        $this->assertSame(array_map(fn ($i) => $i->id, $items), $order);
    }

    public function test_a_busy_group_does_not_fail_or_retry_the_job(): void
    {
        $item = $this->queued();
        $lock = Cache::lock(ProcessGroupChatQueueJob::lockKey($this->group->id), 60);
        $this->assertTrue($lock->get());
        Queue::fake();

        $job = new ProcessGroupChatQueueJob($this->group->id);
        $job->handle();

        $this->assertSame('queued', $item->fresh()->status);
        Queue::assertNothingPushed();
        $lock->release();
    }

    public function test_long_wait_raises_one_admin_alert_per_queue_per_hour(): void
    {
        Queue::fake();
        $listeners = collect($this->app['events']->getRawListeners()[LongWaitDetected::class] ?? [])->map(fn ($l) => is_string($l) ? $l : (is_array($l) ? $l[0] : null));
        $this->assertTrue($listeners->contains(fn ($l) => is_string($l) && str_starts_with($l, \App\Listeners\AlertLongQueueWait::class)), 'Listener tidak terdaftar.');
        // Listener notifikasi bawaan Horizon butuh Redis; yang diuji di sini listener Normi.
        $listener = app(\App\Listeners\AlertLongQueueWait::class);
        $listener->handle(new LongWaitDetected('redis', 'ai', 185));
        $listener->handle(new LongWaitDetected('redis', 'ai', 240));

        $alert = AdminAlert::sole();
        $this->assertSame('danger', $alert->level);
        $this->assertStringContainsString('Jawaban Normi', $alert->title);
        Queue::assertPushed(SendAdminAlertMail::class, 1);
    }

    public function test_worker_check_alerts_after_two_minutes_down_and_resolves_when_back(): void
    {
        Queue::fake();
        $masters = Mockery::mock(MasterSupervisorRepository::class);
        $masters->shouldReceive('all')->andReturn([], [], [(object) ['status' => 'running']]);
        $this->app->instance(MasterSupervisorRepository::class, $masters);
        $this->app->instance(WorkloadRepository::class, Mockery::mock(WorkloadRepository::class));

        $this->artisan('normchat:check-workers')->assertFailed();
        $this->assertSame(0, AdminAlert::count());

        $this->travel(3)->minutes();
        $this->artisan('normchat:check-workers')->assertFailed();
        $this->assertSame('Worker antrean berhenti', AdminAlert::sole()->title);

        $this->artisan('normchat:check-workers')->assertSuccessful();
        $this->assertNotNull(AdminAlert::sole()->resolved_at);
    }

    public function test_superadmin_queue_page_shows_workers_queues_and_normi_requests(): void
    {
        config()->set('normchat.superadmin_emails', ['boss@normchat.test']);
        $boss = User::factory()->create(['email' => 'boss@normchat.test']);
        $masters = Mockery::mock(MasterSupervisorRepository::class);
        $masters->shouldReceive('all')->andReturn([(object) ['status' => 'running']]);
        $workload = Mockery::mock(WorkloadRepository::class);
        $workload->shouldReceive('get')->andReturn([['name' => 'ai', 'length' => 3, 'wait' => 42, 'processes' => 2]]);
        $this->app->instance(MasterSupervisorRepository::class, $masters);
        $this->app->instance(WorkloadRepository::class, $workload);
        $this->queued();
        $done = $this->queued();
        $done->update(['status' => 'processed', 'processed_at' => now()->addSeconds(12)]);

        $html = $this->actingAs($boss)->get(route('superadmin.queues'))->assertOk()->getContent();
        $this->assertStringContainsString('Worker berjalan', $html);
        $this->assertStringContainsString('Jawaban Normi', $html);
        $this->assertStringContainsString('42 dtk', $html);
        $this->assertStringContainsString('Median 12 dtk', $html);

        $this->actingAs($this->owner)->get(route('superadmin.queues'))->assertForbidden();
        $this->assertFalse(Gate::forUser($boss)->allows('viewHorizon'));
    }
}
