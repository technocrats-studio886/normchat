<?php

namespace App\Jobs;

use App\Events\LinkPreviewReady;
use App\Models\LinkPreview;
use App\Services\Links\LinkPreviewFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Membaca pratinjau satu tautan; bila dari pesan baru, kartunya dikirim ke grup lewat realtime. */
class FetchLinkPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $url, public ?int $groupId = null, public ?int $messageId = null)
    {
    }

    public function handle(LinkPreviewFetcher $fetcher): void
    {
        $data = $fetcher->fetch($this->url);
        $preview = LinkPreview::updateOrCreate(['url_hash' => LinkPreview::hashFor($this->url)], [
            'url' => $this->url,
            'title' => $data['title'] ?? null,
            'site_name' => $data['site_name'] ?? null,
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'status' => $data ? LinkPreview::OK : LinkPreview::FAILED,
            'fetched_at' => now(),
        ]);

        if ($data && $this->groupId && $this->messageId) {
            event(new LinkPreviewReady($this->groupId, $this->messageId, $preview->toCard()));
        }
    }
}
