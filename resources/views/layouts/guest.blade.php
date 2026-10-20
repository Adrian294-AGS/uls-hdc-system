<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.theme-head')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-transparent dark:text-gray-100">
        @include('partials.theme-bg')
        <x-toasts />
        <x-confirm-modal />

        <div class="absolute right-4 top-4 z-50">
            <x-theme-toggle />
        </div>

        <div class="relative z-0 min-h-screen flex flex-col justify-center items-center px-4 pt-16 pb-8 sm:pt-8">
            <div class="glass-card w-full sm:max-w-md px-6 py-6 sm:px-8">
                <a href="/" class="flex flex-col items-center gap-2 text-center">
                    <x-application-logo class="w-16 h-16 fill-current text-gray-700 dark:text-gray-200" />
                    <span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">ULS HDC</span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Human Development Center') }}</span>
                </a>

                <div class="mt-6">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
