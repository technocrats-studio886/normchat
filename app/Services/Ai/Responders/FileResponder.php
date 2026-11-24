<?php

namespace App\Services\Ai\Responders;

use App\Enums\AiMode;
use App\Services\Ai\AiActivity;
use App\Services\Ai\AiReply;
use App\Services\Ai\AiTask;
use App\Services\Ai\Context\ContextBundle;
use App\Services\Ai\Files\DocumentImages;
use App\Services\Ai\Files\DocumentStamp;
use App\Services\Ai\Files\ExportOverflow;
use App\Services\Ai\Files\FileBuilder;
use App\Services\Ai\Files\FileEdit;
use App\Services\Ai\Files\FileFormats;
use App\Services\Ai\Files\FileRequest;
use App\Services\Ai\Files\RevisionCheck;
use App\Services\Ai\Files\PilotDocument;
use App\Services\Ai\Files\DocumentReview;
use App\Services\Ai\Files\SectionRevision;
use App\Services\Ai\PromptBuilder;
use App\Services\AttachmentTextExtractor;
use App\Services\Files\StoredFiles;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fitur "Buat file" (@normi-file, atau permintaan file lewat @normi): Normi menulis isi file sebagai JSON
 * terstruktur, lalu server merendernya (FileBuilder). Format apa pun: PDF, Word, Excel, PowerPoint, CSV, JSON,
 * HTML, kode program, dan ZIP untuk banyak file sekaligus. "Ubah foto ini jadi PDF" dikerjakan langsung
 * tanpa memanggil model.
 */
final class FileResponder implements AiResponder
{
    private const MAX_OUTPUT_TOKENS = 16_000;

    /** Jenis log pemakaian tetap "document" seperti sebelum fitur ini (metrik superadmin). */
    private const KIND = 'document';

    public const PROMPT = "MODE BUAT FILE. Kamu membuat file yang diminta anggota grup. File akan langsung dikirim ke grup sebagai lampiran.\n"
        ."Balas HANYA dengan JSON valid:\n"
        .'{"title": "judul singkat", "summary": "1 kalimat Bahasa Indonesia untuk grup tentang file yang dibuat", "files": [FILE, ...]}'."\n"
        ."FILE berbentuk {\"name\": \"nama-file.ext\", ...isi sesuai format}:\n"
        ."- pdf, docx, md, txt: \"content\" berisi Markdown lengkap (judul #, subjudul ##, paragraf, list, tabel Markdown).\n"
        .'- xlsx: "sheets": [{"name": "Nama sheet", "rows": [["Header 1", "Header 2"], ["teks", 1500000], ["Total", "=SUM(B2:B10)"]]}].'
        ." Angka ditulis sebagai angka (tanpa titik ribuan), rumus diawali \"=\".\n"
        .'- pptx: "slides": [{"title": "Judul presentasi", "subtitle": "…"}, {"title": "Judul slide", "bullets": ["poin", {"text": "sub-poin", "level": 1}]}].'
        ." Slide pertama slide judul; 3-6 poin ringkas per slide.\n"
        ."- csv/tsv: \"sheets\" seperti xlsx (satu sheet) atau \"content\" teks CSV.\n"
        ."- format teks lain (json, html, xml, yaml, sql, svg, ics, vcf, srt, kode program seperti py/js/ts/php/java/kt/dart/go/c/cpp/cs/sh, dll.): \"content\" berisi isi file utuh tanpa code fence.\n"
        ."Aturan:\n"
        ."- Isi harus lengkap, benar, dan langsung bisa dipakai; jangan menulis \"...\" atau \"isi sendiri\".\n"
        ."- Satu permintaan biasanya satu file. Proyek atau beberapa berkas: tulis semuanya di \"files\" dengan jalur folder (mis. \"src/App.jsx\"), server membungkusnya jadi ZIP.\n"
        ."- Nama file huruf kecil, pakai tanda hubung, ekstensi sesuai format.\n"
        .'- Kode program: lengkap dan bisa dijalankan, sertakan README.md singkat bila proyek terdiri dari beberapa file.';

    public function __construct(private readonly ?FileRequest $request = null) {}

    public function activity(): string
    {
        return AiActivity::FILE;
    }

    public function respond(AiTask $task): AiReply
    {
        $message = $task->message;
        if (FileRequest::exportsRepliedText($message)) {
            $source = FileEdit::visible($task, $message->reply_to_message_id);
            if (!$source) {
                return AiReply::notice(self::KIND, 'Pesan sumber tidak tersedia.');
            }
            $task->progress(AiActivity::FILE, 'sedang mengekspor pesan ke PDF');
            $name = 'pesan-'.$source->id.'.pdf';
            try {
                $file = app(FileBuilder::class)->build([['name' => $name, 'content' => $source->content]], 'pdf', '');
                return $this->store($task, $file, 'Ini PDF dari pesan yang kamu pilih.', $task->model);
            } catch (Throwable $error) {
                report($error);
                return AiReply::notice(self::KIND, 'PDF belum berhasil dibuat. Pesan aslinya masih tersedia.')->withMeta(['temporary_failure' => true]);
            }
        }
        $pending = FileEdit::pending($message);
        $origin = $pending ? FileEdit::visible($task, (int) $pending->system_meta['file_position_request']) : $message;
        if (! $origin || ($pending && $origin->sender_id != $task->requester->id)) {
            return AiReply::notice(self::KIND, 'Permintaan sebelumnya tidak tersedia. Kirim lagi gambar dan instruksinya.');
        }
        $text = (string) $origin->content;
        $stage = $pending?->system_meta['file_stage'] ?? null;
        $extra = (string) ($pending?->system_meta['file_answer'] ?? '');
        if ($stage === 'revision') {
            $extra = trim($extra."\n".$message->content);
        }
        if ($extra !== '') {
            $text .= "\nKlarifikasi pengguna: ".$extra;
        }
        $resume = ['file_position_user' => $origin->sender_id, 'file_position_request' => $origin->id, 'file_answer' => mb_substr($extra, -3000)];
        $request = $this->request ?? FileRequest::forFileMode($text);
        $format = $request->format();
        $task->progress(AiActivity::FILE, $format !== null
            ? 'sedang membuat file '.FileFormats::label($format)
            : 'sedang membuat file');

        $images = $format === 'pdf' && $request->isConversion ? $this->images($task) : [];
        if ($images !== []) {
            return $this->imagesToPdf($task, $images);
        }

        $embed = FileEdit::wantsImage($text) || ($origin->hasImageAttachment()
            && ($origin->ai_mode === AiMode::File || str_contains(mb_strtolower($text), '@normi-file')
                || preg_match('/\b(?:tambah\w*|sisip\w*|masuk\w*|pasang\w*|tempel\w*)\s+(?:ini|ke|di|pada)\b/iu', $text))
            && in_array($format, [null, 'pdf', 'docx', 'xlsx', 'pptx'], true)
            && ! preg_match('/\b(?:referensi|contoh|baca\w*|analisis|analisa|ekstrak|salin|transkrip|tanpa|jangan)\b/iu', $text));
        $filename = FileEdit::filename($text);
        // "revisi" sebagai isi dokumen ("biaya revisi 2x" di invoice) bukan permintaan merevisi file (eval 23 Nov 2026).
        $plain = trim((string) preg_replace(AiMode::anyTagPattern(), '', $text));
        $revision = preg_match('/^(?:(?:tolong|coba|bantu|mohon)\s+)*(?:revisi|rename)\b|\b(?:revisi|rename)\s+(?:(?:isi|nama)\s+)?(?:file|dokumen|berkas|rab|pdf|word|excel|powerpoint|docx|xlsx|pptx|ppt|proposal|surat|laporan|invoice|cv|presentasi|slide)\b|\b(?:file|dokumen|berkas|rab|pdf|word|excel|powerpoint|docx|xlsx|pptx)(?:\s+\w+){0,2}\s+(?:sebelumnya|tadi|terakhir|kemarin)\b/iu', $plain)
            || ($embed && preg_match('/\b(?:ke|di|pada|dalam)\s+(?:file\s+)?(?:pdf|dokumen|file|rab)\s*(?:itu|tersebut)\b/iu', $text))
            || ($filename && preg_match('/\b(?:ganti|ubah)\b/iu', $text));
        $source = PilotDocument::supports($origin) ? $origin : FileEdit::source($task, $origin, $format, (bool) $revision);
        if ($pending && ! empty($pending->system_meta['file_position_source'])) {
            $source = FileEdit::visible($task, (int) $pending->system_meta['file_position_source']);
            $revision = true;
        }
        if ($revision && ! $source) {
            return AiReply::notice(self::KIND, 'Balas langsung file yang ingin direvisi, lalu sertakan gambar dan instruksinya.');
        }
        $resume['file_position_source'] = $source?->id;
        $image = null;
        if ($embed) {
            $photo = $origin->hasImageAttachment() ? $origin : ($origin->reply_to_message_id ? FileEdit::visible($task, $origin->reply_to_message_id) : null);
            if (! $photo?->hasImageAttachment()) {
                return AiReply::notice(self::KIND, 'Lampirkan gambar yang ingin dimasukkan beserta instruksinya.');
            }
            $positionText = ($pending && ! $stage) ? (string) $message->content : ($pending?->system_meta['file_placement'] ?? $text);
            $position = FileEdit::position($positionText);
            $resume['file_placement'] = mb_substr($positionText, 0, 3000);
            if (! $position || preg_match('/\b(?:setiap|semua)\s+(?:halaman|slide|sheet)|\b(?:halaman|slide|sheet)\s+(?:[2-9]|\d{2})/iu', $positionText)) {
                return AiReply::notice(self::KIND, 'Gambarnya mau diletakkan di mana? Balas pesan ini dengan posisi atas atau bawah dan perataannya, misalnya “kanan atas” atau “kiri bawah”.')
                    ->withMeta($resume);
            }
            try {
                $image = DocumentImages::prepare($photo, $position);
            } catch (\RuntimeException $e) {
                return AiReply::notice(self::KIND, $e->getMessage());
            }
        }

        $sourceText = '';
        $scopeNote = '';
        $pilot = PilotDocument::supports($source);
        $contentEdit = (bool) preg_match('/\b(?:revisi|edit|ubah|ganti|koreksi|perbarui|naikkan|turunkan|tambahkan|hapus)\b/iu', (string) $origin->content);
        if ($pilot && preg_match('/\b(?:menjadi|jadi|sebagai|ke|format)\s+(?:file\s+)?(pdf|docx|word|pptx|ppt|powerpoint|xlsx|excel)\b/iu', (string) $origin->content, $targetFormat)) {
            $format = FileFormats::ALIASES[mb_strtolower($targetFormat[1])];
        } elseif ($pilot && $contentEdit && !FileRequest::detect((string) $origin->content)?->format()) {
            $format = PilotDocument::extension($source);
        }
        if ($source) {
            $ext = $pilot ? PilotDocument::extension($source) : FileFormats::extensionOf($source->attachment_original_name);
            $format ??= $ext;
            // A request to change text/data as well must go through the content editor.
            if (($embed || $filename) && RevisionCheck::metadataOnly($text) && $ext === $format && ($source->sender_type === 'ai' || ($pilot && $ext !== 'ppt'))) {
                try {
                    $bytes = app(StoredFiles::class)->get($source->attachment_disk, $source->attachment_path, 15 * 1024 * 1024);
                    if (! $bytes) {
                        throw new \RuntimeException('File sumber tidak tersedia.');
                    }
                    $file = ['ext' => $ext, 'binary' => $bytes, 'mime' => FileFormats::mime($ext), 'name' => $filename ? $filename.'.'.$ext : $source->attachment_original_name];
                    if ($image) {
                        $file = DocumentStamp::apply($file, $image);
                    }

                    return $this->store($task, $file, $image ? 'Gambar sudah dimasukkan ke '.$file['name'].'.' : 'Nama file sudah diubah.', $task->model);
                } catch (Throwable $e) {
                    report($e);

                    return AiReply::notice(self::KIND, 'File sumber gagal direvisi. Kirim ulang dokumen aslinya.');
                }
            }
            if ($pilot) {
                $meta = $pending?->system_meta ?? [];
                $doc = DocumentReview::read($source, (string) $message->content, $meta, $resume, self::KIND);
                if ($doc instanceof AiReply) {
                    return $doc;
                }
                if ($doc['pages']) {
                    $scopeNote = ' Hasil ini menggunakan halaman '.$doc['pages'].' dari PDF sumber.';
                    $text .= $scopeNote;
                }
                $current = $meta['pilot_version'] ?? null;
                $reply = preg_replace('/^@normi(?:-file)?\s+/iu', '', mb_strtolower(trim($message->content, " .!\t\r\n")));
                foreach (['layout', 'full', 'rebuild'] as $approval) {
                    $key = 'pilot_'.$approval;
                    $resume[$key] = $current === $doc['version'] && !empty($meta[$key]);
                }
                if ($current === $doc['version'] && isset($meta['pilot_draft'])) {
                    $resume['pilot_draft'] = $meta['pilot_draft'];
                }
                $originalExt = PilotDocument::extension($source);
                if ($originalExt === 'ppt' && (!$format || $format === 'ppt')) {
                    $format = 'pptx';
                }
                $nativeEdit = !$resume['pilot_rebuild'] && $contentEdit && $format === $originalExt && in_array($format, ['docx', 'pptx', 'pdf'], true);
                $needsLayout = $contentEdit || $resume['pilot_rebuild'] || $request->isConversion
                    || preg_match('/\b(?:konversi|convert|ekspor|export|jadikan|simpan sebagai)\b/iu', $text);
                if ($needsLayout && (!$nativeEdit || $format === 'pdf') && !$resume['pilot_layout']) {
                    if ($current !== $doc['version'] || $stage !== 'pilot_layout' || $reply !== 'susun ulang') {
                        return AiReply::notice(self::KIND, 'Untuk membuat hasil ini, tata letak sumber perlu disusun ulang. Gambar dan gaya asli bisa berubah. Balas “susun ulang” jika setuju.')->withMeta([...$resume, 'file_stage' => 'pilot_layout']);
                    }
                    $resume['pilot_layout'] = true;
                }
                if ($nativeEdit && !in_array($stage, ['split'], true)) {
                    $result = SectionRevision::edit($task, $source, $doc, $text, $resume, $image, $filename);
                    if ($result instanceof AiReply) {
                        return $result;
                    }
                    return $this->store($task, $result[0], 'Revisi sudah diterapkan pada '.$result[0]['name'].'.'.$scopeNote, $result[1]);
                }
                $task->prompts->prepareDocument($source, null);
                // Disusun ulang utuh: semua bagian, dalam bentuk ringkas (baris bernomor + tabel TSV).
                $sourceText = \App\Services\Ai\Documents\DocumentProjection::compact($doc['sections'], $doc['tables'] ?? []);
            } else {
                $sourceText = $this->sourceText($source);
            }
            if (! $sourceText || str_contains($sourceText, '[…dipotong:')) {
                return AiReply::notice(self::KIND, 'Isi lengkap file sumber belum bisa dibaca. Kirim bagian yang ingin direvisi.');
            }
        }
        if ($stage === 'split') {
            if (! preg_match('/^(?:@\S+\s+)?(?:bagi|pecah|ya|setuju|lanjut|oke)[\s.!]*$/iu', trim($message->content))) {
                return AiReply::notice(self::KIND, 'Belum ada file yang dikirim. Balas “bagi” untuk menyetujui pembagian.')->withMeta($pending->system_meta);
            }
            $spec = Cache::get('file-draft:'.$pending->system_meta['file_draft']);
            if (! $spec) {
                return AiReply::notice(self::KIND, 'Draf pembagian kedaluwarsa. Kirim ulang permintaan awal.');
            }
            $file = app(FileBuilder::class)->build($spec['files'], $format, $spec['title'], forceZip: true, split: true, image: $image);
            if ($filename) {
                $file['name'] = $filename.'.zip';
            }

            return $this->store($task, $file, 'Hasil tersedia di arsip ZIP.'.$scopeNote, $task->model);
        }
        $instruction = $format !== null
            ? 'Format yang diminta: '.FileFormats::label($format)." (.{$format})".'.'
            : ($embed ? 'Gunakan PDF kecuali format dokumen lain diminta.' : 'Pilih format file yang paling sesuai dengan permintaan.');
        $instruction .= $scopeNote;
        $instruction .= ' Nama unduhan hanya di files[].name. Judul dan isi berasal dari materi dokumen. Jangan ulang judul atau masukkan label versi/nama penerima dari nama unduhan. Gunakan bagian, tabel dan rincian yang relevan; jangan mengarang data. Jika gambar disisipkan server, jangan buat placeholder atau tautan gambar.';
        if ($source) {
            $instruction .= ' Terapkan semua perubahan yang jelas. Jika data kurang, sertakan "clarification" (pertanyaan untuk bagian itu) bersama draf files. Jaga data lain dan perbarui total terkait.';
        }
        $draft = $stage === 'revision' && isset($pending->system_meta['file_draft']) ? Cache::get('file-draft:'.$pending->system_meta['file_draft']) : null;
        $messages = $task->compose(function (ContextBundle $context) use ($task, $instruction, $origin, $sourceText, $extra, $draft) {
            $messages = $task->prompts->conversation(PromptBuilder::chatSystemPrompt($task->group)."\n\n".self::PROMPT."\n".$instruction, $context, $origin);
            if ($sourceText !== '') {
                $messages[] = ['role' => 'user', 'content' => "Dokumen sumber untuk revisi (pertahankan rincian yang tidak diminta berubah):\n".$sourceText];
            }

            if ($draft) {
                $messages[] = ['role' => 'user', 'content' => 'Draf sebelumnya: '.json_encode($draft['files'], JSON_UNESCAPED_UNICODE)];
            }
            if ($extra !== '') {
                $messages[] = ['role' => 'user', 'content' => 'Klarifikasi: '.$extra];
            }

            return $messages;
        });

        $task->planChat($messages, self::MAX_OUTPUT_TOKENS, minOutputTokens: 2000, feature: 'Buat file');
        [$completion, $model] = $task->complete($messages, 240, ['response_format' => ['type' => 'json_object']]);
        if (! $completion->hasText()) {
            return AiReply::failed(self::KIND);
        }
        $task->recordChat(self::KIND, $model, $completion);

        $answer = json_decode($completion->text, true);
        if ($source && $completion->finishReason !== 'length' && empty($answer['files']) && is_string($answer['clarification'] ?? null) && trim($answer['clarification']) !== '') {
            return AiReply::notice(self::KIND, mb_substr($answer['clarification'], 0, 500))->withMeta([...$resume, 'file_stage' => 'revision']);
        }
        if ($completion->finishReason === 'length' || (str_starts_with(ltrim($completion->text), '{') && empty(json_decode($completion->text, true)['files']))) {
            return AiReply::notice(self::KIND, 'Dokumen belum lengkap. Coba minta satu bagian atau satu file terlebih dahulu.');
        }
        $spec = self::parse((string) $completion->text, $format);
        $title = $spec['title'];
        if ($filename && count($spec['files']) === 1 && in_array($format ?? FileFormats::extensionOf($spec['files'][0]['name'] ?? ''), ['pdf', 'docx'], true)
            && ! preg_match('/\b(?:judul|title)\b/iu', $text)) {
            $content = $spec['files'][0]['content'] ?? '';
            if (is_string($content)) {
                $spec['files'][0]['content'] = preg_replace_callback('/^#{1,6}[ \\t]+([^\\n]+)$/mu', fn ($m) => Str::slug(trim($m[1], '* ')) === $filename ? '' : $m[0], $content);
            }
            if (Str::slug($title) === $filename) {
                $title = '';
            }
        }

        if ($source) {
            $clarification = json_decode($completion->text, true)['clarification'] ?? null;
            if (is_string($clarification) && trim($clarification) !== '') {
                return $this->saveDraft($spec, $resume, 'revision', $clarification);
            }
        }
        try {
            $file = app(FileBuilder::class)->build($spec['files'], $format, $title, forceZip: $format === 'zip');
            if (($image || $pilot) && $format && $file['ext'] !== $format) {
                throw new \RuntimeException('Format hasil berbeda dari permintaan.');
            }
            if ($image) {
                $file = DocumentStamp::apply($file, $image);
            }
            if ($filename) {
                $file['name'] = $filename.'.'.$file['ext'];
            }
            if ($source) {
                $rendered = app(AttachmentTextExtractor::class)->rendered($file, 30000);
                $question = $rendered === null ? 'Isi final terlalu besar atau belum bisa diperiksa. Bagian mana yang ingin direvisi terlebih dahulu?'
                    : RevisionCheck::question($task, $text, $sourceText, $rendered, $file['name'], $image);
                if ($question) {
                    return $this->saveDraft($spec, $resume, 'revision', $question);
                }
            }
        } catch (ExportOverflow $e) {
            if ($source) {
                $question = RevisionCheck::question($task, $text, $sourceText, json_encode($spec['files'], JSON_UNESCAPED_UNICODE), $filename, $image);
                if ($question) {
                    return $this->saveDraft($spec, $resume, 'revision', $question);
                }
            }

            return $this->saveDraft($spec, $resume, 'split', $e->getMessage().' Mau dibagi dalam ZIP? Balas “bagi”. Workbook dengan rumus disimpan utuh di ZIP agar referensi sel tetap benar.');
        } catch (Throwable $e) {
            Log::error('AI file build failed.', ['format' => $format, 'error' => $e->getMessage()]);

            return AiReply::notice(self::KIND, 'Isi atau format dokumen belum bisa dirender. Coba ulang dengan satu file dan rincian yang ingin dimasukkan.');
        }

        return $this->store($task, $file, $spec['summary'].$scopeNote, $model);

    }

    /** Isi berkas sumber untuk revisi: dokumen kanonik bila ada, pembaca teks lama bila belum bisa dikonversi. */
    private function sourceText(\App\Models\Message $source): ?string
    {
        if (\App\Services\Ai\Documents\DocumentStore::supports($source)) {
            try {
                $document = app(\App\Services\Ai\Documents\DocumentStore::class)->ensure($source);
                $text = \App\Services\Ai\Documents\DocumentProjection::compact($document['sections'], $document['tables'] ?? []);

                return mb_strlen($text) <= 30000 ? $text : $text."\n[…dipotong: file lebih panjang dari batas yang dibaca AI]";
            } catch (\RuntimeException) {
                // Pembaca teks lama di bawah.
            }
        }

        return app(AttachmentTextExtractor::class)->extract($source, 30000);
    }

    private function saveDraft(array $spec, array $resume, string $stage, string $question): AiReply
    {
        $key = (string) Str::uuid();
        Cache::put('file-draft:'.$key, $spec, now()->addDay());

        return AiReply::notice(self::KIND, $question)->withMeta([...$resume, 'file_stage' => $stage, 'file_draft' => $key]);
    }

    /**
     * JSON dari model. Bila model membalas teks biasa (tidak sesuai format), teks itu dipakai sebagai isi file.
     *
     * @return array{title: string, summary: string, files: list<array<string, mixed>>}
     */
    public static function parse(string $raw, ?string $format): array
    {
        $data = json_decode($raw, true);
        if (! is_array($data) || ! is_array($data['files'] ?? null) || $data['files'] === []) {
            $markdown = trim((string) preg_replace('/^```[a-zA-Z]*\s*\n|\n```\s*$/u', '', $raw));
            $title = preg_match('/^#\s+(.+)$/mu', $markdown, $m) ? trim($m[1]) : 'Dokumen Normi AI';

            return [
                'title' => Str::limit($title, 80, ''),
                'summary' => '',
                'files' => [['name' => Str::slug($title).'.'.($format ?? 'pdf'), 'content' => $markdown]],
            ];
        }

        $title = trim((string) ($data['title'] ?? '')) ?: 'File Normi AI';

        return [
            'title' => Str::limit($title, 80, ''),
            'summary' => trim((string) ($data['summary'] ?? '')),
            'files' => array_values(array_filter($data['files'], 'is_array')),
        ];
    }

    /** @param  array{binary: string, mime: string, name: string, ext: string}  $file */
    private function store(AiTask $task, array $file, string $summary, $model): AiReply
    {
        $path = sprintf('group-%d/%s/ai-file-%s.%s', $task->group->id, now()->format('Y/m'), Str::uuid(), $file['ext']);
        if (! Storage::disk('normchat_attachments')->put($path, $file['binary'])) {
            return AiReply::notice(self::KIND, 'File belum berhasil disimpan. Coba lagi.')->withMeta(['temporary_failure' => true]);
        }

        return AiReply::attachment(self::KIND, $summary !== '' ? $summary : 'File '.$file['name'].' sudah siap.', [
            'message_type' => 'file',
            'path' => $path,
            'mime' => $file['mime'],
            'original_name' => $file['name'],
            'size' => strlen($file['binary']),
        ], $model);
    }

    /** @param  list<array{binary: string, mime: string}>  $images */
    private function imagesToPdf(AiTask $task, array $images): AiReply
    {
        $task->progress(AiActivity::FILE, 'sedang mengubah gambar ke PDF');
        $name = pathinfo((string) ($task->message->attachment_original_name ?: $task->message->replyToMessage?->attachment_original_name ?: 'gambar'), PATHINFO_FILENAME);

        try {
            $file = app(FileBuilder::class)->imagesToPdf($images, $name);
        } catch (Throwable $e) {
            Log::error('Image to PDF failed.', ['error' => $e->getMessage()]);

            return AiReply::notice(self::KIND, 'Gagal mengubah gambar ke PDF. Coba lagi.')->withMeta(['temporary_failure' => true]);
        }

        return $this->store($task, $file, 'Gambarnya sudah kuubah jadi PDF.', $task->model);
    }

    /** @return list<array{binary: string, mime: string}> gambar di pesan ini atau pesan yang dibalasnya */
    private function images(AiTask $task): array
    {
        $images = [];
        $message = $task->message;
        $target = $message->reply_to_message_id ? FileEdit::visible($task, $message->reply_to_message_id) : null;
        foreach ([$message, $target] as $source) {
            if (! $source?->hasImageAttachment() || ! $source->attachment_path) {
                continue;
            }
            $binary = app(StoredFiles::class)->get($source->attachment_disk ?: 'normchat_attachments', $source->attachment_path, 15 * 1024 * 1024);
            $mime = (string) ($source->attachment_mime ?: 'image/jpeg');
            if (is_string($binary) && $binary !== '' && in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
                $images[] = ['binary' => $binary, 'mime' => $mime];
            }
        }

        return $images;
    }
}
