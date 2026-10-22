@php($user = Auth::user())

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

        <div class="glass-panel overflow-hidden sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <p class="text-xl font-semibold">{{ __('Welcome, :name', ['name' => $user->name]) }}</p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Role:') }}
                    <span class="inline-flex items-center rounded-full bg-indigo-50 dark:bg-indigo-500/10 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:text-indigo-300">{{ $user->role->label() }}</span>
                </p>

                @if ($slot->isNotEmpty())
                    <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">{{ $slot }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
