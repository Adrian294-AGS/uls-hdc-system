<?php

namespace App\Support\Imports;

use App\Enums\Role;
use App\Models\Program;
use App\Models\User;
use App\Support\Form5;
use App\Support\Validation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Student roster: creates student accounts. The temporary password is the student number and
 * must be changed on first login. Counselors may only import into programs of their colleges.
 */
final class RosterImport extends ImportType
{
    /**
     * @var Collection<string, int>|null program code (upper case) => id
     */
    private ?Collection $programs = null;

    public function key(): string
    {
        return 'roster';
    }

    public function label(): string
    {
        return 'Student roster';
    }

    public function columns(): array
    {
        return [
            'last_name' => ['label' => 'Last name', 'required' => true, 'description' => 'Letters, spaces, ñ, periods, hyphens and apostrophes only.', 'example' => 'Dela Cruz'],
            'first_name' => ['label' => 'First name', 'required' => true, 'description' => 'Same rules as the last name.', 'example' => 'Juan'],
            'middle_name' => ['label' => 'Middle name', 'required' => false, 'description' => 'Optional.', 'example' => 'Santos'],
            'student_number' => ['label' => 'Student number', 'required' => true, 'description' => 'Letters, numbers and dashes. Rows whose student number already exists are skipped. Also the temporary password.', 'example' => self::EXAMPLE_PREFIX.'-26-00001'],
            'email' => ['label' => 'Email', 'required' => true, 'description' => 'A valid e-mail address. Rows whose e-mail already exists are skipped.', 'example' => 'juan.delacruz@example.com'],
            'program_code' => ['label' => 'Program code', 'required' => true, 'description' => 'One of the program codes in the dropdown (only programs in your colleges).', 'example' => (string) ($this->programs()->keys()->first() ?? 'BSIT')],
            'year_level' => ['label' => 'Year level', 'required' => true, 'description' => 'A number from 1 to 5.', 'example' => '1'],
        ];
    }

    public function choices(): array
    {
        return [
            'program_code' => $this->programs()->keys()->all(),
            'year_level' => array_map('strval', array_keys(Form5::YEAR_LEVELS)),
        ];
    }

    protected function normalize(array $values): array
    {
        $values = parent::normalize($values);
        $values['email'] = isset($values['email']) ? mb_strtolower($values['email']) : null;
        $values['program_code'] = isset($values['program_code']) ? mb_strtoupper($values['program_code']) : null;
        // Accept "1", "1st Year" or "1.0" (Excel numbers).
        if (isset($values['year_level']) && preg_match('/^\s*(\d)/', $values['year_level'], $match)) {
            $values['year_level'] = $match[1];
        }

        return $values;
    }

    protected function rules(array $values): array
    {
        return [
            'last_name' => Validation::name(),
            'first_name' => Validation::name(),
            'middle_name' => Validation::name(required: false),
            'student_number' => Validation::studentNumber(),
            'email' => ['required', 'email', 'max:255'],
            'program_code' => ['required', Rule::in($this->programs()->keys()->all())],
            'year_level' => ['required', 'integer', Rule::in(array_keys(Form5::YEAR_LEVELS))],
        ];
    }

    protected function messages(): array
    {
        return [
            ...Validation::messages(['last_name' => 'name', 'first_name' => 'name', 'middle_name' => 'name', 'student_number' => 'student_number']),
            'program_code.in' => 'Unknown program code, or the program is not in your colleges.',
            'year_level.in' => 'The year level must be a number from 1 to 5.',
        ];
    }

    protected function skipReason(array $values): ?string
    {
        if (User::where('student_number', $values['student_number'])->exists()) {
            return 'A student with this student number already exists.';
        }

        if (User::where('email', $values['email'])->exists()) {
            return 'An account with this e-mail already exists.';
        }

        return null;
    }

    protected function duplicateKey(array $values): string
    {
        return mb_strtolower($values['student_number']).'|'.$values['email'];
    }

    public function check(array $rows): array
    {
        $results = parent::check($rows);

        // The same e-mail on two different student numbers is also a duplicate.
        $emails = [];
        foreach ($results as $index => $result) {
            if ($result['status'] !== 'valid') {
                continue;
            }
            $email = $result['values']['email'];
            if (isset($emails[$email])) {
                $results[$index]['status'] = 'skipped';
                $results[$index]['reason'] = "Same e-mail as row {$emails[$email]} in this file.";
            } else {
                $emails[$email] = $result['row'];
            }
        }

        return $results;
    }

    public function import(array $values): void
    {
        $student = new User([
            'last_name' => $values['last_name'],
            'first_name' => $values['first_name'],
            'middle_name' => $values['middle_name'] ?? null,
            'student_number' => $values['student_number'],
            'email' => $values['email'],
            'program_id' => $this->programs()[$values['program_code']],
            'year_level' => (int) $values['year_level'],
            'password' => Hash::make($values['student_number']),
        ]);
        $student->role = Role::Student;
        $student->must_change_password = true;
        $student->save();
    }

    /**
     * Programs in the importer's colleges.
     *
     * @return Collection<string, int>
     */
    private function programs(): Collection
    {
        return $this->programs ??= Program::whereIn('college_id', $this->user->accessibleCollegeIds())
            ->orderBy('code')
            ->get(['id', 'code'])
            ->mapWithKeys(fn (Program $program) => [mb_strtoupper($program->code) => $program->id]);
    }
}
