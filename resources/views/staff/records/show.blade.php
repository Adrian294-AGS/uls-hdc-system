@php
    use App\Enums\RecordStatus;
    use App\Support\Form5;

    $student = $record->student;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <a href="{{ route('staff.records.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">&larr; {{ __('All records') }}</a>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $record->fullName() ?: $student->name }}</h2>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="#intake" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Intake interviews') }} &darr;</a>
                <a href="#sata" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ $sata ? __('SATA result') : __('Encode SATA result') }} &darr;</a>
                <x-record-status :status="$record->status" />
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 dark:bg-green-500/10 px-4 py-3 text-sm text-green-800 dark:text-green-300" role="status">{{ session('status') }}</div>
            @endif

            <div class="glass-panel sm:rounded-lg p-4 sm:p-6">
                <div class="flex flex-col gap-6 sm:flex-row sm:items-start">
                    <div class="shrink-0">
                        @if ($record->photo_path)
                            <img src="{{ route('staff.records.photo', $record) }}" alt="{{ __('2x2 photo of :name', ['name' => $student->name]) }}" class="h-32 w-32 rounded border border-gray-200 dark:border-gray-700 object-cover">
                        @else
                            <div class="flex h-32 w-32 items-center justify-center rounded border border-dashed border-gray-300 dark:border-gray-600 text-xs text-gray-500 dark:text-gray-400">{{ __('No photo') }}</div>
                        @endif
                    </div>

                    <dl class="grid flex-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Student no.') }}</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $student->student_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Program / College') }}</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $student->program?->code }} – {{ $student->program?->college?->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Term') }}</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ Form5::SEMESTERS[$record->semester] ?? '' }}, {{ __('S.Y.') }} {{ $record->school_year }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Submitted') }}</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $record->submitted_at?->format('F j, Y g:i A') ?? __('Not yet') }}</dd>
                        </div>
                        @if ($record->consent_given)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Data privacy consent') }}</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ __('Given by ":name" on :date', ['name' => $record->consent_name, 'date' => $record->consented_at->format('F j, Y')]) }}</dd>
                            </div>
                        @endif
                        @if ($record->status === RecordStatus::Reviewed)
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('Reviewed') }}</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ __('By :name on :date', ['name' => $record->reviewer?->name ?? __('(removed user)'), 'date' => $record->reviewed_at?->format('F j, Y g:i A')]) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                @can('review', $record)
                    <form method="POST" action="{{ route('staff.records.review', $record) }}" class="mt-6 flex flex-wrap items-center gap-3 border-t border-gray-100 dark:border-gray-700 pt-4"
                        x-data x-on:submit="if (! confirm(@js(__('Mark this record as reviewed? The student will no longer be able to edit it.')))) $event.preventDefault()">
                        @csrf
                        <x-primary-button>{{ __('Mark as reviewed') }}</x-primary-button>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('After this, the student can no longer edit the record.') }}</p>
                    </form>
                @elseif ($record->status === RecordStatus::Draft)
                    <p class="mt-6 border-t border-gray-100 dark:border-gray-700 pt-4 text-sm text-gray-600 dark:text-gray-400">{{ __('This record is still a draft. It can be reviewed after the student submits it.') }}</p>
                @endif
            </div>

            <div class="glass-panel sm:rounded-lg p-4 sm:p-6">
                @include('student.record.summary', ['editable' => false])
            </div>

            {{-- Form 8 intake interviews (staff only): metadata only, never the answers --}}
            <section id="intake" class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="intake-heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="intake-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Intake Interviews (HDC Form 8)') }}</h3>
                    @can('create', [\App\Models\IntakeInterview::class, $student])
                        <a href="{{ route('staff.intake.create', $student) }}" class="inline-flex items-center px-3 py-1.5 bg-gray-800 dark:bg-gray-100 dark:text-gray-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                            {{ __('New intake interview') }}
                        </a>
                    @endcan
                </div>

                @if ($intakes->isEmpty())
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ __('No intake interview has been recorded for this student yet.') }}</p>
                @else
                    <div class="mt-4 overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Date of interview') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Interviewer') }}</th>
                                    <th scope="col" class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Status') }}</th>
                                    <th scope="col" class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($intakes as $intake)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                                            {{ $intake->interview_at?->format('M j, Y g:i A') ?? __('Not set (started :date)', ['date' => $intake->created_at->format('M j, Y')]) }}
                                        </td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $intake->counselor?->name ?? __('(removed user)') }}</td>
                                        <td class="px-4 py-2">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <x-intake-status :status="$intake->status" />
                                                @if ($intake->needsFollowUp())
                                                    <x-follow-up-badge />
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-right whitespace-nowrap">
                                            <a href="{{ route('staff.intake.show', $intake) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Open') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- SATA entrance test (staff only) --}}
            <section id="sata" class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="sata-heading">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="sata-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Entrance Test Result (SATA)') }}</h3>
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        @if ($sataCount)
                            <a href="{{ route('staff.sata.index', $student) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ trans_choice('History (:count result)|History (:count results)', $sataCount) }}</a>
                        @endif
                        @if ($sata)
                            <a href="{{ route('staff.sata.edit', $sata) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit') }}</a>
                        @endif
                        <a href="{{ route('staff.sata.create', $student) }}" class="inline-flex items-center px-3 py-1.5 bg-gray-800 dark:bg-gray-100 dark:text-gray-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                            {{ $sata ? __('Encode retake') : __('Encode SATA result') }}
                        </a>
                    </div>
                </div>

                @if ($sata)
                    <div class="mt-4">
                        @include('staff.sata.partials.result-table', ['result' => $sata])
                    </div>
                @else
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ __('No SATA result has been encoded for this student yet.') }}</p>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
