<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('My Student Cumulative Record') }}
            </h2>
            <div class="flex flex-wrap items-center gap-3">
                <x-record-status :status="$record->status" />
                <a href="{{ route('student.record.pdf', $record) }}"
                    class="inline-flex items-center gap-2 rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
                    {{ __('Download PDF') }}
                </a>
            </div>
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
