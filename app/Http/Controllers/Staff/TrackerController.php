<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\StudentRecordQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Submission tracker (FR-04): status counts per college and year level, and who has not submitted.
 */
class TrackerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = StudentRecordQuery::fromRequest($request, [StudentRecordQuery::NOT_STARTED, 'draft']);
        $counts = $query->statusCounts();
        $statusKeys = array_keys(StudentRecordQuery::STATUSES);

        $colleges = $query->colleges()
            ->when($query->filters()['college_id'], fn ($colleges, $collegeId) => $colleges->where('id', $collegeId))
            ->map(function ($college) use ($counts, $statusKeys) {
                $collegeCounts = $counts->where('college_id', $college->id);

                $rows = $collegeCounts->groupBy(fn ($row) => $row->year_level ?? 0)
                    ->sortKeys()
                    ->map(fn ($rows, $yearLevel) => [
                        'year_level' => $yearLevel ?: null,
                        'counts' => $this->tally($rows, $statusKeys),
                    ])
                    ->values();

                return [
                    'college' => $college,
                    'rows' => $rows,
                    'totals' => $this->tally($collegeCounts, $statusKeys),
                ];
            })
            ->values();

        return view('staff.tracker', [
            'query' => $query,
            'colleges' => $colleges,
            'grandTotals' => $this->tally($counts, $statusKeys),
            'withoutSata' => $query->withoutSataCount(),
            'students' => $query->notSubmitted()
                ->with(['program.college', 'latestCumulativeRecord'])
                ->withExists('entranceTestResults as has_sata')
                ->withIntakeSummary()
                ->orderBy('users.last_name')
                ->orderBy('users.first_name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    /**
     * @param  iterable<object{record_status: string, total: int}>  $rows
     * @param  list<string>  $statusKeys
     * @return array<string, int>
     */
    private function tally(iterable $rows, array $statusKeys): array
    {
        $tally = array_fill_keys($statusKeys, 0);

        foreach ($rows as $row) {
            $tally[$row->record_status] = ($tally[$row->record_status] ?? 0) + $row->total;
        }

        return $tally + ['total' => array_sum($tally)];
    }
}
