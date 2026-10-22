{{-- Global success/error toasts: session('status'), session('error'), and `toast` window events. --}}
@php
    $statusMessages = [
        'profile-updated' => __('Your profile has been saved.'),
        'password-updated' => __('Your password has been changed. You were signed out on your other devices.'),
        'verification-link-sent' => __('A new verification link has been sent to your email address.'),
    ];
    $initial = array_values(array_filter([
        session('status') ? ['type' => 'success', 'message' => $statusMessages[session('status')] ?? session('status')] : null,
        session('error') ? ['type' => 'error', 'message' => session('error')] : null,
    ]));
@endphp

<div x-data="toasts(@js($initial))" class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex flex-col items-center gap-2 px-4 sm:items-end sm:pe-6" aria-live="polite">
    {{-- Server-rendered copy for no-JS and tests; Alpine replaces it. --}}
    @foreach ($initial as $toast)
        <div x-show="false" class="sr-only">{{ $toast['message'] }}</div>
    @endforeach
    <template x-for="toast in items" :key="toast.id">
        <div x-transition.opacity.duration.200ms :role="toast.type === 'error' ? 'alert' : 'status'"
            :class="toast.type === 'error'
                ? 'border-red-300/70 bg-red-50/95 text-red-900 dark:border-red-400/30 dark:bg-red-950/90 dark:text-red-100'
                : 'border-green-300/70 bg-green-50/95 text-green-900 dark:border-green-400/30 dark:bg-green-950/90 dark:text-green-100'"
            class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg backdrop-blur-md">
            <svg x-show="toast.type !== 'error'" class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" /></svg>
            <svg x-show="toast.type === 'error'" class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
            <p class="flex-1" x-text="toast.message"></p>
            <button type="button" x-on:click="remove(toast.id)" class="-m-1 rounded p-1 opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-indigo-500" aria-label="{{ __('Dismiss') }}">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" /></svg>
            </button>
        </div>
    </template>
</div>
