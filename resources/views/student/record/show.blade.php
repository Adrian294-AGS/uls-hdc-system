<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('My Student Cumulative Record') }}
            </h2>
            <x-record-status :status="$record->status" />
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="glass-panel sm:rounded-lg p-4 sm:p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <p>
                            {{ \App\Support\Form5::SEMESTERS[$record->semester] ?? '' }}, {{ __('S.Y.') }} {{ $record->school_year }}
                        </p>
                        @if ($record->submitted_at)
                            <p>{{ __('Submitted on :date', ['date' => $record->submitted_at->format('F j, Y g:i A')]) }}</p>
                        @endif
                        @if ($record->consent_given)
                            <p>{{ __('Data privacy consent given by ":name" on :date', ['name' => $record->consent_name, 'date' => $record->consented_at->format('F j, Y')]) }}</p>
                        @endif
                    </div>

                    @can('update', $record)
                        <a href="{{ route('student.record.edit', [$record, $record->submitted_at ? 'review' : $record->nextIncompleteStep()]) }}"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-100 dark:text-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                            {{ $record->submitted_at ? __('Edit my record') : __('Continue') }}
                        </a>
                    @endcan
                </div>

                @if ($record->status === \App\Enums\RecordStatus::Reviewed)
                    <div class="mt-4 rounded-md bg-gray-50 dark:bg-gray-900/40 px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                        {{ __('The HDC office reviewed this record on :date. It can no longer be edited. Please visit the HDC office if something needs to change.', ['date' => $record->reviewed_at?->format('F j, Y')]) }}
                    </div>
                @endif

                <div class="mt-6">
                    @include('student.record.summary', ['editable' => false])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
