<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'student_number' => '26-00001',
            'program_id' => Program::factory()->create()->id,
            'year_level' => 1,
            'email' => 'juan@example.com',
            'password' => 'Student-pass-2026',
            'password_confirmation' => 'Student-pass-2026',
        ], $overrides);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $program = Program::factory()->create();

        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee($program->college->name);
        $response->assertSee($program->name);
    }

    public function test_new_students_can_register(): void
    {
        $response = $this->post('/register', $this->validPayload());

        $this->assertAuthenticated();
        $response->assertRedirect(route('student.dashboard', absolute: false));

        $user = User::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame(Role::Student, $user->role);
        $this->assertSame('Juan Santos Dela Cruz', $user->name);
        $this->assertSame('26-00001', $user->student_number);
        $this->assertSame(1, $user->year_level);
    }

    public function test_role_in_the_request_is_ignored(): void
    {
        $this->post('/register', $this->validPayload(['role' => 'director']));

        $this->assertSame(Role::Student, User::where('email', 'juan@example.com')->firstOrFail()->role);
    }

    public function test_student_number_must_be_unique(): void
    {
        User::factory()->student()->create(['student_number' => '26-00001']);

        $response = $this->post('/register', $this->validPayload());

        $response->assertSessionHasErrors('student_number');
        $this->assertGuest();
    }

    public function test_program_and_year_level_are_validated(): void
    {
        $response = $this->post('/register', $this->validPayload(['program_id' => 999, 'year_level' => 6]));

        $response->assertSessionHasErrors(['program_id', 'year_level']);
        $this->assertGuest();
    }
}
