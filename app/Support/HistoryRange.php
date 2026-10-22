<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;

/** Filter rentang waktu riwayat dari query string (?range=week|month|3month|all). */
final class HistoryRange
{
    public const OPTIONS = ['week', 'month', '3month', 'all'];

    public static function from(?string $value, string $default): string
    {
        return in_array($value, self::OPTIONS, true) ? $value : $default;
    }

    public static function apply(Builder $query, string $range, string $column = 'created_at'): Builder
    {
        $since = self::since($range);

        return $since ? $query->where($column, '>=', $since) : $query;
    }

    private static function since(string $range): ?CarbonInterface
    {
        return match ($range) {
            'week' => now()->subWeek(),
            'month' => now()->subMonth(),
            '3month' => now()->subMonths(3),
            default => null,
        };
    }
}
