import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig(({ command }) => ({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            buildDirectory: 'build',
            refresh: false,
        }),
        vue(),
    ],

    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },

    // Настройки билда
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
        cssCodeSplit: true,
        minify: 'esbuild',
        sourcemap: false,
        rollupOptions: {
            output: {
                manualChunks: {
                    vue: ['vue'],
                },
            },
        },
    },

    // Dev-сервер — используется только локально
    server: {
        host: 'localhost',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
        cors: true,
        hmr: {
            host: 'localhost',
        },
    },
}))
