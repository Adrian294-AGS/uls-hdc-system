@php
    use App\Support\Reports\Suppression;

    $query = $filters->query();
    $selectClasses = 'mt-1 block w-full glass-input focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:focus:border-indigo-400 dark:focus:ring-indigo-400 text-sm';
    $linkButton = 'inline-flex items-center gap-2 px-3 py-2 rounded-md text-xs font-semibold uppercase tracking-widest shadow-sm transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Reports') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ Auth::user()->isDirector() ? __('All colleges') : __('Your assigned colleges only') }} · {{ __('Counts only, never names or individual answers.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.csv', [$report->key(), ...$query]) }}" class="{{ $linkButton }} bg-gray-800 text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
                    {{ __('Download CSV') }}
                </a>
                <a href="{{ route('reports.print', [$report->key(), ...$query]) }}" target="_blank" rel="noopener" class="{{ $linkButton }} border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                    {{ __('Print / Save as PDF') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            {{-- Report tabs keep the current filters --}}
            <nav class="glass-panel sm:rounded-lg flex flex-wrap gap-1 p-1.5" aria-label="{{ __('Reports') }}">
                @foreach ($reports as $key => $tab)
                    <a href="{{ route('reports.show', [$key, ...$query]) }}" @class([
                        'rounded-md px-4 py-2 text-sm font-medium transition',
                        'bg-indigo-600 text-white shadow-sm dark:bg-indigo-500' => $key === $report->key(),
                        'text-gray-700 hover:bg-white/70 dark:text-gray-300 dark:hover:bg-slate-700/60' => $key !== $report->key(),
                    ]) @if ($key === $report->key()) aria-current="page" @endif>{{ __($tab->title()) }}</a>
                @endforeach
            </nav>

            <form method="GET" action="{{ route('reports.show', $report->key()) }}" class="glass-panel sm:rounded-lg p-4" role="search" aria-label="{{ __('Report filters') }}">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                    <div>
                        <x-input-label for="school_year" :value="__('School year')" />
                        <select id="school_year" name="school_year" class="{{ $selectClasses }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($schoolYears as $year)
                                <option value="{{ $year }}" @selected($filters->schoolYear === $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="semester" :value="__('Semester')" />
                        <select id="semester" name="semester" class="{{ $selectClasses }}">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($semesters as $key => $label)
                                <option value="{{ $key }}" @selected($filters->semester === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="from" :value="__('From')" />
                        <x-text-input id="from" name="from" type="date" class="mt-1 block w-full text-sm" :value="$filters->from?->format('Y-m-d')" />
                    </div>
                    <div>
                        <x-input-label for="to" :value="__('To')" />
                        <x-text-input id="to" name="to" type="date" class="mt-1 block w-full text-sm" :value="$filters->to?->format('Y-m-d')" />
                    </div>
                    <div>
                        <x-input-label for="college_id" :value="__('College')" />
                        <select id="college_id" name="college_id" class="{{ $selectClasses }}">
                            <option value="">{{ Auth::user()->isDirector() ? __('All colleges') : __('All my colleges') }}</option>
                            @foreach ($filters->collegeOptions() as $college)
                                <option value="{{ $college->id }}" @selected($filters->collegeId === $college->id)>{{ $college->code }} – {{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="program_id" :value="__('Program')" />
                        <select id="program_id" name="program_id" class="{{ $selectClasses }}">
                            <option value="">{{ __('All programs') }}</option>
                            @foreach ($filters->programOptions() as $program)
                                <option value="{{ $program->id }}" @selected($filters->programId === $program->id)>{{ $program->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="year_level" :value="__('Year level')" />
                        <select id="year_level" name="year_level" class="{{ $selectClasses }}">
                            <option value="">{{ __('All year levels') }}</option>
                            @foreach ($yearLevels as $level => $label)
                                <option value="{{ $level }}" @selected($filters->yearLevel === $level)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ __('Apply filters') }}</x-primary-button>
                    @if ($query)
                        <a href="{{ route('reports.show', $report->key()) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Clear') }}</a>
                    @endif
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('The semester filter needs a school year.') }}</p>
                </div>
            </form>

            <div class="rounded-md border border-indigo-200/70 bg-indigo-50/90 px-4 py-3 text-sm text-indigo-900 dark:border-indigo-400/30 dark:bg-indigo-500/10 dark:text-indigo-200" role="note">
                <p>{{ __($report->description()) }}</p>
                <p class="mt-1">{{ __('To protect privacy, counts from 1 to :max are shown as ":marker". Zero is shown as 0.', ['max' => Suppression::minimum() - 1, 'marker' => Suppression::marker()]) }}</p>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($result->summary as $item)
                    <div class="glass-panel sm:rounded-lg px-4 py-4">
                        <dt class="text-sm text-gray-600 dark:text-gray-400">{{ __($item['label']) }}</dt>
                        <dd class="mt-1 text-3xl font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ $item['value'] }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($result->chart)
                <section class="glass-panel sm:rounded-lg p-4 sm:p-6" aria-labelledby="chart-heading">
                    <h3 id="chart-heading" class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __($result->chart['title']) }}</h3>
                    <div class="relative mt-4" style="height: {{ count($result->chart['labels']) * 30 + 40 }}px">
                        <canvas data-report-chart="{{ json_encode($result->chart) }}" role="img"
                            aria-label="{{ __('Bar chart of :title. The same numbers are in the table below.', ['title' => $result->chart['title']]) }}"></canvas>
                    </div>
                    @include('reports.partials.table', ['table' => new \App\Support\Reports\ReportTable(
                        __('Numbers shown in the chart'),
                        [__('Concern'), __('Students')],
                        array_map(null, $result->chart['labels'], $result->chart['display']),
                    ), 'compact' => true])
                </section>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($result->tables as $table)
                    <section @class(['glass-panel sm:rounded-lg p-4 sm:p-6', 'lg:col-span-2' => count($table->headers) > 3])>
                        @include('reports.partials.table', ['table' => $table])
                    </section>
                @endforeach
            </div>
        </div>
    </div>

    @vite('resources/js/reports.js')
</x-app-layout>
