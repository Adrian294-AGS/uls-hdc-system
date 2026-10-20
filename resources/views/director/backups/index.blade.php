@php
    $latest = $backups->first();
    $stale = ! $latest || $latest->date()->lt(now()->subDays(\App\Support\Backups::STALE_AFTER_DAYS));
    $local = fn ($date) => $date->copy()->setTimezone(config('hdc.local_timezone'))->format('M j, Y g:i A');
    $size = fn (float $bytes) => $bytes >= 1048576 ? number_format($bytes / 1048576, 1).' MB' : number_format($bytes / 1024, 1).' KB';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Backups') }}</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Database and student photos, encrypted. Made every night at 2:00 AM and kept :days days.', ['days' => $keepDays]) }}</p>
            </div>
            <form method="POST" action="{{ route('director.backups.store') }}"
                data-confirm="{{ __('Make a backup now? It can take a minute; please keep this page open.') }}" data-confirm-title="{{ __('Back up now?') }}" data-confirm-button="{{ __('Back up now') }}">
                @csrf
                <x-primary-button>{{ __('Back up now') }}</x-primary-button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if ($error)
                <div class="rounded-md bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-300" role="alert">{{ $error }}</div>
            @endif

            @unless ($encrypted)
                <div class="rounded-md border border-amber-300/60 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/10 dark:text-amber-200" role="alert">
                    {{ __('BACKUP_ARCHIVE_PASSWORD is not set, so backups are not encrypted (on the live server they are refused). Ask the system administrator to set it in .env.') }}
                </div>
            @endunless

            <div @class([
                'rounded-md px-4 py-3 text-sm',
                'bg-red-50 text-red-800 dark:bg-red-500/10 dark:text-red-300' => $stale,
                'bg-green-50 text-green-800 dark:bg-green-500/10 dark:text-green-300' => ! $stale,
            ]) role="status">
                @if ($latest)
                    {{ __('Last backup: :date.', ['date' => $local($latest->date())]) }}
                    @if ($stale) {{ __('This is more than :days days old: check that the server cron job is running.', ['days' => \App\Support\Backups::STALE_AFTER_DAYS]) }} @endif
                @else
                    {{ __('There are no backups yet. Click "Back up now", and make sure the server cron job is set up (see README).') }}
                @endif
            </div>

            <div class="glass-panel sm:rounded-lg overflow-hidden">
                @if ($backups->isEmpty())
                    <p class="p-6 text-sm text-gray-600 dark:text-gray-400">{{ __('No backups yet.') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Date') }}</th>
                                <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('File') }}</th>
                                <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300">{{ __('Size') }}</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($backups as $backup)
                                @php($filename = basename($backup->path()))
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-900 dark:text-gray-100">{{ $local($backup->date()) }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $filename }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $size($backup->sizeInBytes()) }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-4">
                                            <a href="{{ route('director.backups.download', $filename) }}" class="font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Download') }}</a>
                                            <form method="POST" action="{{ route('director.backups.destroy', $filename) }}"
                                                data-confirm="{{ __('Delete the backup from :date? This cannot be undone.', ['date' => $local($backup->date())]) }}" data-confirm-title="{{ __('Delete backup?') }}" data-confirm-button="{{ __('Delete') }}" data-confirm-danger>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Downloaded backups can only be opened with the backup password (use 7-Zip). Keep a copy outside this server. How to restore: see README, "Backups and restore".') }}</p>
        </div>
    </div>
</x-app-layout>
