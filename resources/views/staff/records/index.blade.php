<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Student Records') }}</h2>
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ trans_choice(':count student|:count students', $students->total()) }}</p>
                <x-export-menu :action="route('staff.records.export-list')" :formats="['xlsx' => 'Excel', 'csv' => 'CSV', 'pdf' => 'PDF']"
                    :hidden="array_filter(request()->only(\App\Http\Controllers\Staff\EntranceTestResultController::LIST_FILTERS), 'filled')"
                    data-sensitive-message="{{ __('Include sensitive fields (contact details, birth date, address, SATA quotient) in this export? Handle the file as confidential.') }}"
                    data-sensitive-title="{{ __('Export sensitive fields?') }}"
                    x-bind:data-confirm="sensitive ? $el.dataset.sensitiveMessage : null"
                    x-bind:data-confirm-title="sensitive ? $el.dataset.sensitiveTitle : null"
                    x-bind:data-confirm-button="sensitive ? 'Export' : null">
                    <p class="text-xs text-gray-600 dark:text-gray-400">{{ __('Exports the current filtered list: name, student no., program, college, year level, Form 5 status, submitted date, SATA and intake status.') }}</p>
                    @if (Auth::user()->isDirector())
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="sensitive" value="1" x-model="sensitive" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-indigo-500">
                            <span>{{ __('Include sensitive fields') }} <span class="block text-xs text-gray-500 dark:text-gray-400">{{ __('Director only. Contact details, birth date, address and SATA quotient. Never Form 8 answers.') }}</span></span>
                        </label>
                    @endif
                </x-export-menu>
            </div>
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
