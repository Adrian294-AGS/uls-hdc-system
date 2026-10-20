{{-- Light/dark switch: moon in light mode, sun in dark mode. toggleTheme() comes from partials/theme-head. --}}
<button type="button" onclick="toggleTheme()" aria-label="Toggle light/dark mode" title="Toggle light/dark mode"
    {{ $attributes->merge(['class' => 'theme-toggle inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/40 bg-white/60 text-gray-700 shadow-sm backdrop-blur-md transition hover:bg-white/80 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-white/10 dark:bg-slate-800/60 dark:text-gray-200 dark:hover:bg-slate-700/80 dark:hover:text-white dark:focus:ring-offset-gray-900']) }}>
    <span class="icon-moon">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
        </svg>
    </span>
    <span class="icon-sun">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
        </svg>
    </span>
</button>
