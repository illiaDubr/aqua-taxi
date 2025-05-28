import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    base: '/build/',
    build: {
        manifest: true,
        outDir: path.resolve(__dirname, 'public/build'),
        emptyOutDir: true,
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/js/app.js'),
        },
    },
    publicDir: false, // отключаем, чтобы не мешал (vite жаловался)
});
