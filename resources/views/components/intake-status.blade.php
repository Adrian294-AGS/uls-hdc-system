{{-- Intake interview status badge; "None" when the student has no intake interview. --}}
@props(['status' => null])

@if ($status)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset '.$status->badgeClasses()]) }}>
        {{ $status->label() }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-400']) }}>
        {{ __('None') }}
    </span>
@endif
