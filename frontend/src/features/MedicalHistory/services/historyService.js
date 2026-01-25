import api from '../../../services/api';

const historyService = {
    getHistory: async () => {
        const response = await api.get('/medical-history');
        return response.data;
    },
    getDetail: async (ingresoId) => {
        const response = await api.get(`/medical-history/${ingresoId}`);
        return response.data;
    },
    getAttachments: async () => {
        const response = await api.get('/medical-history/attachments');
        return response.data;
    },
    printFormula: (evolucionId) => {
        return api.get(`/medical-history/${evolucionId}/pdf-formula`, { responseType: 'blob' })
            .then((response) => {
                const file = new Blob([response.data], { type: 'application/pdf' });
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            })
            .catch(err => console.error("Error descargando PDF", err));
    },
    printOrder: (evolucionId) => {
        return api.get(`/medical-history/${evolucionId}/pdf-orden`, { responseType: 'blob' })
            .then((response) => {
                const file = new Blob([response.data], { type: 'application/pdf' });
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            })
            .catch(err => console.error("Error descargando PDF", err));
    },
    printIncapacidad: (evolucionId) => {
        return api.get(`/medical-history/${evolucionId}/pdf-incapacidad`, { responseType: 'blob' })
            .then((response) => {
                const file = new Blob([response.data], { type: 'application/pdf' });
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            })
            .catch(err => console.error("Error descargando PDF", err));
    },
    printHistoryComplete: (ingresoId) => {
        return api.get(`/medical-history/${ingresoId}/pdf-completo`, { responseType: 'blob' })
            .then((response) => {
                const file = new Blob([response.data], { type: 'application/pdf' });
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            })
            .catch(err => console.error("Error descargando PDF", err));
    },
    // Nuevas funciones para Cirugía/Notas Operatorias
    getSurgeries: async (ingresoId) => {
        const response = await api.get(`/medical-history/surgeries/${ingresoId}`);
        return response.data;
    },
    printNotaOperatoria: (notaId) => {
        return api.get(`/medical-history/surgeries/${notaId}/pdf`, { responseType: 'blob' })
            .then((response) => {
                const file = new Blob([response.data], { type: 'application/pdf' });
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            })
            .catch(err => console.error("Error descargando Nota Operatoria", err));
    },
    sendSurgeryEmail: async (notaId) => {
        const response = await api.post(`/medical-history/surgeries/${notaId}/send-email`);
        return response.data;
    },
    sendReportEmail: async (ingresoId, type = 'all', evolucionId = null) => {
        const response = await api.post(`/medical-history/${ingresoId}/send-email`, {
            type,
            evolucion_id: evolucionId
        });
        return response.data;
    }
};

export default historyService;