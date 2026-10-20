@php($retryAfter = (int) (($exception ?? null)?->getHeaders()['Retry-After'] ?? 0))

<x-error-page code="429" :title="__('Too many attempts')"
    :message="__('You tried this too many times in a short time. Please wait a moment, then try again.')">
    @if ($retryAfter > 0)
        {{ trans_choice('You can try again in :count second.|You can try again in :count seconds.', $retryAfter) }}
    @endif
</x-error-page>
