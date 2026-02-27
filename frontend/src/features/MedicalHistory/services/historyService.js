import api from '../../../services/api';

const historyService = {
    getHistory: async () => {
        const response = await api.get('/medical-history');
        return response.data;
    },
    // Nuevo endpoint específico para hospitalización
    getHospitalization: async () => {
        const response = await api.get('/hospitalization');
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
    printOrder: (evolucionId, servicio = null, tipo = null, ids = null) => {
        const params = new URLSearchParams();
        if (servicio) params.append('servicio', servicio);
        if (tipo) params.append('tipo', tipo);
        if (ids) params.append('ids', ids);
        
        const queryString = params.toString() ? `?${params.toString()}` : '';

        return api.get(`/medical-history/${evolucionId}/pdf-orden${queryString}`, { responseType: 'blob' })
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
    // Imprimir PDF de Procedimientos No Quirúrgicos (Hospitalización)
    printNoQx: (ingresoId) => {
        return api.get(`/hospitalization/${ingresoId}/pdf-no-qx`, { responseType: 'blob' })
            .then((response) => {
                const file = new Blob([response.data], { type: 'application/pdf' });
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            })
            .catch(err => console.error("Error descargando PDF No Qx", err));
    },
    // Enviar Email de Procedimientos No Quirúrgicos
    sendNoQxEmail: async (ingresoId) => {
        const response = await api.post(`/hospitalization/${ingresoId}/send-email-no-qx`);
        return response.data;
    },
    // Nuevo endpoint para Apoyos Diagnósticos
    getDiagnosticSupport: async () => {
        const response = await api.get('/diagnostic-support');
        return response.data;
    },
    printDiagnosticSupport: (resultadoId) => {
        return api.get(`/diagnostic-support/${resultadoId}/pdf`, { responseType: 'blob' })
            .then((response) => {
                const file = new Blob([response.data], { type: 'application/pdf' });
                const fileURL = URL.createObjectURL(file);
                window.open(fileURL, '_blank');
            })
            .catch(err => console.error("Error descargando PDF Apoyo Diagnóstico", err));
    },
    sendDiagnosticEmail: async (resultadoId) => {
        const response = await api.post(`/diagnostic-support/${resultadoId}/send-email`);
        return response.data;
    },
    // Nuevas funciones para Cirugía/Notas Operatorias
    getSurgeries: async (ingresoId) => {
        const response = await api.get(`/medical-history/surgeries/${ingresoId}`);
        return response.data;
    },
    saveSurvey: async (formData) => {
        const response = await api.post('/medical-history/satisfaction-survey', formData);
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
    sendReportEmail: async (ingresoId, type = 'all', evolucionId = null, servicio = null) => {
        const response = await api.post(`/medical-history/${ingresoId}/send-email`, {
            type,
            evolucion_id: evolucionId,
            servicio: servicio
        });
        return response.data;
    },
    getPermissions: async () => {
        const response = await api.get('/config-reporte-permisos');
        return response.data;
    },
    updatePermission: async (data) => {
        const response = await api.post('/config-reporte-permisos', data);
        return response.data;
    }
};

export default historyService;