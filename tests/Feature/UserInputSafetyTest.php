<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Exports\ChatExportBuilder;
use App\Services\Exports\DocxChatExporter;
use App\Services\Exports\ExportOptions;
use App\Services\Exports\PdfChatExporter;
use App\Services\Groups\GroupAvatars;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Input pengguna yang berisi HTML, markdown, sintaks kode, atau karakter aneh tampil apa adanya sebagai teks:
 * tidak tersisip sebagai HTML (XSS) dan tidak merusak halaman atau berkas ekspor.
 * Sisi browser (bubble, markdown Normi, blok kode, mermaid, pratinjau tautan, cari) diuji di harness Playwright.
 */
class UserInputSafetyTest extends TestCase
{
    use RefreshDatabase;

    private const IMG = '<img src=x onerror="alert(1)">';

    private const SCRIPT = '</script><script>alert(2)</script>';

    private User $owner;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GroupAvatars::DISK);
        config()->set('normchat.pricing.free_groups_enabled', false);
        $this->owner = User::factory()->create(['name' => 'Sari ' . self::IMG, 'tour_seen_at' => now()]);
        $this->group = Group::create([
            'name' => 'Grup ' . self::IMG,
            'description' => "Desk \"><svg onload=alert(3)> & **tebal**\n" . self::SCRIPT,
            'owner_id' => $this->owner->id,
            'status' => 'active',
            'ai_persona_style' => 'Gaya ' . self::IMG,
            'ai_persona_guardrails' => '</textarea>' . self::SCRIPT,
        ]);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    private function say(string $content, string $senderType = 'user'): Message
    {
        return Message::create([
            'group_id' => $this->group->id,
            'sender_type' => $senderType,
            'sender_id' => $senderType === 'user' ? $this->owner->id : null,
            'message_type' => 'text',
            'content' => $content,
        ]);
    }

    private function assertNoRawPayload(string $html, string $page): void
    {
        foreach (['<img src=x onerror', '<svg onload', '<script>alert', '</textarea><'] as $raw) {
            $this->assertStringNotContainsString($raw, $html, "{$page} memuat HTML mentah dari input: {$raw}");
        }
    }

    public function test_pages_show_user_input_as_text(): void
    {
        $this->say('Halo ' . self::IMG . ' ' . self::SCRIPT . ' [x](javascript:alert(4)) ```js');
        $this->say('Tom & Jerry <b>tebal</b> ‮RTL 🧪');

        $chat = $this->actingAs($this->owner)->get(route('chat.show', $this->group))->assertOk()->getContent();
        $this->assertNoRawPayload($chat, 'Ruang chat');
        $this->assertStringContainsString('Grup &lt;img src=x onerror=&quot;alert(1)&quot;&gt;', $chat);
        // Data awal pesan ada di <script type="application/json">: "</script>" tidak boleh menutupnya lebih awal.
        preg_match('/<script type="application\/json" data-initial-messages[^>]*>(.*?)<\/script>/s', $chat, $match);
        $contents = collect(json_decode($match[1] ?? '[]', true))->pluck('content');
        $this->assertContains('Halo ' . self::IMG . ' ' . self::SCRIPT . ' [x](javascript:alert(4)) ```js', $contents);
        $this->assertContains('Tom & Jerry <b>tebal</b> RTL 🧪', $contents);

        foreach ([
            'Daftar grup' => route('groups.index'),
            'Info grup' => route('settings.show', $this->group),
            'Persona Normi' => route('settings.ai.persona', $this->group),
            'Halaman gabung' => route('groups.join', $this->group->share_id),
        ] as $page => $url) {
            $viewer = $page === 'Halaman gabung' ? User::factory()->create(['tour_seen_at' => now()]) : $this->owner;
            $html = $this->actingAs($viewer)->get($url)->assertOk()->getContent();
            $this->assertNoRawPayload($html, $page);
            $this->assertStringContainsString('Grup &lt;img src=x', $html, "{$page} tidak menampilkan nama grup sebagai teks");
        }
    }

    public function test_direction_control_characters_are_removed_from_names_messages_and_file_names(): void
    {
        $rlo = "\u{202E}";
        $this->owner->update(['name' => "Dimas {$rlo}gnp.exe"]);
        $this->group->update(['name' => "Kelas {$rlo}cba", 'description' => "Desk \u{2066}isi\u{2069}"]);
        $message = $this->say("tagihan {$rlo}0005 rupiah \u{202B}x\u{202C}");
        $file = Message::create(['group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->owner->id, 'message_type' => 'file', 'content' => '', 'attachment_original_name' => "laporan{$rlo}fdp.exe"]);

        $this->assertSame('Dimas gnp.exe', $this->owner->fresh()->name);
        $this->assertSame('Kelas cba', $this->group->fresh()->name);
        $this->assertSame('Desk isi', $this->group->fresh()->description);
        $this->assertSame('tagihan 0005 rupiah x', $message->fresh()->content);
        $this->assertSame('laporanfdp.exe', $file->fresh()->attachment_original_name);
        // Emoji gabungan (ZWJ) dan teks Arab tidak tersentuh.
        $this->assertSame('👩‍💻 مرحبا', $this->say('👩‍💻 مرحبا')->fresh()->content);
    }

    public function test_exports_keep_markup_as_text_and_stay_valid(): void
    {
        $this->say('Halo ' . self::IMG . ' Tom & Jerry <b>tebal</b> ' . self::SCRIPT);
        $this->say("Jawab " . self::IMG . "\n\n[tautan](javascript:alert(5)) **md**\n\n```html\n<script>alert(6)</script>\n```", 'ai');
        $this->say("📊 POLL: Pilih <b>satu</b> & dua\n1. <i>A</i>\n2. B \"><svg onload=alert(7)>");
        $document = app(ChatExportBuilder::class)->build($this->group, new ExportOptions('docx', 'all', null, null, [], true, true, 'Asia/Jakarta'), $this->owner);

        // DOCX: document.xml tetap XML yang sah, dan teksnya memuat tanda < & apa adanya.
        $docx = app(DocxChatExporter::class)->render($document);
        $path = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($path, $docx);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);
        $dom = new \DOMDocument();
        $this->assertTrue(@$dom->loadXML($xml), 'document.xml rusak karena karakter dari input.');
        $text = $dom->textContent;
        $this->assertStringContainsString('Grup ' . self::IMG, $text);
        $this->assertStringContainsString('Tom & Jerry <b>tebal</b>', $text);
        $this->assertStringContainsString('<script>alert(6)</script>', $text);
        $this->assertStringContainsString('<i>A</i>', $text);
        $this->assertStringNotContainsString('javascript:', $xml);

        // PDF: HTML sumbernya meng-escape semua input; tautan javascript: tidak menjadi tautan.
        $html = (fn () => $this->html($document))->call(app(PdfChatExporter::class));
        $this->assertNoRawPayload($html, 'Ekspor PDF');
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('Tom &amp; Jerry &lt;b&gt;tebal&lt;/b&gt;', $html);
        $this->assertStringStartsWith('%PDF', app(PdfChatExporter::class)->render($document));
    }
}
