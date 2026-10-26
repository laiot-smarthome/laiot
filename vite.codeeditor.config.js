import { defineConfig } from 'vite';

/**
 * Lazy-chunk редактора кода: CodeMirror 6 грузится только на страницах
 * с textarea[data-code-editor].
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
      input: 'resources/js/codeeditor/index.js',
      output: {
        format: 'iife',
        entryFileNames: 'mydomo-codeeditor.js',
        assetFileNames: (assetInfo) => {
          const name = (assetInfo.names && assetInfo.names[0]) || assetInfo.name || '';
          if (name.endsWith('.css')) {
            return 'mydomo-codeeditor.css';
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
