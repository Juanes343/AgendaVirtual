import api from '../../../services/api';

const authService = {
    login: async (credentials) => {
        // credentials: { tipo_doc, usuario, passwd }
        const response = await api.post('/login', credentials);
        return response.data;
    },

    register: async (userData) => {
        const response = await api.post('/register', userData);
        return response.data;
    },

    checkPatient: async (data) => {
        const response = await api.post('/check-patient', data);
        return response.data;
    },

    verifyRegistrationToken: async (token) => {
        const response = await api.get(`/verify-registration-token/${token}`);
        return response.data;
    },
    
    recoverPassword: async (data) => {
        const response = await api.post('/recover-password', data);
        return response.data;
    },

    resetPassword: async (data) => {
        const response = await api.post('/reset-password', data);
        return response.data;
    },

    activateAccount: async (token) => {
        const response = await api.get(`/activate-account/${token}`);
        return response.data;
    }
};

export default authService;
