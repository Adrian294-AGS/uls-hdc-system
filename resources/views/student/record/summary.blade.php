{{-- Read-only summary of a Form 5 record. Set $editable to show "Edit" links per section. --}}
@php
    use App\Support\Form5;

    $editable ??= false;
    $yesNo = fn (?bool $value) => $value === null ? null : ($value ? 'Yes' : 'No');
    $option = fn (array $options, $key) => $key === null ? null : ($options[$key] ?? $key);
    $list = fn (array $options, ?array $keys) => implode(', ', Form5::labels($options, $keys)) ?: null;
@endphp

<div class="space-y-6">
    @foreach ([
        'entry' => [
            'Month of entry' => $option(Form5::MONTHS, $record->month_of_entry),
            'School year' => $record->school_year,
            'Semester' => $option(Form5::SEMESTERS, $record->semester),
            'Student type' => $option(Form5::STUDENT_TYPES, $record->student_type),
            'Photo' => $record->photo_path ? 'Uploaded' : null,
        ],
        'personal' => [
            'Name' => $record->fullName(),
            'Course preference' => $record->program ? $record->program->code.' – '.$record->program->name : null,
            'Year level' => $option(Form5::YEAR_LEVELS, $record->year_level),
            'Major' => $record->major,
            'Home address' => $record->home_address,
            'Birth date' => $record->birth_date?->format('F j, Y'),
            'Age' => $record->age(),
            'Place of birth' => $record->place_of_birth,
            'Sex' => $option(Form5::SEXES, $record->sex),
            'Civil status' => $option(Form5::CIVIL_STATUSES, $record->civil_status),
            'Spouse name' => $record->spouse_name,
            'No. of children' => $record->number_of_children,
            'Mobile no.' => $record->contact_number,
            'E-mail address' => $record->email,
            'Nationality' => $record->nationality,
            'Religion' => $record->religion,
            'Ethnicity' => $record->ethnicity,
            'Boarding house address' => $record->boarding_address,
            'Emergency contact name' => $record->emergency_contact_name,
            'Emergency contact address' => $record->emergency_contact_address,
            'Emergency contact no.' => $record->emergency_contact_number,
        ],
        'family' => [
            "Parents' status" => $list(Form5::PARENTS_STATUSES, $record->parents_status),
            'How do you see your family at present?' => $record->family_view,
            'Specific concern regarding your family' => $record->family_concern,
            'Dialect/language spoken at home' => $record->home_language,
        ],
        'health' => [
            'Height' => $record->height,
            'Weight' => $record->weight,
            'Handicap' => $record->handicap,
            'Normal eyesight' => $yesNo($record->normal_eyesight),
            'Normal hearing' => $yesNo($record->normal_hearing),
            'Persistent illness' => $record->persistent_illness,
            'Hospitalized' => $yesNo($record->hospitalized),
            'Reason for hospitalization' => $record->hospitalization_reason,
            'Surgical operation' => $yesNo($record->had_surgery),
            'Surgery details' => $record->surgery_details,
            'Allergies' => $yesNo($record->has_allergies),
            'Allergy details' => $record->allergy_details,
        ],
        'education' => [
            'Scholarships and honors received' => $record->scholarships_honors,
            'Nature of schooling' => $option(Form5::SCHOOLING_NATURES, $record->schooling_nature),
            'If interrupted, why?' => $record->interruption_reason,
            'Scholarship you wish to apply for' => $record->scholarship_to_apply,
            'Subject(s) you have difficulty with' => $record->difficult_subjects,
            'Best-liked subject(s)' => $record->best_liked_subjects,
            'Educational facilities at home' => $list(Form5::HOME_FACILITIES, $record->home_facilities),
            'Book recently finished' => $record->recent_book,
        ],
        'vocational' => [
            'Reason in choosing your course' => $list(Form5::COURSE_REASONS, $record->course_reasons),
            'Others (specify)' => $record->course_reason_other,
            'Second course alternative' => $record->second_course,
            'Occupation/job seriously considered, and why' => $record->considered_occupation,
            'Work experience as a student' => $yesNo($record->has_work_experience),
            'When' => $record->work_experience_when,
            'Specific work experience' => $record->work_experience_details,
            'What you like about it' => $record->work_experience_likes,
        ],
        'social' => [
            'Leisure-time activities/hobbies' => $record->hobbies,
        ],
        'self' => [
            'Self-description' => $list(Form5::SELF_DESCRIPTIONS, $record->self_description),
        ],
        'skills' => [
            'Skills to develop' => $list(Form5::SKILLS, $record->skills_to_develop),
            'Others (specify)' => $record->skills_other,
            'Willing to talk with a counselor' => $yesNo($record->willing_to_see_counselor),
            'Why?' => $record->counselor_willingness_reason,
        ],
    ] as $step => $fields)
        <section class="rounded-md border border-gray-200 dark:border-gray-700">
            <header class="flex items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-4 py-2">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ Form5::STEPS[$step] }}
                    @if (! $record->hasCompletedStep($step))
                        <span class="ms-2 rounded bg-amber-100 dark:bg-amber-400/10 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:text-amber-300">{{ __('Not completed') }}</span>
                    @endif
                </h4>
                @if ($editable)
                    <a href="{{ route('student.record.edit', [$record, $step]) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit') }}</a>
                @endif
            </header>

            <div class="px-4 py-3">
                @if ($step === 'family')
                    <div class="mb-4 grid gap-4 sm:grid-cols-2">
                        @foreach (Form5::PARENT_RELATIONS as $relation => $relationLabel)
                            @php
                                $parent = $record->parent($relation);
                            @endphp
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $relationLabel }}</p>
                                @if ($parent && $parent->name)
                                    <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $parent->name }}@if ($parent->age), {{ $parent->age }}@endif</p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ collect([$parent->religion, $parent->occupation, $parent->monthly_income ? 'Income: '.$parent->monthly_income : null, $parent->educational_attainment])->filter()->implode(' · ') }}</p>
                                @else
                                    <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">—</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @php
                    $rows = match ($step) {
                        'family' => ['Siblings', $record->siblings->map(fn ($s) => collect([$s->name, $option(Form5::SEXES, $s->sex), $s->age, $s->year_course_school, $s->occupation])->filter(fn ($v) => filled($v))->implode(' · '))],
                        'education' => ['Schools attended', $record->schools->map(fn ($s) => collect([$option(Form5::SCHOOL_LEVELS, $s->level), $s->school_name, $s->place, $s->inclusive_years, $s->grade_average ? 'Average: '.$s->grade_average : null])->filter()->implode(' · '))],
                        'social' => ['Club/organization memberships', $record->organizations->map(fn ($o) => collect([$o->organization, $o->activity])->filter()->implode(' · '))],
                        default => null,
                    };
                @endphp

                @if ($rows)
                    <div class="mb-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $rows[0] }}</p>
                        @forelse ($rows[1] as $line)
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $line }}</p>
                        @empty
                            <p class="mt-1 text-sm text-gray-400 dark:text-gray-500">—</p>
                        @endforelse
                    </div>
                @endif

                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                    @foreach ($fields as $label => $value)
                        @continue(blank($value) && in_array($label, ['Spouse name', 'No. of children', 'Reason for hospitalization', 'Surgery details', 'Allergy details', 'If interrupted, why?', 'Others (specify)', 'When', 'Specific work experience', 'What you like about it'], true))
                        <div @class(['sm:col-span-2' => strlen((string) $value) > 80 || strlen($label) > 40])>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-sm text-gray-900 dark:text-gray-100">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    @endforeach
</div>
