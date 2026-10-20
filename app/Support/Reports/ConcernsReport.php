<?php

namespace App\Support\Reports;

use App\Enums\IntakeStatus;
use App\Support\Form5;
use App\Support\Form8;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Psychological concerns from FINALIZED Form 8 intake interviews. Each student counts once
 * per concern, using their latest finalized interview inside the selected period.
 */
final class ConcernsReport extends Report
{
    public function key(): string
    {
        return 'concerns';
    }

    public function title(): string
    {
        return 'Psychological concerns';
    }

    public function description(): string
    {
        return 'Counts students whose latest finalized intake interview (HDC Form 8) in the selected period answered "Yes". Drafts are not counted. The period filters use the date of the intake interview.';
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $fields = Form8::yesNoFields();
        $rows = $this->latestInterviews($filters)
            ->selectRaw('programs.college_id, users.year_level, count(*) as students, '.implode(', ', array_map(
                fn (string $field) => "sum(case when ii.{$field} = 1 then 1 else 0 end) as {$field}",
                $fields,
            )))
            ->groupBy('programs.college_id', 'users.year_level')
            ->get();

        $colleges = $filters->colleges()->pluck('code', 'id')->all();
        $yearLevels = Form5::YEAR_LEVELS + ($rows->contains(fn ($row) => $row->year_level === null) ? ['none' => 'Not set'] : []);

        $total = [];
        $byCollege = [];
        $byYear = [];
        foreach ($rows as $row) {
            $year = $row->year_level ?? 'none';
            foreach ($fields as $field) {
                $count = (int) $row->{$field};
                $total[$field] = ($total[$field] ?? 0) + $count;
                $byCollege[$field][$row->college_id] = ($byCollege[$field][$row->college_id] ?? 0) + $count;
                $byYear[$field][$year] = ($byYear[$field][$year] ?? 0) + $count;
            }
        }

        return new ReportResult(
            summary: [
                ['label' => 'Students with a finalized intake interview in this period', 'value' => Suppression::count((int) $rows->sum('students'))],
            ],
            tables: [
                ReportTable::totals('Psychological concerns', 'Concern', Form8::CONCERNS, $total),
                ReportTable::breakdown('Psychological concerns by college', 'Concern', Form8::CONCERNS, $colleges, $byCollege),
                ReportTable::breakdown('Psychological concerns by year level', 'Concern', Form8::CONCERNS, $yearLevels, $byYear),
                ReportTable::totals('Relationship concerns', 'Concern', Form8::RELATIONSHIP_CONCERNS, $total),
                ReportTable::totals('Coping strategies', 'Strategy', Form8::COPING_STRATEGIES, $total),
            ],
            chart: $this->sortedChart('Psychological concerns, most to least common', Form8::CONCERNS, $total),
        );
    }

    /**
     * Each covered student's latest finalized interview in the period (alias `ii`).
     */
    private function latestInterviews(ReportFilters $filters): Builder
    {
        $query = DB::table('intake_interviews as ii')
            ->join('users', 'users.id', '=', 'ii.student_id')
            ->join('programs', 'programs.id', '=', 'users.program_id')
            ->where('ii.status', IntakeStatus::Finalized->value);

        $filters->applyToStudents($query);
        $filters->applyWindow($query, 'ii.interview_at');

        return $query->whereNotExists(function (Builder $newer) use ($filters): void {
            $newer->selectRaw('1')
                ->from('intake_interviews as newer')
                ->whereColumn('newer.student_id', 'ii.student_id')
                ->where('newer.status', IntakeStatus::Finalized->value)
                ->where(fn (Builder $later) => $later
                    ->whereColumn('newer.interview_at', '>', 'ii.interview_at')
                    ->orWhere(fn (Builder $sameTime) => $sameTime
                        ->whereColumn('newer.interview_at', '=', 'ii.interview_at')
                        ->whereColumn('newer.id', '>', 'ii.id')));
            $filters->applyWindow($newer, 'newer.interview_at');
        });
    }
}
