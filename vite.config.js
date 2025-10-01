import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.js'], buildDirectory: 'build', refresh: false }),
        vue(),
    ],
    resolve: { alias: { '@': path.resolve(__dirname, 'resources/js') } },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
        cssMinify: false,     // <-- временно отключаем минификацию CSS
    },
    server: { host: true, port: 5173 },
})
