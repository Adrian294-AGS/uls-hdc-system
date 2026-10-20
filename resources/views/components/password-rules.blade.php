{{-- Live checklist + strength meter for a new-password input (rules match Password::defaults()). --}}
@props(['for'])

<div x-data="passwordRules(@js($for))" {{ $attributes->merge(['class' => 'mt-2']) }} aria-live="polite">
    <div class="flex items-center gap-3">
        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" aria-hidden="true">
            <div class="h-full rounded-full transition-all duration-300"
                :style="`width: ${value ? (score + 1) / 6 * 100 : 0}%`"
                :class="['bg-red-500', 'bg-red-500', 'bg-amber-500', 'bg-yellow-500', 'bg-green-500', 'bg-green-600'][score]"></div>
        </div>
        <span class="w-24 text-right text-xs text-gray-600 dark:text-gray-400" x-text="value ? label : ''"></span>
    </div>
    <ul class="mt-2 grid gap-1 text-xs sm:grid-cols-2">
        <template x-for="rule in rules" :key="rule.key">
            <li class="flex items-center gap-1.5" :class="passed(rule) ? 'text-green-700 dark:text-green-400' : 'text-gray-600 dark:text-gray-400'">
                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path x-show="passed(rule)" fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                    <circle x-show="! passed(rule)" cx="10" cy="10" r="3" />
                </svg>
                <span x-text="rule.label"></span>
                <span class="sr-only" x-text="passed(rule) ? '{{ __('(met)') }}' : '{{ __('(not met yet)') }}'"></span>
            </li>
        </template>
    </ul>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('It must also differ from your current password and must not appear in known data breaches.') }}</p>
</div>
