@php
    $editing = $counselor->exists;
    $startEditing = ! $editing || $errors->any();
@endphp

<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('director.counselors.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">&larr; {{ __('All counselors') }}</a>
        <h2 class="mt-1 font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $editing ? __('Edit :name', ['name' => $counselor->name]) : __('Add counselor') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if ($editing && ! $counselor->is_active)
                <div class="rounded-md bg-gray-100 dark:bg-gray-700 px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ __('This account is deactivated. Reactivate it from the counselors list to let them log in again.') }}</div>
            @endif

            <div x-data="editableForm(@js($startEditing))">
            <form method="POST" action="{{ $editing ? route('director.counselors.update', $counselor) : route('director.counselors.store') }}"
                class="glass-panel sm:rounded-lg p-4 sm:p-6 space-y-6"
                @if ($editing) data-confirm="{{ __('Save the changes to :name\'s account?', ['name' => $counselor->name]) }}" data-confirm-button="{{ __('Save changes') }}" @endif>
                @if ($editing)
                    <div class="flex justify-end" x-show="! editing" @if ($startEditing) style="display: none" @endif>
                        <x-secondary-button x-on:click="edit()" data-edit-button>
                            <svg class="me-1.5 h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" /></svg>
                            {{ __('Edit') }}
                        </x-secondary-button>
                    </div>
                @endif

                <fieldset class="space-y-6" @disabled(! $startEditing) x-bind:disabled="! editing">
                <legend class="sr-only">{{ __('Counselor account') }}</legend>

                <div class="grid gap-6 sm:grid-cols-3">
                    <x-form.text name="last_name" label="Last name" kind="name" :value="$counselor->last_name" required autocomplete="off" />
                    <x-form.text name="first_name" label="First name" kind="name" :value="$counselor->first_name" required autocomplete="off" />
                    <x-form.text name="middle_name" label="Middle name" kind="name" :value="$counselor->middle_name" autocomplete="off" />
                </div>

                <x-form.text name="email" label="Email" kind="email" :value="$counselor->email" required autocomplete="off" />

                <x-form.text name="password" type="password" autocomplete="new-password" :required="! $editing"
                    :label="$editing ? 'Reset password (optional)' : 'Temporary password'"
                    :hint="$editing
                        ? 'Leave blank to keep the current password. If you set one, they must change it on next login.'
                        : 'Give this to the counselor. They must change it on first login.'" minlength="10" />
                <x-password-rules for="password" class="-mt-4" />

                <x-form.checkboxes name="colleges" label="Assigned colleges" required :columns="2"
                    :options="$colleges->mapWithKeys(fn ($college) => [$college->id => $college->code.' – '.$college->name])->all()"
                    :value="$counselor->colleges?->pluck('id')->all() ?? []"
                    hint="The counselor will only see students in these colleges. Choose at least one." />
                </fieldset>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-700 pt-6" x-show="editing" @unless ($startEditing) style="display: none" @endunless>
                    @if ($editing)
                        <x-secondary-button x-on:click="cancel()">{{ __('Cancel') }}</x-secondary-button>
                    @else
                        <a href="{{ route('director.counselors.index') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:text-gray-900 dark:hover:text-gray-100">{{ __('Cancel') }}</a>
                    @endif
                    <x-primary-button>{{ $editing ? __('Save changes') : __('Create counselor') }}</x-primary-button>
                </div>

                @csrf
                @if ($editing)
                    @method('PUT')
                @endif
            </form>
            </div>
        </div>
    </div>
</x-app-layout>
