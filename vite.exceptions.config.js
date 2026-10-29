import {defineConfig} from 'vite';

// 2026-10-29 — отдельный небольшой бандл: ошибка не запускает виджеты панели.
export default defineConfig({
    publicDir: false,
    build: {
        outDir: 'public/assets/admin',
        emptyOutDir: false,
        sourcemap: false,
        target: 'es2018',
        rollupOptions: {
            input: 'resources/js/app-exceptions.js',
            output: {format: 'iife', entryFileNames: 'laiot-exceptions.js'},
        },
    },
});
