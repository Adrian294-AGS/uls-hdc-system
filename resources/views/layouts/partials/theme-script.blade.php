{{-- Runs before first paint: applies the saved theme (or the device setting) so there is no white flash. --}}
<script>
    (function () {
        var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        try {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                dark = saved === 'dark';
            }
        } catch (e) {}
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

        // Preload the background for the current theme so it shows as early as possible.
        var preload = document.createElement('link');
        preload.rel = 'preload';
        preload.as = 'image';
        preload.type = 'image/webp';
        preload.href = dark ? @json(Vite::asset('resources/images/bg-night.webp')) : @json(Vite::asset('resources/images/bg-day.webp'));
        preload.setAttribute('fetchpriority', 'high');
        document.head.appendChild(preload);

        var transitionTimer = null;

        // Switches the theme. The day/night backgrounds crossfade and colors ease along with them.
        window.applyTheme = function (isDark, persist) {
            var root = document.documentElement;
            var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (! reduceMotion) {
                root.classList.add('theme-transition');
                clearTimeout(transitionTimer);
                transitionTimer = setTimeout(function () {
                    root.classList.remove('theme-transition');
                }, 750);
            }

            root.classList.toggle('dark', isDark);
            root.style.colorScheme = isDark ? 'dark' : 'light';
            if (persist) {
                try { localStorage.setItem('theme', isDark ? 'dark' : 'light'); } catch (e) {}
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: isDark }));
        };
    })();
</script>
