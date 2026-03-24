<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Message;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Throwable;

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
        $export = Export::query()->find($this->exportId);

        if (! $export) {
            return;
        }

        $export->status = 'processing';
        $export->save();

        try {
            $messages = Message::query()
            ->where('group_id', $export->group_id)
            ->orderBy('created_at')
            ->with('sender:id,name')
            ->get(['sender_type', 'sender_id', 'content', 'created_at']);

            $extension = strtolower($export->file_type) === 'pdf' ? 'pdf' : 'docx';
            $filename = sprintf('group-%d-export-%d.%s', $export->group_id, $export->id, $extension);

            if ($extension === 'pdf') {
                $binary = $this->renderPdf($messages->all(), $export->group_id);
            } else {
                $binary = $this->renderDocx($messages->all(), $export->group_id);
            }

            Storage::disk('normchat_exports')->put($filename, $binary);

            $export->status = 'done';
            $export->storage_path = $filename;
            $export->file_name = $filename;
            $export->save();
        } catch (Throwable $e) {
            report($e);

            $export->status = 'failed';
            $export->save();
        }
    }

    private function renderPdf(array $messages, int $groupId): string
    {
        $rows = [];

        foreach ($messages as $message) {
            $sender = $this->resolveSenderLabel($message->sender_type, $message->sender?->name ?? null);
            $rows[] = sprintf(
                '<tr><td style="padding:8px;border-bottom:1px solid #e2e8f0;white-space:nowrap;">%s</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;">%s</td><td style="padding:8px;border-bottom:1px solid #e2e8f0;">%s</td></tr>',
                e(optional($message->created_at)->format('Y-m-d H:i:s')),
                e($sender),
                nl2br(e($message->content))
            );
        }

        $html = sprintf(
            '<html><body style="font-family: DejaVu Sans, sans-serif;"><h2>Normchat Export - Group %d</h2><p>Generated at: %s</p><table width="100%%" cellspacing="0" cellpadding="0"><thead><tr><th align="left">Waktu</th><th align="left">Pengirim</th><th align="left">Pesan</th></tr></thead><tbody>%s</tbody></table></body></html>',
            $groupId,
            e(now()->format('Y-m-d H:i:s')),
            implode('', $rows)
        );

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    private function renderDocx(array $messages, int $groupId): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addTitle(sprintf('Normchat Export - Group %d', $groupId), 1);
        $section->addText('Generated at: '.now()->format('Y-m-d H:i:s'));
        $section->addTextBreak(1);

        foreach ($messages as $message) {
            $sender = $this->resolveSenderLabel($message->sender_type, $message->sender?->name ?? null);
            $timestamp = optional($message->created_at)->format('Y-m-d H:i:s');

            $section->addText(sprintf('[%s] %s', $timestamp, $sender), ['bold' => true]);
            $section->addText((string) $message->content);
            $section->addTextBreak(1);
        }

        $temp = tmpfile();
        $meta = stream_get_meta_data($temp);
        $tmpPath = $meta['uri'];

        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        $binary = file_get_contents($tmpPath) ?: '';
        fclose($temp);

        return $binary;
    }

    private function resolveSenderLabel(string $senderType, ?string $senderName): string
    {
        if ($senderType === 'ai') {
            return 'AI';
        }

        return $senderName ?: strtoupper($senderType);
    }
}
