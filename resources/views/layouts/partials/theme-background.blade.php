{{-- Fixed day and night beach backgrounds; CSS crossfades them when the theme changes.
     The wrapper keeps both layers (and their tint) behind the page and lets clicks pass through. --}}
<div class="pointer-events-none fixed inset-0 -z-10" aria-hidden="true" data-theme-background>
    <div class="theme-bg theme-bg-day"></div>
    <div class="theme-bg theme-bg-night"></div>
</div>
