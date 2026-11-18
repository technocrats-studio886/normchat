<?php

namespace Tests\Feature;

use App\Enums\AiMode;
use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\AiUsageLog;
use App\Models\ChatMessageQueue;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\AiActivity;
use App\Services\Ai\Context\MaintenanceAi;
use App\Services\Ai\Files\DocumentStamp;
use App\Services\Ai\Files\ExportOverflow;
use App\Services\Ai\Files\FileBuilder;
use App\Services\Ai\Files\FileRequest;
use App\Services\AttachmentTextExtractor;
use App\Services\Chat\MessagePresenter;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Tests\TestCase;
use ZipArchive;

/**
 * Perbaikan 11 Nov 2026: file format apa pun (poin 9), Canvas dengan kode proyek (poin 10), arahkan ke fitur
 * Normi alih-alih aplikasi lain (poin 13), dan status aktivitas Normi per tahap.
 */
class NormiFilesAndSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('normchat.usd_to_idr', 17950);
        config()->set('services.openai.api_key', 'sk-test');
        Event::fake([MessageSent::class, TypingStatus::class]);
        Storage::fake('normchat_attachments');

        $this->owner = User::factory()->create(['name' => 'Lesmana Sanor']);
        $this->group = Group::create([
            'name' => 'Grup 1',
            'owner_id' => $this->owner->id,
            'password_hash' => Hash::make('1234'),
            'status' => 'active',
            'ai_model' => 'gpt-6-luna',
        ]);
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->owner->id,
            'role_id' => Role::forKey(Role::OWNER)->id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    public function test_file_requests_are_recognised_in_many_phrasings(): void
    {
        $cases = [
            '@normi ubah jadi pdf' => 'pdf',
            '@normi buatkan excel anggaran rapat bulan ini' => 'xlsx',
            'tolong bikin slide presentasi tentang energi surya' => 'pptx',
            'buatkan presentasi untuk sidang skripsi' => 'pptx',
            'export data tadi ke csv' => 'csv',
            'simpan sebagai file python ya' => 'py',
            'buatkan main.py untuk kalkulator' => 'py',
            'buatkan dokumen notulen rapat tadi' => 'pdf',
            'jadikan zip semua filenya' => 'zip',
            'buatkan file word surat izin' => 'docx',
        ];
        foreach ($cases as $text => $format) {
            $this->assertSame($format, FileRequest::detect($text)?->format(), $text);
        }

        foreach (['buatkan web pakai next.js', 'jelaskan cara kerja python', 'buatkan gambar kucing', 'apa itu pdf?', 'password wifi kantor apa'] as $text) {
            $this->assertNull(FileRequest::detect($text), $text);
        }
        $this->assertTrue(FileRequest::detect('@normi ubah jadi pdf')->isConversion);
    }

    public function test_excel_request_renders_a_real_workbook(): void
    {
        $this->fakeChat(json_encode([
            'title' => 'Anggaran Rapat',
            'summary' => 'Ini anggaran rapat dalam Excel.',
            'files' => [['name' => 'anggaran-rapat.xlsx', 'sheets' => [[
                'name' => 'Anggaran',
                'rows' => [['Item', 'Biaya'], ['Konsumsi', 1500000], ['Sewa ruang', '750000'], ['Total', '=SUM(B2:B3)']],
            ]]]],
        ]));

        $this->runAi(['content' => '@normi buatkan excel anggaran rapat']);

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('Ini anggaran rapat dalam Excel.', $reply->content);
        $this->assertSame('anggaran-rapat.xlsx', $reply->attachment_original_name);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $reply->attachment_mime);
        $this->assertSame(AiMode::File, $reply->ai_mode);

        $zip = $this->openZip($reply);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<v>1500000</v>', $sheet);
        $this->assertStringContainsString('<v>750000</v>', $sheet);
        $this->assertStringContainsString('<f>SUM(B2:B3)</f>', $sheet);
        $this->assertStringContainsString('name="Anggaran"', (string) $zip->getFromName('xl/workbook.xml'));
        $this->assertSame('document', AiUsageLog::firstOrFail()->kind);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions') && str_contains((string) $request['messages'][0]['content'], 'MODE BUAT FILE')
            && $request['response_format'] === ['type' => 'json_object']);
        Event::assertDispatched(TypingStatus::class, fn (TypingStatus $event) => $event->isTyping && $event->detail === 'sedang membuat file Excel');
    }

    public function test_powerpoint_request_renders_slides(): void
    {
        $this->fakeChat(json_encode([
            'title' => 'Energi Surya',
            'summary' => 'Presentasi energi surya.',
            'files' => [['name' => 'energi-surya.pptx', 'slides' => [
                ['title' => 'Energi Surya', 'subtitle' => 'Kelompok 3'],
                ['title' => 'Kelebihan', 'bullets' => ['Bersih', '**Murah** jangka panjang', ['text' => 'Perawatan mudah', 'level' => 1]]],
                ['title' => 'Tantangan', 'bullets' => ['Biaya awal & penyimpanan <baterai>']],
            ]]],
        ]));

        $this->runAi(['content' => '@normi-file bikin slide presentasi energi surya']);

        $zip = $this->openZip(Message::where('sender_type', 'ai')->firstOrFail());
        $this->assertNotFalse($zip->getFromName('ppt/slides/slide3.xml'));
        $this->assertStringContainsString('Kelompok 3', (string) $zip->getFromName('ppt/slides/slide1.xml'));
        $this->assertStringContainsString('&lt;baterai&gt;', (string) $zip->getFromName('ppt/slides/slide3.xml'));
        $presentation = (string) $zip->getFromName('ppt/presentation.xml');
        $this->assertSame(3, substr_count($presentation, '<p:sldId '));
        $this->assertStringContainsString('cx="12192000" cy="6858000"', $presentation);
        $this->assertSame(3, substr_count((string) $zip->getFromName('[Content_Types].xml'), 'presentationml.slide+xml'));
    }

    public function test_multi_file_project_is_zipped_and_code_files_keep_paths(): void
    {
        $this->fakeChat(json_encode([
            'title' => 'Todo React',
            'summary' => 'Proyek todo React.',
            'files' => [
                ['name' => 'package.json', 'content' => '{"name": "todo"}'],
                ['name' => 'src/App.jsx', 'content' => "```jsx\nexport default function App() { return null; }\n```"],
                ['name' => '../../etc/passwd', 'content' => 'x'],
            ],
        ]));

        $this->runAi(['content' => '@normi-file buatkan proyek todo react']);

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('todo-react.zip', $reply->attachment_original_name);
        $zip = $this->openZip($reply);
        $this->assertSame("export default function App() { return null; }\n", $zip->getFromName('src/App.jsx'));
        $this->assertNotFalse($zip->getFromName('etc/passwd.txt'));
        $this->assertFalse($zip->getFromName('../../etc/passwd'));
    }

    public function test_photo_to_pdf_is_converted_without_calling_the_model(): void
    {
        Http::fake();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        Storage::disk('normchat_attachments')->put('group-1/foto.png', $png);

        $this->runAi([
            'content' => '@normi ubah jadi pdf',
            'message_type' => 'image',
            'attachment_disk' => 'normchat_attachments',
            'attachment_path' => 'group-1/foto.png',
            'attachment_mime' => 'image/png',
            'attachment_original_name' => 'Screenshot_2026-11-10.png',
        ]);

        $this->assertNoModelCall();
        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('application/pdf', $reply->attachment_mime);
        $this->assertSame('screenshot-2026-11-10.pdf', $reply->attachment_original_name);
        $this->assertStringStartsWith('%PDF', Storage::disk('normchat_attachments')->get($reply->attachment_path));
        $this->assertSame(0.0, (float) AiUsageLog::firstOrFail()->charged_credits);
    }

    public function test_questions_about_a_photo_are_still_answered_by_chat(): void
    {
        $this->fakeChat('Itu foto pria berjas.');
        Storage::disk('normchat_attachments')->put('group-1/jas.jpg', 'jpeg');

        $this->runAi([
            'content' => '@normi apa warna dasi di foto ini?',
            'message_type' => 'image',
            'attachment_disk' => 'normchat_attachments',
            'attachment_path' => 'group-1/jas.jpg',
            'attachment_mime' => 'image/jpeg',
        ]);

        $this->assertSame('Itu foto pria berjas.', Message::where('sender_type', 'ai')->firstOrFail()->content);
        Event::assertDispatched(TypingStatus::class, fn (TypingStatus $event) => $event->activity === 'reading' && $event->detail === 'sedang melihat gambar');
    }

    public function test_chat_prompt_lists_normi_features_and_suggestion_marker_becomes_a_button(): void
    {
        // Permintaan yang jelas sudah langsung dikerjakan fiturnya (FeatureRouterTest); penanda tetap jadi tombol
        // untuk kasus yang lolos dari router.
        $this->fakeChat("Untuk itu pakai Deep Research ya.\n[[fitur:deep_research]]");

        $this->runAi(['content' => '@normi menurutmu kematian ibu di Indonesia gimana sekarang']);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions') && str_contains((string) $request['messages'][0]['content'], 'Jangan pernah menyarankan aplikasi atau situs lain')
            && str_contains((string) $request['messages'][0]['content'], '[[fitur:image]]'));
        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('Untuk itu pakai Deep Research ya.', $reply->content);
        $presented = app(MessagePresenter::class)->present($reply);
        $this->assertSame('deep_research', $presented['suggestion']['mode']);
        $this->assertSame('menurutmu kematian ibu di Indonesia gimana sekarang', $presented['suggestion']['text']);
    }

    public function test_canvas_with_framework_project_offers_zip_download(): void
    {
        $this->fakeChat(json_encode([
            'kind' => 'web',
            'title' => 'Toko Kue',
            'summary' => 'Landing page toko kue dengan Next.js.',
            'content' => '<!DOCTYPE html><html><body>Toko</body></html>',
            'project' => ['name' => 'toko-kue-next', 'files' => [
                ['name' => 'package.json', 'content' => '{"name":"toko-kue"}'],
                ['name' => 'app/page.tsx', 'content' => 'export default function Page() { return <main>Toko</main>; }'],
                ['name' => 'README.md', 'content' => '# Jalankan\nnpm install && npm run dev'],
            ]],
        ]));

        $this->runAi(['content' => '@normi-canvas buatkan landing page toko kue pakai next.js']);

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('canvas', $reply->message_type);
        $project = app(MessagePresenter::class)->present($reply)['canvas_project'];
        $this->assertSame('toko-kue-next.zip', $project['name']);
        $this->assertSame(3, $project['files']);
        Event::assertDispatched(TypingStatus::class, fn (TypingStatus $event) => $event->activity === 'code' && $event->detail === 'sedang menulis program');

        $response = $this->actingAs($this->owner)->get($project['url']);
        $response->assertOk()->assertHeader('Content-Type', 'application/zip');
        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, $response->streamedContent());
        $zip = new ZipArchive;
        $zip->open($path);
        $this->assertStringContainsString('<main>Toko</main>', (string) $zip->getFromName('app/page.tsx'));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions') && str_contains((string) $request['messages'][0]['content'], 'React/ReactDOM 18'));
    }

    public function test_awaiting_question_carries_current_normi_activity(): void
    {
        $question = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => '@normi-research harga cabai']);
        AiActivity::report((int) $this->group->id, 'research', 'sedang mencari "harga cabai November 2026"');

        $presented = app(MessagePresenter::class)->present($question, null, ['awaiting_ai' => true]);

        $this->assertSame(['activity' => 'research', 'detail' => 'sedang mencari "harga cabai November 2026"'], $presented['ai_activity']);
        AiActivity::stop((int) $this->group->id);
        $this->assertNull(app(MessagePresenter::class)->present($question, null, ['awaiting_ai' => true])['ai_activity']);
    }

    /** Pemeliharaan ingatan (embedding) boleh jalan; yang tidak boleh adalah panggilan model chat atau gambar. */
    public function test_logo_revision_waits_for_position_then_preserves_the_original_rab(): void
    {
        config(['normchat.documents_all_groups' => true, 'normchat.document_pilot_groups' => []]);
        $disk = Storage::disk('normchat_attachments');
        $source = app(FileBuilder::class)->build([['name' => 'rab.pdf', 'content' => "# Rincian Biaya\n\nTotal Rp18.750.000"]], 'pdf', 'Rincian Biaya');
        $disk->put('rab.pdf', $source['binary']);
        Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'message_type' => 'file', 'content' => 'RAB siap.', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'rab.pdf', 'attachment_original_name' => 'rab.pdf', 'attachment_mime' => 'application/pdf']);
        $im = imagecreatetruecolor(50, 25);
        ob_start();
        imagepng($im);
        $disk->put('logo.png', ob_get_clean());
        $this->runAi(['content' => '@normi tambahkan logo ini ke PDF sebelumnya, nama file RAB Final Bang Faisal', 'message_type' => 'image', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'logo.png', 'attachment_original_name' => 'logo.png', 'attachment_mime' => 'image/png']);
        $question = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertStringContainsString('di mana', $question->content);
        $this->assertNull($question->attachment_path);
        $this->runAi(['reply_to_message_id' => $question->id, 'content' => 'Kanan atas']);
        $result = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertSame('rab-final-bang-faisal.pdf', $result->attachment_original_name, $result->content);
        $binary = $disk->get($result->attachment_path);
        $this->assertMatchesRegularExpression('~/Subtype\s*/Image~', $binary);
        $parser = new Parser;
        $this->assertSame(trim($parser->parseContent($source['binary'])->getText()), trim($parser->parseContent($binary)->getText()));
        $this->assertNoModelCall();
    }

    public function test_new_pdf_with_upload_keeps_filename_out_of_document(): void
    {
        config(['normchat.documents_all_groups' => true, 'normchat.document_pilot_groups' => []]);
        $this->fakeChat(json_encode(['title' => 'RAB Final Bang Faisal', 'files' => [['name' => 'wrong.pdf', 'content' => "# RAB Final Bang Faisal\n\n## Rincian biaya\n\nTotal Rp123.456"]]]));
        $image = imagecreatetruecolor(40, 20);
        ob_start();
        imagepng($image);
        Storage::disk('normchat_attachments')->put('logo.png', ob_get_clean());
        $this->runAi(['content' => '@normi-file buat PDF dengan logo di kanan atas, nama file RAB Final Bang Faisal', 'message_type' => 'image', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'logo.png', 'attachment_original_name' => 'logo.png', 'attachment_mime' => 'image/png']);
        $result = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertSame('rab-final-bang-faisal.pdf', $result->attachment_original_name, $result->content);
        $binary = Storage::disk('normchat_attachments')->get($result->attachment_path);
        $this->assertMatchesRegularExpression('~/Subtype\s*/Image~', $binary);
        $text = (new Parser)->parseContent($binary)->getText();
        $this->assertStringContainsString('Rp123.456', $text);
        $this->assertStringNotContainsString('Faisal', $text);
    }

    public function test_empty_containers_are_not_delivered_as_completed_files(): void
    {
        foreach (['pdf' => ['content' => '# Judul'], 'docx' => ['content' => ' '], 'xlsx' => ['sheets' => [['name' => 'Sheet', 'rows' => [['']]]]], 'pptx' => ['slides' => [['title' => 'Judul']]]] as $ext => $spec) {
            try {
                app(FileBuilder::class)->build([['name' => 'empty.'.$ext, ...$spec]], $ext, 'Judul');
                $this->fail("Empty $ext accepted");
            } catch (\RuntimeException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    public function test_office_exports_embed_uploaded_picture_and_keep_original_data(): void
    {
        $im = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();
        foreach (['docx' => ['content' => "# RAB\n\nTotal 12345"], 'xlsx' => ['rows' => [['Total', 12345]]], 'pptx' => ['slides' => [['title' => 'RAB', 'subtitle' => 'Total 12345']]]] as $ext => $spec) {
            $file = app(FileBuilder::class)->build([[...$spec, 'name' => 'rab.'.$ext]], $ext, 'RAB');
            $file = DocumentStamp::apply($file, ['binary' => $bytes, 'position' => 'top', 'align' => 'right', 'width' => 100, 'height' => 50]);
            $tmp = tempnam(sys_get_temp_dir(), 'normi');
            file_put_contents($tmp, $file['binary']);
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($tmp) === true);
            $picture = $text = $link = false;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $part = $zip->getNameIndex($i);
                $value = $zip->getFromIndex($i);
                if (str_ends_with($part, '.xml') || str_ends_with($part, '.rels')) {
                    $this->assertNotFalse(simplexml_load_string($value), $part);
                    $link = $link || str_contains($value, 'r:embed="nc');
                    $text = $text || str_contains($value, '12345');
                }
                $picture = $picture || $bytes === $value;
            }
            $this->assertTrue($picture && $text && $link, $ext);
            $zip->close();
            unlink($tmp);
        }
    }

    public function test_docx_table_and_sheet_cells_are_read_for_followup_questions(): void
    {
        $builder = app(FileBuilder::class);
        foreach ([
            'docx' => ['content' => "# Daftar\n\n| Barang | Nilai |\n|---|---|\n| Kursi | 12345 |"],
            'xlsx' => ['rows' => [['Item', 'Nilai'], ['A', 100], ['B', 200], ['Total', '=SUM(B2:B3)'], ['DP', '=B4/2+5'], ['Lain', '=INDIRECT("B2")']]],
        ] as $ext => $spec) {
            $file = $builder->build([['name' => 'uji.'.$ext, ...$spec]], $ext, 'Uji');
            Storage::disk('normchat_attachments')->put($file['name'], $file['binary']);
            $text = app(AttachmentTextExtractor::class)->extract(new Message(['attachment_original_name' => $file['name'], 'attachment_disk' => 'normchat_attachments', 'attachment_path' => $file['name']]));
            foreach ($ext === 'docx' ? ['Kursi | 12345'] : ['B4: =SUM(B2:B3) → 300', 'B5: =B4/2+5 → 155', 'B6: =INDIRECT("B2") → belum bisa dihitung'] as $expected) {
                $this->assertStringContainsString($expected, $text);
            }
        }
    }

    public function test_repeat_submission_returns_one_message_and_one_queue_item(): void
    {
        Queue::fake();
        $data = ['client_message_id' => (string) Str::uuid(), 'content' => '@normi uji kirim ulang'];
        $url = route('chat.store', $this->group);
        $first = $this->actingAs($this->owner)->postJson($url, $data)->assertCreated()->json('message.id');
        $this->actingAs($this->owner)->postJson($url, $data)->assertCreated()->assertJsonPath('message.id', $first);
        $this->assertSame(1, Message::where('client_message_id', $data['client_message_id'])->count());
        $this->assertSame(1, ChatMessageQueue::where('message_id', $first)->count());
        Queue::assertPushed(ProcessGroupChatQueueJob::class, 1);
    }

    public function test_split_requires_consent_and_keeps_last_slide_without_regeneration(): void
    {
        $slides = array_map(fn ($i) => ['title' => 'Slide '.$i, 'bullets' => ['Materi '.$i]], range(1, 61));
        $this->fakeChat(json_encode(['title' => 'Materi', 'files' => [['name' => 'materi.pptx', 'slides' => $slides]]]));
        $this->runAi(['content' => '@normi-file buat presentasi materi dalam pptx']);
        $question = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertStringContainsString('61 slide', $question->content);
        $this->assertNull($question->attachment_path);
        $this->runAi(['content' => 'bagi', 'reply_to_message_id' => $question->id]);
        $result = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $zip = $this->openZip($result);
        $this->assertSame(2, $zip->numFiles);
        $tmp = tempnam(sys_get_temp_dir(), 'ppt');
        file_put_contents($tmp, $zip->getFromIndex(1));
        $part = new ZipArchive;
        $part->open($tmp);
        $this->assertStringContainsString('Materi 61', $part->getFromName('ppt/slides/slide1.xml'));
        $part->close();
        unlink($tmp);
    }

    public function test_split_workbook_retains_last_row_and_formulas(): void
    {
        $rows = array_fill(0, 5000, ['A', 1]);
        $rows[] = ['TOTAL_AKHIR', 5000];
        $builder = app(FileBuilder::class);
        foreach ([false, true] as $formula) {
            if ($formula) {
                $rows[5000][1] = '=SUM(B1:B5000)';
            }
            $files = [['name' => 'data.xlsx', 'rows' => $rows]];
            try {
                $builder->build($files, 'xlsx', 'Data');
                $this->fail('Ekspor melebihi batas tanpa konfirmasi.');
            } catch (ExportOverflow $e) {
                $this->assertStringContainsString('5001', $e->getMessage());
            }
            $out = $builder->build($files, 'xlsx', 'Data', forceZip: true, split: true);
            $tmp = tempnam(sys_get_temp_dir(), 'zip');
            file_put_contents($tmp, $out['binary']);
            $archive = new ZipArchive;
            $archive->open($tmp);
            $this->assertSame($formula ? 1 : 2, $archive->numFiles);
            $last = $archive->getFromIndex($archive->numFiles - 1);
            $archive->close();
            file_put_contents($tmp, $last);
            $archive->open($tmp);
            $xml = $archive->getFromName('xl/worksheets/sheet1.xml');
            $this->assertStringContainsString('TOTAL_AKHIR', $xml);
            if ($formula) {
                $this->assertStringContainsString('SUM(B1:B5000)', $xml);
            }
            $archive->close();
            unlink($tmp);
        }
    }

    public function test_combined_revision_checks_shipping_and_stamps_real_image(): void
    {
        $spec = ['content' => "# RAB\n\nOngkir Rp20.000"];
        $file = app(FileBuilder::class)->build([['name' => 'rab.pdf', ...$spec]], 'pdf', 'RAB');
        $disk = Storage::disk('normchat_attachments');
        $disk->put('rab.pdf', $file['binary']);
        $source = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'message_type' => 'file', 'content' => 'RAB', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'rab.pdf', 'attachment_original_name' => 'rab.pdf', 'attachment_mime' => 'application/pdf']);
        $im = imagecreatetruecolor(20, 10);
        ob_start();
        imagepng($im);
        $disk->put('logo.png', ob_get_clean());
        $this->fakeChat(json_encode(['title' => 'RAB', 'files' => [['name' => 'rab.pdf', 'content' => "# RAB\n\nOngkir Rp50.000"]]]));
        $this->mock(MaintenanceAi::class, function ($mock) {
            $mock->shouldReceive('json')->withArgs(fn ($kind) => $kind === 'file_revision_check')->once()->andReturn(['data' => ['ok' => true, 'bukti' => ['Ongkir Rp50.000']]]);
            $mock->shouldReceive('json')->zeroOrMoreTimes()->andReturn(null);
            $mock->shouldReceive('embed')->zeroOrMoreTimes()->andReturn(null);
        });
        $this->runAi(['content' => '@normi pasang logo di kanan atas dan naikkan ongkir jadi Rp50.000', 'reply_to_message_id' => $source->id, 'message_type' => 'image', 'attachment_disk' => 'normchat_attachments', 'attachment_mime' => 'image/png', 'attachment_original_name' => 'logo.png', 'attachment_path' => 'logo.png']);
        $result = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertNotNull($result->attachment_path, $result->content);
        $bytes = $disk->get($result->attachment_path);
        $this->assertMatchesRegularExpression('~/Subtype\s*/Image~', $bytes);
        $text = (new Parser)->parseContent($bytes)->getText();
        $this->assertStringContainsString('Ongkir Rp50.000', $text);
        $this->assertStringNotContainsString('20.000', $text);
    }

    public function test_unverified_revision_is_withheld_and_reply_resumes_same_document(): void
    {
        $disk = Storage::disk('normchat_attachments');
        $sourceFile = app(FileBuilder::class)->build([['name' => 'rab.pdf', 'content' => "# RAB\n\nOngkir Rp20.000"]], 'pdf', 'RAB');
        $disk->put('rab.pdf', $sourceFile['binary']);
        $source = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'message_type' => 'file', 'content' => 'RAB', 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'rab.pdf', 'attachment_original_name' => 'rab.pdf', 'attachment_mime' => 'application/pdf']);
        $attempt = 0;
        Http::fake(['api.openai.com/v1/chat/completions' => function () use (&$attempt) {
            $price = ++$attempt === 1 ? '20.000' : '50.000';

            return Http::response(['choices' => [['message' => ['content' => json_encode(['title' => 'RAB', 'files' => [['name' => 'rab.pdf', 'content' => "# RAB\n\nOngkir Rp".$price]]])], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500]]);
        }]);
        $this->mock(MaintenanceAi::class, function ($mock) {
            $mock->shouldReceive('json')->withArgs(fn ($kind) => $kind === 'file_revision_check')->once()->andReturn(['data' => ['ok' => true, 'bukti' => ['Ongkir Rp50.000']]]);
            $mock->shouldReceive('json')->zeroOrMoreTimes()->andReturn(null);
            $mock->shouldReceive('embed')->zeroOrMoreTimes()->andReturn(null);
        });
        $this->runAi(['content' => '@normi-file revisi PDF, ubah ongkir jadi Rp50.000', 'reply_to_message_id' => $source->id]);
        $question = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertNull($question->attachment_path);
        $this->assertSame('revision', $question->system_meta['file_stage']);
        $this->runAi(['content' => 'Pada baris ongkir.', 'reply_to_message_id' => $question->id]);
        $result = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertNotNull($result->attachment_path, $result->content);
        $this->assertStringContainsString('Ongkir Rp50.000', (new Parser)->parseContent($disk->get($result->attachment_path))->getText());
    }

    private function assertNoModelCall(): void
    {
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/chat/completions')
            || str_contains($request->url(), '/images/') || str_contains($request->url(), '/responses'));
    }

    public function test_pilot_upload_edits_word_in_place_and_checks_the_rendered_result(): void
    {
        config(['normchat.document_pilot_groups' => [(string) $this->group->id]]);
        $source = app(FileBuilder::class)->build([['name' => 'notulen.docx', 'content' => "# Notulen\n\nLokasi rapat Aula Timur\n\nPeserta tetap hadir."]], 'docx', 'Notulen');
        Storage::disk('normchat_attachments')->put('notulen.docx', $source['binary']);
        $sections = \App\Services\Ai\Files\OfficeSections::process($source['binary'], 'docx')['sections'];
        $values = array_merge(...$sections);
        $old = 'Lokasi rapat Aula Timur';
        $id = array_search($old, $values, true);
        $this->assertIsString($id);
        $this->fakeChat(json_encode(['version' => substr(hash('sha256', $source['binary']), 0, 16), 'changes' => [
            ['id' => $id, 'old' => $old, 'new' => 'Lokasi rapat Aula Barat'],
        ]]));
        $this->mock(MaintenanceAi::class, function ($mock) {
            $mock->shouldReceive('json')->withArgs(fn ($kind) => $kind === 'file_revision_check')->once()
                ->andReturn(['data' => ['ok' => true, 'bukti' => ['Lokasi rapat Aula Barat']]]);
            $mock->shouldReceive('json')->zeroOrMoreTimes()->andReturn(null);
            $mock->shouldReceive('embed')->zeroOrMoreTimes()->andReturn(null);
        });
        $this->runAi(['content' => '@normi-file revisi lokasi rapat ke Aula Barat', 'message_type' => 'file',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'notulen.docx',
            'attachment_original_name' => 'notulen.docx', 'attachment_mime' => $source['mime']]);
        $reply = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertSame('notulen.docx', $reply->attachment_original_name, (string) $reply->content);
        $updated = \App\Services\Ai\Files\OfficeSections::process(Storage::disk('normchat_attachments')->get($reply->attachment_path), 'docx')['sections'];
        $values[$id] = 'Lokasi rapat Aula Barat';
        $this->assertSame($values, array_merge(...$updated));
    }

    public function test_pilot_scan_requires_column_and_layout_confirmation_before_pdf_revision(): void
    {
        config(['normchat.documents_all_groups' => true, 'normchat.document_pilot_groups' => []]);
        $source = app(FileBuilder::class)->build([['name' => 'rab.pdf', 'content' => "# RAB\n\nOngkir Rp20.000"]], 'pdf', 'RAB');
        Storage::disk('normchat_attachments')->put('scan.pdf', $source['binary']);
        Http::fake(['http://docling:8010/pdf' => Http::response(['text' => "RAB\nOngkir Rp20.000",
            'tables' => [['table' => 1, 'rows' => [['Item', 'Nominal'], ['Ongkir', 'Rp20.000']]]]])]);
        $this->fakeChat(json_encode(['version' => substr(hash('sha256', $source['binary']), 0, 16), 'changes' => [
            ['id' => 'l2', 'old' => 'Ongkir Rp20.000', 'new' => 'Ongkir Rp50.000'],
        ]]));
        $this->mock(MaintenanceAi::class, function ($mock) {
            $mock->shouldReceive('json')->withArgs(fn ($kind) => $kind === 'file_revision_check')->once()
                ->andReturn(['data' => ['ok' => true, 'bukti' => ['Rp50.000']]]);
            $mock->shouldReceive('json')->zeroOrMoreTimes()->andReturn(null);
            $mock->shouldReceive('embed')->zeroOrMoreTimes()->andReturn(null);
        });
        $latest = fn () => Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->runAi(['content' => '@normi-file revisi ongkir jadi Rp50.000', 'message_type' => 'file',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'scan.pdf', 'attachment_original_name' => 'rab.pdf', 'attachment_mime' => 'application/pdf']);
        $this->assertStringContainsString('Item | Nominal', $latest()->content);
        $this->runAi(['content' => 'belum', 'reply_to_message_id' => $latest()->id]);
        $this->assertSame('pilot_table', $latest()->system_meta['file_stage']);
        $this->runAi(['content' => 'kolom benar', 'reply_to_message_id' => $latest()->id]);
        $this->assertSame('pilot_layout', $latest()->system_meta['file_stage']);
        $this->runAi(['content' => 'susun ulang', 'reply_to_message_id' => $latest()->id]);
        $this->assertSame('application/pdf', $latest()->attachment_mime, (string) $latest()->content);
        $this->assertStringContainsString('Rp50.000', app(AttachmentTextExtractor::class)->extract($latest()));
        $this->assertSame(1, Http::recorded(fn ($req) => str_contains($req->url(), 'docling:8010'))->count());
    }

    public function test_pilot_chat_reuses_confirmed_scan_and_sends_compact_json_once(): void
    {
        config(['normchat.document_pilot_groups' => [(string) $this->group->id]]);
        $file = app(FileBuilder::class)->build([['name' => 'scan.pdf', 'content' => "# Total\n\nBiaya rapat"]], 'pdf', 'Biaya');
        Storage::disk('normchat_attachments')->put('scan.pdf', $file['binary']);
        Http::fake(['http://docling:8010/pdf' => Http::response(['text' => "Kegiatan\nSewa ruangan 75000", 'tables' => [
            ['table' => 1, 'rows' => [['Keterangan', 'Nilai'], ['Sewa ruangan', '75000']]],
        ]])]);
        $this->fakeChat('Sewa ruangan Rp75.000.');
        $this->runAi(['content' => '@normi berapa sewa ruangan?', 'message_type' => 'file', 'attachment_disk' => 'normchat_attachments',
            'attachment_path' => 'scan.pdf', 'attachment_original_name' => 'scan.pdf', 'attachment_mime' => 'application/pdf']);
        $question = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Keterangan | Nilai', $question->content);
        $this->runAi(['content' => 'kolom benar', 'reply_to_message_id' => $question->id]);
        $reply = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertSame('Sewa ruangan Rp75.000.', $reply->content);
        Http::assertSent(function (Request $r) {
            if (!str_ends_with($r->url(), '/chat/completions')) {
                return false;
            }
            $parts = collect($r['messages'])->last()['content'];
            return is_array($parts) && str_contains($parts[0]['text'], '"l2":"Sewa ruangan 75000"');
        });
    }

    public function test_pilot_rejects_stale_or_unseen_edits_and_preserves_powerpoint_parts(): void
    {
        $file = app(FileBuilder::class)->build([['name' => 'materi.pptx', 'slides' => [
            ['title' => 'Pelajaran', 'subtitle' => 'Kelas XI'], ['title' => 'Materi', 'bullets' => ['Biologi', 'Matematika']],
        ]]], 'pptx', 'Pelajaran');
        $sections = \App\Services\Ai\Files\OfficeSections::process($file['binary'], 'pptx')['sections'];
        $id = array_search('Biologi', array_merge(...$sections), true);
        $this->assertIsString($id);
        $change = ['id' => $id, 'old' => 'Biologi', 'new' => 'Kimia'];
        $view = ['version' => 'v1', 'sections' => $sections];
        foreach ([['version' => 'v2', 'changes' => [$change]], ['version' => 'v1', 'changes' => [['id' => 'unknown']]]] as $bad) {
            try {
                \App\Services\Ai\Files\SectionRevision::validate($view, $bad, false);
                $this->fail('Invalid patch was accepted.');
            } catch (\RuntimeException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $result = \App\Services\Ai\Files\OfficeSections::process($file['binary'], 'pptx', [$id => $change]);
        $updated = \App\Services\Ai\Files\OfficeSections::process($result['binary'], 'pptx')['sections'];
        $this->assertSame('Kimia', array_merge(...$updated)[$id]);
        $this->assertContains('Matematika', array_merge(...$updated));
    }

    private function openZip(Message $message): ZipArchive
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, Storage::disk('normchat_attachments')->get($message->attachment_path));
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Lampiran bukan ZIP yang valid.');

        return $zip;
    }

    private function fakeChat(string $content): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $content], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500],
        ])]);
    }

    private function runAi(array $attributes): void
    {
        $message = Message::create(array_merge([
            'group_id' => $this->group->id,
            'sender_type' => 'user',
            'sender_id' => $this->owner->id,
            'message_type' => 'text',
        ], $attributes));

        ChatMessageQueue::create([
            'group_id' => $this->group->id,
            'message_id' => $message->id,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        (new ProcessGroupChatQueueJob($this->group->id))->handle();
    }
}
