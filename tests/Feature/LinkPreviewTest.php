<?php

namespace Tests\Feature;

use App\Events\LinkPreviewReady;
use App\Jobs\FetchLinkPreviewJob;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Links\HostResolver;
use App\Services\Links\LinkExtractor;
use App\Services\Links\LinkPreviewFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** docs/rancangan-pratinjau-tautan.md */
class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const PAGE = '<html><head><title>Judul cadangan</title>'
        . '<meta property="og:title" content="TikTok &middot; Bontang Post">'
        . '<meta property="og:site_name" content="TikTok">'
        . '<meta name="description" content="Video terbaru">'
        . '<meta property="og:image" content="/img/thumb.jpg">'
        . '</head><body>x</body></html>';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('normchat.pricing.free_groups_enabled', false);
        // DNS palsu: example.com publik, intranet.test mengarah ke alamat privat.
        $this->app->instance(HostResolver::class, new class extends HostResolver {
            public function resolve(string $host): array
            {
                if (filter_var($host, FILTER_VALIDATE_IP)) {
                    return [$host];
                }

                return match ($host) {
                    'intranet.test' => ['10.0.0.5'],
                    'metadata.test' => ['169.254.169.254'],
                    default => ['93.184.216.34'],
                };
            }
        });
    }

    public function test_extractor_trims_punctuation_and_keeps_wikipedia_parentheses(): void
    {
        $this->assertSame(
            ['https://a.com/x', 'https://en.wikipedia.org/wiki/Foo_(bar)', 'http://b.id/y?z=1'],
            LinkExtractor::urls('cek https://a.com/x, lalu (https://en.wikipedia.org/wiki/Foo_(bar)) dan http://b.id/y?z=1.'),
        );
        $this->assertSame('cek, lalu dan.', LinkExtractor::textWithout('cek https://a.com/x, lalu (https://en.wikipedia.org/wiki/Foo_(bar)) dan http://b.id/y?z=1.'));
    }

    public function test_fetcher_reads_open_graph_and_makes_the_image_absolute(): void
    {
        Http::fake(['https://example.com/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

        $data = app(LinkPreviewFetcher::class)->fetch('https://example.com/video/1');

        $this->assertSame(['title' => 'TikTok · Bontang Post', 'site_name' => 'TikTok', 'description' => 'Video terbaru', 'image_url' => 'https://example.com/img/thumb.jpg'], $data);
        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0] ?? '', 'NormiChatBot'));
    }

    public function test_fetcher_refuses_private_addresses_redirects_into_them_and_non_html(): void
    {
        Http::fake([
            'https://example.com/go' => Http::response('', 302, ['Location' => 'http://intranet.test/admin']),
            'https://example.com/file.pdf' => Http::response('%PDF', 200, ['Content-Type' => 'application/pdf']),
            '*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html']),
        ]);
        $fetcher = app(LinkPreviewFetcher::class);

        $this->assertNull($fetcher->fetch('http://intranet.test/'));
        $this->assertNull($fetcher->fetch('http://metadata.test/latest/meta-data'));
        $this->assertNull($fetcher->fetch('http://127.0.0.1/'));
        $this->assertNull($fetcher->fetch('https://example.com:22/'));
        $this->assertNull($fetcher->fetch('ftp://example.com/'));
        $this->assertNull($fetcher->fetch('https://user:pass@example.com/'));
        $this->assertNull($fetcher->fetch('https://example.com/go'));
        $this->assertNull($fetcher->fetch('https://example.com/file.pdf'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'intranet.test') || str_contains($request->url(), '127.0.0.1'));
    }

    public function test_only_the_first_512_kb_is_parsed_and_http_images_are_dropped(): void
    {
        $page = '<html><head><meta property="og:title" content="Awal"><meta property="og:image" content="http://example.com/a.jpg"></head><body>'
            . str_repeat('a', 700000) . '<meta property="og:description" content="Terlalu jauh"></body></html>';
        Http::fake(['*' => Http::response($page, 200, ['Content-Type' => 'text/html'])]);

        $data = app(LinkPreviewFetcher::class)->fetch('https://example.com/');

        $this->assertSame('Awal', $data['title']);
        $this->assertNull($data['image_url']);
        $this->assertNull($data['description']);
    }

    public function test_sending_a_link_queues_the_preview_and_the_card_reaches_the_bubble(): void
    {
        [$owner, $group] = $this->group();
        Queue::fake();

        $this->actingAs($owner)->postJson(route('chat.store', $group), ['content' => 'lihat ini https://example.com/video/1 seru'])->assertCreated()
            ->assertJsonPath('message.link_preview', null);
        Queue::assertPushed(FetchLinkPreviewJob::class, fn ($job) => $job->url === 'https://example.com/video/1' && $job->groupId === $group->id);

        Event::fake([LinkPreviewReady::class]);
        Http::fake(['*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html'])]);
        $message = Message::latest('id')->first();
        (new FetchLinkPreviewJob('https://example.com/video/1', $group->id, $message->id))->handle(app(LinkPreviewFetcher::class));

        Event::assertDispatched(LinkPreviewReady::class, fn ($event) => $event->messageId === $message->id && $event->card['title'] === 'TikTok · Bontang Post');
        $html = $this->actingAs($owner)->get(route('chat.show', $group))->getContent();
        // JSON halaman memakai JSON_HEX_* (tanda · bisa ditulis ·): cukup pastikan URL dan judulnya ada.
        $this->assertStringContainsString('"link_preview":{"url":"https:\/\/example.com\/video\/1","title":"TikTok ', $html);
        $this->assertStringContainsString('Bontang Post","site_name":"TikTok"', $html);
    }

    public function test_failed_previews_retry_after_a_day_and_links_tab_groups_entries_with_chat_text(): void
    {
        [$owner, $group] = $this->group();
        Queue::fake();
        $say = fn (string $content, $at) => tap(Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $owner->id, 'message_type' => 'text', 'content' => $content]), fn ($m) => $m->forceFill(['created_at' => $at])->save());
        $say('lama https://old.example.com/a', now()->subDays(40));
        $say('minggu lalu https://week.example.com/b', now()->subDays(10));
        $say('Bantu like https://vt.tiktok.com/ZSgc4j1Ko/ ya', now()->subHour());
        LinkPreview::create(['url_hash' => LinkPreview::hashFor('https://vt.tiktok.com/ZSgc4j1Ko/'), 'url' => 'https://vt.tiktok.com/ZSgc4j1Ko/', 'title' => 'TikTok · Noerhayathiee', 'image_url' => 'https://p16.tiktokcdn.com/x.jpg', 'status' => LinkPreview::OK, 'fetched_at' => now()]);
        LinkPreview::create(['url_hash' => LinkPreview::hashFor('https://week.example.com/b'), 'url' => 'https://week.example.com/b', 'status' => LinkPreview::FAILED, 'fetched_at' => now()->subHours(2)]);

        $page = $this->actingAs($owner)->get(route('settings.show', $group))->assertOk();
        $page->assertSeeInOrder(['TERBARU', 'TikTok · Noerhayathiee', 'vt.tiktok.com', 'Bantu like ya', 'MINGGU LALU', 'week.example.com', 'minggu lalu', \Illuminate\Support\Str::upper(now()->subDays(40)->locale('id')->translatedFormat('F Y')), 'old.example.com'])
            ->assertSee('https://p16.tiktokcdn.com/x.jpg', false)
            ->assertSee('data-jump-message', false);
        // Belum pernah dibaca: diantrekan. Gagal 2 jam lalu: belum dicoba lagi. Sudah ok: tidak dibaca ulang.
        Queue::assertPushed(FetchLinkPreviewJob::class, fn ($job) => $job->url === 'https://old.example.com/a');
        Queue::assertNotPushed(FetchLinkPreviewJob::class, fn ($job) => in_array($job->url, ['https://week.example.com/b', 'https://vt.tiktok.com/ZSgc4j1Ko/'], true));

        $this->travel(23)->hours();
        $this->assertTrue(LinkPreview::where('url', 'https://week.example.com/b')->first()->needsFetch());
    }

    public function test_links_tab_respects_closed_history_and_hidden_messages(): void
    {
        [$owner, $group] = $this->group();
        Queue::fake();
        Message::create(['group_id' => $group->id, 'sender_type' => 'user', 'sender_id' => $owner->id, 'message_type' => 'text', 'content' => 'rahasia https://secret.example.com/']);
        $friend = User::factory()->create();
        GroupMember::create(['group_id' => $group->id, 'user_id' => $friend->id, 'role_id' => Role::forKey(Role::MEMBER)->id, 'status' => 'active', 'joined_at' => now(), 'history_from_message_id' => Message::max('id')]);

        $this->actingAs($friend)->get(route('settings.show', $group))->assertDontSee('secret.example.com')->assertSee('Belum ada tautan.');
    }

    private function group(): array
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'Kelas', 'owner_id' => $owner->id, 'status' => 'active']);
        GroupMember::create(['group_id' => $group->id, 'user_id' => $owner->id, 'role_id' => Role::forKey(Role::OWNER)->id, 'status' => 'active', 'joined_at' => now()]);

        return [$owner, $group];
    }
}
