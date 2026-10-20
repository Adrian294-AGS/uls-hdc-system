@php use App\Support\Form5; @endphp

<p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
    {{ __('Please check your answers. Use "Edit" on any section to change it.') }}
</p>

@error('record')
    <div class="mt-4 rounded-md bg-red-50 dark:bg-red-500/10 px-4 py-3 text-sm text-red-800 dark:text-red-300" role="alert">{{ $message }}</div>
@enderror

<div class="mt-6">
    @include('student.record.summary', ['editable' => true])
</div>

<form method="POST" action="{{ route('student.record.submit', $record) }}" class="mt-8 space-y-4 border-t border-gray-200 dark:border-gray-700 pt-6"
    data-confirm="{{ $record->submitted_at ? __('Send your updated record to the HDC?') : __('Submit your Student Cumulative Record to the HDC? You can still edit it until a counselor reviews it.') }}"
    data-confirm-title="{{ __('Submit your record?') }}" data-confirm-button="{{ $record->submitted_at ? __('Update submission') : __('Submit') }}">
    @csrf

    <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ __('Data privacy consent') }}</h4>

    <label class="flex items-start gap-3 text-sm text-gray-800 dark:text-gray-200">
        <input type="checkbox" name="consent" value="1" required class=""mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-indigo-500 dark:focus:ring-indigo-400 dark:focus:ring-offset-gray-800"
            @checked(old('consent', $record->consent_given))>
        <span>{{ Form5::CONSENT_TEXT }}</span>
    </label>
    <x-input-error :messages="$errors->get('consent')" />

    <div class="max-w-md">
        <x-form.text name="consent_name" label="Signature over printed name (type your full name)" kind="name" maxlength="255" :value="$record->consent_name" required autocomplete="name" />
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
        <a href="{{ route('student.record.edit', [$record, Form5::previousStep('review')]) }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">
            &larr; {{ __('Back') }}
        </a>
        <x-primary-button>
            {{ $record->submitted_at ? __('Update submission') : __('Submit my record') }}
        </x-primary-button>
    </div>
</form>
