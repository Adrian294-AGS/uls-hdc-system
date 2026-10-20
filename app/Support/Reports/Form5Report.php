<?php

namespace App\Support\Reports;

use App\Enums\RecordStatus;
use App\Support\Form5;
use App\Support\StudentRecordQuery;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Form 5 (Student Cumulative Record) summary: submission status, risk self-descriptions
 * and willingness to see a counselor. Each covered student counts once, using their latest
 * record in the selected term.
 */
final class Form5Report extends Report
{
    private const COUNTED_STATUSES = [RecordStatus::Submitted->value, RecordStatus::Reviewed->value];

    public function key(): string
    {
        return 'form5';
    }

    public function title(): string
    {
        return 'Form 5 summary';
    }

    public function description(): string
    {
        return 'Uses each student\'s latest Student Cumulative Record for the selected school year and semester; the date range uses the date the record was started. Self-description and counselor counts include submitted and reviewed records only.';
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $riskItems = array_intersect_key(Form5::SELF_DESCRIPTIONS, array_flip((array) config('hdc.risk_self_descriptions', [])));
        $counted = "r.status in ('".implode("', '", self::COUNTED_STATUSES)."')";

        $latestRecordIds = DB::table('cumulative_records')
            ->selectRaw('max(id)')
            ->when($filters->schoolYear, fn ($query, $year) => $query->where('school_year', $year))
            ->when($filters->semester, fn ($query, $semester) => $query->where('semester', $semester))
            ->when($filters->from, fn ($query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters->to, fn ($query, $to) => $query->where('created_at', '<=', $to))
            ->groupBy('user_id');

        $query = DB::table('users')
            ->join('programs', 'programs.id', '=', 'users.program_id')
            ->leftJoin('cumulative_records as r', function (JoinClause $join) use ($latestRecordIds): void {
                $join->on('r.user_id', '=', 'users.id')->whereIn('r.id', $latestRecordIds);
            });
        $filters->applyToStudents($query);

        $statusExpression = "coalesce(r.status, '".StudentRecordQuery::NOT_STARTED."')";
        $rows = $query
            ->selectRaw(implode(', ', [
                'programs.college_id',
                'users.year_level',
                "{$statusExpression} as record_status",
                'count(*) as students',
                "sum(case when {$counted} and r.willing_to_see_counselor = 1 then 1 else 0 end) as willing_yes",
                "sum(case when {$counted} and r.willing_to_see_counselor = 0 then 1 else 0 end) as willing_no",
                ...array_map(fn (string $item) => "sum(case when {$counted} and r.self_description like '%\"{$item}\"%' then 1 else 0 end) as risk_{$item}", array_keys($riskItems)),
            ]))
            ->groupBy('programs.college_id', 'users.year_level')
            ->groupByRaw($statusExpression)
            ->get();

        $colleges = $filters->colleges()->pluck('code', 'id')->all();
        $yearLevels = Form5::YEAR_LEVELS + ($rows->contains(fn ($row) => $row->year_level === null) ? ['none' => 'Not set'] : []);
        $statuses = StudentRecordQuery::STATUSES;

        $statusByCollege = [];
        $statusByYear = [];
        $risk = [];
        $willing = [];
        foreach ($rows as $row) {
            $year = $row->year_level ?? 'none';
            $statusByCollege[$row->college_id][$row->record_status] = ($statusByCollege[$row->college_id][$row->record_status] ?? 0) + (int) $row->students;
            $statusByYear[$year][$row->record_status] = ($statusByYear[$year][$row->record_status] ?? 0) + (int) $row->students;
            $willing[$row->college_id]['yes'] = ($willing[$row->college_id]['yes'] ?? 0) + (int) $row->willing_yes;
            $willing[$row->college_id]['no'] = ($willing[$row->college_id]['no'] ?? 0) + (int) $row->willing_no;
            foreach (array_keys($riskItems) as $item) {
                $risk[$item][$row->college_id] = ($risk[$item][$row->college_id] ?? 0) + (int) $row->{"risk_{$item}"};
            }
        }

        $submitted = (int) $rows->whereIn('record_status', self::COUNTED_STATUSES)->sum('students');

        return new ReportResult(
            summary: [
                ['label' => 'Students covered by this report', 'value' => Suppression::count((int) $rows->sum('students'))],
                ['label' => 'Submitted or reviewed records', 'value' => Suppression::count($submitted)],
            ],
            tables: [
                ReportTable::breakdown('Submission status by college', 'College', $colleges, $statuses, $statusByCollege),
                ReportTable::breakdown('Submission status by year level', 'Year level', $yearLevels, $statuses, $statusByYear),
                ReportTable::breakdown('Self-description items that signal risk, by college', 'Self-description', $riskItems, $colleges, $risk,
                    'Submitted and reviewed records only.'),
                ReportTable::breakdown('Willing to see a counselor, by college', 'College', $colleges, ['yes' => 'Yes', 'no' => 'No'], $willing,
                    'Submitted and reviewed records only.'),
            ],
        );
    }
}
