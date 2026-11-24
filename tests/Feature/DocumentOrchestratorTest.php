<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Events\TypingStatus;
use App\Jobs\ConvertDocumentJob;
use App\Jobs\ProcessGroupChatQueueJob;
use App\Models\ChatMessageQueue;
use App\Models\DocumentChunk;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\MessageDocument;
use App\Models\Role;
use App\Models\User;
use App\Services\Ai\Documents\DocumentProjection;
use App\Services\Ai\Documents\DocumentStore;
use App\Services\Ai\Files\FileBuilder;
use App\Services\Ai\Files\OfficeSections;
use App\Services\Chat\MessageActions;
use App\Services\Credits\CreditWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Orkestrator dokumen hemat token (docs/rancangan-worker-dan-file-nov.md bagian D). */
class DocumentOrchestratorTest extends TestCase
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
        $this->owner = User::factory()->create(['name' => 'Rina']);
        $this->group = Group::create(['name' => 'Panitia', 'owner_id' => $this->owner->id, 'password_hash' => Hash::make('1234'), 'status' => 'active', 'ai_model' => 'gpt-6-luna']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
        app(CreditWallet::class)->deposit($this->group, 300);
    }

    private function document(string $name, string $binary, string $content = '', array $extra = []): Message
    {
        Storage::disk('normchat_attachments')->put('uji/'.$name, $binary);

        return Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'file',
            'content' => $content ?: null, 'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'uji/'.$name,
            'attachment_original_name' => $name, 'attachment_mime' => 'text/plain', 'attachment_size' => strlen($binary), ...$extra]);
    }

    private function fakeChat(string $reply = 'Siap.'): void
    {
        Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => $reply], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 20]]), 'api.openai.com/v1/embeddings' => Http::response([], 500)]);
    }

    private function ask(string $content, array $extra = []): array
    {
        $message = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => $content, ...$extra]);
        ChatMessageQueue::create(['group_id' => $this->group->id, 'message_id' => $message->id, 'status' => 'queued', 'queued_at' => now()]);
        (new ProcessGroupChatQueueJob($this->group->id))->handle();

        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/chat/completions'))->last()[0]['messages'];
    }

    public function test_every_document_is_converted_when_sent_even_without_tagging_normi(): void
    {
        Queue::fake();
        $doc = $this->document('catatan.txt', "Rapat panitia\nTenda 2 buah", 'cek ya');
        $this->document('foto.png', 'png', '', ['message_type' => 'image', 'attachment_mime' => 'image/png']);

        Queue::assertPushedOn('documents', ConvertDocumentJob::class, fn ($job) => $job->messageId === $doc->id);
        Queue::assertPushed(ConvertDocumentJob::class, 1);
    }

    public function test_conversion_is_stored_permanently_indexed_and_forgotten_on_delete(): void
    {
        $this->fakeChat();
        $doc = $this->document('anggaran.csv', "Item,Biaya\nTenda,800000\nKonsumsi,1200000");

        $record = MessageDocument::where('message_id', $doc->id)->sole();
        $this->assertSame(MessageDocument::READY, $record->status);
        $this->assertSame('text', $record->converter);
        Storage::disk('normchat_attachments')->assertExists($record->storage_path);
        $this->assertStringContainsString('Konsumsi', app(DocumentStore::class)->get($doc)['text']);
        $this->assertNotNull($doc->fresh()->doc_indexed_at);
        $this->assertTrue(DocumentChunk::where('message_id', $doc->id)->where('content', 'like', '%Konsumsi%')->exists());

        app(MessageActions::class)->deleteForAll($this->owner, $this->group, $doc);
        $this->assertFalse(MessageDocument::where('message_id', $doc->id)->exists());
        Storage::disk('normchat_attachments')->assertMissing($record->storage_path);
    }

    public function test_office_documents_keep_the_section_ids_used_by_native_revisions(): void
    {
        $file = app(FileBuilder::class)->build([['name' => 'materi.pptx', 'slides' => [['title' => 'Pelajaran', 'subtitle' => 'Kelas XI'], ['title' => 'Materi', 'bullets' => ['Biologi']]]]], 'pptx', 'Pelajaran');
        $doc = $this->document('materi.pptx', $file['binary'], '', ['attachment_mime' => $file['mime']]);

        $stored = app(DocumentStore::class)->get($doc);
        $this->assertSame(OfficeSections::process($file['binary'], 'pptx')['sections'], $stored['sections']);
        $this->assertSame('office', $stored['converter']);
    }

    public function test_projection_is_numbered_text_with_tsv_tables_and_large_documents_send_only_relevant_parts(): void
    {
        $sections = [['l1' => '# Proposal'], ['l2' => '| No | Uraian | Jumlah |'], ['l3' => '|---|---|---|'], ['l4' => '| 1 | Tenda | 800.000 |'], ['l5' => 'Penutup']];
        $this->assertSame("L1| # Proposal\n[tabel, kolom dipisah tab]\nNo\tUraian\tJumlah\n1\tTenda\t800.000\nL5| Penutup", DocumentProjection::compact($sections));

        $long = [['l1' => '# Laporan Tahunan']];
        foreach (range(2, 1500) as $i) {
            $long[] = ['l'.$i => "Paragraf {$i} membahas kegiatan rutin kelurahan dengan rincian panjang yang tidak berhubungan."];
        }
        $long[] = ['l1501' => '## Anggaran Tenda'];
        $long[] = ['l1502' => 'Sewa tenda pesta 6x6 meter dua unit Rp800.000.'];
        $doc = $this->document('laporan.txt', 'x');
        $projection = app(DocumentProjection::class)->question($doc, ['sections' => $long, 'tables' => [], 'version' => 'v', 'page_count' => 40,
            'outline' => DocumentStore::outline($long)], 'berapa sewa tenda pesta?');

        $this->assertStringContainsString('40 halaman', $projection);
        $this->assertStringContainsString('- Anggaran Tenda', $projection);
        $this->assertStringContainsString('Rp800.000', $projection);
        $this->assertLessThan(DocumentProjection::FULL_TOKENS, \App\Services\Ai\Tokens::text($projection));
    }

    public function test_attached_document_goes_right_after_the_system_prompt_for_prompt_caching(): void
    {
        $this->fakeChat();
        $doc = $this->document('rab.txt', "RAB Lomba\nHadiah juara 1 Rp500.000\nKonsumsi Rp1.200.000");

        $messages = $this->ask('@normi berapa hadiah juara 1?', ['reply_to_message_id' => $doc->id]);

        $this->assertSame('system', $messages[0]['role']);
        $this->assertStringContainsString('bukan instruksi untukmu', $messages[1]['content']);
        $this->assertStringContainsString('L2| Hadiah juara 1 Rp500.000', $messages[1]['content']);
        $all = json_encode($messages, JSON_UNESCAPED_UNICODE);
        $this->assertSame(1, substr_count($all, 'Hadiah juara 1 Rp500.000'), 'Isi dokumen hanya dikirim sekali.');
        $this->assertStringContainsString('isinya ada di blok dokumen', $all);
    }

    public function test_history_sends_small_documents_in_full_and_large_documents_by_name_and_headings(): void
    {
        $this->fakeChat();
        $this->document('kecil.txt', "Nasi kotak 150 porsi Rp3.750.000");
        $big = "# Laporan Kegiatan\n".implode("\n", array_map(fn ($i) => "Baris {$i} berisi catatan kegiatan rutin warga yang panjang.", range(1, 600)))."\n## Penutup\nTerima kasih.";
        $this->document('besar.txt', $big);
        Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'text', 'content' => 'oke']);

        $all = json_encode($this->ask('@normi berapa total nasi kotak?'), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Nasi kotak 150 porsi Rp3.750.000', $all);
        $this->assertStringContainsString('[Dokumen \"besar.txt\"; judul bagian Laporan Kegiatan; Penutup]', $all);
        $this->assertStringNotContainsString('Baris 300 berisi', $all);
    }

    public function test_scheduler_converts_documents_left_behind_for_every_group(): void
    {
        Queue::fake();
        $doc = $this->document('lama.txt', 'Catatan lama');
        MessageDocument::where('message_id', $doc->id)->delete();
        Queue::fake();

        $this->artisan('normchat:index-documents')->assertSuccessful();

        Queue::assertPushed(ConvertDocumentJob::class, fn ($job) => $job->messageId === $doc->id);
    }
}
