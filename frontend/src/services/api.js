import axios from 'axios';

// Configuración base de Axios
const api = axios.create({
    baseURL: 'http://localhost:8000/api', // Ajusta el puerto si tu Laravel corre en otro
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

export default api;
