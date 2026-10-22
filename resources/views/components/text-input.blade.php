{{-- Text input. `kind` (name, phone, student-number, email) adds the HTML rules from App\Support\Validation. --}}
@props(['disabled' => false, 'kind' => null, 'invalid' => false])

<input @disabled($disabled) @if ($invalid) aria-invalid="true" @endif
    {{ $attributes->merge(\App\Support\Validation::htmlAttributes($kind))->merge(['class' => 'glass-input focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:focus:border-indigo-400 dark:focus:ring-indigo-400 aria-[invalid=true]:border-red-500 disabled:cursor-default disabled:border-transparent disabled:bg-transparent disabled:shadow-none disabled:px-0 disabled:text-gray-900 dark:disabled:text-gray-100 dark:disabled:border-transparent dark:disabled:bg-transparent']) }}>
