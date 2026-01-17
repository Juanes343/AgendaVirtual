import api from '../../../services/api';

const appointmentService = {
  // Obtener Tipos de Cita (Consulta)
  getAppointmentTypes: async () => {
    const response = await api.get('/appointments/types');
    return response.data;
  },

  // Obtener Planes
  getPlans: async () => {
    const response = await api.get('/appointments/plans');
    return response.data;
  },

  // Obtener Servicios (MODIFICADO: Requiere Plan + Tipo)
  getServices: async (planId, appointmentTypeId) => {
    const response = await api.get('/appointments/services', {
      params: { 
        plan_id: planId,
        appointment_type_id: appointmentTypeId 
      }
    });
    return response.data;
  },

  // Obtener Profesionales
  getProfessionals: async (appointmentTypeId, serviceId) => {
    const response = await api.get('/appointments/professionals', {
      params: { 
        appointment_type_id: appointmentTypeId,
        service_id: serviceId
      }
    });
    return response.data;
  },

  // Obtener Disponibilidad
  getAvailability: async (filters) => {
    const response = await api.get('/appointments/availability', {
      params: filters
    });
    return response.data;
  },

  // Agendar
  bookAppointment: async (data) => {
    const response = await api.post('/appointments/book', data);
    return response.data;
  }
};

export default appointmentService;