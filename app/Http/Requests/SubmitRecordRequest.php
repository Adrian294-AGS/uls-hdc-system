<?php

namespace App\Http\Requests;

use App\Models\CumulativeRecord;
use App\Support\Form5;
use App\Support\Validation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Final Form 5 submission: requires the data privacy consent and every step completed.
 */
class SubmitRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->record());
    }

    public function record(): CumulativeRecord
    {
        return $this->route('record');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'consent' => ['accepted'],
            'consent_name' => Validation::name(max: 255),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => 'You must agree to the Data Privacy Notice before submitting.',
            'consent_name.required' => 'Type your full name as your signature.',
            ...Validation::messages(['consent_name' => 'name']),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $missing = $this->record()->missingSteps();

                if ($missing !== []) {
                    $titles = implode(', ', array_map(fn (string $step) => Form5::STEPS[$step], $missing));
                    $validator->errors()->add('record', "Please complete these sections first: {$titles}.");
                }
            },
        ];
    }
}
