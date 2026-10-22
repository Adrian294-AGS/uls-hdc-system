<?php

namespace App\Http\Requests\Staff;

use App\Models\IntakeInterview;
use App\Support\Form5;
use App\Support\Form8;
use App\Support\Validation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a Form 8 intake interview. Drafts may be saved with any field empty; finalizing
 * (or saving an already-finalized interview) requires the interview date/time and the
 * interviewee's acknowledgment.
 */
class IntakeInterviewRequest extends FormRequest
{
    public const ACTION_DRAFT = 'draft';

    public const ACTION_FINALIZE = 'finalize';

    protected function prepareForValidation(): void
    {
        foreach (['contact_number', 'emergency_contact_number'] as $phone) {
            if ($this->has($phone)) {
                $this->merge([$phone => Validation::normalizePhone($this->input($phone))]);
            }
        }
    }

    public function authorize(): bool
    {
        $interview = $this->route('interview');

        return $interview
            ? $this->user()->can('update', $interview)
            : $this->user()->can('create', [IntakeInterview::class, $this->route('student')]);
    }

    /**
     * Whether this save leaves the interview finalized.
     */
    public function finalizes(): bool
    {
        return $this->input('action') === self::ACTION_FINALIZE
            || ($this->route('interview')?->isFinalized() ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $finalizing = $this->finalizes();

        $rules = [
            'action' => ['required', Rule::in([self::ACTION_DRAFT, self::ACTION_FINALIZE])],
            'interview_at' => [$finalizing ? 'required' : 'nullable', 'date'],
            'interviewee_acknowledged' => $finalizing ? ['accepted'] : ['nullable', 'boolean'],
            'date_of_entry' => ['nullable', 'date', 'before_or_equal:'.Validation::today()],
            'academic_status' => ['nullable', 'string', 'max:100'],

            'name' => Validation::name(required: false, max: 255),
            'nickname' => Validation::name(required: false),
            'course' => ['nullable', 'string', 'max:255'],
            'home_address' => ['nullable', 'string', 'max:500'],
            'age' => ['nullable', 'integer', 'between:10,99'],
            'sex' => ['nullable', Rule::in(array_keys(Form5::SEXES))],
            'birth_date' => ['nullable', 'date', 'before:'.Validation::today()],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['nullable', Rule::in(array_keys(Form5::CIVIL_STATUSES))],
            'spouse_name' => Validation::name(required: false, max: 255),
            'number_of_children' => ['nullable', 'integer', 'between:0,30'],
            'citizenship' => ['nullable', Rule::in(array_keys(Form8::CITIZENSHIPS))],
            'citizenship_other' => ['nullable', 'required_if:citizenship,other', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => Validation::phone(required: false),
            'boarding_address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => Validation::name(required: false, max: 255),
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_number' => Validation::phone(required: false),

            'relationship_during_intake' => ['nullable', 'array'],
            'relationship_during_intake.*' => [Rule::in(array_keys(Form8::RELATIONSHIP_DURING_INTAKE))],
        ];

        foreach (Form8::yesNoFields() as $field) {
            $rules[$field] = ['nullable', 'boolean'];
        }

        foreach (Form8::SPECIFY_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:1000'];
        }

        foreach (array_keys(Form8::NOTES) as $field) {
            $rules[$field] = ['nullable', 'string', 'max:10000'];
        }

        return $rules;
    }

    /**
     * Validated form data, ready to fill the model (no workflow fields).
     *
     * @return array<string, mixed>
     */
    public function interviewData(): array
    {
        $data = collect($this->validated())->except('action')->all();
        $data['interviewee_acknowledged'] = $this->boolean('interviewee_acknowledged');
        $data['relationship_during_intake'] = array_values($data['relationship_during_intake'] ?? []);

        if (($data['citizenship'] ?? null) !== 'other') {
            $data['citizenship_other'] = null;
        }

        return $data;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'interview_at.required' => 'Enter the date and time of the intake interview before finalizing.',
            'interviewee_acknowledged.accepted' => 'The interviewee must acknowledge the interview before it can be finalized.',
            'citizenship_other.required_if' => 'Please specify the citizenship.',
            'date_of_entry.before_or_equal' => 'The date of entry cannot be in the future.',
            ...Validation::messages([
                'name' => 'name', 'nickname' => 'name', 'spouse_name' => 'name', 'emergency_contact_name' => 'name',
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
            'interview_at' => 'date and time of intake interview',
            'birth_date' => 'birthdate',
            'emergency_contact_name' => 'emergency contact name',
            'emergency_contact_relationship' => 'relationship',
        ];
    }
}
