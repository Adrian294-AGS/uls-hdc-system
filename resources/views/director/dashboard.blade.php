<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Director Dashboard') }}
        </h2>
    </x-slot>

    <x-welcome-card>
        {{ __('You have access to all colleges.') }}
    </x-welcome-card>

    <x-staff-stats :stats="$stats" />

    @php($backupStale = ! $lastBackup || $lastBackup->lt(now()->subDays(\App\Support\Backups::STALE_AFTER_DAYS)))
    <div class="pb-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('director.backups.index') }}" @class([
                'glass-panel sm:rounded-lg flex flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 border-l-4 hover:opacity-90',
                'border-l-red-500' => $backupStale,
                'border-l-green-500' => ! $backupStale,
            ]) data-backup-status>
                <span>
                    <span class="block text-sm text-gray-600 dark:text-gray-400">{{ __('Last successful backup') }}</span>
                    <span class="block text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ $lastBackup ? $lastBackup->copy()->setTimezone(config('hdc.local_timezone'))->format('F j, Y g:i A') : __('No backups yet') }}
                    </span>
                </span>
                @if ($backupStale)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700 ring-1 ring-inset ring-red-600/30 dark:bg-red-500/15 dark:text-red-300 dark:ring-red-400/40">
                        {{ $lastBackup ? __('Older than :days days: check the server cron job', ['days' => \App\Support\Backups::STALE_AFTER_DAYS]) : __('Make the first backup') }}
                    </span>
                @else
                    <span class="text-sm font-medium text-green-700 dark:text-green-400">{{ __('Up to date') }}</span>
                @endif
            </a>
        </div>
    </div>
</x-app-layout>
