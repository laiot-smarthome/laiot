import { defineConfig } from 'vite';

/**
 * Lazy-chunk графиков: ApexCharts грузится только при открытии истории
 * с числовыми данными.
 */
export default defineConfig({
  base: './',
  publicDir: false,
  build: {
    outDir: 'public/assets/admin',
    emptyOutDir: false,
    cssCodeSplit: false,
    sourcemap: false,
    target: 'es2018',
    reportCompressedSize: false,
    rollupOptions: {
      input: 'resources/js/charts/index.js',
      output: {
        format: 'iife',
        entryFileNames: 'laiot-charts.js',
        assetFileNames: (assetInfo) => {
          const name = (assetInfo.names && assetInfo.names[0]) || assetInfo.name || '';
          if (name.endsWith('.css')) {
            return 'laiot-charts.css';
          }
          if (/\.(woff2?|ttf|eot|otf)$/i.test(name)) {
            return 'fonts/[name][extname]';
          }
          return 'media/[name][extname]';
        },
      },
    },
  },
});
