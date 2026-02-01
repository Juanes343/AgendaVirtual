import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
  ],

  // Ruta base EXACTA donde se sirve el build
  base: '/PortalPaciente/SERVIMEDICOS/AgendaVirtual/frontend/build/',

  build: {
    outDir: 'build',
  },
})