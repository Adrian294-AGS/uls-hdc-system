<?php

namespace App\Support\Reports;

/**
 * One aggregate table. Every cell is already a display string (counts suppressed),
 * so pages, CSV and print views can never show a raw small count.
 */
final class ReportTable
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public readonly string $title,
        public readonly array $headers,
        public readonly array $rows,
        public readonly ?string $note = null,
    ) {}

    /**
     * Items (rows) x groups (columns) of counts.
     *
     * @param  array<string, string>  $items  key => row label
     * @param  array<string|int, string>  $groups  key => column label
     * @param  array<string, array<string|int, int>>  $counts  [item][group] => count
     */
    public static function breakdown(string $title, string $itemHeader, array $items, array $groups, array $counts, ?string $note = null): self
    {
        $rows = [];
        foreach ($items as $item => $label) {
            $row = [$label];
            foreach (array_keys($groups) as $group) {
                $row[] = Suppression::count($counts[$item][$group] ?? 0);
            }
            $rows[] = $row;
        }

        return new self($title, [$itemHeader, ...array_values($groups)], $rows, $note);
    }

    /**
     * A two-column "item | students" table.
     *
     * @param  array<string, string>  $items
     * @param  array<string, int>  $counts
     */
    public static function totals(string $title, string $itemHeader, array $items, array $counts, ?string $note = null): self
    {
        $rows = [];
        foreach ($items as $item => $label) {
            $rows[] = [$label, Suppression::count($counts[$item] ?? 0)];
        }

        return new self($title, [$itemHeader, 'Students'], $rows, $note);
    }
}
