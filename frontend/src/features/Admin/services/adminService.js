import api from "../../../services/api";

const adminService = {
    getPermissions: async () => {
        const response = await api.get('/config-reporte-permisos');
        return response.data;
    },
    updatePermission: async (data) => {
        const response = await api.post('/config-reporte-permisos', data);
        return response.data;
    }
};

export default adminService;