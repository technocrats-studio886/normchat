<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\Group;
use App\Models\Message;
use App\Models\PollVote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            $group = Group::query()->find($export->group_id);
            $groupName = trim((string) ($group?->name ?? ''));
            if ($groupName === '') {
                $groupName = 'Grup';
            }

            $messages = Message::query()
                ->where('group_id', $export->group_id)
                ->orderBy('created_at')
                ->with('sender:id,name')
                ->get([
                    'id',
                    'group_id',
                    'message_type',
                    'sender_type',
                    'sender_id',
                    'content',
                    'created_at',
                    'attachment_disk',
                    'attachment_path',
                    'attachment_mime',
                    'attachment_original_name',
                ]);

            $pollStatsMap = $this->buildPollStatsMap($messages->all());

            $extension = strtolower($export->file_type) === 'pdf' ? 'pdf' : 'docx';
            $slug = Str::slug($groupName) ?: 'grup';
            $storageName = sprintf('%s-%d.%s', $slug, $export->id, $extension);
            $displayName = sprintf('%s.%s', $groupName, $extension);

            if ($extension === 'pdf') {
                $binary = $this->renderPdf($messages->all(), $groupName, $pollStatsMap);
            } else {
                $binary = $this->renderDocx($messages->all(), $groupName, $pollStatsMap);
            }

            Storage::disk('normchat_exports')->put($storageName, $binary);

            $export->status = 'done';
            $export->storage_path = $storageName;
            $export->file_name = $displayName;
            $export->save();
        } catch (Throwable $e) {
            report($e);

            $export->status = 'failed';
            $export->save();
        }
    }

    private function renderPdf(array $messages, string $groupName, array $pollStatsMap): string
    {
        $rows = [];

        foreach ($messages as $message) {
            $sender = $this->resolveSenderLabel($message->sender_type, $message->sender?->name ?? null);
            $timestamp = e(optional($message->created_at)->format('Y-m-d H:i:s'));
            $contentHtml = nl2br(e((string) ($message->content ?? '')));
            $linksHtml = $this->renderPdfLinks((string) ($message->content ?? ''));
            $attachmentHtml = $this->renderPdfAttachment($message);
            $pollHtml = $this->renderPdfPoll($pollStatsMap[(int) $message->id] ?? null);

            $rows[] = sprintf(
                '<div style="border:1px solid #e2e8f0;border-radius:10px;padding:10px 12px;margin-bottom:10px;">'
                    . '<div style="font-size:11px;color:#64748b;margin-bottom:6px;">[%s] <strong style="color:#0f172a;">%s</strong></div>'
                    . '%s%s%s%s'
                . '</div>',
                $timestamp,
                e($sender),
                $contentHtml !== '' ? '<div style="font-size:12px;color:#0f172a;line-height:1.45;">'.$contentHtml.'</div>' : '',
                $pollHtml,
                $linksHtml,
                $attachmentHtml
            );
        }

        $html = sprintf(
            '<html><body style="font-family: DejaVu Sans, sans-serif;"><h2>Normchat Export - %s</h2><p>Generated at: %s</p><div>%s</div></body></html>',
            e($groupName),
            e(now()->format('Y-m-d H:i:s')),
            implode('', $rows)
        );

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    private function renderDocx(array $messages, string $groupName, array $pollStatsMap): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $tempImagePaths = [];
        $section->addTitle(sprintf('Normchat Export - %s', $groupName), 1);
        $section->addText('Generated at: '.now()->format('Y-m-d H:i:s'));
        $section->addTextBreak(1);

        foreach ($messages as $message) {
            $sender = $this->resolveSenderLabel($message->sender_type, $message->sender?->name ?? null);
            $timestamp = optional($message->created_at)->format('Y-m-d H:i:s');

            $section->addText(sprintf('[%s] %s', $timestamp, $sender), ['bold' => true]);

            $content = trim((string) ($message->content ?? ''));
            if ($content !== '') {
                $section->addText($content);
            }

            $this->appendDocxPoll($section, $pollStatsMap[(int) $message->id] ?? null);
            $this->appendDocxLinks($section, (string) ($message->content ?? ''));
            $this->appendDocxAttachment($section, $message, $tempImagePaths);

            $section->addTextBreak(1);
        }

        $temp = tmpfile();
        $meta = stream_get_meta_data($temp);
        $tmpPath = $meta['uri'];

        IOFactory::createWriter($phpWord, 'Word2007')->save($tmpPath);
        $binary = file_get_contents($tmpPath) ?: '';
        fclose($temp);

        foreach ($tempImagePaths as $imagePath) {
            if (is_string($imagePath) && $imagePath !== '' && file_exists($imagePath)) {
                @unlink($imagePath);
            }
        }

        return $binary;
    }

    private function resolveSenderLabel(string $senderType, ?string $senderName): string
    {
        if ($senderType === 'ai') {
            return 'AI';
        }

        return $senderName ?: strtoupper($senderType);
    }

    private function buildPollStatsMap(array $messages): array
    {
        $pollDefinitions = [];

        foreach ($messages as $message) {
            $poll = $this->parsePollDefinition((string) ($message->content ?? ''));
            if (! $poll) {
                continue;
            }

            $pollDefinitions[(int) $message->id] = $poll;
        }

        if ($pollDefinitions === []) {
            return [];
        }

        $pollIds = array_keys($pollDefinitions);
        $votesByPoll = [];

        PollVote::query()
            ->selectRaw('poll_message_id, option_number, COUNT(*) as votes_total')
            ->whereIn('poll_message_id', $pollIds)
            ->groupBy('poll_message_id', 'option_number')
            ->get()
            ->each(function (PollVote $voteRow) use (&$votesByPoll): void {
                $pollId = (int) $voteRow->poll_message_id;
                $optionNumber = (int) $voteRow->option_number;
                $votesTotal = (int) ($voteRow->getAttribute('votes_total') ?? 0);

                if (! isset($votesByPoll[$pollId])) {
                    $votesByPoll[$pollId] = [];
                }

                $votesByPoll[$pollId][$optionNumber] = $votesTotal;
            });

        $result = [];

        foreach ($pollDefinitions as $pollId => $poll) {
            $options = [];
            $totalVotes = 0;

            foreach (($poll['options'] ?? []) as $option) {
                $optionNumber = (int) ($option['number'] ?? 0);
                if ($optionNumber <= 0) {
                    continue;
                }

                $count = (int) ($votesByPoll[$pollId][$optionNumber] ?? 0);
                $totalVotes += $count;

                $options[] = [
                    'number' => $optionNumber,
                    'label' => (string) ($option['label'] ?? ''),
                    'count' => $count,
                ];
            }

            $options = array_map(function (array $option) use ($totalVotes): array {
                $percent = $totalVotes > 0
                    ? round(($option['count'] / $totalVotes) * 100, 1)
                    : 0;

                return $option + ['percent' => $percent];
            }, $options);

            $result[(int) $pollId] = [
                'question' => (string) ($poll['question'] ?? ''),
                'options' => $options,
                'total_votes' => $totalVotes,
            ];
        }

        return $result;
    }

    private function parsePollDefinition(string $content): ?array
    {
        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $content));
        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^(\x{1F4CA}\s*)?poll:/iu', $normalized)) {
            return null;
        }

        $lines = collect(explode("\n", $normalized))
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '')
            ->values();

        if ($lines->isEmpty()) {
            return null;
        }

        $question = trim((string) preg_replace('/^(\x{1F4CA}\s*)?poll:\s*/iu', '', (string) $lines->first()));
        if ($question === '') {
            return null;
        }

        $options = [];
        foreach ($lines->slice(1) as $line) {
            if (! preg_match('/^(\d+)[\.)]\s+(.+)$/u', (string) $line, $matches)) {
                continue;
            }

            $number = (int) ($matches[1] ?? 0);
            $label = trim((string) ($matches[2] ?? ''));
            if ($number <= 0 || $label === '') {
                continue;
            }

            $options[] = [
                'number' => $number,
                'label' => $label,
            ];
        }

        if (count($options) < 2) {
            return null;
        }

        return [
            'question' => $question,
            'options' => array_values(array_slice($options, 0, 8)),
        ];
    }

    private function extractLinks(string $text): array
    {
        if (! preg_match_all('/https?:\/\/[^\s<]+/i', $text, $matches)) {
            return [];
        }

        return collect($matches[0] ?? [])
            ->map(fn ($url) => trim((string) $url))
            ->filter(fn ($url) => $url !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function detectAttachmentKind(Message $message): string
    {
        $messageType = strtolower((string) ($message->message_type ?? ''));
        $mime = strtolower((string) ($message->attachment_mime ?? ''));
        $name = strtolower((string) ($message->attachment_original_name ?? ''));

        if ($messageType === 'image' || str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if ($messageType === 'video' || str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if ($messageType === 'voice' || str_starts_with($mime, 'audio/')) {
            return 'voice';
        }
        if (preg_match('/\.(jpg|jpeg|png|webp|gif|bmp|heic|heif)$/i', $name)) {
            return 'image';
        }
        if (preg_match('/\.(mp4|mov|m4v|webm|3gp|mkv)$/i', $name)) {
            return 'video';
        }
        if (preg_match('/\.(mp3|wav|ogg|aac|m4a)$/i', $name)) {
            return 'voice';
        }

        return 'file';
    }

    private function readAttachmentBinary(Message $message): ?string
    {
        if (! $message->attachment_disk || ! $message->attachment_path) {
            return null;
        }

        try {
            $disk = Storage::disk((string) $message->attachment_disk);
            if (! $disk->exists((string) $message->attachment_path)) {
                return null;
            }

            $binary = $disk->get((string) $message->attachment_path);
            return $binary !== '' ? $binary : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function renderPdfPoll(?array $pollStats): string
    {
        if (! is_array($pollStats)) {
            return '';
        }

        $rows = [];
        foreach (($pollStats['options'] ?? []) as $option) {
            $rows[] = sprintf(
                '<li style="margin-bottom:2px;">%d. %s - %d vote (%.1f%%)</li>',
                (int) ($option['number'] ?? 0),
                e((string) ($option['label'] ?? '')),
                (int) ($option['count'] ?? 0),
                (float) ($option['percent'] ?? 0)
            );
        }

        $summary = (int) ($pollStats['total_votes'] ?? 0) > 0
            ? sprintf('%d total vote', (int) ($pollStats['total_votes'] ?? 0))
            : 'Belum ada vote';

        return sprintf(
            '<div style="margin-top:8px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;">'
                . '<div style="font-size:12px;font-weight:700;margin-bottom:4px;">Polling: %s</div>'
                . '<ul style="margin:0 0 4px 14px;padding:0;font-size:11px;">%s</ul>'
                . '<div style="font-size:10px;color:#475569;">%s</div>'
            . '</div>',
            e((string) ($pollStats['question'] ?? '')),
            implode('', $rows),
            e($summary)
        );
    }

    private function renderPdfLinks(string $content): string
    {
        $links = $this->extractLinks($content);
        if ($links === []) {
            return '';
        }

        $items = collect($links)
            ->map(fn ($url) => '<li style="margin-bottom:2px;"><a href="'.e($url).'" style="color:#2563eb;text-decoration:underline;">'.e($url).'</a></li>')
            ->implode('');

        return '<div style="margin-top:8px;"><div style="font-size:11px;font-weight:700;color:#334155;">Link</div><ul style="margin:2px 0 0 14px;padding:0;font-size:11px;color:#2563eb;">'.$items.'</ul></div>';
    }

    private function renderPdfAttachment(Message $message): string
    {
        if (! $message->attachment_path) {
            return '';
        }

        $kind = $this->detectAttachmentKind($message);
        $name = e((string) ($message->attachment_original_name ?: basename((string) $message->attachment_path)));
        $mime = e((string) ($message->attachment_mime ?: 'application/octet-stream'));
        $attachmentUrl = $this->attachmentAccessUrl($message);
        $urlBlock = $attachmentUrl
            ? '<div style="margin-top:6px;font-size:10px;color:#2563eb;">URL: <a href="'.e($attachmentUrl).'" style="color:#2563eb;text-decoration:underline;">'.e($attachmentUrl).'</a></div>'
            : '';

        if ($kind === 'image') {
            $binary = $this->readAttachmentBinary($message);
            if ($binary !== null) {
                $safeMime = (string) ($message->attachment_mime ?: 'image/jpeg');
                if (str_starts_with(strtolower($safeMime), 'image/')) {
                    $dataUri = 'data:'.$safeMime.';base64,'.base64_encode($binary);
                    return '<div style="margin-top:8px;"><img src="'.$dataUri.'" style="max-width:100%;max-height:280px;border-radius:8px;border:1px solid #cbd5e1;" />'.$urlBlock.'</div>';
                }
            }

            return '<div style="margin-top:8px;font-size:11px;color:#334155;">[Gambar] '.$name.$urlBlock.'</div>';
        }

        if ($kind === 'video') {
            return '<div style="margin-top:8px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;font-size:11px;color:#0f172a;"><strong>[Video]</strong> '.$name.' <span style="color:#64748b;">('.$mime.')</span>'.$urlBlock.'</div>';
        }

        if ($kind === 'voice') {
            return '<div style="margin-top:8px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;font-size:11px;color:#0f172a;"><strong>[Voice note]</strong> '.$name.$urlBlock.'</div>';
        }

        return '<div style="margin-top:8px;padding:8px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;font-size:11px;color:#0f172a;"><strong>[File]</strong> '.$name.' <span style="color:#64748b;">('.$mime.')</span>'.$urlBlock.'</div>';
    }

    private function appendDocxPoll($section, ?array $pollStats): void
    {
        if (! is_array($pollStats)) {
            return;
        }

        $section->addText('Polling: '.(string) ($pollStats['question'] ?? ''), ['bold' => true]);
        foreach (($pollStats['options'] ?? []) as $option) {
            $section->addText(sprintf(
                '%d. %s - %d vote (%.1f%%)',
                (int) ($option['number'] ?? 0),
                (string) ($option['label'] ?? ''),
                (int) ($option['count'] ?? 0),
                (float) ($option['percent'] ?? 0)
            ));
        }

        if ((int) ($pollStats['total_votes'] ?? 0) <= 0) {
            $section->addText('Belum ada vote');
        }
    }

    private function appendDocxLinks($section, string $content): void
    {
        $links = $this->extractLinks($content);
        if ($links === []) {
            return;
        }

        $section->addText('Link:', ['bold' => true]);
        foreach ($links as $url) {
            try {
                $section->addLink($url, $url, ['color' => '2563EB', 'underline' => 'single']);
            } catch (Throwable) {
                $section->addText($url);
            }
        }
    }

    private function appendDocxAttachment($section, Message $message, array &$tempImagePaths): void
    {
        if (! $message->attachment_path) {
            return;
        }

        $kind = $this->detectAttachmentKind($message);
        $name = (string) ($message->attachment_original_name ?: basename((string) $message->attachment_path));
        $mime = (string) ($message->attachment_mime ?: 'application/octet-stream');
        $attachmentUrl = $this->attachmentAccessUrl($message);

        if ($kind === 'image') {
            $binary = $this->readAttachmentBinary($message);
            if ($binary !== null) {
                $tmpPath = tempnam(sys_get_temp_dir(), 'ncimg_');
                if (is_string($tmpPath) && $tmpPath !== '') {
                    file_put_contents($tmpPath, $binary);
                    try {
                        $section->addImage($tmpPath, ['width' => 320, 'keepRatio' => true]);
                        if ($attachmentUrl) {
                            $section->addText('URL lampiran:');
                            try {
                                $section->addLink($attachmentUrl, $attachmentUrl, ['color' => '2563EB', 'underline' => 'single']);
                            } catch (Throwable) {
                                $section->addText($attachmentUrl);
                            }
                        }
                        $tempImagePaths[] = $tmpPath;
                    } catch (Throwable) {
                        @unlink($tmpPath);
                        $section->addText('[Gambar] '.$name);
                    }
                    return;
                }
            }

            $section->addText('[Gambar] '.$name);
            if ($attachmentUrl) {
                $section->addText('URL lampiran: '.$attachmentUrl);
            }
            return;
        }

        if ($kind === 'video') {
            $section->addText('[Video] '.$name.' (preview tidak tersedia di DOCX)');
            if ($attachmentUrl) {
                $section->addText('URL lampiran: '.$attachmentUrl);
            }
            return;
        }

        if ($kind === 'voice') {
            $section->addText('[Voice note] '.$name);
            if ($attachmentUrl) {
                $section->addText('URL lampiran: '.$attachmentUrl);
            }
            return;
        }

        $section->addText('[File] '.$name.' ('.$mime.')');
        if ($attachmentUrl) {
            $section->addText('URL lampiran: '.$attachmentUrl);
        }
    }

    private function attachmentAccessUrl(Message $message): ?string
    {
        $groupId = (int) ($message->group_id ?? 0);
        $messageId = (int) ($message->id ?? 0);
        if ($groupId <= 0 || $messageId <= 0) {
            return null;
        }

        try {
            return route('chat.attachment', [
                'group' => $groupId,
                'message' => $messageId,
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
