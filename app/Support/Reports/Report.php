<?php

namespace App\Support\Reports;

/**
 * An aggregate report (FR-09). Reports return counts only: never names, never individual answers.
 */
abstract class Report
{
    /**
     * Report key => class, in tab order.
     *
     * @var array<string, class-string<Report>>
     */
    public const REPORTS = [
        'concerns' => ConcernsReport::class,
        'form5' => Form5Report::class,
        'sata' => SataReport::class,
    ];

    abstract public function key(): string;

    abstract public function title(): string;

    /**
     * Which records the report counts, and which date the period filters apply to.
     */
    abstract public function description(): string;

    abstract public function build(ReportFilters $filters): ReportResult;

    public static function make(string $key): ?self
    {
        $class = self::REPORTS[$key] ?? null;

        return $class ? new $class : null;
    }

    /**
     * Bar chart data sorted from most to least common. Suppressed counts get no bar and
     * sort together after the visible counts, so the chart never reveals their order.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, int>  $counts
     * @return array{title: string, labels: list<string>, values: list<int|null>, display: list<string>}
     */
    protected function sortedChart(string $title, array $labels, array $counts): array
    {
        $position = array_flip(array_keys($labels));
        $keys = array_keys($labels);

        usort($keys, function (string $a, string $b) use ($counts, $position): int {
            $rank = fn (int $count): array => match (true) {
                $count === 0 => [2, 0],
                Suppression::isSuppressed($count) => [1, 0],
                default => [0, -$count],
            };

            return [$rank($counts[$a] ?? 0), $position[$a]] <=> [$rank($counts[$b] ?? 0), $position[$b]];
        });

        return [
            'title' => $title,
            'labels' => array_map(fn (string $key) => $labels[$key], $keys),
            'values' => array_map(fn (string $key) => Suppression::isSuppressed($counts[$key] ?? 0) ? null : ($counts[$key] ?? 0), $keys),
            'display' => array_map(fn (string $key) => Suppression::count($counts[$key] ?? 0), $keys),
        ];
    }
}
