<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Student Records') }}</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ trans_choice(':count student|:count students', $students->total()) }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @include('staff.partials.filters', [
                'action' => route('staff.records.index'),
                'statuses' => \App\Support\StudentRecordQuery::STATUSES,
            ])

            @include('staff.partials.student-table')
        </div>
    </div>
</x-app-layout>
