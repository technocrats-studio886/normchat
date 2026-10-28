<?php

namespace App\Services\Exports;

use App\Models\Message;
use App\Models\User;
use App\Support\UserTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filter unduh riwayat chat: format, rentang waktu (dalam zona waktu user), pengirim, dan isi
 * tambahan (gambar, polling). Disimpan di exports.filters agar file bisa dibuat di antrean.
 */
final class ExportOptions
{
    public const RANGES = ['7d', '30d', 'all', 'custom'];

    /**
     * @param  list<string>  $senders  'ai' dan/atau id user; kosong = semua pengirim
     */
    public function __construct(
        public readonly string $format,
        public readonly string $range,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly array $senders,
        public readonly bool $images,
        public readonly bool $polls,
        public readonly string $timezone,
    ) {
    }

    public static function fromRequest(Request $request, User $user): self
    {
        $data = $request->validate([
            'file_type' => ['required', 'in:pdf,docx'],
            'range' => ['sometimes', 'in:' . implode(',', self::RANGES)],
            'from' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:range,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'senders' => ['sometimes', 'array', 'max:200'],
            'senders.*' => ['string', 'regex:/^(ai|\d+)$/'],
            'images' => ['sometimes', 'boolean'],
            'polls' => ['sometimes', 'boolean'],
        ]);

        return new self(
            $data['file_type'],
            $data['range'] ?? 'all',
            $data['from'] ?? null,
            $data['to'] ?? null,
            array_values(array_unique($data['senders'] ?? [])),
            (bool) ($data['images'] ?? true),
            (bool) ($data['polls'] ?? true),
            UserTime::zone($user),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['format'] ?? 'pdf'),
            (string) ($data['range'] ?? 'all'),
            $data['from'] ?? null,
            $data['to'] ?? null,
            (array) ($data['senders'] ?? []),
            (bool) ($data['images'] ?? true),
            (bool) ($data['polls'] ?? true),
            (string) ($data['timezone'] ?? UserTime::DEFAULT_ZONE),
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} batas waktu dalam UTC */
    public function bounds(): array
    {
        $now = CarbonImmutable::now($this->timezone);

        return match ($this->range) {
            '7d' => [$now->subDays(6)->startOfDay()->utc(), null],
            '30d' => [$now->subDays(29)->startOfDay()->utc(), null],
            'custom' => [
                CarbonImmutable::parse((string) $this->from, $this->timezone)->startOfDay()->utc(),
                CarbonImmutable::parse((string) $this->to, $this->timezone)->endOfDay()->utc(),
            ],
            default => [null, null],
        };
    }

    /** Pesan grup yang masuk ekspor: termasuk yang dihapus (diberi penanda), tanpa pemberitahuan pribadi Normi. */
    public function query(int $groupId): Builder
    {
        [$from, $to] = $this->bounds();
        $users = array_map('intval', array_filter($this->senders, fn ($sender) => $sender !== 'ai'));

        return Message::withTrashed()
            ->where('group_id', $groupId)
            ->whereNull('visible_to_user_id')
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->when($this->senders !== [], fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->when(in_array('ai', $this->senders, true), fn (Builder $ai) => $ai->orWhere('sender_type', 'ai'))
                ->when($users !== [], fn (Builder $user) => $user->orWhere(fn (Builder $u) => $u->where('sender_type', 'user')->whereIn('sender_id', $users)))))
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /** Keterangan rentang di bawah judul, mis. "1–27 Oktober 2026". */
    public function rangeLabel(?CarbonImmutable $first, ?CarbonImmutable $last): string
    {
        if ($first === null || $last === null) {
            return 'Belum ada pesan';
        }
        $first = $first->setTimezone($this->timezone)->locale('id');
        $last = $last->setTimezone($this->timezone)->locale('id');

        return $first->isSameDay($last)
            ? $first->translatedFormat('j F Y')
            : $first->translatedFormat($first->isSameYear($last) ? 'j F' : 'j F Y') . ' – ' . $last->translatedFormat('j F Y');
    }
}
