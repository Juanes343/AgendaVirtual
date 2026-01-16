import axios from 'axios';

// Configuración base de Axios
const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api', 
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// INTERCEPTOR: Agrega el token a cada petición automáticamente
api.interceptors.request.use(
    (config) => {
        // Busca el token en el almacenamiento local
        const token = localStorage.getItem('token'); 
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// INTERCEPTOR: Maneja errores de sesión expirada (opcional pero recomendado)
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Si el token venció o es inválido, podrías redirigir al login
            // window.location.href = '/login'; 
            console.error("Sesión no autorizada o expirada");
        }
        return Promise.reject(error);
    }
);

export default api;