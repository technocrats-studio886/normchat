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
use App\Services\Ai\GroupAiAssistant;
use App\Services\Credits\CreditWallet;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pilihan composer: model per pesan, mode jawaban (Guided Learning, gambar, Canvas,
 * Deep Research) dengan prompt tersembunyi, serta aturan format lampiran.
 */
class AiModesTest extends TestCase
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

        $this->owner = User::factory()->create();
        $this->group = Group::create([
            'name' => 'Kelas BD',
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

    public function test_model_chosen_in_composer_overrides_group_model(): void
    {
        // Advanced terbuka mulai 500 NK (ModelAccess).
        app(CreditWallet::class)->deposit($this->group, 200);
        $this->fakeChat('Jawaban');

        $this->runAi(['content' => '@normi halo', 'ai_model' => 'gpt-6-astra']);

        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-6-astra');
        $this->assertSame('gpt-6-astra', AiUsageLog::firstOrFail()->model);
    }

    public function test_inactive_model_choice_falls_back_to_group_model(): void
    {
        $this->fakeChat('Jawaban');

        $this->runAi(['content' => '@normi halo', 'ai_model' => 'gpt-5.4-mini']);

        Http::assertSent(fn (Request $request) => $request['model'] === 'gpt-6-luna');
    }

    public function test_guided_learning_adds_hidden_tutor_instructions(): void
    {
        $this->fakeChat('Apa yang sudah kamu ketahui tentang turunan?');

        $this->runAi(['content' => '@normi ajari aku turunan', 'ai_mode' => AiMode::GuidedLearning]);

        Http::assertSent(function (Request $request) {
            $system = (string) $request['messages'][0]['content'];
            $latest = json_encode(end($request->data()['messages']));

            return str_contains($system, 'MODE GUIDED LEARNING') && ! str_contains($latest, 'MODE GUIDED LEARNING');
        });
        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('Apa yang sudah kamu ketahui tentang turunan?', $reply->content);
        $this->assertSame('guided_learning', AiUsageLog::firstOrFail()->kind);
    }

    public function test_image_mode_draws_even_without_image_keywords(): void
    {
        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['prompt' => 'a cat', 'precise' => false, 'use_reference' => false, 'caption' => 'Kucing'])]]],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]),
            'api.openai.com/v1/images/generations' => Http::response([
                'data' => [['b64_json' => base64_encode('png-bytes')]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 100],
            ]),
        ]);

        $this->runAi(['content' => '@normi kucing oranye di jendela', 'ai_mode' => AiMode::Image]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'images/generations'));
        $this->assertSame('image', Message::where('sender_type', 'ai')->firstOrFail()->message_type);
    }

    public function test_canvas_web_page_is_stored_as_canvas_message(): void
    {
        $html = '<!DOCTYPE html><html><body><h1>Kalkulator BMI</h1></body></html>';
        $this->fakeChat(json_encode(['kind' => 'web', 'title' => 'Kalkulator BMI', 'summary' => 'Kalkulator BMI siap dicoba.', 'content' => $html]));

        $this->runAi(['content' => '@normi buatkan kalkulator BMI', 'ai_mode' => AiMode::Canvas]);

        Http::assertSent(fn (Request $request) => ($request['response_format']['type'] ?? null) === 'json_object'
            && str_contains((string) $request['messages'][0]['content'], 'MODE CANVAS')
            && $request['max_completion_tokens'] > 4000);

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertSame('canvas', $reply->message_type);
        $this->assertSame('text/html', $reply->attachment_mime);
        $this->assertSame('kalkulator-bmi.html', $reply->attachment_original_name);
        $this->assertSame('Kalkulator BMI siap dicoba.', $reply->content);
        Storage::disk('normchat_attachments')->assertExists($reply->attachment_path);
        $this->assertSame($html, Storage::disk('normchat_attachments')->get($reply->attachment_path));
        $this->assertSame('canvas', AiUsageLog::firstOrFail()->kind);
    }

    public function test_replying_to_a_canvas_sends_it_for_revision(): void
    {
        Storage::disk('normchat_attachments')->put('group-1/canvas-lama.md', '# Rencana Belajar Lama');
        $previous = Message::create([
            'group_id' => $this->group->id,
            'sender_type' => 'ai',
            'message_type' => 'canvas',
            'content' => 'Rencana belajar.',
            'attachment_disk' => 'normchat_attachments',
            'attachment_path' => 'group-1/canvas-lama.md',
            'attachment_mime' => 'text/markdown',
            'attachment_original_name' => 'rencana-belajar.md',
        ]);
        $this->fakeChat(json_encode(['kind' => 'document', 'title' => 'Rencana Belajar', 'summary' => 'Sudah direvisi.', 'content' => '# Rencana Belajar Baru']));

        $this->runAi(['content' => 'tambahkan jadwal mingguan', 'reply_to_message_id' => $previous->id, 'ai_mode' => AiMode::Canvas]);

        Http::assertSent(fn (Request $request) => str_contains(json_encode($request['messages']), 'Rencana Belajar Lama'));
        $reply = Message::where('sender_type', 'ai')->latest('id')->firstOrFail();
        $this->assertSame('text/markdown', $reply->attachment_mime);
        $this->assertSame('# Rencana Belajar Baru', Storage::disk('normchat_attachments')->get($reply->attachment_path));
    }

    public function test_broken_canvas_output_is_not_billed(): void
    {
        $this->fakeChat('bukan json');

        $this->runAi(['content' => '@normi buat canvas', 'ai_mode' => AiMode::Canvas]);

        $this->assertStringContainsString('Gagal membuat canvas', (string) Message::where('sender_type', 'ai')->value('content'));
        $this->assertSame(0, AiUsageLog::count());
        $this->assertSame(300.0, $this->group->groupToken->fresh()->credits);
    }

    public function test_deep_research_uses_web_search_and_bills_each_search(): void
    {
        Http::fake(['api.openai.com/v1/responses' => Http::response([
            'status' => 'completed',
            'output' => [
                ['type' => 'web_search_call', 'status' => 'completed'],
                ['type' => 'web_search_call', 'status' => 'completed'],
                ['type' => 'message', 'content' => [[
                    'type' => 'output_text',
                    'text' => "## Ringkasan\n- Inflasi turun.",
                    'annotations' => [['type' => 'url_citation', 'url' => 'https://bps.go.id/inflasi', 'title' => 'BPS']],
                ]]],
            ],
            'usage' => ['input_tokens' => 2000, 'output_tokens' => 1000],
        ])]);

        $this->runAi(['content' => '@normi riset inflasi Indonesia 2026', 'ai_mode' => AiMode::DeepResearch]);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/v1/responses')
            && $request['tools'] === [['type' => 'web_search']]
            && str_contains((string) $request['instructions'], 'MODE DEEP RESEARCH')
            && $request['model'] === 'gpt-6-luna');

        $reply = Message::where('sender_type', 'ai')->firstOrFail();
        $this->assertStringContainsString('[BPS](https://bps.go.id/inflasi)', (string) $reply->content);

        // Token Cepat: (2.000 × 0,10 + 1.000 × 0,50) / 1M × 1,5 = $0,00105; 2 pencarian × $0,01 × 2 (media) = $0,04
        // → $0,04105 × 17.950 ÷ 120 = 6,1404 → 6,15 NK
        $log = AiUsageLog::firstOrFail();
        $this->assertSame('research', $log->kind);
        $this->assertSame(6.15, $log->charged_credits);
        $this->assertContains('web_search', array_column($log->breakdown_json['lines'], 'component'));
    }

    public function test_composer_choice_is_saved_only_for_messages_to_ai(): void
    {
        Queue::fake();

        $this->actingAs($this->owner)->postJson($this->url('messages'), [
            'content' => '@normi buatkan landing page', 'ai_mode' => 'canvas', 'ai_model' => 'gpt-6-astra',
        ])->assertCreated();
        $this->actingAs($this->owner)->postJson($this->url('messages'), [
            'content' => 'halo semua', 'ai_mode' => 'canvas', 'ai_model' => 'gpt-6-astra',
        ])->assertCreated();

        [$toAi, $plain] = Message::orderBy('id')->get();
        $this->assertSame([AiMode::Canvas, 'gpt-6-astra'], [$toAi->ai_mode, $toAi->ai_model]);
        $this->assertSame([null, null], [$plain->ai_mode, $plain->ai_model]);

        $this->actingAs($this->owner)->postJson($this->url('messages'), ['content' => '@normi x', 'ai_mode' => 'sulap'])
            ->assertJsonValidationErrors('ai_mode');
        $this->actingAs($this->owner)->postJson($this->url('messages'), ['content' => '@normi x', 'ai_model' => 'gpt-5.4-mini'])
            ->assertJsonValidationErrors('ai_model');
    }

    public function test_attachment_formats_are_broad_but_unsafe_ones_are_refused(): void
    {
        Queue::fake();

        foreach (['tugas.py', 'laporan.docx', 'data.json', 'materi.epub', 'desain.psd', 'kode.zip'] as $name) {
            $this->actingAs($this->owner)->postJson($this->url('messages'), ['attachment' => UploadedFile::fake()->create($name, 10)])
                ->assertCreated();
        }

        foreach (['halaman.html', 'ikon.svg', 'setup.exe', 'app.apk', 'skrip.bat'] as $name) {
            $this->actingAs($this->owner)->postJson($this->url('messages'), ['attachment' => UploadedFile::fake()->create($name, 10)])
                ->assertJsonValidationErrors('attachment');
        }

        // Ekstensi diganti agar lolos, tetapi isinya tetap halaman HTML (berkas sungguhan agar MIME dibaca dari isi).
        $path = tempnam(sys_get_temp_dir(), 'nc');
        file_put_contents($path, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');
        $this->actingAs($this->owner)->postJson($this->url('messages'), [
            'attachment' => new UploadedFile($path, 'catatan.txt', null, null, true),
        ])->assertJsonValidationErrors('attachment');
    }

    public function test_attachments_are_served_sandboxed_except_pdf(): void
    {
        Queue::fake();
        $this->actingAs($this->owner)->postJson($this->url('messages'), ['attachment' => UploadedFile::fake()->create('catatan.txt', 1)]);
        $this->actingAs($this->owner)->postJson($this->url('messages'), ['attachment' => UploadedFile::fake()->create('materi.pdf', 1, 'application/pdf')]);
        [$text, $pdf] = Message::orderBy('id')->get();

        $this->actingAs($this->owner)->get($this->url("messages/{$text->id}/attachment"))
            ->assertOk()->assertHeader('Content-Security-Policy', 'sandbox')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->owner)->get($this->url("messages/{$pdf->id}/attachment"))
            ->assertOk()->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_chat_page_offers_models_and_modes(): void
    {
        $this->actingAs($this->owner)->get($this->url('chat'))
            ->assertOk()
            ->assertSee('data-ai-model-option="gpt-6-astra"', false)
            ->assertDontSee('data-ai-model-option="gpt-5.4-mini"', false)
            ->assertSee('data-ai-mode-option="deep_research"', false)
            ->assertSee('Unggah file');
    }

    public function test_reply_markers_stay_out_of_ai_history_and_answers(): void
    {
        $question = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'content' => '@normi siapa Messi?']);
        $answer = Message::create(['group_id' => $this->group->id, 'sender_type' => 'ai', 'reply_to_message_id' => $question->id, 'content' => 'Lionel Messi adalah pesepak bola Argentina.']);
        // Model kadang meniru penanda format riwayat lama di akhir jawabannya.
        $this->fakeChat('Oh, maksudmu Cristiano Ronaldo. [reply]');

        $this->runAi(['content' => '@normi maksudku Ronaldo', 'reply_to_message_id' => $answer->id, 'reply_quote_text' => 'pesepak bola Argentina']);

        Http::assertSent(function (Request $request) {
            $history = collect($request['messages'])->slice(1, -1);
            $assistant = $history->firstWhere('role', 'assistant');

            return $assistant['content'] === 'Lionel Messi adalah pesepak bola Argentina.'
                && ! str_contains(json_encode($request['messages']), '[reply');
        });
        $this->assertSame('Oh, maksudmu Cristiano Ronaldo.', Message::where('sender_type', 'ai')->latest('id')->value('content'));
    }

    public function test_reply_marker_cleanup_keeps_the_rest_of_the_answer(): void
    {
        $this->assertSame('Ronaldo meraih 5 Ballon d’Or.', GroupAiAssistant::withoutReplyMarkers('Ronaldo meraih 5 Ballon d’Or. [reply]'));
        $this->assertSame("Baris satu\nBaris dua", GroupAiAssistant::withoutReplyMarkers("Baris satu [reply mengutip: \"bola\"]\nBaris dua"));
        $this->assertSame('Tanda [penting] tetap ada.', GroupAiAssistant::withoutReplyMarkers('Tanda [penting] tetap ada.'));
        $this->assertNull(GroupAiAssistant::withoutReplyMarkers(null));
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

    private function url(string $path): string
    {
        return "/groups/{$this->group->id}/{$path}";
    }
}
