import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

/**
 * Vite build configuration for the SmartRecur WordPress plugin.
 *
 * Output:
 *   assets/js/smartrecur-app.js  — single React bundle (no code-splitting).
 *   assets/css/smartrecur-app.css — compiled Tailwind, scoped under .smartrecur-wrap.
 */
export default defineConfig({
  plugins: [react()],
  build: {
    outDir: 'assets',
    emptyOutDir: false,
    cssCodeSplit: false,
    sourcemap: false,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/index.tsx'),
      output: {
        entryFileNames: 'js/smartrecur-app.js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (chunkInfo) => {
          if (chunkInfo.name && chunkInfo.name.endsWith('.css')) {
            return 'css/smartrecur-app.css';
          }
          return 'assets/[name][extname]';
        },
        manualChunks: undefined,
      },
    },
  },
});
