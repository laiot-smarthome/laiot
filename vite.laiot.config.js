import { defineConfig } from 'vite';

/**
 * LaIOT admin shell: JS + CSS без jQuery и alpha-бандла.
 *
 * Output is committed to public/assets/admin/laiot.js и laiot.css.
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
      input: 'resources/js/app-laiot.js',
      output: {
        format: 'iife',
        entryFileNames: 'laiot.js',
        assetFileNames: (assetInfo) => {
          const name = (assetInfo.names && assetInfo.names[0]) || assetInfo.name || '';
          if (name.endsWith('.css')) {
            return 'laiot.css';
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
