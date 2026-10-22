<?php

namespace App\Http\Requests\Director;

use App\Models\College;
use App\Models\User;
use App\Support\Validation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create/edit a counselor account. Never accepts a role: the controller sets it.
 */
class CounselorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isDirector();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $counselor = $this->route('counselor');

        return [
            'last_name' => Validation::name(),
            'first_name' => Validation::name(),
            'middle_name' => Validation::name(required: false),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($counselor)],
            // Required when creating; optional on edit (filled = reset to a new temporary password).
            'password' => [$counselor ? 'nullable' : 'required', 'string', Password::defaults()],
            'colleges' => ['required', 'array', 'min:1'],
            'colleges.*' => ['integer', 'distinct', Rule::exists(College::class, 'id')],
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
            'colleges.required' => 'Assign at least one college.',
            'colleges.min' => 'Assign at least one college.',
            ...Validation::messages(['last_name' => 'name', 'first_name' => 'name', 'middle_name' => 'name']),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['password' => 'temporary password'];
    }
}
