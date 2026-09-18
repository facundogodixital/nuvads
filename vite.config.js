import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ command }) => ({
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
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0',
    port: Number(process.env.PORT),
    strictPort: true,
    https: command === 'serve' ? {
      key: readFileSync(new URL('./docker/nginx/certs/app.nuvads.ai-key.pem', import.meta.url)),
      cert: readFileSync(new URL('./docker/nginx/certs/app.nuvads.ai.pem', import.meta.url)),
    } : undefined,
    hmr: {
      host: 'app.nuvads.ai',
    },
    watch: {
      // Evitar vigilar datos de las bases, archivos generados y dependencias PHP.
      ignored: ['**/docker/**', '**/storage/**', '**/vendor/**'],
    },
  },
}));
