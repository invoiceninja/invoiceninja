import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  resolve: {
    tsconfigPaths: true,
  },
  server: {
    port: 3000,
  },
  build: {
    assetsDir: '',
    chunkSizeWarningLimit: 1500,
    rolldownOptions: {
      output: {
        // This will output a single bundle file
        entryFileNames: 'bundle.[hash].js',
        manualChunks: undefined,
      },
    },
  },
});
