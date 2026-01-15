import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
  ],
  // Configura la base del path para la URL de despliegue si es necesario.
  // Por defecto es '/'
  base: '/', 
  build: {
    outDir: 'build',
  },
})
