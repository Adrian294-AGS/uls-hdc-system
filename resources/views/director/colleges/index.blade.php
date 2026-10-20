<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Colleges & Programs') }}</h2>
            <a href="{{ route('director.colleges.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-100 dark:text-gray-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                + {{ __('Add college') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @forelse ($colleges as $college)
                <section class="glass-panel sm:rounded-lg overflow-hidden" aria-labelledby="college-{{ $college->id }}">
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 px-4 py-3">
                        <h3 id="college-{{ $college->id }}" class="text-base font-semibold text-gray-900 dark:text-gray-100">
                            <span class="me-2 inline-flex rounded bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:text-gray-300">{{ $college->code }}</span>{{ $college->name }}
                        </h3>
                        <a href="{{ route('director.colleges.edit', $college) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit / add programs') }}</a>
                    </header>

                    <div class="grid gap-6 p-4 md:grid-cols-3">
                        <div class="md:col-span-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Programs') }}</p>
                            @forelse ($college->programs as $program)
                                <div class="mt-2 flex items-baseline justify-between gap-3 text-sm">
                                    <span class="text-gray-900 dark:text-gray-100"><span class="font-medium">{{ $program->code }}</span> – {{ $program->name }}</span>
                                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ trans_choice(':count student|:count students', $program->students_count) }}</span>
                                </div>
                            @empty
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No programs yet.') }}</p>
                            @endforelse
                        </div>

                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Assigned counselors') }}</p>
                            @forelse ($college->counselors as $counselor)
                                <p class="mt-2 text-sm text-gray-900 dark:text-gray-100">{{ $counselor->name }}</p>
                            @empty
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('No active counselor assigned.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            @empty
                <div class="glass-panel sm:rounded-lg p-6 text-sm text-gray-600 dark:text-gray-400">{{ __('No colleges yet. Add the first one.') }}</div>
            @endforelse
        </div>
    </div>
</x-app-layout>
