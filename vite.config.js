import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import laravel from 'laravel-vite-plugin'
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            buildDirectory: 'build', // => public/build
            refresh: true,
        }),
        vue(),
    ],
    resolve: { alias: { '@': path.resolve(__dirname, 'resources/js') } },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,           // кладёт manifest.json в public/build/
    },
})
