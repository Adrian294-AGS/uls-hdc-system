{{-- Read-only until "Edit"; "Cancel" restores the saved values. Saving asks for confirmation. --}}
@php($hasErrors = $errors->hasAny(['last_name', 'first_name', 'middle_name', 'email']))

<section x-data="editableForm(@js($hasErrors))" data-profile-section>
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Profile Information') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Your name and the e-mail address you use to log in.') }}
            </p>
        </div>
        <x-secondary-button x-show="! editing" x-on:click="edit()" data-edit-button>
            <svg class="me-1.5 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" /></svg>
            {{ __('Edit') }}
        </x-secondary-button>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6"
        data-confirm="{{ __('Save the changes to your profile?') }}" data-confirm-button="{{ __('Save changes') }}">
        @csrf
        @method('patch')

        <fieldset class="space-y-6" @disabled(! $hasErrors) x-bind:disabled="! editing">
            <legend class="sr-only">{{ __('Profile Information') }}</legend>
            <div>
                <x-input-label for="last_name" :value="__('Last Name')" />
                <x-text-input id="last_name" name="last_name" type="text" class="mt-1 block w-full" kind="name" :invalid="$errors->has('last_name')" :value="old('last_name', $user->last_name)" required autocomplete="family-name" />
                <x-input-error class="mt-2" :messages="$errors->get('last_name')" />
            </div>

            <div>
                <x-input-label for="first_name" :value="__('First Name')" />
                <x-text-input id="first_name" name="first_name" type="text" class="mt-1 block w-full" kind="name" :invalid="$errors->has('first_name')" :value="old('first_name', $user->first_name)" required autocomplete="given-name" />
                <x-input-error class="mt-2" :messages="$errors->get('first_name')" />
            </div>

            <div>
                <x-input-label for="middle_name" :value="__('Middle Name (optional)')" />
                <x-text-input id="middle_name" name="middle_name" type="text" class="mt-1 block w-full" kind="name" :invalid="$errors->has('middle_name')" :value="old('middle_name', $user->middle_name)" autocomplete="additional-name" />
                <x-input-error class="mt-2" :messages="$errors->get('middle_name')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" class="mt-1 block w-full" kind="email" :invalid="$errors->has('email')" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}
                        <button form="send-verification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>
                @endif
            </div>
        </fieldset>

        <div class="mt-6 flex items-center gap-3" x-show="editing" @unless ($hasErrors) style="display: none" @endunless>
            <x-primary-button>{{ __('Save') }}</x-primary-button>
            <x-secondary-button x-on:click="cancel()">{{ __('Cancel') }}</x-secondary-button>
        </div>
    </form>
</section>
