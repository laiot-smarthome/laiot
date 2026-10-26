import { defineConfig } from 'vite';

/**
 * MyDoMo admin shell: JS + CSS без jQuery и alpha-бандла.
 *
 * Output is committed to public/assets/admin/mydomo.js и mydomo.css.
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
      input: 'resources/js/app-mydomo.js',
      output: {
        format: 'iife',
        entryFileNames: 'mydomo.js',
        assetFileNames: (assetInfo) => {
          const name = (assetInfo.names && assetInfo.names[0]) || assetInfo.name || '';
          if (name.endsWith('.css')) {
            return 'mydomo.css';
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
