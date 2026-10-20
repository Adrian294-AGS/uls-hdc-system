@php
    use App\Support\Form5;
    use App\Support\Form8;

    $editing = $interview->exists;
    $finalized = $interview->isFinalized();
    $backUrl = match (true) {
        $editing => route('staff.intake.show', $interview),
        (bool) $student->latestCumulativeRecord => route('staff.records.show', $student->latestCumulativeRecord),
        default => route('staff.records.index'),
    };

    // Paper layout: two columns of Yes/No questions.
    $concernColumns = array_chunk(Form8::CONCERNS, 8, true);
    $relationshipColumns = array_chunk(Form8::RELATIONSHIP_CONCERNS, 2, true);
    $copingColumns = array_chunk(Form8::COPING_STRATEGIES, 5, true);

    $relationshipChecked = array_map('strval', (array) (session()->hasOldInput() ? old('relationship_during_intake', []) : ($interview->relationship_during_intake ?? [])));
    $interviewAt = old('interview_at', $interview->interview_at?->format('Y-m-d\TH:i'));
    $acknowledged = session()->hasOldInput() ? (bool) old('interviewee_acknowledged') : $interview->interviewee_acknowledged;
    $inputClasses = 'mt-1 block w-full glass-input focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:focus:border-indigo-400 dark:focus:ring-indigo-400';
    $sectionHeading = 'text-base font-semibold uppercase tracking-wide text-gray-900 dark:text-gray-100';
@endphp

<x-app-layout>
    <x-slot name="header">
        <a href="{{ $backUrl }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
            &larr; {{ $editing ? __('Back to interview') : __('Back to record') }}
        </a>
        <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $editing ? __('Edit intake interview') : __('New intake interview') }}: {{ $student->name }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('HDC Form 8') }} · {{ $student->student_number }} · {{ $student->program?->code }} – {{ $student->program?->college?->name }}
        </p>
    </x-slot>

    <div class="py-8">
        <form method="POST" action="{{ $editing ? route('staff.intake.update', $interview) : route('staff.intake.store', $student) }}"
            class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <x-flash />

            <div class="rounded-md border border-amber-300/60 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200" role="note">
                {{ __('Highly confidential. Only HDC staff assigned to this student\'s college can see this form. The student never sees it.') }}
            </div>

            @if ($finalized)
                <div class="rounded-md bg-blue-50 dark:bg-blue-500/10 px-4 py-3 text-sm text-blue-800 dark:text-blue-200" role="status">
                    {{ __('This interview is finalized. Only the director can edit it, and every change is recorded in the access log.') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-300" role="alert">
                    <p class="font-medium">{{ __('Please fix the following:') }}</p>
                    <ul class="mt-1 list-disc ps-5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Header --}}
            <section class="glass-panel sm:rounded-lg p-4 sm:p-6">
                <div class="text-center">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('University of La Salette · The Human Development Center') }}</p>
                    <h3 class="mt-1 text-lg font-semibold tracking-wide text-gray-900 dark:text-gray-100">{{ __('INTAKE INTERVIEW FORM') }}</h3>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <x-form.text name="date_of_entry" label="Date of entry" type="date" :value="$interview->date_of_entry" />
                    <x-form.text name="academic_status" label="Academic status" :value="$interview->academic_status" maxlength="100" />
                </div>
            </section>

            {{-- I. Personal data --}}
            <section class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="personal-heading">
                <h3 id="personal-heading" class="{{ $sectionHeading }}">{{ __('I. Personal data') }}</h3>
                @unless ($editing)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ $student->latestCumulativeRecord ? __('Pre-filled from the student\'s latest Student Cumulative Record. Check and update anything that changed.') : __('This student has no Student Cumulative Record yet, so nothing could be pre-filled.') }}
                    </p>
                @endunless
                <div class="mt-4 grid gap-4 sm:grid-cols-6">
                    <x-form.text name="name" label="Name" :value="$interview->name" class="sm:col-span-3" maxlength="255" />
                    <x-form.text name="nickname" label="Nickname" :value="$interview->nickname" class="sm:col-span-1" maxlength="100" />
                    <x-form.text name="course" label="Course" :value="$interview->course" class="sm:col-span-2" maxlength="255" />
                    <x-form.text name="home_address" label="Complete home address" :value="$interview->home_address" class="sm:col-span-6" maxlength="500" />
                    <x-form.text name="age" label="Age" type="number" :value="$interview->age" class="sm:col-span-1" min="10" max="99" />
                    <x-form.select name="sex" label="Sex" :options="Form5::SEXES" :value="$interview->sex" class="sm:col-span-1" />
                    <x-form.text name="birth_date" label="Birthdate" type="date" :value="$interview->birth_date" class="sm:col-span-2" />
                    <x-form.text name="place_of_birth" label="Place of birth" :value="$interview->place_of_birth" class="sm:col-span-2" maxlength="255" />
                    <x-form.select name="civil_status" label="Civil status" :options="Form5::CIVIL_STATUSES" :value="$interview->civil_status" class="sm:col-span-2" />
                    <x-form.text name="spouse_name" label="If married, name of spouse" :value="$interview->spouse_name" class="sm:col-span-3" maxlength="255" />
                    <x-form.text name="number_of_children" label="No. of children" type="number" :value="$interview->number_of_children" class="sm:col-span-1" min="0" max="30" />
                    <x-form.select name="citizenship" label="Citizenship" :options="Form8::CITIZENSHIPS" :value="$interview->citizenship" class="sm:col-span-2" />
                    <x-form.text name="citizenship_other" label="If not Filipino, please specify" :value="$interview->citizenship_other" class="sm:col-span-2" maxlength="100" />
                    <x-form.text name="religion" label="Religion" :value="$interview->religion" class="sm:col-span-2" maxlength="100" />
                    <x-form.text name="contact_number" label="Contact no." :value="$interview->contact_number" class="sm:col-span-2" maxlength="50" />
                    <x-form.text name="boarding_address" label="Boarding house address" :value="$interview->boarding_address" class="sm:col-span-4" maxlength="500" />
                    <x-form.text name="emergency_contact_name" label="Name of person we can contact in case of emergency" :value="$interview->emergency_contact_name" class="sm:col-span-4" maxlength="255" />
                    <x-form.text name="emergency_contact_relationship" label="Relationship" :value="$interview->emergency_contact_relationship" class="sm:col-span-2" maxlength="100" />
                    <x-form.text name="emergency_contact_address" label="Address" :value="$interview->emergency_contact_address" class="sm:col-span-4" maxlength="500" />
                    <x-form.text name="emergency_contact_number" label="Contact no." :value="$interview->emergency_contact_number" class="sm:col-span-2" maxlength="50" />
                </div>
            </section>

            {{-- II. Psychological concerns + relationship concerns --}}
            <section class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="concerns-heading">
                <h3 id="concerns-heading" class="{{ $sectionHeading }}">{{ __('II. Psychological concerns') }}</h3>
                <div class="mt-3 grid gap-x-10 sm:grid-cols-2">
                    @foreach ($concernColumns as $column)
                        <div class="divide-y divide-gray-200/70 dark:divide-white/10">
                            @foreach ($column as $key => $label)
                                <x-form.yes-no-pair :name="$key" :label="$label" :value="$interview->{$key}" />
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <x-form.text name="concerns_specify" label="Specify (e.g., loss of significant others: who)" :value="$interview->concerns_specify" class="mt-4" maxlength="1000" />

                <h4 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-900 dark:text-gray-100">{{ __('Relationship concerns') }}</h4>
                <div class="mt-2 grid gap-x-10 sm:grid-cols-2">
                    @foreach ($relationshipColumns as $column)
                        <div class="divide-y divide-gray-200/70 dark:divide-white/10">
                            @foreach ($column as $key => $label)
                                <x-form.yes-no-pair :name="$key" :label="$label" :value="$interview->{$key}" />
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <x-form.text name="relationship_other" label="Others: specify" :value="$interview->relationship_other" class="mt-4" maxlength="1000" />
            </section>

            {{-- III. Coping strategies --}}
            <section class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="coping-heading">
                <h3 id="coping-heading" class="{{ $sectionHeading }}">{{ __('III. Coping strategies') }}</h3>
                <div class="mt-3 grid gap-x-10 sm:grid-cols-2">
                    @foreach ($copingColumns as $column)
                        <div class="divide-y divide-gray-200/70 dark:divide-white/10">
                            @foreach ($column as $key => $label)
                                <x-form.yes-no-pair :name="$key" :label="$label" :value="$interview->{$key}" />
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <x-form.text name="coping_other" label="Others: please specify" :value="$interview->coping_other" class="mt-4" maxlength="1000" />
            </section>

            {{-- IV–VIII. Counselor notes --}}
            <section class="glass-panel sm:rounded-lg p-4 sm:p-6 space-y-6" aria-label="{{ __('Counselor notes') }}">
                <x-form.textarea name="client_description" :label="Form8::NOTES['client_description']" :value="$interview->client_description" rows="4" maxlength="10000" />
                <x-form.textarea name="presenting_concerns" :label="Form8::NOTES['presenting_concerns']" :value="$interview->presenting_concerns" rows="4" maxlength="10000" />

                <fieldset>
                    <legend class="block font-medium text-sm text-gray-700 dark:text-gray-300">{{ __('VI. Relationship with the client during the intake summary') }}</legend>
                    <div class="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                        @foreach (Form8::RELATIONSHIP_DURING_INTAKE as $key => $label)
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                                <input type="checkbox" name="relationship_during_intake[]" value="{{ $key }}"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-indigo-500 dark:focus:ring-indigo-400 dark:focus:ring-offset-gray-800"
                                    @checked(in_array($key, $relationshipChecked, true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('relationship_during_intake')" class="mt-2" />
                </fieldset>

                <x-form.textarea name="conceptualization" :label="Form8::NOTES['conceptualization']" :value="$interview->conceptualization" rows="4" maxlength="10000" />
                <x-form.textarea name="recommendations" :label="Form8::NOTES['recommendations']" :value="$interview->recommendations" rows="4" maxlength="10000" />
            </section>

            {{-- Sign-off --}}
            <section class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="signoff-heading">
                <h3 id="signoff-heading" class="{{ $sectionHeading }}">{{ __('Sign-off') }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Needed to finalize. A draft can be saved without them.') }}</p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="interview_at">{{ __('Date and time of intake interview') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></x-input-label>
                        <input id="interview_at" name="interview_at" type="datetime-local" value="{{ $interviewAt }}" class="{{ $inputClasses }}">
                        <x-input-error :messages="$errors->get('interview_at')" class="mt-2" />
                    </div>
                    <div>
                        <span class="block font-medium text-sm text-gray-700 dark:text-gray-300">{{ __('Interviewer') }}</span>
                        <p class="mt-1 rounded-md border border-gray-300/70 bg-gray-50/70 px-3 py-2 text-gray-900 dark:border-white/10 dark:bg-slate-800/40 dark:text-gray-100">{{ $interviewer?->name ?? __('(removed user)') }}</p>
                    </div>
                </div>
                <label class="mt-4 flex items-start gap-3 text-sm text-gray-800 dark:text-gray-200">
                    <input type="checkbox" name="interviewee_acknowledged" value="1" @checked($acknowledged)
                        class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-indigo-500 dark:focus:ring-indigo-400 dark:focus:ring-offset-gray-800">
                    <span>{{ __('The interviewee acknowledges this intake interview (in place of the interviewee\'s signature).') }} <span class="text-red-600 dark:text-red-400" aria-hidden="true">*</span></span>
                </label>
                <x-input-error :messages="$errors->get('interviewee_acknowledged')" class="mt-2" />
            </section>

            <div class="glass-panel sm:rounded-lg flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <a href="{{ $backUrl }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Cancel') }}</a>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($finalized)
                        <x-primary-button name="action" value="finalize">{{ __('Save changes') }}</x-primary-button>
                    @else
                        <x-secondary-button type="submit" name="action" value="draft">{{ __('Save as draft') }}</x-secondary-button>
                        <x-primary-button name="action" value="finalize"
                            data-confirm="{{ __('Finalize this intake interview? After this, only the director can edit it.') }}"
                            x-data x-on:click="if (! confirm($el.dataset.confirm)) $event.preventDefault()">
                            {{ __('Finalize') }}
                        </x-primary-button>
                    @endif
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
