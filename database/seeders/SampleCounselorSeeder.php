<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\College;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Local/test-only counselor assigned to CIT (password "password"). Refuses to run in production,
 * even when called directly with db:seed --class.
 */
class SampleCounselorSeeder extends Seeder
{
    public const EMAIL = 'counselor@uls.test';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('SampleCounselorSeeder creates a demo account and must never run in production.');
        }

        $counselor = User::firstOrNew(['email' => self::EMAIL]);
        $counselor->fill([
            'first_name' => 'Sample',
            'last_name' => 'Counselor',
            'password' => 'password',
        ]);
        $counselor->role = Role::Counselor;
        $counselor->email_verified_at ??= now();
        $counselor->save();

        $counselor->colleges()->sync(College::where('code', 'CIT')->pluck('id'));
    }
}
