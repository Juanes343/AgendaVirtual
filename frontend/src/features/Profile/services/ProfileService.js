import api from "../../../services/api";

const ProfileService = {
  /**
   * Actualiza los datos básicos del paciente
   * @param {Object} data - Objeto con los datos a actualizar (direccion, email, celular, etc.)
   * @returns {Promise} - Respuesta del servidor
   */
  updateProfile: async (data) => {
    try {
      const response = await api.put("/profile/update", data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Cambia la contraseña del usuario
   * @param {Object} data - Objeto con las contraseñas { currentPassword, newPassword, confirmPassword }
   * @returns {Promise} - Respuesta del servidor
   */
  changePassword: async (data) => {
    try {
      const response = await api.post("/profile/change-password", data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};

export default ProfileService;