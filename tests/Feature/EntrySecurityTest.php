<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Support\SafeHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** Titik masuk data: HTML dari luar, lampiran, header unduhan, dan diagram Mermaid. */
class EntrySecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('normchat_attachments');
        Queue::fake();
        $this->user = User::factory()->create();
        $this->group = Group::create(['name' => 'Kelas BD', 'owner_id' => $this->user->id, 'password_hash' => Hash::make('1234'), 'status' => 'active']);
        GroupMember::create(['group_id' => $this->group->id, 'user_id' => $this->user->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);
    }

    public function test_safe_html_keeps_formatting_and_drops_scripts(): void
    {
        $vectors = [
            '<script>alert(1)</script>Halo' => 'Halo',
            '<img src=x onerror=alert(1)>Foto' => 'Foto',
            '<svg onload=alert(1)><circle/></svg>Ikon' => 'Ikon',
            '<p onclick="alert(1)" style="color:red">Teks</p>' => '<p>Teks</p>',
            '<a href="javascript:alert(1)">klik</a>' => 'klik',
            '<a href="  java&#x09;script:alert(1)">klik</a>' => 'klik',
            '<a href="JaVaScRiPt:alert(1)">klik</a>' => 'klik',
            '<a href="data:text/html,<script>alert(1)</script>">klik</a>' => 'klik',
            '<iframe src="https://evil.test"></iframe>Isi' => 'Isi',
            '<style>body{display:none}</style><b>Tebal</b>' => '<b>Tebal</b>',
            '<p>a &lt;script&gt; b</p>' => '<p>a &lt;script&gt; b</p>',
            '<font color="red">Merah</font>' => 'Merah',
            '<form action="https://evil.test"><input name="p"></form>Form' => 'Form',
            '"><script>alert(1)</script>' => '&quot;&gt;',
        ];
        foreach ($vectors as $input => $expected) {
            $this->assertSame($expected, SafeHtml::clean($input), $input);
        }

        $this->assertSame(
            '<p><strong>Halo</strong> <a href="https://normi.chat/x?a=1&amp;b=2" target="_blank" rel="noopener noreferrer nofollow">tautan</a></p><ul><li>satu</li></ul>',
            SafeHtml::clean('<p><strong>Halo</strong> <a href="https://normi.chat/x?a=1&b=2" class="x">tautan</a></p><ul><li>satu</li></ul>')
        );
        $this->assertSame('<a href="mailto:a@b.c" target="_blank" rel="noopener noreferrer nofollow">surel</a>', SafeHtml::clean('<a href="mailto:a@b.c">surel</a>'));
    }

    public function test_mailbox_body_from_other_users_is_sanitized(): void
    {
        view()->share('errors', new ViewErrorBag());
        $html = view('mailbox.show', [
            'mail' => ['id' => 'm-1', 'subject' => 'Halo', 'body' => '<p>Isi <img src=x onerror="alert(1)"><script>alert(2)</script></p>'],
            'error' => null,
            'currentUser' => $this->user,
        ])->render();

        $this->assertStringContainsString('<p>Isi </p>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('onerror="alert(1)"', $html);
        $this->assertStringNotContainsString('<script>alert(2)', $html);
    }

    public function test_fake_upload_type_is_not_trusted(): void
    {
        // Isi teks biasa (lolos pemeriksaan isi) yang diaku browser sebagai halaman HTML.
        $path = tempnam(sys_get_temp_dir(), 'nc');
        file_put_contents($path, "catatan rapat\nbaris kedua");
        $this->actingAs($this->user)->postJson(route('chat.store', $this->group), [
            'attachment' => new UploadedFile($path, 'catatan.txt', 'text/html', null, true),
        ])->assertCreated();

        $message = Message::firstOrFail();
        $this->assertSame('text/plain', $message->attachment_mime);
        $this->actingAs($this->user)->get(route('chat.attachment', [$this->group, $message]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function test_attachment_name_cannot_break_the_download_header(): void
    {
        Storage::disk('normchat_attachments')->put('g/a.txt', 'x');
        $message = Message::create([
            'group_id' => $this->group->id, 'sender_type' => 'user', 'sender_id' => $this->user->id, 'message_type' => 'file',
            'attachment_disk' => 'normchat_attachments', 'attachment_path' => 'g/a.txt', 'attachment_mime' => 'text/plain',
            'attachment_original_name' => 'la"poran"; filename=evil.html/ü.txt',
        ]);

        $disposition = $this->actingAs($this->user)->get(route('chat.attachment', [$this->group, $message]))
            ->assertOk()->headers->get('Content-Disposition');

        $this->assertStringStartsWith('attachment; filename="la_poran_; filename=evil.html_u.txt"; filename*=utf-8\'\'', $disposition);
        $this->assertSame(1, substr_count($disposition, 'filename="'));
    }

    public function test_mermaid_diagrams_run_in_strict_mode(): void
    {
        $this->actingAs($this->user)->get(route('groups.index'))
            ->assertOk()
            ->assertSee("securityLevel:'strict'", false)
            ->assertDontSee("securityLevel:'loose'", false);
    }
}
