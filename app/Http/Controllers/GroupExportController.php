<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateExportJob;
use App\Models\Export;
use App\Models\Group;
use App\Services\Files\StoredFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Export riwayat chat (PDF/DOCX) dan daftar backup grup. */
class GroupExportController extends Controller
{
    private const DISK = 'normchat_exports';

    public function index(Request $request, Group $group): View
    {
        $this->authorize('view', $group);
        $user = $request->user();

        $group->load([
            'exports' => fn ($query) => $query->latest('created_at'),
            'backups' => fn ($query) => $query->with('creator')->latest('created_at'),
        ]);

        return view('settings.history-export', [
            'group' => $group,
            'canExportChat' => $user->can('exportChat', $group),
            'canCreateBackup' => $user->can('createBackup', $group),
            'canRestoreBackup' => $user->can('restoreBackup', $group),
        ]);
    }

    /** Dibuat langsung (bukan lewat queue) agar tombol export langsung mengunduh file. */
    public function store(Request $request, Group $group): RedirectResponse|StreamedResponse
    {
        $this->authorize('exportChat', $group);
        $fileType = $request->validate(['file_type' => ['required', 'in:pdf,docx']])['file_type'];

        $export = Export::create([
            'group_id' => $group->id,
            'file_name' => sprintf('%s.%s', $group->name, $fileType),
            'storage_path' => '',
            'file_type' => $fileType,
            'status' => 'queued',
            'created_by' => $request->user()->id,
            'created_at' => now(),
        ]);

        (new GenerateExportJob($export->id))->handle();

        return $this->download($group, $export->refresh())
            ?? back()->withErrors(['export' => 'File belum siap. Coba lagi sebentar lagi.']);
    }

    public function show(Group $group, Export $export): StreamedResponse|RedirectResponse
    {
        $this->authorize('exportChat', $group);
        abort_unless((int) $export->group_id === (int) $group->id, 404);

        return $this->download($group, $export) ?? back()->withErrors(['export' => 'File tidak tersedia.']);
    }

    private function download(Group $group, Export $export): ?StreamedResponse
    {
        if ($export->status !== 'done' || ! $export->storage_path || app(StoredFiles::class)->size(self::DISK, $export->storage_path) === null) {
            return null;
        }

        return Storage::disk(self::DISK)->download(
            $export->storage_path,
            $export->file_name ?: sprintf('%s.%s', $group->name, $export->file_type)
        );
    }
}
