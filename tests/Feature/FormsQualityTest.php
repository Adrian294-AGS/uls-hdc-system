<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\CumulativeRecord;
use App\Models\EntranceTestResult;
use App\Models\IntakeInterview;
use App\Models\Program;
use App\Models\User;
use App\Support\Validation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 1: read-only edit mode, password rules, input validation, error pages and confirmations.
 */
class FormsQualityTest extends TestCase
{
    use RefreshDatabase;

    private const CURRENT_PASSWORD = 'Current-pass-2026';

    private function student(): User
    {
        return User::factory()->student(Program::factory()->create())->create(['password' => self::CURRENT_PASSWORD]);
    }

    // ---- Profile edit mode -------------------------------------------------------------------

    public function test_profile_is_read_only_until_edit(): void
    {
        $html = $this->actingAs($this->student())->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<fieldset class="space-y-6"\s+disabled\s+x-bind:disabled="! editing">/', $html);
        $this->assertStringContainsString('x-data="editableForm(false)"', $html);
        $this->assertStringContainsString('data-edit-button', $html);
        $this->assertStringContainsString('x-on:click="cancel()"', $html);
    }

    public function test_profile_reopens_in_edit_mode_after_a_validation_error_and_keeps_old_input(): void
    {
        $user = $this->student();

        $html = $this->actingAs($user)->from(route('profile.edit'))->followingRedirects()
            ->patch(route('profile.update'), ['last_name' => 'Cruz2', 'first_name' => 'Ana', 'email' => $user->email])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('may only contain letters', $html);
        $this->assertStringContainsString('x-data="editableForm(true)"', $html);
        $this->assertStringContainsString('value="Cruz2"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
    }

    public function test_staff_edit_pages_are_read_only_until_edit(): void
    {
        $director = User::factory()->director()->create();
        $college = College::factory()->create();
        $program = Program::factory()->for($college)->create();
        $counselor = User::factory()->counselor([$college])->create();

        foreach ([
            route('director.counselors.edit', $counselor),
            route('director.colleges.edit', $college),
            route('director.programs.edit', $program),
        ] as $url) {
            $html = $this->actingAs($director)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('editableForm(false)', $html, $url);
            $this->assertMatchesRegularExpression('/<fieldset[^>]*\sdisabled\s+x-bind:disabled="! editing"/', $html, $url);
        }

        // Create pages stay editable.
        $html = $this->actingAs($director)->get(route('director.counselors.create'))->getContent();
        $this->assertStringContainsString('editableForm(true)', $html);
    }

    // ---- Passwords --------------------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function weakPasswords(): array
    {
        return [
            'too short' => ['Ab-1234', 'at least 10 characters'],
            'no uppercase' => ['abcdefgh-123', 'uppercase and one lowercase'],
            'no lowercase' => ['ABCDEFGH-123', 'uppercase and one lowercase'],
            'no number' => ['Abcdefgh-xyz', 'at least one number'],
            'no symbol' => ['Abcdefgh1234', 'at least one symbol'],
            'same as current' => [self::CURRENT_PASSWORD, 'different from your current one'],
        ];
    }

    #[DataProvider('weakPasswords')]
    public function test_each_password_rule_is_enforced(string $password, string $message): void
    {
        $user = $this->student();

        $this->actingAs($user)->from(route('profile.edit'))->put(route('password.update'), [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrorsIn('updatePassword', 'password');

        $this->assertStringContainsString($message, session('errors')->getBag('updatePassword')->first('password'));
        $this->assertTrue(Hash::check(self::CURRENT_PASSWORD, $user->fresh()->password));
    }

    public function test_current_password_and_confirmation_are_required(): void
    {
        $user = $this->student();

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'Wrong-pass-2026',
            'password' => 'Brand-new-2026',
            'password_confirmation' => 'Different-2026',
        ])->assertSessionHasErrorsIn('updatePassword', ['current_password', 'password']);
    }

    public function test_breached_passwords_are_rejected_when_the_check_is_online(): void
    {
        $password = 'Breached-pass-1';
        $hash = strtoupper(sha1($password));
        $this->pwnedPasswords = fn () => Http::response(substr($hash, 5).":42\r\n", 200);

        $this->actingAs($this->student())->put(route('password.update'), [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_password_check_does_not_block_when_the_breach_service_is_offline(): void
    {
        $this->pwnedPasswords = fn () => Http::failedConnection();
        Log::spy();

        $this->actingAs($this->student())->put(route('password.update'), [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => 'Brand-new-2026',
            'password_confirmation' => 'Brand-new-2026',
        ])->assertSessionHasNoErrors();

        // Being offline is expected: a one-line warning, not an error with a stack trace.
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'Breached-password check unavailable'))->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_changing_the_password_signs_out_other_sessions(): void
    {
        $user = $this->student();
        $oldHash = $user->password;

        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => self::CURRENT_PASSWORD,
            'password' => 'Brand-new-2026',
            'password_confirmation' => 'Brand-new-2026',
        ])->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');

        $user->refresh();
        $this->assertTrue(Hash::check('Brand-new-2026', $user->password));

        // Another device still carries the old password hash in its session: it is signed out.
        $this->app['auth']->forgetGuards();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])
            ->get(route('student.dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_forms_show_the_live_rule_checklist(): void
    {
        $this->actingAs($this->student())->get(route('profile.edit'))->assertSee('passwordRules(', false);
        $this->app['auth']->forgetGuards();
        $this->get(route('register'))->assertSee('passwordRules(', false);
    }

    // ---- Names, phones, dates ---------------------------------------------------------------

    /**
     * @return array<string, array{string, bool}>
     */
    public static function names(): array
    {
        return [
            'ñ, hyphen and period' => ['Peña-Dela Cruz Jr.', true],
            'apostrophe and accent' => ["D'Angelo José", true],
            'digits' => ['Juan2', false],
            'symbols' => ['Juan <b>', false],
            'leading space-only' => [' ', false],
        ];
    }

    #[DataProvider('names')]
    public function test_names_allow_letters_spaces_ñ_hyphens_periods_and_apostrophes(string $name, bool $valid): void
    {
        $response = $this->post('/register', [
            'last_name' => $name,
            'first_name' => 'Juan',
            'student_number' => '26-00001',
            'program_id' => Program::factory()->create()->id,
            'year_level' => 1,
            'email' => 'juan@example.com',
            'password' => 'Student-pass-2026',
            'password_confirmation' => 'Student-pass-2026',
        ]);

        $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('last_name');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function phones(): array
    {
        return [
            '09 format' => ['09171234567', true],
            '+639 format' => ['+639171234567', true],
            'with spaces and dashes' => ['0917-123 4567', true],
            'too short' => ['0917123456', false],
            'landline' => ['(078) 682-1234', false],
            'not a mobile prefix' => ['08171234567', false],
        ];
    }

    #[DataProvider('phones')]
    public function test_form_5_phone_numbers_must_be_philippine_mobile_numbers(string $phone, bool $valid): void
    {
        $student = $this->student();
        $record = CumulativeRecord::factory()->for($student, 'student')->create();

        $response = $this->actingAs($student)->put(route('student.record.update', [$record, 'personal']), [
            'last_name' => 'Dela Cruz', 'first_name' => 'Juan', 'program_id' => $student->program_id, 'year_level' => 1,
            'home_address' => 'Santiago City', 'sex' => 'male', 'civil_status' => 'single', 'contact_number' => $phone,
            'email' => 'juan@example.com', 'birth_date' => '2008-01-15', 'place_of_birth' => 'Santiago City',
            'nationality' => 'Filipino', 'emergency_contact_name' => 'Maria Dela Cruz', 'emergency_contact_address' => 'Santiago City',
            'emergency_contact_number' => '09181234567',
        ]);

        if ($valid) {
            $response->assertSessionHasNoErrors();
            $this->assertMatchesRegularExpression('/^(09|\+639)\d{9}$/', $record->fresh()->contact_number);
        } else {
            $response->assertSessionHasErrors('contact_number');
        }
    }

    public function test_dates_cannot_be_in_the_future_and_today_in_manila_is_accepted(): void
    {
        $director = User::factory()->director()->create();
        $student = User::factory()->student(Program::factory()->create())->create();
        $payload = fn (string $date) => ['exam_date' => $date, 'quotient' => 100, 'quotient_descriptor' => 'Average']
            + collect(array_keys(EntranceTestResult::subtests()))->flatMap(fn ($key) => ["{$key}_score" => 10, "{$key}_descriptor" => 'Average'])->all();

        // 06:00 in Manila is still "yesterday" in UTC: today's Manila date must still be accepted.
        $this->travelTo(CarbonImmutable::parse('2026-10-20 06:00', 'Asia/Manila'));
        $this->assertSame('2026-10-20', Validation::today());

        $this->actingAs($director)->post(route('staff.sata.store', $student), $payload('2026-10-20'))->assertSessionHasNoErrors();
        $this->actingAs($director)->post(route('staff.sata.store', $student), $payload('2026-10-21'))->assertSessionHasErrors('exam_date');

        $record = CumulativeRecord::factory()->for($student, 'student')->create();
        $this->actingAs($student)->put(route('student.record.update', [$record, 'personal']), ['birth_date' => '2030-01-01'])
            ->assertSessionHasErrors('birth_date');
    }

    public function test_form_8_rejects_bad_names_and_phones(): void
    {
        $director = User::factory()->director()->create();
        $student = User::factory()->student(Program::factory()->create())->create();

        $this->actingAs($director)->post(route('staff.intake.store', $student), [
            'action' => 'draft', 'name' => 'Ana 123', 'contact_number' => '12345',
        ])->assertSessionHasErrors(['name', 'contact_number']);
    }

    public function test_inputs_carry_matching_html_rules(): void
    {
        $html = $this->get(route('register'))->getContent();
        $input = function (string $id) use ($html): string {
            preg_match('/<input\b[^>]*\bid="'.$id.'"[^>]*>/s', $html, $match);

            return $match[0] ?? '';
        };

        $this->assertStringContainsString('pattern="[\p{L}\p{M}]', $input('last_name'));
        $this->assertStringContainsString('maxlength="100"', $input('last_name'));
        $this->assertStringContainsString('required', $input('last_name'));
        $this->assertStringContainsString('pattern="[A-Za-z0-9\-]+"', $input('student_number'));
        $this->assertStringContainsString('type="email"', $input('email'));
        $this->assertStringContainsString('minlength="10"', $input('password'));
    }

    // ---- Error pages --------------------------------------------------------------------------

    public function test_custom_404_page_is_friendly(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Back to dashboard')
            ->assertSee('glass-card', false);
    }

    public function test_custom_419_page_offers_to_log_in_again(): void
    {
        Route::middleware('web')->post('/_test/expired', fn () => throw new TokenMismatchException);

        $this->post('/_test/expired')
            ->assertStatus(419)
            ->assertSee('Your session has expired')
            ->assertSee('Log in again')
            ->assertSee(route('login'), false);
    }

    public function test_500_page_never_shows_technical_details(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_test/broken', fn () => throw new RuntimeException('SQLSTATE secret table detail'));

        $this->get('/_test/broken')
            ->assertStatus(500)
            ->assertSee('Something went wrong')
            ->assertDontSee('SQLSTATE')
            ->assertDontSee('secret table detail');
    }

    public function test_every_error_page_renders(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $html = view("errors.{$code}")->render();
            $this->assertStringContainsString('Error '.$code, $html);
        }
    }

    // ---- Toasts and confirmations ---------------------------------------------------------------

    public function test_flash_messages_appear_as_toasts(): void
    {
        $director = User::factory()->director()->create();

        $this->actingAs($director)->withSession(['status' => 'Saved it.', 'error' => 'Could not do it.'])
            ->get(route('director.dashboard'))
            ->assertSee('x-data="toasts(', false)
            ->assertSee('Saved it.')
            ->assertSee('Could not do it.');
    }

    public function test_confirm_modal_guards_the_listed_actions(): void
    {
        $college = College::factory()->create();
        $program = Program::factory()->for($college)->create();
        $director = User::factory()->director()->create();
        $counselor = User::factory()->counselor([$college])->create();
        $student = User::factory()->student($program)->create();
        $submitted = CumulativeRecord::factory()->submitted()->for($student, 'student')->create();
        $sata = EntranceTestResult::factory()->for($student, 'student')->create();
        $draftIntake = IntakeInterview::factory()->for($student, 'student')->create();
        $finalIntake = IntakeInterview::factory()->finalized()->for($student, 'student')->create();

        $pages = [
            'profile save' => [route('profile.edit'), 'Save the changes to your profile?'],
            'counselor save' => [route('director.counselors.edit', $counselor), 'Save the changes to'],
            'counselor deactivate' => [route('director.counselors.index'), 'Deactivate '],
            'college save' => [route('director.colleges.edit', $college), 'Save the changes to '.$college->code],
            'program save and delete' => [route('director.programs.edit', $program), 'Save the changes to '.$program->code],
            'mark reviewed' => [route('staff.records.show', $submitted), 'Mark this record as reviewed?'],
            'SATA edit' => [route('staff.sata.edit', $sata), 'Save the changes to this SATA result?'],
            'Form 8 draft edit and finalize' => [route('staff.intake.edit', $draftIntake), 'Finalize this intake interview?'],
            'Form 8 finalized edit' => [route('staff.intake.edit', $finalIntake), 'Save the changes to this finalized intake interview?'],
        ];

        foreach ($pages as $label => [$url, $message]) {
            $html = $this->actingAs($director)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('data-confirm="'.e($message), $html, $label);
            $this->assertStringContainsString('x-data x-show="$store.confirm.open"', $html, "{$label}: modal markup");
            $this->assertStringNotContainsString('confirm(@js', $html, $label);
        }

        $html = $this->actingAs($director)->get(route('director.colleges.edit', College::factory()->create()))->getContent();
        $this->assertStringContainsString('data-confirm-danger', $html, 'Delete college');
        $this->assertStringContainsString('data-confirm="'.e('Save the changes to this intake interview draft?'), $this->actingAs($director)->get(route('staff.intake.edit', $draftIntake))->getContent());
    }

    public function test_form_5_asks_only_on_final_submit(): void
    {
        $student = $this->student();
        $record = CumulativeRecord::factory()->completed()->for($student, 'student')->create();

        $step = $this->actingAs($student)->get(route('student.record.edit', [$record, 'personal']))->getContent();
        $this->assertStringNotContainsString('data-confirm="', $step);

        $review = $this->actingAs($student)->get(route('student.record.edit', [$record, 'review']))->getContent();
        $this->assertStringContainsString('data-confirm="'.e('Submit your Student Cumulative Record to the HDC?'), $review);
    }
}
