<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/**
 * Creates the single HDC Director account from DIRECTOR_EMAIL / DIRECTOR_PASSWORD in .env.
 * The password must meet the app's password rules (10+ characters, mixed case, a number and a symbol)
 * and must not be the placeholder from the example .env files.
 * If the account already exists it is left untouched (its password is not reset).
 */
class DirectorSeeder extends Seeder
{
    /**
     * Placeholder values shipped in .env.example / .env.production.example.
     *
     * @var list<string>
     */
    public const PLACEHOLDER_PASSWORDS = ['change-me-to-a-strong-password', 'ChangeMe-Strong-Passw0rd'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $config = config('hdc.director');

        if (blank($config['email']) || blank($config['password'])) {
            throw new RuntimeException('Set DIRECTOR_EMAIL and DIRECTOR_PASSWORD in .env before seeding the director account.');
        }

        $validator = Validator::make(
            ['email' => $config['email'], 'password' => $config['password']],
            ['email' => ['email'], 'password' => [Password::defaults(), 'not_in:'.implode(',', self::PLACEHOLDER_PASSWORDS)]],
        );

        if ($validator->fails()) {
            throw new RuntimeException('DIRECTOR_EMAIL / DIRECTOR_PASSWORD in .env are not acceptable: '.implode(' ', $validator->errors()->all()));
        }

        if (User::where('email', $config['email'])->exists()) {
            $this->command?->info("Director account {$config['email']} already exists; skipped.");

            return;
        }

        $director = new User([
            'first_name' => $config['first_name'],
            'last_name' => $config['last_name'],
            'email' => $config['email'],
            'password' => $config['password'],
        ]);
        $director->role = Role::Director;
        $director->email_verified_at = now();
        $director->save();
    }
}
