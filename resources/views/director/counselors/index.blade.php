<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ __('Counselors') }}</h2>
            <a href="{{ route('director.counselors.create') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-100 dark:text-gray-900 rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white">
                + {{ __('Add counselor') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="glass-panel sm:rounded-lg overflow-hidden">
                @if ($counselors->isEmpty())
                    <p class="p-6 text-sm text-gray-600 dark:text-gray-400">{{ __('No counselor accounts yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Name') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Email') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Assigned colleges') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Status') }}</th>
                                    <th scope="col" class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-300">{{ __('Last login') }}</th>
                                    <th scope="col" class="px-4 py-3 text-right font-semibold text-gray-700 dark:text-gray-300"><span class="sr-only">{{ __('Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach ($counselors as $counselor)
                                    <tr @class(['opacity-60' => ! $counselor->is_active])>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                            {{ $counselor->last_name }}, {{ $counselor->first_name }} {{ $counselor->middle_name }}
                                            @if ($counselor->must_change_password)
                                                <span class="ms-1 text-xs font-normal text-gray-500 dark:text-gray-400">({{ __('temporary password') }})</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $counselor->email }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($counselor->colleges as $college)
                                                    <span class="inline-flex rounded bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs font-medium text-gray-700 dark:text-gray-300" title="{{ $college->name }}">{{ $college->code }}</span>
                                                @empty
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('None') }}</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($counselor->is_active)
                                                <span class="inline-flex rounded-full bg-green-50 dark:bg-green-500/10 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">{{ __('Active') }}</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-400">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap" @if ($counselor->last_login_at) title="{{ $counselor->last_login_at->format('F j, Y g:i A') }}" @endif>
                                            {{ $counselor->last_login_at?->diffForHumans() ?? __('Never') }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-3">
                                                <a href="{{ route('director.counselors.edit', $counselor) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Edit') }}</a>
                                                @if ($counselor->is_active)
                                                    <form method="POST" action="{{ route('director.counselors.deactivate', $counselor) }}"
                                                        data-confirm="{{ __('Deactivate :name? They will no longer be able to log in.', ['name' => $counselor->name]) }}" data-confirm-title="{{ __('Deactivate counselor?') }}" data-confirm-button="{{ __('Deactivate') }}" data-confirm-danger>
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">{{ __('Deactivate') }}</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="{{ route('director.counselors.reactivate', $counselor) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">{{ __('Reactivate') }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
