<?php

namespace App\Http\Controllers\Student;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveRecordStepRequest;
use App\Http\Requests\SubmitRecordRequest;
use App\Models\College;
use App\Models\CumulativeRecord;
use App\Models\User;
use App\Notifications\RecordSubmitted;
use App\Support\Form5;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Form 5 (Student Cumulative Record) wizard for students.
 */
class CumulativeRecordController extends Controller
{
    /**
     * Send the student to the right place: start, continue, or view their latest record.
     */
    public function index(Request $request): RedirectResponse
    {
        $record = $request->user()->latestCumulativeRecord;

        if (! $record) {
            return redirect()->route('student.record.create');
        }

        if ($record->isEditableByStudent() && ! $record->submitted_at) {
            return redirect()->route('student.record.edit', [$record, $record->nextIncompleteStep()]);
        }

        return redirect()->route('student.record.show', $record);
    }

    /**
     * First step (entry information) before the record exists.
     */
    public function create(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', CumulativeRecord::class);

        if ($record = $request->user()->latestCumulativeRecord) {
            return redirect()->route('student.record.edit', [$record, 'entry']);
        }

        $now = now();

        return view('student.record.wizard', [
            'record' => new CumulativeRecord([
                'school_year' => Form5::schoolYearFor($now),
                'month_of_entry' => $now->month,
            ]),
            'step' => 'entry',
        ]);
    }

    /**
     * Save the entry step and create the draft record, pre-filled from the student's account.
     */
    public function store(SaveRecordStepRequest $request): RedirectResponse
    {
        $user = $request->user();

        $record = new CumulativeRecord([
            ...$request->recordAttributes(),
            'last_name' => $user->last_name,
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'program_id' => $user->program_id,
            'year_level' => $user->year_level,
            'email' => $user->email,
        ]);
        $record->user_id = $user->id;
        $this->storePhoto($request, $record);
        $record->markStepCompleted('entry');
        $record->save();

        return redirect()
            ->route('student.record.edit', [$record, Form5::nextStep('entry')])
            ->with('status', 'Draft saved.');
    }

    public function show(CumulativeRecord $record): View
    {
        Gate::authorize('view', $record);

        return view('student.record.show', ['record' => $this->loadForDisplay($record)]);
    }

    public function edit(Request $request, CumulativeRecord $record, string $step): View|RedirectResponse
    {
        Gate::authorize('view', $record);

        if ($request->user()->cannot('update', $record)) {
            return redirect()->route('student.record.show', $record);
        }

        return view('student.record.wizard', [
            'record' => $this->loadForDisplay($record),
            'step' => $step,
            'colleges' => $step === 'personal'
                ? College::with(['programs' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get()
                : collect(),
        ]);
    }

    /**
     * "Save and continue": save this step as a draft and move to the next one.
     */
    public function update(SaveRecordStepRequest $request, CumulativeRecord $record, string $step): RedirectResponse
    {
        abort_if($step === 'review', 404);

        DB::transaction(function () use ($request, $record, $step): void {
            $record->fill($request->recordAttributes());

            match ($step) {
                'entry' => $this->storePhoto($request, $record),
                'family' => $this->saveFamilyRows($request, $record),
                'education' => $this->replaceRows($record->schools(), $request->validated('schools', [])),
                'social' => $this->replaceRows($record->organizations(), $request->validated('organizations', [])),
                default => null,
            };

            $record->markStepCompleted($step);
            $record->save();
        });

        return redirect()
            ->route('student.record.edit', [$record, Form5::nextStep($step)])
            ->with('status', 'Draft saved.');
    }

    /**
     * Final submission with data privacy consent.
     */
    public function submit(SubmitRecordRequest $request, CumulativeRecord $record): RedirectResponse
    {
        $isFirstSubmission = $record->status === RecordStatus::Draft;

        $record->consent_given = true;
        $record->consented_at = now();
        $record->consent_name = $request->validated('consent_name');
        $record->status = RecordStatus::Submitted;
        $record->submitted_at = now();
        $record->save();

        if ($isFirstSubmission) {
            $student = $request->user()->loadMissing('program');

            // A notification problem must never undo the student's submission.
            try {
                Notification::send(
                    User::query()->staffResponsibleFor($student->program?->college_id)->get(),
                    new RecordSubmitted($record->setRelation('student', $student)),
                );
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return redirect()
            ->route('student.record.show', $record)
            ->with('status', 'Your Student Cumulative Record has been submitted. Thank you!');
    }

    /**
     * Serve the 2x2 photo from private storage.
     */
    public function photo(CumulativeRecord $record): StreamedResponse
    {
        Gate::authorize('view', $record);

        abort_unless($record->photo_path && Storage::exists($record->photo_path), 404);

        return Storage::response($record->photo_path);
    }

    private function loadForDisplay(CumulativeRecord $record): CumulativeRecord
    {
        return $record->load(['parents', 'siblings', 'schools', 'organizations', 'program.college']);
    }

    /**
     * Store a new photo first and only then remove the old one, so a failed upload never loses the
     * photo the student already had.
     *
     * @throws ValidationException
     */
    private function storePhoto(SaveRecordStepRequest $request, CumulativeRecord $record): void
    {
        $newPath = null;

        if ($request->hasFile('photo')) {
            try {
                $newPath = $request->file('photo')->store('record-photos/'.$request->user()->id);
            } catch (Throwable $exception) {
                report($exception);
            }

            if (! $newPath) {
                throw ValidationException::withMessages([
                    'photo' => 'Your photo could not be uploaded. Please try again, or continue without a photo for now.',
                ]);
            }
        }

        if (($newPath || $request->boolean('remove_photo')) && $record->photo_path) {
            Storage::delete($record->photo_path);
            $record->photo_path = null;
        }

        if ($newPath) {
            $record->photo_path = $newPath;
        }
    }

    private function saveFamilyRows(SaveRecordStepRequest $request, CumulativeRecord $record): void
    {
        foreach (array_keys(Form5::PARENT_RELATIONS) as $relation) {
            $record->parents()->updateOrCreate(
                ['relation' => $relation],
                $request->validated("parents.$relation", []),
            );
        }

        $this->replaceRows($record->siblings(), $request->validated('siblings', []));
    }

    /**
     * Replace all rows of a repeatable section, keeping the order the student entered.
     *
     * @param  HasMany<Model, CumulativeRecord>  $relation
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function replaceRows($relation, array $rows): void
    {
        $relation->delete();

        foreach (array_values($rows) as $position => $row) {
            $relation->create([...$row, 'position' => $position]);
        }
    }
}
