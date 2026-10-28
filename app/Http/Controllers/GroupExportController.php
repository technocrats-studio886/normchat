<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExportJob;
use App\Models\Export;
use App\Models\Group;
use App\Models\GroupMember;
use App\Services\Exports\ChatExportBuilder;
use App\Services\Exports\ExportOptions;
use App\Services\Files\StoredFiles;
use App\Support\UserTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman "Riwayat & Cadangan": unduh riwayat chat (PDF/DOCX) dengan filter, dibuat di antrean;
 * dan cadangan grup (GroupBackupController). Unduhan disimpan 7 hari.
 */
class GroupExportController extends Controller
{
    private const DISK = 'normchat_exports';

    public const KEEP_DAYS = 7;

    public function index(Request $request, Group $group): View
    {
        $this->authorize('view', $group);
        $user = $request->user();
        $canBackup = $user->can('createBackup', $group);

        return view('settings.history-export', [
            'group' => $group,
            'canExportChat' => $user->can('exportChat', $group),
            'canBackup' => $canBackup,
            'exports' => $this->exportsOf($group, $request)->map(fn (Export $export) => $this->present($group, $export))->all(),
            'backups' => $canBackup
                ? $group->backups()->with('creator:id,name')->latest('created_at')->get()->map(fn ($backup) => GroupBackupController::present($backup))->all()
                : [],
            // Pengirim untuk filter: anggota grup (termasuk yang sudah keluar tapi pernah mengirim) dan Normi.
            'senders' => GroupMember::where('group_id', $group->id)->with('user:id,name')->get()
                ->pluck('user')->filter()->sortBy('name')->map(fn ($member) => ['id' => (string) $member->id, 'name' => $member->name])->values()->all(),
        ]);
    }

    /** Daftar unduhan milik user (untuk memperbarui status tanpa memuat ulang halaman). */
    public function list(Request $request, Group $group): JsonResponse
    {
        $this->authorize('exportChat', $group);

        return response()->json(['ok' => true, 'exports' => $this->exportsOf($group, $request)->map(fn (Export $export) => $this->present($group, $export))]);
    }

    /** Jumlah pesan yang akan masuk unduhan dengan filter ini. */
    public function count(Request $request, Group $group, ChatExportBuilder $builder): JsonResponse
    {
        $this->authorize('exportChat', $group);

        return response()->json(['ok' => true, 'count' => $builder->count($group, ExportOptions::fromRequest($request, $request->user()))]);
    }

    public function store(Request $request, Group $group): JsonResponse
    {
        $this->authorize('exportChat', $group);
        $options = ExportOptions::fromRequest($request, $request->user());

        $export = Export::create([
            'group_id' => $group->id,
            'file_name' => Export::fileNameFor($group->name, now()->setTimezone($options->timezone), $options->format),
            'storage_path' => '',
            'file_type' => $options->format,
            'status' => 'queued',
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'filters' => $options->toArray(),
        ]);
        GenerateExportJob::dispatch($export->id);

        return response()->json(['ok' => true, 'export' => $this->present($group, $export)], 201);
    }

    public function show(Request $request, Group $group, Export $export): StreamedResponse
    {
        $this->authorize('exportChat', $group);
        abort_unless((int) $export->group_id === (int) $group->id && (int) $export->created_by === (int) $request->user()->id, 404);
        abort_unless($export->status === 'done' && $export->storage_path && app(StoredFiles::class)->size(self::DISK, $export->storage_path) !== null, 404);

        return Storage::disk(self::DISK)->download($export->storage_path, $export->file_name);
    }

    private function exportsOf(Group $group, Request $request)
    {
        return Export::where('group_id', $group->id)->where('created_by', $request->user()->id)->latest('created_at')->get();
    }

    private function present(Group $group, Export $export): array
    {
        return [
            'id' => $export->id,
            'file_name' => $export->file_name,
            'format' => strtoupper((string) $export->file_type),
            'status' => $export->status,
            'message_count' => (int) $export->message_count,
            'created_at' => UserTime::format($export->created_at),
            'expires_at' => UserTime::format($export->created_at?->copy()->addDays(self::KEEP_DAYS), 'j M Y'),
            'download_url' => $export->status === 'done' ? route('settings.export.download', [$group, $export]) : null,
        ];
    }
}
