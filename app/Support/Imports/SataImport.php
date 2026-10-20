<?php

namespace App\Support\Imports;

use App\Http\Requests\Staff\EntranceTestResultRequest;
use App\Models\EntranceTestResult;
use App\Models\User;
use App\Support\Validation;
use Illuminate\Support\Collection;

/**
 * SATA results: same rules as the SATA encoding form. Students must exist and be in the
 * importer's colleges; a result for the same exam date is skipped.
 */
final class SataImport extends ImportType
{
    /**
     * @var Collection<string, int>|null visible students: student number (lower case) => id
     */
    private ?Collection $students = null;

    public function key(): string
    {
        return 'sata';
    }

    public function label(): string
    {
        return 'SATA results';
    }

    public function columns(): array
    {
        $limits = config('sata.limits');
        $columns = [
            'student_number' => ['label' => 'Student number', 'required' => true, 'description' => 'Must belong to an existing student in your colleges.', 'example' => self::EXAMPLE_PREFIX.'-26-00001'],
            'exam_date' => ['label' => 'Exam date', 'required' => true, 'description' => 'Date of examination as YYYY-MM-DD (e.g. 2026-06-15). Not in the future.', 'example' => '2026-06-15'],
        ];

        foreach (EntranceTestResult::subtests() as $key => $subtest) {
            $columns["{$key}_score"] = ['label' => "{$subtest['code']} score", 'required' => true, 'description' => "{$subtest['name']} standard score, {$limits['subtest']['min']} to {$limits['subtest']['max']}.", 'example' => '10'];
            $columns["{$key}_descriptor"] = ['label' => "{$subtest['code']} descriptor", 'required' => true, 'description' => "{$subtest['name']} descriptor (dropdown).", 'example' => 'Average'];
        }

        return $columns + [
            'quotient' => ['label' => 'Quotient', 'required' => true, 'description' => "Overall quotient, {$limits['quotient']['min']} to {$limits['quotient']['max']}.", 'example' => '104'],
            'quotient_descriptor' => ['label' => 'Quotient descriptor', 'required' => true, 'description' => 'Quotient descriptor (dropdown).', 'example' => 'Average'],
        ];
    }

    public function choices(): array
    {
        $descriptors = config('sata.descriptors');
        $choices = ['quotient_descriptor' => $descriptors];
        foreach (array_keys(EntranceTestResult::subtests()) as $key) {
            $choices["{$key}_descriptor"] = $descriptors;
        }

        return $choices;
    }

    protected function normalize(array $values): array
    {
        $values = parent::normalize($values);
        $descriptors = collect(config('sata.descriptors'))->mapWithKeys(fn (string $descriptor) => [mb_strtolower($descriptor) => $descriptor]);

        foreach ($values as $key => $value) {
            if ($value !== null && str_ends_with($key, '_descriptor')) {
                $values[$key] = $descriptors[mb_strtolower($value)] ?? $value;
            }
            if ($value !== null && (str_ends_with($key, '_score') || $key === 'quotient') && is_numeric($value) && (float) $value == (int) $value) {
                $values[$key] = (string) (int) $value;
            }
        }

        return $values;
    }

    protected function rules(array $values): array
    {
        $rules = (new EntranceTestResultRequest)->rules();
        unset($rules['remarks']);

        return [
            'student_number' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $this->students()->has(mb_strtolower((string) $value))) {
                    $fail('No student with this student number in your colleges.');
                }
            }],
            ...$rules,
            'exam_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Validation::today(), 'after:2000-01-01'],
        ];
    }

    protected function messages(): array
    {
        return [
            'exam_date.date_format' => 'Use the date format YYYY-MM-DD, e.g. 2026-06-15.',
            'exam_date.before_or_equal' => 'The exam date cannot be in the future.',
        ];
    }

    protected function skipReason(array $values): ?string
    {
        $exists = EntranceTestResult::where('student_id', $this->studentId($values['student_number']))
            ->whereDate('exam_date', $values['exam_date'])
            ->exists();

        return $exists ? 'This student already has a SATA result for this exam date.' : null;
    }

    protected function duplicateKey(array $values): string
    {
        return mb_strtolower($values['student_number']).'|'.$values['exam_date'];
    }

    public function import(array $values): void
    {
        $result = new EntranceTestResult(collect($values)->except('student_number')->all());
        $result->student_id = $this->studentId($values['student_number']);
        $result->encoded_by = $this->user->id;
        $result->updated_by = $this->user->id;
        $result->save();
    }

    private function studentId(string $studentNumber): int
    {
        return $this->students()[mb_strtolower($studentNumber)];
    }

    /**
     * @return Collection<string, int>
     */
    private function students(): Collection
    {
        return $this->students ??= User::query()
            ->studentsVisibleTo($this->user)
            ->whereNotNull('student_number')
            ->pluck('users.id', 'student_number')
            ->mapWithKeys(fn (int $id, string $number) => [mb_strtolower($number) => $id]);
    }
}
