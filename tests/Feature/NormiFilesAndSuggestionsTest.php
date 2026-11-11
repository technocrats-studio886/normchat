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
use App\Services\Ai\Files\FileRequest;
use App\Services\Chat\MessagePresenter;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $zip = new ZipArchive();
        $zip->open($path);
        $this->assertStringContainsString('<main>Toko</main>', (string) $zip->getFromName('app/page.tsx'));

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/chat/completions') && str_contains((string) $request['messages'][0]['content'], 'React/ReactDOM 18'));
    }

    public function test_awaiting_question_carries_current_normi_activity(): void
    {
        $question = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => '@normi-research harga cabai']);
        \App\Services\Ai\AiActivity::report((int) $this->group->id, 'research', 'sedang mencari "harga cabai November 2026"');

        $presented = app(MessagePresenter::class)->present($question, null, ['awaiting_ai' => true]);

        $this->assertSame(['activity' => 'research', 'detail' => 'sedang mencari "harga cabai November 2026"'], $presented['ai_activity']);
        \App\Services\Ai\AiActivity::stop((int) $this->group->id);
        $this->assertNull(app(MessagePresenter::class)->present($question, null, ['awaiting_ai' => true])['ai_activity']);
    }

    /** Pemeliharaan ingatan (embedding) boleh jalan; yang tidak boleh adalah panggilan model chat atau gambar. */
    private function assertNoModelCall(): void
    {
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/chat/completions')
            || str_contains($request->url(), '/images/') || str_contains($request->url(), '/responses'));
    }

    private function openZip(Message $message): ZipArchive
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($path, Storage::disk('normchat_attachments')->get($message->attachment_path));
        $zip = new ZipArchive();
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
