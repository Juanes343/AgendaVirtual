import React, { useState, useEffect } from "react";
import { User, Lock, Mail, Phone, MapPin, Save, AlertCircle, CheckCircle, XCircle } from "lucide-react";
import { useUser } from "../../../contexts/UserContext/UserContext";
import ProfileService from "../services/ProfileService";

export default function ProfileView() {
  const { user } = useUser();
  const [loading, setLoading] = useState(false);
  const [modal, setModal] = useState({ show: false, type: 'success', message: '' });

  const [passwordData, setPasswordData] = useState({
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
  });

  // Estado para los datos del paciente
  const [formData, setFormData] = useState({
    direccion: "",
    email: "",
    celular: "",
    
    // Campos de solo lectura
    primer_nombre: "",
    segundo_nombre: "",
    primer_apellido: "",
    segundo_apellido: "",
    numero_documento: "",
    tipo_documento: "",
    fecha_nacimiento: "",
    sexo: "",
    estado_civil: "",
  });

  useEffect(() => {
    if (user && user.paciente) {
      setFormData({
        direccion: user.paciente.residencia_direccion || user.paciente.direccion || "",
        email: user.paciente.email || user.email || "",
        celular: user.paciente.celular || user.paciente.celular_telefono || "",
        primer_nombre: user.paciente.primer_nombre || "",
        segundo_nombre: user.paciente.segundo_nombre || "",
        primer_apellido: user.paciente.primer_apellido || "",
        segundo_apellido: user.paciente.segundo_apellido || "",
        numero_documento: user.paciente.paciente_id || "", 
        tipo_documento: user.paciente.tipo_id_paciente || "", 
        fecha_nacimiento: user.paciente.fecha_nacimiento || "",
        sexo: user.paciente.sexo_id || "", 
        estado_civil: user.paciente.tipo_estado_civil_id || "",
      });
    }
  }, [user]);

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setFormData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const handlePasswordChange = (e) => {
    const { name, value } = e.target;
    setPasswordData((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  const closeModal = () => {
    setModal({ ...modal, show: false });
  };

  const handleSubmitDatos = async (e) => {
    e.preventDefault();
    setLoading(true);
    try {
        await ProfileService.updateProfile(formData);
        setModal({ 
            show: true, 
            type: 'success', 
            message: "Datos actualizados correctamente." 
        });
    } catch (error) {
        console.error(error);
        const msg = error.response?.data?.message || "Error al actualizar los datos. Intente nuevamente.";
        setModal({ 
            show: true, 
            type: 'error', 
            message: msg 
        });
    } finally {
        setLoading(false);
    }
  };

  const handleSubmitPassword = async (e) => {
    e.preventDefault();
    if (passwordData.newPassword !== passwordData.confirmPassword) {
      setModal({ show: true, type: 'error', message: "Las nuevas contraseñas no coinciden." });
      return;
    }
    
    setLoading(true);
    try {
        await ProfileService.changePassword({
            newPassword: passwordData.newPassword,
            newPassword_confirmation: passwordData.confirmPassword
        });

        setModal({ 
            show: true, 
            type: 'success', 
            message: "Contraseña actualizada correctamente." 
        });
        setPasswordData({ currentPassword: "", newPassword: "", confirmPassword: "" });

    } catch (error) {
        console.error(error);
        const msg = error.response?.data?.message || "Error al actualizar la contraseña.";
        setModal({ 
            show: true, 
            type: 'error', 
            message: msg 
        });
    } finally {
        setLoading(false);
    }
  };

  return (
    <div className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 relative">
      {/* Modal Overlay */}
      {modal.show && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm animate-in fade-in duration-200">
            <div className="bg-[#1e293b] border border-white/10 rounded-2xl p-6 max-w-sm w-full shadow-2xl animate-in zoom-in-95 duration-200">
                <div className="flex flex-col items-center gap-4 text-center">
                    <div className={`p-4 rounded-full ${modal.type === 'success' ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500'}`}>
                        {modal.type === 'success' ? <CheckCircle size={40} /> : <XCircle size={40} />}
                    </div>
                    <h3 className="text-xl font-bold text-white">
                        {modal.type === 'success' ? '¡Éxito!' : 'Error'}
                    </h3>
                    <p className="text-gray-300">
                        {modal.message}
                    </p>
                    <button 
                        onClick={closeModal}
                        className="mt-2 w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-medium transition-colors shadow-lg shadow-blue-500/20"
                    >
                        Entendido
                    </button>
                </div>
            </div>
        </div>
      )}

      <div className="flex items-center justify-between">
        <h2 className="text-2xl font-bold text-white flex items-center gap-2">
          <User className="h-6 w-6 text-blue-400" />
          Datos Básicos
        </h2>
      </div>

      {/* Sección 1: Datos Personales (Formulario Principal) */}
      <div className="bg-card/50 backdrop-blur-md border border-white/10 rounded-xl p-6 shadow-xl">
        <div className="mb-6 pb-4 border-b border-white/10">
          <h3 className="text-lg font-semibold text-white">Información Personal</h3>
          <p className="text-sm text-gray-400">Actualice su información de contacto y residencia</p>
        </div>

        <form onSubmit={handleSubmitDatos} className="space-y-6">
          {/* Fila 1: Documento (Solo lectura) */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300">Tipo Documento</label>
              <input
                type="text"
                value={formData.tipo_documento}
                readOnly
                className="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-gray-400 cursor-not-allowed"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300">Número Documento</label>
              <input
                type="text"
                value={formData.numero_documento}
                readOnly
                className="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-gray-400 cursor-not-allowed"
              />
            </div>
          </div>

          {/* Fila 2: Nombres */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300">Nombres</label>
              <div className="flex gap-2">
                <input
                  type="text"
                  name="primer_nombre"
                  value={formData.primer_nombre}
                  onChange={handleInputChange}
                  className="w-1/2 bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                  placeholder="Primer Nombre"
                />
                <input
                  type="text"
                  name="segundo_nombre"
                  value={formData.segundo_nombre}
                  onChange={handleInputChange}
                  className="w-1/2 bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                  placeholder="Segundo Nombre"
                />
              </div>
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300">Apellidos</label>
              <div className="flex gap-2">
                 <input
                  type="text"
                  name="primer_apellido"
                  value={formData.primer_apellido}
                  onChange={handleInputChange}
                  className="w-1/2 bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                  placeholder="Primer Apellido"
                />
                <input
                  type="text"
                  name="segundo_apellido"
                  value={formData.segundo_apellido}
                  onChange={handleInputChange}
                  className="w-1/2 bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                  placeholder="Segundo Apellido"
                />
              </div>
            </div>
          </div>

          {/* Fila 3: Dirección (Full Width) */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-300 flex items-center gap-2">
              <MapPin className="w-4 h-4 text-blue-400" /> Dirección de Residencia
            </label>
            <input
              type="text"
              name="direccion"
              value={formData.direccion}
              onChange={handleInputChange}
              className="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
              placeholder="Ingrese su dirección completa"
            />
          </div>

          {/* Fila 4: Contacto (Email y Celular) */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300 flex items-center gap-2">
                <Mail className="w-4 h-4 text-blue-400" /> Email
              </label>
              <input
                type="email"
                name="email"
                value={formData.email}
                onChange={handleInputChange}
                className="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                placeholder="ejemplo@correo.com"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300 flex items-center gap-2">
                <Phone className="w-4 h-4 text-blue-400" /> Celular
              </label>
              <input
                type="tel"
                name="celular"
                value={formData.celular}
                onChange={handleInputChange}
                className="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                placeholder="Número de celular"
              />
            </div>
          </div>
        
          <div className="flex justify-center pt-4">
             <button
                type="submit"
                disabled={loading}
                className="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-lg shadow-blue-500/20 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
             >
                {loading ? (
                    <span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                ) : (
                    <Save className="w-5 h-5" />
                )}
                Actualizar Datos
             </button>
          </div>
        </form>
      </div>

      {/* Sección 2: Cambio de Contraseña */}
      <div className="bg-card/50 backdrop-blur-md border border-white/10 rounded-xl p-6 shadow-xl">
        <div className="mb-6 pb-4 border-b border-white/10">
          <h3 className="text-lg font-semibold text-white flex items-center gap-2">
             <Lock className="w-5 h-5 text-yellow-400" />
             Cambio de Contraseña
          </h3>
          <p className="text-sm text-gray-400">Mantenga su cuenta segura actualizando su contraseña periódicamente</p>
        </div>

        <form onSubmit={handleSubmitPassword} className="space-y-6 max-w-2xl mx-auto">
           {/* Contraseña Actual (Opcional si el sistema la requiere) */}
           {/* 
           <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300">Contraseña Actual</label>
              <input
                type="password"
                name="currentPassword"
                value={passwordData.currentPassword}
                onChange={handlePasswordChange}
                className="w-full bg-black/20 border border-white/10 rounded-lg px-4 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
              />
            </div>
            */}

            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300">Nueva Contraseña</label>
              <div className="relative">
                <input
                    type="password"
                    name="newPassword"
                    value={passwordData.newPassword}
                    onChange={handlePasswordChange}
                    className="w-full bg-black/20 border border-white/10 rounded-lg pl-4 pr-10 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                    placeholder="Ingrese nueva contraseña"
                />
                <Lock className="w-4 h-4 text-gray-400 absolute right-3 top-3" />
              </div>
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-300">Confirmar Contraseña</label>
              <div className="relative">
                <input
                    type="password"
                    name="confirmPassword"
                    value={passwordData.confirmPassword}
                    onChange={handlePasswordChange}
                    className="w-full bg-black/20 border border-white/10 rounded-lg pl-4 pr-10 py-2 text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition-all"
                    placeholder="Repita la nueva contraseña"
                />
                <Lock className="w-4 h-4 text-gray-400 absolute right-3 top-3" />
              </div>
            </div>

            <div className="bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-4 flex gap-3 text-sm text-yellow-200">
                <AlertCircle className="w-5 h-5 shrink-0" />
                <p>Asegúrese de usar una contraseña segura que incluya números y letras. No comparta su clave con nadie.</p>
            </div>

            <div className="flex justify-center pt-2">
                <button
                    type="submit"
                    disabled={loading || !passwordData.newPassword}
                    className="px-8 py-3 bg-slate-700 hover:bg-slate-600 text-white rounded-lg font-medium shadow-lg transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    {loading ? (
                        <span className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                    ) : (
                        <Lock className="w-4 h-4" />
                    )}
                    Actualizar Contraseña
                </button>
            </div>
        </form>
      </div>
    </div>
  );
}