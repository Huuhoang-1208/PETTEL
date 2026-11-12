import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  base: '/owner/',
  plugins: [vue()],
  test: { environment: 'jsdom', setupFiles: './tests/setup.js', exclude: ['**/node_modules/**', '**/dist/**', 'tests/e2e/**'] },
  server: {
    proxy: Object.fromEntries(['/api', '/sanctum', '/login', '/logout', '/register', '/forgot-password', '/reset-password'].map((path) => [path, {
      target: 'http://localhost:8080',
      configure(proxy) {
        proxy.on('proxyReq', (proxyRequest) => {
          proxyRequest.setHeader('host', 'localhost:8080');
          proxyRequest.setHeader('origin', 'http://localhost:8080');
          proxyRequest.setHeader('referer', 'http://localhost:8080/owner/');
        });
      },
    }])),
  },
});
