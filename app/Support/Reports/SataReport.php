<?php

namespace App\Support\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * SATA entrance test summary: students per quotient descriptor and average quotient, per
 * college. Each student counts once, using their latest result inside the selected period.
 */
final class SataReport extends Report
{
    public function key(): string
    {
        return 'sata';
    }

    public function title(): string
    {
        return 'SATA summary';
    }

    public function description(): string
    {
        return 'Uses each student\'s latest SATA entrance test result in the selected period. The period filters use the date of examination. Averages based on fewer than '.Suppression::minimum().' students are hidden.';
    }

    public function build(ReportFilters $filters): ReportResult
    {
        $query = DB::table('entrance_test_results as etr')
            ->join('users', 'users.id', '=', 'etr.student_id')
            ->join('programs', 'programs.id', '=', 'users.program_id');
        $filters->applyToStudents($query);
        $filters->applyWindow($query, 'etr.exam_date');
        $query->whereNotExists(function (Builder $newer) use ($filters): void {
            $newer->selectRaw('1')
                ->from('entrance_test_results as newer')
                ->whereColumn('newer.student_id', 'etr.student_id')
                ->where(fn (Builder $later) => $later
                    ->whereColumn('newer.exam_date', '>', 'etr.exam_date')
                    ->orWhere(fn (Builder $sameDay) => $sameDay
                        ->whereColumn('newer.exam_date', '=', 'etr.exam_date')
                        ->whereColumn('newer.id', '>', 'etr.id')));
            $filters->applyWindow($newer, 'newer.exam_date');
        });

        $rows = $query
            ->selectRaw('programs.college_id, etr.quotient_descriptor, count(*) as students, sum(etr.quotient) as quotient_sum')
            ->groupBy('programs.college_id', 'etr.quotient_descriptor')
            ->get();

        $colleges = $filters->colleges()->pluck('code', 'id')->all();
        $descriptors = collect(config('sata.descriptors'))
            ->merge($rows->pluck('quotient_descriptor')->filter())
            ->unique()
            ->mapWithKeys(fn (string $descriptor) => [$descriptor => $descriptor])
            ->all();

        $byDescriptor = [];
        $perCollege = [];
        foreach ($rows as $row) {
            $byDescriptor[$row->quotient_descriptor][$row->college_id] = (int) $row->students;
            $perCollege[$row->college_id]['students'] = ($perCollege[$row->college_id]['students'] ?? 0) + (int) $row->students;
            $perCollege[$row->college_id]['sum'] = ($perCollege[$row->college_id]['sum'] ?? 0) + (int) $row->quotient_sum;
        }

        $averageRows = [];
        foreach ($colleges as $collegeId => $code) {
            $students = $perCollege[$collegeId]['students'] ?? 0;
            $averageRows[] = [$code, Suppression::count($students), Suppression::average($perCollege[$collegeId]['sum'] ?? 0, $students)];
        }

        return new ReportResult(
            summary: [
                ['label' => 'Students with a SATA result in this period', 'value' => Suppression::count((int) $rows->sum('students'))],
            ],
            tables: [
                ReportTable::breakdown('Students per quotient descriptor, by college', 'Quotient descriptor', $descriptors, $colleges, $byDescriptor),
                new ReportTable('Average quotient by college', ['College', 'Students', 'Average quotient'], $averageRows,
                    'An average is hidden when it is based on fewer than '.Suppression::minimum().' students.'),
            ],
        );
    }
}
