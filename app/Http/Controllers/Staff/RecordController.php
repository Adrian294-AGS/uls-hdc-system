<?php

namespace App\Http\Controllers\Staff;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\CumulativeRecord;
use App\Support\StudentRecordQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Form 5 records for counselors (assigned colleges) and the director (all colleges).
 */
class RecordController extends Controller
{
    public function index(Request $request): View
    {
        $query = StudentRecordQuery::fromRequest($request);

        return view('staff.records.index', [
            'query' => $query,
            'students' => $query->students()
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
     * Full read-only record. Every view is written to the access log.
     */
    public function show(Request $request, CumulativeRecord $record): View
    {
        Gate::authorize('view', $record);

        $this->log($request, $record, AccessLog::ACTION_VIEW);

        $record->load(['student.program.college', 'program.college', 'parents', 'siblings', 'schools', 'organizations', 'reviewer']);
        $sata = $record->student->latestEntranceTestResult()->with(['encoder', 'updater'])->first();

        if ($sata) {
            EntranceTestResultController::logView($request, $sata);
        }

        return view('staff.records.show', [
            'record' => $record,
            'sata' => $sata,
            'sataCount' => $record->student->entranceTestResults()->count(),
            'intakes' => $record->student->intakeInterviews()->with('counselor')->latest('id')->get(),
        ]);
    }

    public function review(Request $request, CumulativeRecord $record): RedirectResponse
    {
        Gate::authorize('review', $record);

        $record->status = RecordStatus::Reviewed;
        $record->reviewed_by = $request->user()->id;
        $record->reviewed_at = now();
        $record->save();

        $this->log($request, $record, AccessLog::ACTION_REVIEW);

        return redirect()
            ->route('staff.records.show', $record)
            ->with('status', 'Marked as reviewed. The student can no longer edit this record.');
    }

    public function photo(CumulativeRecord $record): StreamedResponse
    {
        Gate::authorize('view', $record);

        abort_unless($record->photo_path && Storage::exists($record->photo_path), 404);

        return Storage::response($record->photo_path);
    }

    private function log(Request $request, CumulativeRecord $record, string $action): void
    {
        AccessLog::create([
            'user_id' => $request->user()->id,
            'cumulative_record_id' => $record->id,
            'action' => $action,
            'ip' => $request->ip(),
        ]);
    }
}
