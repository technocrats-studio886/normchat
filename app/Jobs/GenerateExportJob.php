<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Group;
use App\Services\Exports\ChatExportBuilder;
use App\Services\Exports\ChatExporter;
use App\Services\Exports\DocxChatExporter;
use App\Services\Exports\PdfChatExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Membuat file export riwayat chat grup (PDF atau DOCX). */
class GenerateExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $exportId)
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $export = Export::find($this->exportId);
        if (! $export) {
            return;
        }

        $export->update(['status' => 'processing']);

        try {
            $groupName = trim((string) Group::find($export->group_id)?->name) ?: 'Grup';
            $exporter = $this->exporterFor((string) $export->file_type);
            $storagePath = sprintf('%s-%d.%s', Str::slug($groupName) ?: 'grup', $export->id, $exporter->extension());

            Storage::disk('normchat_exports')->put(
                $storagePath,
                $exporter->render($groupName, app(ChatExportBuilder::class)->entriesFor((int) $export->group_id))
            );

            $export->update([
                'status' => 'done',
                'storage_path' => $storagePath,
                'file_name' => $groupName . '.' . $exporter->extension(),
            ]);
        } catch (Throwable $e) {
            report($e);
            $export->update(['status' => 'failed']);
        }
    }

    private function exporterFor(string $fileType): ChatExporter
    {
        return strtolower($fileType) === 'pdf' ? new PdfChatExporter() : new DocxChatExporter();
    }
}
