{{-- The one "Are you sure?" dialog. Any form or submit button with data-confirm="..." opens it
     (optional data-confirm-title, data-confirm-button, data-confirm-danger). See resources/js/forms.js. --}}
<div x-data x-show="$store.confirm.open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4"
    role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title" aria-describedby="confirm-modal-message"
    x-on:keydown.escape.window="$store.confirm.open && $store.confirm.answer(false)">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" x-show="$store.confirm.open" x-transition.opacity x-on:click="$store.confirm.answer(false)"></div>
    <div x-show="$store.confirm.open" x-transition x-effect="$store.confirm.open && $nextTick(() => $refs.confirmButton.focus())"
        class="glass-card relative w-full max-w-md p-6">
        <h2 id="confirm-modal-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100" x-text="$store.confirm.title"></h2>
        <p id="confirm-modal-message" class="mt-2 text-sm text-gray-700 dark:text-gray-300" x-text="$store.confirm.message"></p>
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <x-secondary-button x-on:click="$store.confirm.answer(false)">{{ __('Cancel') }}</x-secondary-button>
            <button type="button" x-ref="confirmButton" x-on:click="$store.confirm.answer(true)"
                :class="$store.confirm.danger ? 'bg-red-600 hover:bg-red-500 focus:ring-red-500' : 'bg-gray-800 hover:bg-gray-700 focus:ring-indigo-500 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white'"
                class="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800"
                x-text="$store.confirm.button"></button>
        </div>
    </div>
</div>
