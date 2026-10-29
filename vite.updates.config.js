import {defineConfig} from 'vite';

// 2026-10-29 — автономный прогресс и игра без runtime админки и внешних библиотек.
export default defineConfig({
    publicDir: false,
    build: {
        outDir: 'public/assets/admin', emptyOutDir: false, sourcemap: false, target: 'es2018',
        rollupOptions: {input: 'resources/js/app-updates.js', output: {format: 'iife', entryFileNames: 'laiot-updates.js'}},
    },
});
