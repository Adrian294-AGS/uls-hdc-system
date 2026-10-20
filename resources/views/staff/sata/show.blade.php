@php
    $student = $result->student;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <a href="{{ route('staff.sata.index', $student) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">&larr; {{ __('SATA history') }}</a>
                <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('SATA result: :name', ['name' => $student->name]) }}
                    @if ($isCurrent)
                        <span class="ms-2 align-middle inline-flex rounded-full bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">{{ __('Current') }}</span>
                    @endif
                </h2>
                @include('staff.sata.partials.student-header')
            </div>
            <a href="{{ route('staff.sata.edit', $result) }}" class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700/60">
                {{ __('Edit') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="glass-panel sm:rounded-lg p-4 sm:p-6">
                @include('staff.sata.partials.result-table')
            </div>
        </div>
    </div>
</x-app-layout>
