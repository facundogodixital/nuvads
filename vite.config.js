import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: Number(process.env.PORT),
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
        watch: {
            // Evitar vigilar datos de las bases, archivos generados y dependencias PHP.
            ignored: ['**/docker/**', '**/storage/**', '**/vendor/**'],
        },
    },
});
