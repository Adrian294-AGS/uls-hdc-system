import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    experimental: {
        // Keep url() in the built CSS relative to the stylesheet, so the background images
        // still load when the app is served from a subfolder instead of the domain root.
        renderBuiltUrl(filename, { hostType }) {
            if (hostType === 'css') {
                return { relative: true };
            }
        },
    },
});
