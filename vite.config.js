import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig({
    plugins: [
        laravel({
            // укажи свои входы, если нужны ещё css/scss
            input: ['resources/js/app.js'],
            buildDirectory: 'build', // => public/build
            refresh: false,
        }),
        vue(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: true,
        port: 5173,
    },
})
