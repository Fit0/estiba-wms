import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig } from 'vitest/config';

// The browser talks to one address only: the dev server. It forwards the API calls to the backend,
// so the app needs no CORS and the same code works behind any proxy in production (ADR 0021).
const apiTarget = process.env.API_PROXY_TARGET ?? 'http://localhost:8080';

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      // The person decides when to take a new version (a picker in the middle of a list must not
      // see the page reload), so the app asks and `UpdatePrompt` shows the question (ADR 0040).
      registerType: 'prompt',
      includeAssets: ['icon.svg', 'apple-touch-icon.png'],
      manifest: {
        id: '/',
        name: 'Estiba WMS',
        short_name: 'Estiba',
        description: 'Gestión de almacén: recepción, stock, pedidos y picking.',
        lang: 'es',
        start_url: '/picking',
        scope: '/',
        display: 'standalone',
        background_color: '#081425',
        theme_color: '#081425',
        icons: [
          { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
          { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
          {
            src: 'pwa-maskable-512x512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
      workbox: {
        // The app shell and nothing else: the API is never cached here. What the operator does without
        // a connection is the queue of E07-03, not a stale answer from a cache.
        globPatterns: ['**/*.{js,css,html,svg,png}', '**/*-latin-wght-normal-*.woff2'],
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//, /^\/health/],
        cleanupOutdatedCaches: true,
      },
    }),
  ],
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
    // Test files run side by side, and a busy machine (CI, a laptop running Docker) is slow to render.
    testTimeout: 20_000,
    // The tokens file is read as text by the contrast test, and Vitest empties CSS unless it is asked not to.
    css: { include: [/tokens\.css/] },
  },
});
