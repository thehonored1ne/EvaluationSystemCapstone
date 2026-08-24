import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: [`resources/views/**/*`],
        }),
        tailwindcss(),
    ],
    esbuild: {
        target: 'esnext',
    },
    optimizeDeps: {
        esbuildOptions: {
            target: 'esnext',
        },
    },
    build: {
        target: 'esnext',
    },
    css: {
        postcss: false,
    },
    server: {
        cors: true,
    },
});