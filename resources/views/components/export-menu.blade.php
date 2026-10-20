{{-- "Export" popover: a GET form with one button per format, optional checkboxes in the slot,
     and hidden inputs (e.g. the current list filters). Extra attributes go on the <form>. --}}
@props(['action', 'formats', 'hidden' => [], 'label' => 'Export', 'align' => 'right'])

<div class="relative" x-data="{ open: false, sensitive: false }" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
    <button type="button" x-on:click="open = ! open" :aria-expanded="open.toString()" aria-haspopup="true"
        class="inline-flex items-center gap-2 rounded-md bg-gray-800 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white dark:focus:ring-offset-gray-800">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
        {{ __($label) }}
        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top
        class="glass-card absolute z-40 mt-2 w-72 p-4 text-left {{ $align === 'left' ? 'start-0' : 'end-0' }}">
        <form method="GET" action="{{ $action }}" {{ $attributes }} x-on:submit="setTimeout(() => open = false, 50)">
            @foreach ($hidden as $name => $value)
                @foreach ((array) $value as $item)
                    <input type="hidden" name="{{ is_array($value) ? $name.'[]' : $name }}" value="{{ $item }}">
                @endforeach
            @endforeach

            @if ($slot->isNotEmpty())
                <div class="mb-4 space-y-2 text-sm text-gray-800 dark:text-gray-200">{{ $slot }}</div>
            @endif

            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Download as') }}</p>
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach ($formats as $format => $formatLabel)
                    <button type="submit" name="format" value="{{ $format }}"
                        class="rounded-md border border-gray-300 bg-white/70 px-2 py-2 text-xs font-semibold text-gray-800 shadow-sm transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-white/10 dark:bg-slate-800/60 dark:text-gray-100 dark:hover:bg-slate-700">
                        {{ $formatLabel }}
                    </button>
                @endforeach
            </div>
        </form>
    </div>
</div>
