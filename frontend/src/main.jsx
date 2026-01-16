import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'

import 'bootstrap/dist/css/bootstrap.min.css'
import 'bootstrap-icons/font/bootstrap-icons.css'
import './index.css'

import App from './App.jsx'

// Si el usuario entra sin hash, lo mandamos a la versión con hash para evitar 404 o rutas raras.
const basePath = import.meta.env.BASE_URL || '/'
const hasHashRouting = window.location.hash && window.location.hash.startsWith('#/')

if (!hasHashRouting) {
  const pathname = window.location.pathname
  if (pathname.startsWith(basePath)) {
    const remainingPath = pathname.slice(basePath.length).replace(/^\/+|\/+$/g, '')
    if (remainingPath) {
      const search = window.location.search || ''
      window.location.replace(`${basePath}#/${remainingPath}${search}`)
    }
  }
}

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
)