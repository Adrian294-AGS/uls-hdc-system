<?php

namespace App\Support\Reports;

/**
 * Small-group protection for every number in a report (config hdc.min_group_size).
 * Counts from 1 to min-1 are shown as "<min"; zero stays 0; averages based on fewer
 * than min students are hidden.
 */
final class Suppression
{
    public const HIDDEN_AVERAGE = 'Hidden';

    public static function minimum(): int
    {
        return max(1, (int) config('hdc.min_group_size', 5));
    }

    public static function marker(): string
    {
        return '<'.self::minimum();
    }

    public static function isSuppressed(int $count): bool
    {
        return $count > 0 && $count < self::minimum();
    }

    public static function count(int $count): string
    {
        return self::isSuppressed($count) ? self::marker() : (string) $count;
    }

    public static function average(int|float $sum, int $students, int $decimals = 1): string
    {
        if ($students === 0) {
            return '—';
        }

        return $students < self::minimum() ? self::HIDDEN_AVERAGE : number_format($sum / $students, $decimals);
    }
}
