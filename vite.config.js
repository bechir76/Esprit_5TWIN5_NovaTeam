import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Deux points d'entrée : le site citoyen (front) et la console de gestion (back).
// Les deux feuilles importent resources/css/app.css (thème Tailwind commun).
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/front.css',
                'resources/js/front.js',
                'resources/css/back.css',
                'resources/js/back.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
