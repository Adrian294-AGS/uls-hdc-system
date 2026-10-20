<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Change your temporary password') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Your account was created with a temporary password. Please choose your own password before continuing.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">

        <div>
            <x-input-label for="current_password" :value="__('Temporary password')" />
            <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" required autofocus autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" :invalid="$errors->has('password')" required minlength="10" autocomplete="new-password" />
            <x-password-rules for="password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
            <button type="submit" form="logout-form" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Log out') }}</button>
            <x-primary-button>{{ __('Change password') }}</x-primary-button>
        </div>

        @csrf
        @method('PUT')
    </form>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
        @csrf
    </form>
</x-guest-layout>
