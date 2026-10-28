<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Group;
use App\Models\User;
use App\Services\Exports\ChatExportBuilder;
use App\Services\Exports\ChatExporter;
use App\Services\Exports\DocxChatExporter;
use App\Services\Exports\ExportOptions;
use App\Services\Exports\PdfChatExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Membuat berkas unduhan riwayat chat (PDF atau DOCX) sesuai filter yang dipilih user. */
class GenerateExportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** Grup ramai dengan banyak gambar butuh waktu. */
    public int $timeout = 600;

    public function __construct(public int $exportId)
    {
        $this->onQueue('default');
    }

    public function handle(ChatExportBuilder $builder): void
    {
        $export = Export::find($this->exportId);
        $group = $export ? Group::find($export->group_id) : null;
        $exporter = $export ? User::find($export->created_by) : null;
        if (! $export || ! $group || ! $exporter) {
            return;
        }

        $export->update(['status' => 'processing']);
        $options = ExportOptions::fromArray((array) $export->filters);
        $document = $builder->build($group, $options, $exporter);
        $renderer = $this->exporterFor($options->format);
        $path = sprintf('group-%d/export-%d.%s', $group->id, $export->id, $renderer->extension());

        Storage::disk('normchat_exports')->put($path, $renderer->render($document));
        $export->update(['status' => 'done', 'storage_path' => $path, 'message_count' => $document->count()]);
    }

    public function failed(Throwable $e): void
    {
        Export::whereKey($this->exportId)->update(['status' => 'failed']);
    }

    private function exporterFor(string $format): ChatExporter
    {
        return $format === 'docx' ? new DocxChatExporter() : new PdfChatExporter();
    }
}
