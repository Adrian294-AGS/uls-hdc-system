<?php

namespace App\Enums;

enum IntakeStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Finalized => 'Finalized',
        };
    }

    /**
     * Tailwind classes for the status badge.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30',
            self::Finalized => 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30',
        };
    }
}
