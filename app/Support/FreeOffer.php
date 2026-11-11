<?php

namespace App\Support;

/**
 * Kalimat tawaran grup gratis untuk halaman publik (/, /home, /pricing), disusun dari daftar saldo awal tiap grup
 * gratis di Superadmin › Grup gratis (NormkreditPricing::freeGroupCredits). Berapa pun jumlah grupnya (0 = mati),
 * kalimatnya tetap wajar, jadi tidak ada angka atau "dua grup" yang ditulis mati di view.
 */
final class FreeOffer
{
    private const EN_COUNT = [1 => 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

    private const EN_ORDINAL = [1 => 'first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth', 'eleventh'];

    private const ID_COUNT = [1 => 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh'];

    /** @param  list<float>  $credits  saldo awal grup gratis ke-1, ke-2, … (kosong = tidak ada grup gratis) */
    public function __construct(
        public readonly array $credits,
        public readonly int $memberSlots,
    ) {
    }

    public function active(): bool
    {
        return $this->credits !== [];
    }

    public function count(): int
    {
        return count($this->credits);
    }

    /** @return list<string> */
    public function amounts(string $thousands = ','): array
    {
        return array_map(fn (float $credit) => number_format($credit, 0, $thousands === ',' ? '.' : ',', $thousands), $this->credits);
    }

    public function first(string $thousands = ','): string
    {
        return $this->amounts($thousands)[0] ?? '0';
    }

    // ── Bahasa Indonesia (halaman masuk normi.chat) ──

    /** "Grup pertamamu gratis dengan 70 Normkredit." / "Dua grup pertamamu gratis, dengan 70 dan 50 Normkredit." */
    public function idSentence(): ?string
    {
        if (! $this->active()) {
            return null;
        }
        $amounts = $this->amounts('.');
        $sentence = $this->count() === 1
            ? 'Grup pertamamu gratis dengan ' . $amounts[0] . ' Normkredit.'
            : (self::ID_COUNT[$this->count()] ?? $this->count()) . ' grup pertamamu gratis, dengan ' . self::join($amounts, 'dan') . ' Normkredit.';

        return $sentence . ($this->memberSlots > 0 ? ' Di tiap grup gratis, ' . $this->memberSlots . ' teman pertama ikut tanpa biaya.' : '');
    }

    // ── Bahasa Inggris (situs marketing) ──

    /** "Your first group" / "Your first two groups" */
    public function enGroups(): string
    {
        return $this->count() === 1 ? 'Your first group' : 'Your first ' . self::enCount($this->count()) . ' groups';
    }

    /** "one free group" / "two free groups" */
    public function enAccountGets(): string
    {
        return self::enCount($this->count()) . ' free group' . ($this->count() === 1 ? '' : 's');
    }

    /** "Your first group comes with 70 Normkredit and your second with 50" ($verb: comes/starts). */
    public function enPerGroup(string $verb = 'comes', bool $capital = true): string
    {
        $amounts = $this->amounts();
        $parts = ['first group ' . $verb . ' with ' . ($amounts[0] ?? '0') . ' Normkredit'];
        foreach (array_slice($amounts, 1, null, true) as $index => $amount) {
            $parts[] = 'your ' . self::EN_ORDINAL[$index + 1] . ' with ' . $amount;
        }

        return ($capital ? 'Your ' : 'your ') . self::join($parts, 'and');
    }

    /** "From your third group" (grup berbayar pertama). */
    public function enPaidFrom(bool $short = false): string
    {
        $next = $this->count() + 1;

        return 'From your ' . ($short ? self::enShortOrdinal($next) : (self::EN_ORDINAL[$next] ?? self::enShortOrdinal($next))) . ' group';
    }

    /** Judul kolom tabel: "1st" / "1st and 2nd" / "1st to 3rd". */
    public function enColumn(): string
    {
        return match ($this->count()) {
            1 => '1st',
            2 => '1st and 2nd',
            default => '1st to ' . self::enShortOrdinal($this->count()),
        };
    }

    /** Isi sel saldo awal: "70 / 50". */
    public function enCreditsCell(): string
    {
        return implode(' / ', $this->amounts());
    }

    private static function enCount(int $count): string
    {
        return self::EN_COUNT[$count] ?? (string) $count;
    }

    private static function enShortOrdinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$number % 10] ?? 'th');

        return $number . $suffix;
    }

    /** "a", "a dan b", "a, b, dan c" */
    private static function join(array $items, string $and): string
    {
        $items = array_values($items);
        if (count($items) <= 2) {
            return implode(' ' . $and . ' ', $items);
        }

        return implode(', ', array_slice($items, 0, -1)) . ', ' . $and . ' ' . end($items);
    }
}
