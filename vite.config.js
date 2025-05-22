import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [vue()],
    root: 'resources/js', // путь к твоему JS (если у тебя так)
    publicDir: 'public/vite-public', // если нужен (можно удалить)
    build: {
        manifest: true,
        outDir: path.resolve(__dirname, 'public/build'),
        rollupOptions: {
            input: 'resources/js/app.js',
        },
        emptyOutDir: true,
    },
});
