<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
|
| Needs ONE cron entry on the server (see README, "Cron and queue"):
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
|
| Times are Philippine time.
|
*/

$timezone = config('hdc.schedule_timezone');

// FR-11 backups: database + private uploads, encrypted, kept 30 days.
Schedule::command('backup:clean')->dailyAt('01:00')->timezone($timezone)->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('01:30')->timezone($timezone)->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('07:00')->timezone($timezone);

// Housekeeping.
// Export files are deleted after download; this removes any left behind by an interrupted download.
Schedule::call(function (): void {
    foreach (glob(storage_path('app/tmp-exports/*')) ?: [] as $file) {
        if (is_file($file) && filemtime($file) < now()->subHour()->getTimestamp()) {
            @unlink($file);
        }
    }
})->name('prune-temporary-exports')->hourly();
Schedule::command('auth:clear-resets')->dailyAt('02:30')->timezone($timezone);
Schedule::command('queue:prune-failed --hours=720')->weekly()->timezone($timezone);

// Shared hosting without Supervisor: let cron start a short-lived queue worker every minute.
if (config('hdc.queue_worker_via_scheduler')) {
    Schedule::command('queue:work --stop-when-empty --max-time=55 --tries=3')
        ->everyMinute()
        ->withoutOverlapping(5);
}
