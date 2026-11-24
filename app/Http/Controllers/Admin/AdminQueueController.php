<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Queue\WorkerStatus;
use Illuminate\View\View;

/** Superadmin: status worker antrean dan permintaan Normi (docs/rancangan-worker-dan-file-nov.md). */
class AdminQueueController extends Controller
{
    public function index(WorkerStatus $workers): View
    {
        return view('superadmin.queues', [
            'state' => $workers->state(),
            'queues' => $workers->queues(),
            'ai' => $workers->aiRequests(),
            'failed' => $workers->failedJobs(),
        ]);
    }
}
