<?php

namespace App\Console\Commands;

use App\Http\Controllers\GroupExportController;
use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Unduhan riwayat chat disimpan 7 hari, lalu berkas dan catatannya dihapus. */
class PruneExports extends Command
{
    protected $signature = 'normchat:prune-exports';

    protected $description = 'Hapus unduhan riwayat chat yang lebih dari 7 hari';

    public function handle(): int
    {
        $old = Export::where('created_at', '<', now()->subDays(GroupExportController::KEEP_DAYS))->get();
        foreach ($old as $export) {
            if ($export->storage_path) {
                Storage::disk('normchat_exports')->delete($export->storage_path);
            }
            $export->delete();
        }
        $this->info("Unduhan lama dihapus: {$old->count()}.");

        return self::SUCCESS;
    }
}
