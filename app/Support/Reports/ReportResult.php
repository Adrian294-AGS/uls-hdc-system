<?php

namespace App\Support\Reports;

/**
 * What a report shows: headline numbers, an optional bar chart and aggregate tables.
 * Everything is already suppressed.
 */
final class ReportResult
{
    /**
     * @param  list<array{label: string, value: string}>  $summary
     * @param  list<ReportTable>  $tables
     * @param  array{title: string, labels: list<string>, values: list<int|null>, display: list<string>}|null  $chart
     */
    public function __construct(
        public readonly array $summary,
        public readonly array $tables,
        public readonly ?array $chart = null,
    ) {}
}
