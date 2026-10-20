<?php

namespace Tests\Feature;

use App\Models\Program;
use App\Models\User;
use Database\Seeders\DirectorSeeder;
use Database\Seeders\SampleCounselorSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, bool}>
     */
    public static function passwords(): array
    {
        return [
            'too short' => ['Abc12345', false],
            'letters only' => ['abcdefghijkl', false],
            'numbers only' => ['123456789012', false],
            '10 chars, letters and numbers' => ['abcdefgh12', true],
        ];
    }

    #[DataProvider('passwords')]
    public function test_passwords_need_10_characters_with_letters_and_numbers(string $password, bool $accepted): void
    {
        $response = $this->post('/register', [
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'student_number' => '26-00001',
            'program_id' => Program::factory()->create()->id,
            'year_level' => 1,
            'email' => 'juan@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $accepted ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('password');
    }

    public function test_security_headers_are_sent_and_signed_in_pages_are_not_cached(): void
    {
        $guest = $this->get('/login')->assertOk();
        $guest->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');
        $this->assertStringNotContainsString('no-store', (string) $guest->headers->get('Cache-Control'));

        $user = User::factory()->director()->create();
        $signedIn = $this->actingAs($user)->get(route('director.dashboard'))->assertOk();
        $this->assertStringContainsString('no-store', $signedIn->headers->get('Cache-Control'));

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_http_is_redirected_to_https_when_forced(): void
    {
        config(['app.force_https' => true]);

        $this->get('http://localhost/login?x=1')->assertRedirect('https://localhost/login?x=1')->assertStatus(301);
        $this->get('https://localhost/login')->assertOk();
    }

    public function test_force_https_defaults_on_only_in_production(): void
    {
        $this->assertFalse(config('app.force_https'), 'Off in the testing environment.');
        $this->assertStringContainsString("env('APP_ENV') === 'production'", file_get_contents(config_path('app.php')));
    }

    public function test_password_reset_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'someone@example.com'])->assertStatus(302);
        }

        $this->post('/forgot-password', ['email' => 'someone@example.com'])->assertStatus(429);
    }

    public function test_registration_is_rate_limited_per_ip(): void
    {
        $this->assertContains('throttle:register', Route::getRoutes()->match(request()->create('/register', 'POST'))->gatherMiddleware());
        $this->assertContains('throttle:password-reset', Route::getRoutes()->match(request()->create('/reset-password', 'POST'))->gatherMiddleware());
    }

    public function test_private_files_are_not_served_by_the_storage_route(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(Route::has('storage.local'));
    }

    public function test_sample_counselor_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->expectException(RuntimeException::class);
            Artisan::call('db:seed', ['--class' => SampleCounselorSeeder::class, '--force' => true]);
        } finally {
            $this->app['env'] = 'testing';
            $this->assertSame(0, User::where('email', SampleCounselorSeeder::EMAIL)->count());
        }
    }

    public function test_director_seeder_requires_a_strong_password(): void
    {
        foreach (['short1', 'change-me-to-a-strong-password', 'ChangeMe-Strong-Passw0rd'] as $weak) {
            config(['hdc.director.email' => 'director@uls.test', 'hdc.director.password' => $weak]);

            try {
                $this->seed(DirectorSeeder::class);
                $this->fail("Accepted weak password {$weak}");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('DIRECTOR_PASSWORD', $exception->getMessage());
            }
        }
        $this->assertSame(0, User::count());

        config(['hdc.director.password' => 'Hdc-Director-2026']);
        $this->seed(DirectorSeeder::class);
        $this->assertTrue(User::where('email', 'director@uls.test')->sole()->isDirector());
    }

    public function test_production_backups_must_be_encrypted(): void
    {
        $this->app['env'] = 'production';
        config(['backup.backup.password' => null]);
        // Laravel only forwards console events outside unit tests; turn that on to exercise the guard.
        $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();

        try {
            $this->expectExceptionMessage('BACKUP_ARCHIVE_PASSWORD');
            Artisan::call('backup:run');
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_backups_cover_database_and_private_files_and_keep_30_days(): void
    {
        $this->assertSame([storage_path('app/private')], config('backup.backup.source.files.include'));
        $this->assertSame('aes256', config('backup.backup.encryption'));
        $this->assertSame(['backups'], array_values(config('backup.backup.destination.disks')));
        $this->assertSame(storage_path('app/backups'), config('filesystems.disks.backups.root'));
        $this->assertSame(30, config('backup.cleanup.default_strategy.keep_all_backups_for_days'));
        foreach (['keep_daily_backups_for_days', 'keep_weekly_backups_for_weeks', 'keep_monthly_backups_for_months', 'keep_yearly_backups_for_years'] as $key) {
            $this->assertSame(0, config("backup.cleanup.default_strategy.{$key}"));
        }
        $this->assertSame('OFF', config('database.connections.mysql.dump.mysql_gtid_purged'));

        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => [$event->command, $event->expression, $event->timezone]);
        foreach (['backup:clean', 'backup:run', 'backup:monitor'] as $command) {
            $event = $commands->first(fn ($event) => str_contains($event[0], $command));
            $this->assertNotNull($event, "{$command} is not scheduled");
            $this->assertSame('Asia/Manila', $event[2]);
        }
        $this->assertSame('30 1 * * *', $commands->first(fn ($event) => str_contains($event[0], 'backup:run'))[1]);
        $this->assertNull($commands->first(fn ($event) => str_contains($event[0], 'queue:work')), 'The cron queue worker is opt-in.');
    }
}
