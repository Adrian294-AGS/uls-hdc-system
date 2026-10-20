<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                @if ($student->latestCumulativeRecord)
                    <a href="{{ route('staff.records.show', $student->latestCumulativeRecord) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">&larr; {{ __('Student record') }}</a>
                @else
                    <a href="{{ route('staff.records.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">&larr; {{ __('All records') }}</a>
                @endif
                <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('SATA results: :name', ['name' => $student->name]) }}</h2>
                @include('staff.sata.partials.student-header')
            </div>
            <a href="{{ route('staff.sata.create', $student) }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-100 dark:text-gray-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                + {{ $results->isEmpty() ? __('Encode SATA result') : __('Encode retake') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @forelse ($results as $result)
                <section class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="result-{{ $result->id }}">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <h3 id="result-{{ $result->id }}" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Exam on :date', ['date' => $result->exam_date->format('F j, Y')]) }}
                            @if ($loop->first)
                                <span class="ms-2 inline-flex rounded-full bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">{{ __('Current') }}</span>
                            @else
                                <span class="ms-2 inline-flex rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Earlier result') }}</span>
                            @endif
                        </h3>
                        <a href="{{ route('staff.sata.edit', $result) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit') }}</a>
                    </div>
                    @include('staff.sata.partials.result-table')
                </section>
            @empty
                <div class="glass-panel sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400">{{ __('No SATA result has been encoded for this student yet.') }}</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
