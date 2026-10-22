<script>
(function () {
    var saved = null;
    try { saved = localStorage.getItem('theme'); } catch (e) {}
    var dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.classList.toggle('dark', dark);
})();
window.toggleTheme = function () {
    var dark = !document.documentElement.classList.contains('dark');
    document.documentElement.classList.toggle('dark', dark);
    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
};
</script>
<style>
    html, body { background: transparent !important; }
    .beach-bg { position: fixed; inset: 0; z-index: -10; pointer-events: none;
        background-size: cover; background-position: center; transition: opacity .7s ease-in-out; }
    .beach-day { background-image: url('{{ asset('images/bg-day.png') }}'); opacity: 1; }
    .beach-night { background-image: url('{{ asset('images/bg-night.png') }}'); opacity: 0; }
    html.dark .beach-day { opacity: 0; }
    html.dark .beach-night { opacity: 1; }
    .beach-tint { position: fixed; inset: 0; z-index: -9; pointer-events: none;
        background: rgba(255,255,255,.20); transition: background-color .7s ease-in-out; }
    html.dark .beach-tint { background: rgba(10,20,45,.40); }
    @media (prefers-reduced-motion: reduce) { .beach-bg, .beach-tint { transition: none; } }
    .theme-toggle { position: relative; z-index: 50; cursor: pointer; }
    .theme-toggle .icon-sun { display: none; }
    html.dark .theme-toggle .icon-sun { display: inline; }
    html.dark .theme-toggle .icon-moon { display: none; }
</style>
