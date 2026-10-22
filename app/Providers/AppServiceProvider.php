<?php

namespace App\Providers;

use App\Support\BreachedPasswordVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
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
        // Breach check with a 5-second timeout that logs outages as warnings. extend() (not bind) because
        // Laravel's deferred ValidationServiceProvider registers its own verifier later and would replace ours.
        $this->app->extend(UncompromisedVerifier::class, fn ($verifier, $app) => new BreachedPasswordVerifier($app->make(HttpFactory::class), 5));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every password a person chooses (registration, reset, change, counselor accounts, director seeder).
        // uncompromised() checks the Have I Been Pwned range API; if it cannot be reached it lets the password through.
        Password::defaults(fn () => Password::min(10)->mixedCase()->numbers()->symbols()->uncompromised());

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
