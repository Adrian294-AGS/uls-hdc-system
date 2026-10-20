<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ __('Student Registration') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Create your account to fill out your HDC Student Cumulative Record. Staff accounts are created by the HDC office.') }}
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Last Name -->
        <div>
            <x-input-label for="last_name" :value="__('Last Name')" />
            <x-text-input id="last_name" class="block mt-1 w-full" type="text" name="last_name" :value="old('last_name')" kind="name" :invalid="$errors->has('last_name')" required autofocus autocomplete="family-name" />
            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
        </div>

        <!-- First Name -->
        <div class="mt-4">
            <x-input-label for="first_name" :value="__('First Name')" />
            <x-text-input id="first_name" class="block mt-1 w-full" type="text" name="first_name" :value="old('first_name')" kind="name" :invalid="$errors->has('first_name')" required autocomplete="given-name" />
            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
        </div>

        <!-- Middle Name -->
        <div class="mt-4">
            <x-input-label for="middle_name" :value="__('Middle Name (optional)')" />
            <x-text-input id="middle_name" class="block mt-1 w-full" type="text" name="middle_name" :value="old('middle_name')" kind="name" :invalid="$errors->has('middle_name')" autocomplete="additional-name" />
            <x-input-error :messages="$errors->get('middle_name')" class="mt-2" />
        </div>

        <!-- Student Number -->
        <div class="mt-4">
            <x-input-label for="student_number" :value="__('Student Number')" />
            <x-text-input id="student_number" class="block mt-1 w-full" type="text" name="student_number" :value="old('student_number')" kind="student-number" :invalid="$errors->has('student_number')" required />
            <x-input-error :messages="$errors->get('student_number')" class="mt-2" />
        </div>

        <!-- Program (grouped by college) -->
        <div class="mt-4">
            <x-input-label for="program_id" :value="__('Program')" />
            <select id="program_id" name="program_id" required class="block mt-1 w-full glass-input focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:focus:border-indigo-400 dark:focus:ring-indigo-400">
                <option value="" disabled @selected(! old('program_id'))>{{ __('Select your program') }}</option>
                @foreach ($colleges as $college)
                    <optgroup label="{{ $college->code }} – {{ $college->name }}">
                        @foreach ($college->programs as $program)
                            <option value="{{ $program->id }}" @selected((string) old('program_id') === (string) $program->id)>
                                {{ $program->code }} – {{ $program->name }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('program_id')" class="mt-2" />
        </div>

        <!-- Year Level -->
        <div class="mt-4">
            <x-input-label for="year_level" :value="__('Year Level')" />
            <select id="year_level" name="year_level" required class="block mt-1 w-full glass-input focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:focus:border-indigo-400 dark:focus:ring-indigo-400">
                <option value="" disabled @selected(! old('year_level'))>{{ __('Select your year level') }}</option>
                @foreach ([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year', 5 => '5th Year'] as $level => $label)
                    <option value="{{ $level }}" @selected((string) old('year_level') === (string) $level)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('year_level')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" kind="email" :invalid="$errors->has('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password" :invalid="$errors->has('password')"
                            required minlength="10" autocomplete="new-password" />

            <x-password-rules for="password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
