<?php

namespace App\Services\Queue;

use App\Models\ChatMessageQueue;
use App\Support\Queues;
use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Throwable;

/** Status worker Horizon dan permintaan Normi untuk halaman superadmin Antrean dan normchat:check-workers. */
class WorkerStatus
{
    public function __construct(
        private readonly MasterSupervisorRepository $masters,
        private readonly WorkloadRepository $workload,
    ) {}

    /** @return 'running'|'paused'|'inactive'|'unknown' */
    public function state(): string
    {
        try {
            $masters = collect($this->masters->all());
        } catch (Throwable) {
            return 'unknown';
        }
        if ($masters->isEmpty()) {
            return 'inactive';
        }

        return $masters->contains(fn ($master) => ($master->status ?? '') === 'running') ? 'running' : 'paused';
    }

    public function running(): bool
    {
        return $this->state() === 'running';
    }

    /** @return list<array{queue: string, label: string, waiting: int, wait_seconds: int, processes: int}> */
    public function queues(): array
    {
        try {
            $rows = collect($this->workload->get())->keyBy('name');
        } catch (Throwable) {
            $rows = collect();
        }

        return collect(array_keys(Queues::LABELS))->map(fn (string $queue) => [
            'queue' => $queue,
            'label' => Queues::label($queue),
            'waiting' => (int) ($rows[$queue]['length'] ?? 0),
            'wait_seconds' => (int) ($rows[$queue]['wait'] ?? 0),
            'processes' => (int) ($rows[$queue]['processes'] ?? 0),
        ])->all();
    }

    /** Permintaan Normi 24 jam terakhir: jumlah per status dan lama selesai (antri sampai jawaban). */
    public function aiRequests(): array
    {
        $since = now()->subDay();
        $counts = ChatMessageQueue::where('queued_at', '>=', $since)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $durations = ChatMessageQueue::where('queued_at', '>=', $since)->where('status', 'processed')->whereNotNull('processed_at')
            ->get(['queued_at', 'processed_at'])
            ->map(fn ($item) => max(0, $item->processed_at->getTimestamp() - $item->queued_at->getTimestamp()))
            ->sort()->values();

        return [
            'queued' => (int) ChatMessageQueue::where('status', 'queued')->count(),
            'processing' => (int) ChatMessageQueue::where('status', 'processing')->count(),
            'processed' => (int) ($counts['processed'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
            'median_seconds' => $durations->isEmpty() ? null : (int) $durations[intdiv($durations->count() - 1, 2)],
            'p95_seconds' => $durations->isEmpty() ? null : (int) $durations[(int) floor(($durations->count() - 1) * 0.95)],
            'oldest_waiting' => ($oldest = ChatMessageQueue::where('status', 'queued')->min('queued_at')) ? \Illuminate\Support\Carbon::parse($oldest) : null,
        ];
    }

    /** @return list<array{name: string, queue: string, error: string, failed_at: ?string}> */
    public function failedJobs(int $limit = 20): array
    {
        return DB::table('failed_jobs')->latest('failed_at')->limit($limit)->get(['payload', 'queue', 'exception', 'failed_at'])
            ->map(fn ($row) => [
                'name' => class_basename((string) (json_decode((string) $row->payload, true)['displayName'] ?? 'Job')),
                'queue' => (string) $row->queue,
                'error' => mb_substr(strtok((string) $row->exception, "\n") ?: '', 0, 220),
                'failed_at' => $row->failed_at,
            ])->all();
    }
}
