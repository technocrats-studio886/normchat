<?php

namespace App\Services\Exports;

use App\Models\Group;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\PollService;
use App\Services\Files\StoredFiles;
use Carbon\CarbonImmutable;

/**
 * Menyiapkan pesan grup menjadi ExportDocument: pengirim (nama, foto, warna), kutipan balasan,
 * versi sebelum diedit, penanda pesan terhapus, polling, lampiran, dan rangkaian pesan beruntun.
 */
class ChatExportBuilder
{
    /** Warna nama anggota, sama dengan --nc-person-0..7 mode terang di app.css. */
    public const PERSON_COLORS = ['#1d7a5c', '#9a4b8c', '#2f62b0', '#b4471f', '#6655b0', '#0e6f8a', '#85650a', '#a3365a'];

    public const AI_COLOR = '#A1342A';

    /** Pesan beruntun dari pengirim yang sama digabung bila berselang paling lama sekian menit. */
    private const RUN_GAP_MINUTES = 10;

    /** @var array<int, ?string> foto profil per user (data URI), dibaca sekali */
    private array $avatars = [];

    public function __construct(private readonly PollService $polls, private readonly StoredFiles $files)
    {
    }

    public function build(Group $group, ExportOptions $options, User $exporter): ExportDocument
    {
        $messages = $options->query((int) $group->id)
            ->with(['sender:id,name,avatar_url', 'replyToMessage.sender:id,name', 'versions', 'deleter:id,name'])
            ->get()
            ->reject(fn (Message $message) => ! $options->polls && $this->polls->parse($message->content) !== null)
            ->values();
        $polls = $options->polls ? $this->polls->summariesFor($messages) : [];

        $entries = $messages->map(fn (Message $message) => $this->entry($message, $exporter, $options, $polls[(int) $message->id] ?? null))->all();
        $this->markRuns($entries, $messages->all());

        return new ExportDocument(
            (string) $group->name,
            (string) $exporter->name,
            CarbonImmutable::now($options->timezone),
            $options->rangeLabel($entries[0]->sentAt ?? null, $entries === [] ? null : end($entries)->sentAt),
            $entries,
        );
    }

    /** Jumlah pesan yang akan masuk ekspor, untuk lembar filter. */
    public function count(Group $group, ExportOptions $options): int
    {
        return $options->query((int) $group->id)->count();
    }

    private function entry(Message $message, User $exporter, ExportOptions $options, ?array $poll): ExportEntry
    {
        $isAi = $message->sender_type === 'ai';
        $zone = $options->timezone;
        $local = fn ($time) => CarbonImmutable::parse($time)->setTimezone($zone)->locale('id');
        $versions = $message->versions->sortBy('version_number')->values();
        $reply = $message->replyToMessage;

        return new ExportEntry(
            senderName: $isAi ? 'Normi AI' : ($message->sender?->name ?? 'Anggota'),
            isMine: ! $isAi && (int) $message->sender_id === (int) $exporter->id,
            isAi: $isAi,
            color: $isAi ? self::AI_COLOR : self::PERSON_COLORS[abs((int) $message->sender_id) % 8],
            avatar: $isAi ? null : $this->avatarOf($message->sender),
            sentAt: $local($message->created_at),
            content: trim((string) $message->content),
            quote: $reply ? [
                'name' => $reply->sender_type === 'ai' ? 'Normi AI' : ($reply->sender?->name ?? 'Anggota'),
                'text' => $reply->trashed() ? 'Pesan ini dihapus' : (trim((string) ($message->reply_quote_text ?: $reply->content)) ?: 'Lampiran'),
            ] : null,
            forwardedFrom: $message->forwarded_from_name,
            deleted: $message->trashed() ? [
                'by' => $message->deleter?->name ?? 'Anggota',
                'at' => $local($message->deleted_at),
            ] : null,
            versions: $versions->map(fn ($version) => ['content' => (string) $version->content_snapshot, 'until' => $local($version->edited_at)])->all(),
            editedAt: $versions->isEmpty() ? null : $local($versions->last()->edited_at),
            poll: $poll,
            attachment: $this->attachmentOf($message, $options->images),
        );
    }

    /** @param list<ExportEntry> $entries */
    private function markRuns(array $entries, array $messages): void
    {
        $key = fn (Message $message) => $message->sender_type === 'ai' ? 'ai' : 'user:' . $message->sender_id;
        foreach ($entries as $index => $entry) {
            $previous = $index > 0 ? $messages[$index - 1] : null;
            $sameRun = $previous !== null
                && $key($previous) === $key($messages[$index])
                && $entries[$index - 1]->sentAt->isSameDay($entry->sentAt)
                && $entries[$index - 1]->sentAt->diffInMinutes($entry->sentAt) <= self::RUN_GAP_MINUTES;
            $entry->runStart = ! $sameRun;
            if ($sameRun) {
                $entries[$index - 1]->runEnd = false;
            }
        }
    }

    /** Foto profil yang tersimpan di Normi (route avatars.show); foto dari luar tidak diunduh. */
    private function avatarOf(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        if (array_key_exists($user->id, $this->avatars)) {
            return $this->avatars[$user->id];
        }

        $avatar = null;
        if (preg_match('#/avatars/([A-Za-z0-9]{20,64}\.(jpg|jpeg|png|webp))$#', (string) $user->avatar_url, $match)) {
            $binary = $this->files->get('normchat_avatars', $match[1], 2 * 1024 * 1024);
            $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][strtolower($match[2])];
            $avatar = $binary ? 'data:' . $mime . ';base64,' . base64_encode($binary) : null;
        }

        return $this->avatars[$user->id] = $avatar;
    }

    private function attachmentOf(Message $message, bool $withImages): ?array
    {
        if (! $message->attachment_path || $message->trashed()) {
            return null;
        }

        $kind = $this->kindOf($message);

        return [
            'kind' => $kind,
            'name' => (string) ($message->attachment_original_name ?: basename((string) $message->attachment_path)),
            'mime' => (string) ($message->attachment_mime ?: 'application/octet-stream'),
            'image' => $kind === 'image' && $withImages && $message->attachment_disk
                ? ($this->files->get($message->attachment_disk, (string) $message->attachment_path, 8 * 1024 * 1024) ?: null)
                : null,
        ];
    }

    private function kindOf(Message $message): string
    {
        $type = strtolower((string) $message->message_type);
        $mime = strtolower((string) $message->attachment_mime);
        $name = strtolower((string) $message->attachment_original_name);

        return match (true) {
            $type === 'canvas' => 'canvas',
            $type === 'image' || str_starts_with($mime, 'image/') => 'image',
            $type === 'video' || str_starts_with($mime, 'video/') && $mime !== 'video/webm' => 'video',
            $type === 'voice' || str_starts_with($mime, 'audio/') || $mime === 'video/webm' => 'voice',
            (bool) preg_match('/\.(jpg|jpeg|png|webp|gif|bmp)$/i', $name) => 'image',
            default => 'file',
        };
    }
}
