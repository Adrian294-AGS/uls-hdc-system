<?php

namespace App\Http\Requests;

use App\Models\CumulativeRecord;
use App\Models\Program;
use App\Support\Form5;
use App\Support\Validation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates one step of the Form 5 wizard. The step comes from the route.
 */
class SaveRecordStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $record = $this->record();

        return $record
            ? $this->user()->can('update', $record)
            : $this->user()->can('create', CumulativeRecord::class);
    }

    public function record(): ?CumulativeRecord
    {
        return $this->route('record');
    }

    public function step(): string
    {
        return $this->route('step', 'entry');
    }

    /**
     * Drop repeatable rows the student left completely empty (selects like level/sex don't count).
     */
    protected function prepareForValidation(): void
    {
        foreach (['contact_number', 'emergency_contact_number'] as $phone) {
            if ($this->has($phone)) {
                $this->merge([$phone => Validation::normalizePhone($this->input($phone))]);
            }
        }

        foreach (['siblings', 'schools', 'organizations'] as $section) {
            if (! is_array($this->input($section))) {
                continue;
            }

            $this->merge([
                $section => array_values(array_filter(
                    $this->input($section),
                    fn ($row) => is_array($row) && collect($row)->except(['level', 'sex'])->filter(fn ($value) => filled($value))->isNotEmpty(),
                )),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->step()) {
            'entry' => $this->entryRules(),
            'personal' => $this->personalRules(),
            'family' => $this->familyRules(),
            'health' => $this->healthRules(),
            'education' => $this->educationRules(),
            'vocational' => $this->vocationalRules(),
            'social' => $this->socialRules(),
            'self' => $this->selfDescriptionRules(),
            'skills' => $this->skillsRules(),
            default => [],
        };
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'school_year.regex' => 'Enter the school year like 2026-2027.',
            'school_year.unique' => 'You already have a record for this school year and semester.',
            'self_description.min' => 'Please check at least five.',
            'self_description.required' => 'Please check at least five.',
            'course_reasons.required' => 'Please check at least one reason.',
            '*.required_if' => 'This field is required.',
            'siblings.*.name.required' => 'Enter the name or remove this row.',
            'schools.*.school_name.required' => 'Enter the name of the school or remove this row.',
            'organizations.*.organization.required' => 'Enter the organization or remove this row.',
            'birth_date.before' => 'The birth date must be in the past.',
            ...Validation::messages([
                'last_name' => 'name', 'first_name' => 'name', 'middle_name' => 'name', 'spouse_name' => 'name',
                'emergency_contact_name' => 'name', 'parents.*.name' => 'name', 'siblings.*.name' => 'name',
                'contact_number' => 'phone', 'emergency_contact_number' => 'phone',
            ]),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'program_id' => 'course preference',
            'birth_date' => 'birth date',
            'normal_eyesight' => 'normal eyesight',
            'normal_hearing' => 'normal hearing',
            'had_surgery' => 'surgical operation',
            'has_allergies' => 'allergies',
            'has_work_experience' => 'work experience',
            'willing_to_see_counselor' => 'this question',
            'course_reason_other' => 'other reason',
            'skills_other' => 'other skill',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->step() !== 'entry' || $validator->errors()->has('school_year')) {
                    return;
                }

                [$start, $end] = array_map('intval', explode('-', (string) $this->input('school_year')));

                if ($end !== $start + 1) {
                    $validator->errors()->add('school_year', 'The second year must follow the first (e.g. 2026-2027).');
                }
            },
        ];
    }

    /**
     * Validated scalar attributes for the record, with fields that do not apply cleared.
     *
     * @return array<string, mixed>
     */
    public function recordAttributes(): array
    {
        $data = collect($this->validated())
            ->except(['photo', 'remove_photo', 'parents', 'siblings', 'schools', 'organizations'])
            ->all();

        $onlyIf = fn (bool $applies, string $key): mixed => $applies ? ($data[$key] ?? null) : null;

        return match ($this->step()) {
            'personal' => [
                ...$data,
                'spouse_name' => $onlyIf($data['civil_status'] === 'married', 'spouse_name'),
                'number_of_children' => $onlyIf($data['civil_status'] === 'married', 'number_of_children'),
            ],
            'family' => [...$data, 'parents_status' => $data['parents_status'] ?? []],
            'health' => [
                ...$data,
                'hospitalization_reason' => $onlyIf((bool) $data['hospitalized'], 'hospitalization_reason'),
                'surgery_details' => $onlyIf((bool) $data['had_surgery'], 'surgery_details'),
                'allergy_details' => $onlyIf((bool) $data['has_allergies'], 'allergy_details'),
            ],
            'education' => [
                ...$data,
                'home_facilities' => $data['home_facilities'] ?? [],
                'interruption_reason' => $onlyIf($data['schooling_nature'] === 'interrupted', 'interruption_reason'),
            ],
            'vocational' => [
                ...$data,
                'course_reason_other' => $onlyIf(in_array('others', $data['course_reasons'], true), 'course_reason_other'),
                'work_experience_when' => $onlyIf((bool) $data['has_work_experience'], 'work_experience_when'),
                'work_experience_details' => $onlyIf((bool) $data['has_work_experience'], 'work_experience_details'),
                'work_experience_likes' => $onlyIf((bool) $data['has_work_experience'], 'work_experience_likes'),
            ],
            'skills' => [
                ...$data,
                'skills_to_develop' => $data['skills_to_develop'] ?? [],
                'skills_other' => $onlyIf(in_array('others', $data['skills_to_develop'] ?? [], true), 'skills_other'),
            ],
            default => $data,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function entryRules(): array
    {
        return [
            'month_of_entry' => ['required', 'integer', Rule::in(array_keys(Form5::MONTHS))],
            'school_year' => [
                'required', 'string', 'regex:/^\d{4}-\d{4}$/',
                Rule::unique('cumulative_records')
                    ->where('user_id', $this->user()->id)
                    ->where('semester', $this->input('semester'))
                    ->ignore($this->record()),
            ],
            'semester' => ['required', Rule::in(array_keys(Form5::SEMESTERS))],
            'student_type' => ['required', Rule::in(array_keys(Form5::STUDENT_TYPES))],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personalRules(): array
    {
        return [
            'last_name' => Validation::name(),
            'first_name' => Validation::name(),
            'middle_name' => Validation::name(required: false),
            'program_id' => ['required', 'integer', Rule::exists(Program::class, 'id')],
            'year_level' => ['required', 'integer', Rule::in(array_keys(Form5::YEAR_LEVELS))],
            'major' => ['nullable', 'string', 'max:255'],
            'home_address' => ['required', 'string', 'max:500'],
            'sex' => ['required', Rule::in(array_keys(Form5::SEXES))],
            'civil_status' => ['required', Rule::in(array_keys(Form5::CIVIL_STATUSES))],
            'contact_number' => Validation::phone(),
            'email' => ['required', 'email', 'max:255'],
            'birth_date' => ['required', 'date', 'before:'.Validation::today(), 'after:1900-01-01'],
            'place_of_birth' => ['required', 'string', 'max:255'],
            'nationality' => ['required', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'boarding_address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => Validation::name(max: 255),
            'emergency_contact_address' => ['required', 'string', 'max:500'],
            'emergency_contact_number' => Validation::phone(),
            'spouse_name' => ['nullable', 'required_if:civil_status,married', 'string', 'max:255', 'regex:'.Validation::NAME_REGEX],
            'number_of_children' => ['nullable', 'integer', 'min:0', 'max:30'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function familyRules(): array
    {
        $rules = [
            'parents_status' => ['nullable', 'array'],
            'parents_status.*' => [Rule::in(array_keys(Form5::PARENTS_STATUSES))],
            'family_view' => ['nullable', 'string', 'max:5000'],
            'family_concern' => ['nullable', 'string', 'max:5000'],
            'home_language' => ['nullable', 'string', 'max:255'],
            'siblings' => ['nullable', 'array', 'max:'.Form5::MAX_SIBLINGS],
            'siblings.*.name' => ['required', 'string', 'max:255', 'regex:'.Validation::NAME_REGEX],
            'siblings.*.sex' => ['nullable', Rule::in(array_keys(Form5::SEXES))],
            'siblings.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'siblings.*.year_course_school' => ['nullable', 'string', 'max:255'],
            'siblings.*.occupation' => ['nullable', 'string', 'max:255'],
        ];

        foreach (array_keys(Form5::PARENT_RELATIONS) as $relation) {
            $rules += [
                "parents.$relation.name" => Validation::name(required: false, max: 255),
                "parents.$relation.age" => ['nullable', 'integer', 'min:10', 'max:120'],
                "parents.$relation.religion" => ['nullable', 'string', 'max:100'],
                "parents.$relation.occupation" => ['nullable', 'string', 'max:255'],
                "parents.$relation.monthly_income" => ['nullable', 'string', 'max:100'],
                "parents.$relation.educational_attainment" => ['nullable', 'string', 'max:255'],
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function healthRules(): array
    {
        return [
            'height' => ['nullable', 'string', 'max:30'],
            'weight' => ['nullable', 'string', 'max:30'],
            'handicap' => ['nullable', 'string', 'max:255'],
            'normal_eyesight' => ['required', 'boolean'],
            'normal_hearing' => ['required', 'boolean'],
            'persistent_illness' => ['nullable', 'string', 'max:255'],
            'hospitalized' => ['required', 'boolean'],
            'hospitalization_reason' => ['nullable', 'required_if:hospitalized,1', 'string', 'max:255'],
            'had_surgery' => ['required', 'boolean'],
            'surgery_details' => ['nullable', 'required_if:had_surgery,1', 'string', 'max:255'],
            'has_allergies' => ['required', 'boolean'],
            'allergy_details' => ['nullable', 'required_if:has_allergies,1', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function educationRules(): array
    {
        return [
            'schools' => ['nullable', 'array', 'max:'.Form5::MAX_SCHOOLS],
            'schools.*.level' => ['required', Rule::in(array_keys(Form5::SCHOOL_LEVELS))],
            'schools.*.school_name' => ['required', 'string', 'max:255'],
            'schools.*.place' => ['nullable', 'string', 'max:255'],
            'schools.*.inclusive_years' => ['nullable', 'string', 'max:50'],
            'schools.*.grade_average' => ['nullable', 'string', 'max:50'],
            'scholarships_honors' => ['nullable', 'string', 'max:500'],
            'schooling_nature' => ['required', Rule::in(array_keys(Form5::SCHOOLING_NATURES))],
            'interruption_reason' => ['nullable', 'required_if:schooling_nature,interrupted', 'string', 'max:2000'],
            'scholarship_to_apply' => ['nullable', 'string', 'max:255'],
            'difficult_subjects' => ['nullable', 'string', 'max:255'],
            'best_liked_subjects' => ['nullable', 'string', 'max:255'],
            'home_facilities' => ['nullable', 'array'],
            'home_facilities.*' => [Rule::in(array_keys(Form5::HOME_FACILITIES))],
            'recent_book' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vocationalRules(): array
    {
        return [
            'course_reasons' => ['required', 'array', 'min:1'],
            'course_reasons.*' => [Rule::in(array_keys(Form5::COURSE_REASONS))],
            'course_reason_other' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => in_array('others', (array) $this->input('course_reasons'), true)),
            ],
            'second_course' => ['nullable', 'string', 'max:255'],
            'considered_occupation' => ['nullable', 'string', 'max:2000'],
            'has_work_experience' => ['required', 'boolean'],
            'work_experience_when' => ['nullable', 'required_if:has_work_experience,1', 'string', 'max:255'],
            'work_experience_details' => ['nullable', 'required_if:has_work_experience,1', 'string', 'max:255'],
            'work_experience_likes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function socialRules(): array
    {
        return [
            'organizations' => ['nullable', 'array', 'max:'.Form5::MAX_ORGANIZATIONS],
            'organizations.*.organization' => ['required', 'string', 'max:255'],
            'organizations.*.activity' => ['nullable', 'string', 'max:255'],
            'hobbies' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function selfDescriptionRules(): array
    {
        return [
            'self_description' => ['required', 'array', 'min:'.Form5::MIN_SELF_DESCRIPTIONS],
            'self_description.*' => ['distinct', Rule::in(array_keys(Form5::SELF_DESCRIPTIONS))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function skillsRules(): array
    {
        return [
            'skills_to_develop' => ['nullable', 'array'],
            'skills_to_develop.*' => [Rule::in(array_keys(Form5::SKILLS))],
            'skills_other' => [
                'nullable', 'string', 'max:255',
                Rule::requiredIf(fn () => in_array('others', (array) $this->input('skills_to_develop'), true)),
            ],
            'willing_to_see_counselor' => ['required', 'boolean'],
            'counselor_willingness_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
