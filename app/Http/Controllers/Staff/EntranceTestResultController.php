<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\EntranceTestResultRequest;
use App\Models\AccessLog;
use App\Models\EntranceTestResult;
use App\Models\User;
use App\Support\StudentRecordQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * SATA entrance test results (staff only; students never reach these routes).
 * Every page that displays a result writes a "view_sata" access log.
 */
class EntranceTestResultController extends Controller
{
    /**
     * Filter keys carried through the encoding flow so "encode next" follows the same list.
     *
     * @var list<string>
     */
    public const LIST_FILTERS = ['q', 'college_id', 'program_id', 'year_level', 'status', 'sata', 'intake'];

    /**
     * A student's SATA history (latest first).
     */
    public function index(Request $request, User $student): View
    {
        Gate::authorize('create', [EntranceTestResult::class, $student]);

        $results = $student->entranceTestResults()
            ->with(['encoder', 'updater'])
            ->orderByDesc('exam_date')
            ->orderByDesc('id')
            ->get();

        $results->each(fn (EntranceTestResult $result) => self::logView($request, $result));

        return view('staff.sata.index', [
            'student' => $student->load('program.college', 'latestCumulativeRecord'),
            'results' => $results,
        ]);
    }

    public function create(Request $request, User $student): View
    {
        Gate::authorize('create', [EntranceTestResult::class, $student]);

        return view('staff.sata.form', [
            'student' => $student->load('program.college'),
            'result' => new EntranceTestResult,
            'listFilters' => $request->only(self::LIST_FILTERS),
            'hasHistory' => $student->entranceTestResults()->exists(),
        ]);
    }

    public function store(EntranceTestResultRequest $request, User $student): RedirectResponse
    {
        $result = new EntranceTestResult($request->validated());
        $result->student_id = $student->id;
        $result->encoded_by = $request->user()->id;
        $result->updated_by = $request->user()->id;
        $result->save();

        if ($request->boolean('next')) {
            return $this->redirectToNextStudent($request, $student);
        }

        return redirect()
            ->route('staff.sata.index', $student)
            ->with('status', "SATA result saved for {$student->name}.");
    }

    public function show(Request $request, EntranceTestResult $result): View
    {
        Gate::authorize('view', $result);
        self::logView($request, $result);

        return view('staff.sata.show', [
            'result' => $result->load(['student.program.college', 'encoder', 'updater']),
            'isCurrent' => $result->student->latestEntranceTestResult?->is($result) ?? false,
        ]);
    }

    public function edit(Request $request, EntranceTestResult $result): View
    {
        Gate::authorize('update', $result);
        self::logView($request, $result);

        return view('staff.sata.form', [
            'student' => $result->student->load('program.college'),
            'result' => $result,
            'listFilters' => [],
            'hasHistory' => true,
        ]);
    }

    public function update(EntranceTestResultRequest $request, EntranceTestResult $result): RedirectResponse
    {
        $result->fill($request->validated());
        $result->updated_by = $request->user()->id;
        $result->save();

        return redirect()
            ->route('staff.sata.show', $result)
            ->with('status', 'SATA result updated.');
    }

    public static function logView(Request $request, EntranceTestResult $result): void
    {
        AccessLog::create([
            'user_id' => $request->user()->id,
            'entrance_test_result_id' => $result->id,
            'action' => AccessLog::ACTION_VIEW_SATA,
            'ip' => $request->ip(),
        ]);
    }

    /**
     * "Save and encode next student": the next student in the same filtered list who has no result yet.
     */
    private function redirectToNextStudent(Request $request, User $current): RedirectResponse
    {
        $filters = array_filter($request->only(self::LIST_FILTERS), 'filled');

        $next = StudentRecordQuery::fromRequest($request)
            ->students()
            ->whereDoesntHave('entranceTestResults')
            ->whereKeyNot($current->id)
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->orderBy('users.id')
            ->first();

        if (! $next) {
            return redirect()
                ->route('staff.records.index', $filters)
                ->with('status', "SATA result saved for {$current->name}. Everyone in this list now has a SATA result.");
        }

        return redirect()
            ->route('staff.sata.create', [$next, ...$filters])
            ->with('status', "SATA result saved for {$current->name}. Now encoding {$next->name}.");
    }
}
