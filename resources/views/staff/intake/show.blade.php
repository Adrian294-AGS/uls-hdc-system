@php
    use App\Support\Form5;
    use App\Support\Form8;

    $student = $interview->student;
    $record = $student->latestCumulativeRecord;
    $sectionHeading = 'text-base font-semibold uppercase tracking-wide text-gray-900 dark:text-gray-100';
    $answer = fn (?bool $value) => match ($value) {
        true => __('Yes'),
        false => __('No'),
        null => '—',
    };
    $citizenship = $interview->citizenship === 'other'
        ? ($interview->citizenship_other ?: Form8::CITIZENSHIPS['other'])
        : (Form8::CITIZENSHIPS[$interview->citizenship] ?? null);
    $personal = [
        ['Name', $interview->name, 'sm:col-span-2'],
        ['Nickname', $interview->nickname, ''],
        ['Course', $interview->course, ''],
        ['Complete home address', $interview->home_address, 'sm:col-span-4'],
        ['Age', $interview->age, ''],
        ['Sex', Form5::SEXES[$interview->sex] ?? null, ''],
        ['Birthdate', $interview->birth_date?->format('F j, Y'), ''],
        ['Place of birth', $interview->place_of_birth, ''],
        ['Civil status', Form5::CIVIL_STATUSES[$interview->civil_status] ?? null, ''],
        ['If married, name of spouse', $interview->spouse_name, ''],
        ['No. of children', $interview->number_of_children, ''],
        ['Citizenship', $citizenship, ''],
        ['Religion', $interview->religion, ''],
        ['Contact no.', $interview->contact_number, ''],
        ['Boarding house address', $interview->boarding_address, 'sm:col-span-2'],
        ['Emergency contact', $interview->emergency_contact_name, ''],
        ['Relationship', $interview->emergency_contact_relationship, ''],
        ['Emergency contact address', $interview->emergency_contact_address, ''],
        ['Emergency contact no.', $interview->emergency_contact_number, ''],
    ];
    $yesNoSections = [
        ['II. Psychological concerns', array_chunk(Form8::CONCERNS, 8, true), 'concerns_specify', 'Specify'],
        ['Relationship concerns', array_chunk(Form8::RELATIONSHIP_CONCERNS, 2, true), 'relationship_other', 'Others'],
        ['III. Coping strategies', array_chunk(Form8::COPING_STRATEGIES, 5, true), 'coping_other', 'Others'],
    ];
    $followUpKeys = Form8::followUpConcerns();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ $record ? route('staff.records.show', $record).'#intake' : route('staff.records.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">&larr; {{ $record ? __('Back to record') : __('All records') }}</a>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Intake interview') }}: {{ $student->name }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('HDC Form 8') }} · {{ $student->student_number }} · {{ $student->program?->code }} – {{ $student->program?->college?->name }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($interview->needsFollowUp())
                    <x-follow-up-badge />
                @endif
                <x-intake-status :status="$interview->status" />
                @can('update', $interview)
                    <a href="{{ route('staff.intake.edit', $interview) }}" class="ms-1 inline-flex items-center px-3 py-1.5 bg-gray-800 dark:bg-gray-100 dark:text-gray-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">{{ __('Edit') }}</a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="rounded-md border border-amber-300/60 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200" role="note">
                {{ __('Highly confidential. This view has been recorded in the access log.') }}
            </div>

            <section class="glass-panel sm:rounded-lg p-4 sm:p-6">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Date and time of intake interview') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $interview->interview_at?->format('F j, Y g:i A') ?? __('Not set yet') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Interviewer') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $interview->counselor?->name ?? __('(removed user)') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Interviewee acknowledged') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $interview->interviewee_acknowledged ? __('Yes') : __('No') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Date of entry') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $interview->date_of_entry?->format('F j, Y') ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Academic status') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $interview->academic_status ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Status') }}</dt>
                        <dd class="text-gray-900 dark:text-gray-100">
                            {{ $interview->isFinalized() ? __('Finalized on :date', ['date' => $interview->finalized_at?->format('F j, Y g:i A')]) : __('Draft') }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="personal-heading">
                <h3 id="personal-heading" class="{{ $sectionHeading }}">{{ __('I. Personal data') }}</h3>
                <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                    @foreach ($personal as [$label, $value, $span])
                        <div class="{{ $span }}">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __($label) }}</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ filled($value) ? $value : '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @foreach ($yesNoSections as [$heading, $columns, $otherField, $otherLabel])
                <section class="glass-panel sm:rounded-lg p-4 sm:p-6">
                    <h3 class="{{ $sectionHeading }}">{{ __($heading) }}</h3>
                    <div class="mt-3 grid gap-x-10 sm:grid-cols-2">
                        @foreach ($columns as $column)
                            <dl class="divide-y divide-gray-200/70 dark:divide-white/10 text-sm">
                                @foreach ($column as $key => $label)
                                    <div class="flex items-center justify-between gap-3 py-1.5">
                                        <dt class="text-gray-800 dark:text-gray-200">{{ $label }}?</dt>
                                        <dd @class([
                                            'font-semibold',
                                            'text-red-700 dark:text-red-300' => $interview->{$key} === true && in_array($key, $followUpKeys, true),
                                            'text-gray-900 dark:text-gray-100' => $interview->{$key} === true && ! in_array($key, $followUpKeys, true),
                                            'font-normal text-gray-500 dark:text-gray-400' => $interview->{$key} !== true,
                                        ])>{{ $answer($interview->{$key}) }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endforeach
                    </div>
                    @if (filled($interview->{$otherField}))
                        <p class="mt-3 text-sm text-gray-800 dark:text-gray-200"><span class="font-medium">{{ __($otherLabel) }}:</span> {{ $interview->{$otherField} }}</p>
                    @endif
                </section>
            @endforeach

            <section class="glass-panel sm:rounded-lg p-4 sm:p-6 space-y-6">
                @foreach (['client_description', 'presenting_concerns'] as $field)
                    <div>
                        <h3 class="{{ $sectionHeading }}">{{ __(Form8::NOTES[$field]) }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $interview->{$field} ?: '—' }}</p>
                    </div>
                @endforeach

                <div>
                    <h3 class="{{ $sectionHeading }}">{{ __('VI. Relationship with the client during the intake summary') }}</h3>
                    <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                        {{ implode(', ', Form5::labels(Form8::RELATIONSHIP_DURING_INTAKE, $interview->relationship_during_intake)) ?: '—' }}
                    </p>
                </div>

                @foreach (['conceptualization', 'recommendations'] as $field)
                    <div>
                        <h3 class="{{ $sectionHeading }}">{{ __(Form8::NOTES[$field]) }}</h3>
                        <p class="mt-2 whitespace-pre-line text-sm text-gray-800 dark:text-gray-200">{{ $interview->{$field} ?: '—' }}</p>
                    </div>
                @endforeach
            </section>
        </div>
    </div>
</x-app-layout>
