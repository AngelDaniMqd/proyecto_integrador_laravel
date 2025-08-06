import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/donativos.css',
                'resources/css/inicio.css',
                'resources/css/login.css',
                'resources/css/nosotros.css',
                'resources/css/noticias.css',
                'resources/js/app.js',
                'resources/js/script.js'  // También incluir script.js si existe
            ],
            refresh: true,
        }),
    ],
});