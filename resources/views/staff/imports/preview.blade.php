@php
    $done = $data['status'] === 'done';
    $columns = $import->columns();
    $badge = [
        'valid' => ['Ready', 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30'],
        'imported' => ['Imported', 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-400/10 dark:text-green-300 dark:ring-green-400/30'],
        'skipped' => ['Skipped', 'bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30'],
        'error' => ['Error', 'bg-red-50 text-red-700 ring-red-600/30 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-400/40'],
    ];
    $problemCount = $counts['error'] + $counts['skipped'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('staff.imports.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">&larr; {{ __('Import') }}</a>
        <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $done ? __('Import finished') : __('Check before importing') }}: {{ __($import->label()) }}
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $data['filename'] }}</p>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div class="glass-panel sm:rounded-lg px-4 py-4">
                    <dt class="text-sm text-gray-600 dark:text-gray-400">{{ $done ? __('Imported') : __('Ready to import') }}</dt>
                    <dd class="mt-1 text-3xl font-semibold tabular-nums text-green-700 dark:text-green-400">{{ $done ? $counts['imported'] : $counts['valid'] }}</dd>
                </div>
                <div class="glass-panel sm:rounded-lg px-4 py-4">
                    <dt class="text-sm text-gray-600 dark:text-gray-400">{{ __('Skipped (already exists, duplicate or example)') }}</dt>
                    <dd class="mt-1 text-3xl font-semibold tabular-nums text-amber-700 dark:text-amber-300">{{ $counts['skipped'] }}</dd>
                </div>
                <div class="glass-panel sm:rounded-lg px-4 py-4">
                    <dt class="text-sm text-gray-600 dark:text-gray-400">{{ __('Rows with errors') }}</dt>
                    <dd class="mt-1 text-3xl font-semibold tabular-nums text-red-700 dark:text-red-300">{{ $counts['error'] }}</dd>
                </div>
            </dl>

            <div class="glass-panel sm:rounded-lg flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6">
                <p class="text-sm text-gray-700 dark:text-gray-300">
                    @if ($done)
                        {{ __('Only valid rows were saved. Rows with errors or skipped rows were not changed.') }}
                    @elseif ($counts['valid'] > 0)
                        {{ __('Only the :count ready rows will be imported. Fix the others in your file and upload it again later, if needed.', ['count' => $counts['valid']]) }}
                    @else
                        {{ __('There is nothing to import. Fix the problems in your file and upload it again.') }}
                    @endif
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    @if ($problemCount)
                        <a href="{{ route('staff.imports.errors', $token) }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Download problem report (CSV)') }}</a>
                    @endif
                    @if ($done)
                        <a href="{{ route('staff.imports.index') }}" class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">{{ __('Import another file') }}</a>
                    @else
                        <form method="POST" action="{{ route('staff.imports.destroy', $token) }}">
                            @csrf
                            @method('DELETE')
                            <x-secondary-button type="submit">{{ __('Discard') }}</x-secondary-button>
                        </form>
                        <form method="POST" action="{{ route('staff.imports.store', $token) }}"
                            data-confirm="{{ trans_choice('Import :count row? Rows with errors and skipped rows will not be imported.|Import :count rows? Rows with errors and skipped rows will not be imported.', $counts['valid']) }}"
                            data-confirm-title="{{ __('Import :label?', ['label' => mb_strtolower(__($import->label()))]) }}" data-confirm-button="{{ __('Import') }}">
                            @csrf
                            <x-primary-button :disabled="$counts['valid'] === 0" class="disabled:cursor-not-allowed disabled:opacity-50">
                                {{ trans_choice('Import :count row|Import :count rows', $counts['valid']) }}
                            </x-primary-button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="glass-panel sm:rounded-lg overflow-hidden">
                <div class="max-h-[70vh] overflow-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th scope="col" class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Row') }}</th>
                                <th scope="col" class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Status') }}</th>
                                @foreach ($columns as $column)
                                    <th scope="col" class="px-3 py-2 text-left font-semibold whitespace-nowrap text-gray-700 dark:text-gray-300">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($data['results'] as $result)
                                <tr @class(['bg-red-50/60 dark:bg-red-500/5' => $result['status'] === 'error'])>
                                    <td class="px-3 py-2 align-top tabular-nums text-gray-600 dark:text-gray-400">{{ $result['row'] }}</td>
                                    <td class="px-3 py-2 align-top">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $badge[$result['status']][1] }}">{{ __($badge[$result['status']][0]) }}</span>
                                        @if ($result['reason'])
                                            <p class="mt-1 w-56 text-xs text-amber-800 dark:text-amber-300">{{ $result['reason'] }}</p>
                                        @endif
                                    </td>
                                    @foreach (array_keys($columns) as $key)
                                        @php($error = $result['errors'][$key] ?? null)
                                        <td @class(['px-3 py-2 align-top', 'bg-red-100/80 dark:bg-red-500/20' => $error]) @if ($error) data-cell-error @endif>
                                            <span class="whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $result['values'][$key] ?? '' }}</span>
                                            @if ($error)
                                                <p class="mt-1 w-52 text-xs font-medium text-red-700 dark:text-red-300">{{ $error }}</p>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
