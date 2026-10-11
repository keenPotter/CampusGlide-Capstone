import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Ang /api ay ipinapasa ng Vite sa Laravel (php artisan serve) — walang CORS problem.
export default defineConfig({
  plugins: [vue()],
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
