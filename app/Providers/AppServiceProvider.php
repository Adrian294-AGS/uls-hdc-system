<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every password a person chooses (registration, reset, change, counselor accounts, director seeder).
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        if (config('app.force_https')) {
            URL::forceHttps();
        }

        $this->configureRateLimiting();
        $this->refuseUnencryptedBackupsInProduction();
    }

    /**
     * Login is limited in LoginRequest (5 tries per e-mail + IP). Registration allows more per IP
     * because a whole computer lab can share one address during enrollment.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(30)->by($request->ip()),
        ]);
    }

    /**
     * Backups contain every student's confidential records, so production backups must be encrypted.
     */
    private function refuseUnencryptedBackupsInProduction(): void
    {
        Event::listen(function (CommandStarting $event): void {
            if ($event->command === 'backup:run' && $this->app->isProduction() && blank(config('backup.backup.password'))) {
                throw new RuntimeException('Set BACKUP_ARCHIVE_PASSWORD in .env: production backups must be encrypted.');
            }
        });
    }
}
