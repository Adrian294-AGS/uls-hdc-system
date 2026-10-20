<?php

namespace App\Http\Requests\Staff;

use App\Models\EntranceTestResult;
use App\Support\Validation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a SATA result. Authorization is done in the controller via the policy.
 */
class EntranceTestResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $result = $this->route('result');

        return $result
            ? $this->user()->can('update', $result)
            : $this->user()->can('create', [EntranceTestResult::class, $this->route('student')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $descriptors = Rule::in(config('sata.descriptors'));
        $subtest = config('sata.limits.subtest');
        $quotient = config('sata.limits.quotient');

        $rules = [
            'exam_date' => ['required', 'date', 'before_or_equal:'.Validation::today(), 'after:2000-01-01'],
            'quotient' => ['required', 'integer', "between:{$quotient['min']},{$quotient['max']}"],
            'quotient_descriptor' => ['required', $descriptors],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (array_keys(EntranceTestResult::subtests()) as $key) {
            $rules["{$key}_score"] = ['required', 'integer', "between:{$subtest['min']},{$subtest['max']}"];
            $rules["{$key}_descriptor"] = ['required', $descriptors];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'exam_date.before_or_equal' => 'The date of examination cannot be in the future.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['exam_date' => 'date of examination', 'quotient_descriptor' => 'quotient descriptor'];

        foreach (EntranceTestResult::subtests() as $key => $subtest) {
            $attributes["{$key}_score"] = "{$subtest['code']} standard score";
            $attributes["{$key}_descriptor"] = "{$subtest['code']} descriptor";
        }

        return $attributes;
    }
}
