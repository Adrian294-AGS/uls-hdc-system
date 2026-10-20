{{-- Friendly error page (glass card, beach background, light/dark). Deliberately uses no session,
     no database and no signed-in user, so it still renders when something is badly broken.
     Never shows technical details. --}}
@props(['code', 'title', 'message', 'primaryLabel' => null, 'primaryUrl' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.theme-head')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>{{ $title }} · {{ config('app.name', 'ULS HDC') }}</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased bg-transparent text-gray-900 dark:text-gray-100">
        @include('partials.theme-bg')

        <div class="absolute right-4 top-4 z-50">
            <x-theme-toggle />
        </div>

        <main class="relative z-0 flex min-h-screen items-center justify-center px-4 py-16">
            <div class="glass-card w-full max-w-lg px-6 py-8 text-center sm:px-10">
                <p class="text-sm font-semibold uppercase tracking-widest text-indigo-700 dark:text-indigo-300">{{ __('Error :code', ['code' => $code]) }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
                <p class="mt-3 text-base text-gray-700 dark:text-gray-300">{{ $message }}</p>

                @if ($slot->isNotEmpty())
                    <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">{{ $slot }}</div>
                @endif

                <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                    <a href="{{ $primaryUrl ?? url('/dashboard') }}"
                        class="inline-flex items-center rounded-md bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                        {{ $primaryLabel ?? __('Back to dashboard') }}
                    </a>
                    <button type="button" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')"
                        class="inline-flex items-center rounded-md border border-gray-300 bg-white/70 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-white/10 dark:bg-slate-800/60 dark:text-gray-200 dark:hover:bg-slate-700">
                        {{ __('Go back') }}
                    </button>
                </div>

                <p class="mt-8 text-xs text-gray-500 dark:text-gray-400">{{ __('University of La Salette · Human Development Center') }}</p>
            </div>
        </main>
    </body>
</html>
