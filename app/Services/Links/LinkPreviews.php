<?php

namespace App\Services\Links;

use App\Jobs\FetchLinkPreviewJob;
use App\Models\LinkPreview;
use App\Models\Message;
use Illuminate\Support\Collection;

/** Menyiapkan baris pratinjau dan mengantrekan pembacaan tautan yang belum/perlu dibaca ulang. */
class LinkPreviews
{
    /** Tautan pertama per pesan yang diberi kartu di bubble; tab Tautan memakai semuanya. */
    public const PER_MESSAGE = 3;

    /** @param list<string> $urls */
    public function ensure(array $urls, ?Message $message = null): void
    {
        $existing = LinkPreview::forUrls($urls);
        foreach ($urls as $url) {
            $preview = $existing->get($url) ?? LinkPreview::firstOrCreate(['url_hash' => LinkPreview::hashFor($url)], ['url' => $url, 'status' => LinkPreview::PENDING]);
            if ($preview->wasRecentlyCreated || $preview->needsFetch()) {
                $preview->touch();
                FetchLinkPreviewJob::dispatch($url, $message ? (int) $message->group_id : null, $message ? (int) $message->id : null);
            }
        }
    }

    /** Pesan baru dari anggota: baca pratinjau tautan-tautannya di antrean. */
    public function forNewMessage(Message $message): void
    {
        if ($message->sender_type !== 'user') {
            return;
        }
        $urls = LinkExtractor::urls($message->content, self::PER_MESSAGE);
        if ($urls !== []) {
            $this->ensure($urls, $message);
        }
    }

    /** Kartu untuk tautan pertama pesan (bubble chat); null bila belum siap. */
    public function cardFor(Message $message): ?array
    {
        if ($message->sender_type !== 'user' || $message->trashed()) {
            return null;
        }
        $url = LinkExtractor::urls($message->content, 1)[0] ?? null;

        return $url ? LinkPreview::forUrls([$url])->get($url)?->toCard() : null;
    }

    /** @param Collection<int, Message> $messages */
    public function cardsFor(Collection $messages): array
    {
        $first = $messages->filter(fn (Message $m) => $m->sender_type === 'user' && ! $m->trashed())
            ->mapWithKeys(fn (Message $m) => [$m->id => LinkExtractor::urls($m->content, 1)[0] ?? null])
            ->filter();
        $previews = LinkPreview::forUrls($first->values()->unique()->all());

        return $first->map(fn (string $url) => $previews->get($url)?->toCard())->filter()->all();
    }
}
