import axios from 'axios';

// Configuración base de Axios
// Usar variable de entorno para la URL de la API
const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api', 
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

export default api;
