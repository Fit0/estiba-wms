import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// The browser talks to one address only: the dev server. It forwards the API calls to the backend,
// so the app needs no CORS and the same code works behind any proxy in production (ADR 0021).
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8080';

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': { target: apiTarget, changeOrigin: true },
      '/health': { target: apiTarget, changeOrigin: true },
    },
    // A bind mount from Windows or macOS does not tell the container that a file changed.
    watch: process.env.VITE_USE_POLLING === 'true' ? { usePolling: true, interval: 300 } : {},
  },
  test: {
    environment: 'jsdom',
    setupFiles: './src/test-setup.ts',
    include: ['src/**/*.test.{ts,tsx}'],
  },
});
