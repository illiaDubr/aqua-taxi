// vite.config.js
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],   // добавь css/scss если есть
            buildDirectory: 'build',          // => public/build
            refresh: true,
        }),
        vue(),
    ],
    resolve: {
        alias: { '@': path.resolve(__dirname, 'resources/js') },
    },
    // Эти поля не обязательны с laravel-vite-plugin, но не мешают:
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
    },
    server: {
        host: true,
        port: 5173,
    },
})
