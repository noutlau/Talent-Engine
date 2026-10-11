import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    // En développement, /api est relayé vers Laravel : pas de problème de CORS.
    proxy: { '/api': process.env.VITE_API_TARGET ?? 'http://127.0.0.1:8000' },
  },
})
