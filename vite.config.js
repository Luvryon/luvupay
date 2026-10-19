import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/site.css',
                'resources/css/ui.css',
                'resources/js/app.js',
            ],
            refresh: [
                'resources/views/**',
                'app/**',
                'routes/**',
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: true,
        watch: {
            usePolling: true,
            interval: 300,
        },
        hmr: {
            host: 'localhost',
        },
    },
});
