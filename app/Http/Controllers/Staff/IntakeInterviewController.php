<?php

namespace App\Http\Controllers\Staff;

use App\Enums\IntakeStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IntakeInterviewRequest;
use App\Models\AccessLog;
use App\Models\IntakeInterview;
use App\Models\User;
use App\Notifications\IntakeNeedsFollowUp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * HDC Form 8 intake interviews (staff only; students never reach these routes).
 * Every page that displays an interview writes a "view_intake" access log, and every
 * save of an already-finalized interview (director only) writes "edit_intake".
 */
class IntakeInterviewController extends Controller
{
    public function create(Request $request, User $student): View
    {
        Gate::authorize('create', [IntakeInterview::class, $student]);

        return view('staff.intake.form', [
            'student' => $student->load('program.college', 'latestCumulativeRecord'),
            'interview' => $this->prefilledFor($student),
            'interviewer' => $request->user(),
        ]);
    }

    public function store(IntakeInterviewRequest $request, User $student): RedirectResponse
    {
        $interview = new IntakeInterview($request->interviewData());
        $interview->student_id = $student->id;
        $interview->counselor_id = $request->user()->id;
        $this->applyStatus($interview, $request);
        $interview->save();

        $this->notifyDirectorIfNewlyFlagged($interview, wasFlagged: false, actor: $request->user());

        return redirect()
            ->route('staff.intake.show', $interview)
            ->with('status', $interview->isFinalized() ? __('Intake interview finalized.') : __('Draft saved.'));
    }

    public function show(Request $request, IntakeInterview $interview): View
    {
        Gate::authorize('view', $interview);
        $this->log($request, $interview, AccessLog::ACTION_VIEW_INTAKE);

        return view('staff.intake.show', [
            'interview' => $interview->load(['student.program.college', 'student.latestCumulativeRecord', 'counselor']),
        ]);
    }

    public function edit(Request $request, IntakeInterview $interview): View
    {
        Gate::authorize('update', $interview);
        $this->log($request, $interview, AccessLog::ACTION_VIEW_INTAKE);

        return view('staff.intake.form', [
            'student' => $interview->student->load('program.college', 'latestCumulativeRecord'),
            'interview' => $interview,
            'interviewer' => $interview->counselor,
        ]);
    }

    public function update(IntakeInterviewRequest $request, IntakeInterview $interview): RedirectResponse
    {
        $wasFinalized = $interview->isFinalized();
        $wasFlagged = $interview->needsFollowUp();

        $interview->fill($request->interviewData());
        $this->applyStatus($interview, $request);
        $interview->save();

        if ($wasFinalized) {
            $this->log($request, $interview, AccessLog::ACTION_EDIT_INTAKE);
        }

        $this->notifyDirectorIfNewlyFlagged($interview, $wasFlagged, $request->user());

        return redirect()
            ->route('staff.intake.show', $interview)
            ->with('status', match (true) {
                $wasFinalized => __('Changes saved. This edit was recorded in the access log.'),
                $interview->isFinalized() => __('Intake interview finalized.'),
                default => __('Draft saved.'),
            });
    }

    /**
     * Finalize when asked to; a finalized interview never goes back to draft.
     */
    private function applyStatus(IntakeInterview $interview, IntakeInterviewRequest $request): void
    {
        if ($request->finalizes() && ! $interview->isFinalized()) {
            $interview->status = IntakeStatus::Finalized;
            $interview->finalized_at = now();
        }
    }

    /**
     * A new interview for the student, with section I pre-filled from their latest Form 5.
     */
    private function prefilledFor(User $student): IntakeInterview
    {
        $record = $student->latestCumulativeRecord;
        $program = $record?->program ?? $student->program;
        $nationality = trim((string) $record?->nationality);
        $isFilipino = $nationality === '' || str_contains(mb_strtolower($nationality), 'filipin');

        return new IntakeInterview([
            'date_of_entry' => now(),
            'name' => $record?->fullName() ?: $student->name,
            'course' => $program?->code,
            'home_address' => $record?->home_address,
            'age' => $record?->age(),
            'sex' => $record?->sex,
            'birth_date' => $record?->birth_date,
            'place_of_birth' => $record?->place_of_birth,
            'civil_status' => $record?->civil_status,
            'spouse_name' => $record?->spouse_name,
            'number_of_children' => $record?->number_of_children,
            'citizenship' => $isFilipino ? 'filipino' : 'other',
            'citizenship_other' => $isFilipino ? null : $nationality,
            'religion' => $record?->religion,
            'contact_number' => $record?->contact_number,
            'boarding_address' => $record?->boarding_address,
            'emergency_contact_name' => $record?->emergency_contact_name,
            'emergency_contact_address' => $record?->emergency_contact_address,
            'emergency_contact_number' => $record?->emergency_contact_number,
        ]);
    }

    /**
     * Tell the director(s) when a save turns on the "Needs follow-up" flag. The notification
     * carries only the student's name and a link.
     */
    private function notifyDirectorIfNewlyFlagged(IntakeInterview $interview, bool $wasFlagged, User $actor): void
    {
        if ($wasFlagged || ! $interview->needsFollowUp()) {
            return;
        }

        $directors = User::query()
            ->where('role', Role::Director)
            ->where('is_active', true)
            ->whereKeyNot($actor->id)
            ->get();

        Notification::send($directors, new IntakeNeedsFollowUp($interview));
    }

    private function log(Request $request, IntakeInterview $interview, string $action): void
    {
        AccessLog::create([
            'user_id' => $request->user()->id,
            'intake_interview_id' => $interview->id,
            'action' => $action,
            'ip' => $request->ip(),
        ]);
    }
}
