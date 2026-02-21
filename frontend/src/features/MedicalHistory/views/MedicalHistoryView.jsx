import React, { useEffect, useState } from "react";
import { useLocation } from "react-router-dom";
import historyService from "../services/historyService";
import {
  Eye,
  Printer,
  ArrowLeft,
  FileText,
  Activity,
  Layers,
  Calendar,
  User,
  Search,
  Stethoscope,
  Pill,
  Mail,
  CheckCircle,
  Send,
  Paperclip,
  ChevronDown,
  ChevronUp,
  MessageSquare,
} from "lucide-react";
import { useUser } from "../../../contexts/UserContext/UserContext";
import Swal from "sweetalert2";

const EncuestaCard = ({ item }) => {
  const [isOpen, setIsOpen] = useState(false);
  
  if (!item || parseInt(item.encuesta_completada) !== 1) return null;

  const pregunta1 = item.encuesta_pregunta_1 || '';
  const pregunta2 = item.encuesta_pregunta_2 || '';
  const fecha = item.encuesta_fecha_registro || item.fecha_encuesta || '';
  const fechaSolo = fecha ? fecha.split(' ')[0] : '';

  return (
    <div className="bg-[#1e293b] rounded-xl border border-blue-900/30 overflow-hidden shadow-md mt-6 animate-fade-in-up transition-all duration-300">
      <button 
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="w-full bg-blue-900/40 px-6 py-4 flex items-center justify-between hover:bg-blue-800/40 transition-colors cursor-pointer group/card"
      >
        <div className="flex items-center gap-3 text-left">
          <div className="p-2 rounded-lg bg-blue-500/20 group-hover/card:bg-blue-500/30 transition-colors">
            <CheckCircle className="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <h3 className="text-sm font-bold text-white uppercase tracking-wider">
              Encuesta de Satisfacción
            </h3>
            <p className="text-xs text-blue-400 font-medium italic">
              Respuesta registrada el {fechaSolo}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-3">
           <span className="text-xs font-bold px-3 py-1.5 rounded-lg bg-blue-600/20 text-blue-300 uppercase border border-blue-500/20 group-hover/card:bg-blue-600/30 transition-all">
             {isOpen ? 'Ocultar Detalle' : 'Ver Respuesta'}
           </span>
           {isOpen ? <ChevronUp className="text-blue-400 w-5 h-5" /> : <ChevronDown className="text-blue-400 w-5 h-5" />}
        </div>
      </button>

      {isOpen && (
        <div className="border-t border-blue-900/30 animate-fade-in">
          <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Pregunta 1 Card */}
            <div className="bg-blue-950/40 rounded-xl p-5 border border-blue-800/20 shadow-inner group/q hover:border-blue-700/40 transition-all duration-300">
              <p className="text-sm font-semibold text-blue-300 mb-3 flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-blue-600/30 flex items-center justify-center text-xs text-blue-100 font-bold border border-blue-500/20 group-hover/q:scale-110 transition-transform">1</span>
                ¿Cómo califica la atención recibida por parte del personal médico?
              </p>
              <div className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg font-bold text-lg shadow-sm
                ${pregunta1 === 'Excelente' ? 'bg-green-600/20 text-green-400 border border-green-500/30' : 
                  pregunta1 === 'Bueno' ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30' : 
                  'bg-orange-600/20 text-orange-400 border border-orange-500/30'}`}>
                {pregunta1}
              </div>
            </div>

            {/* Pregunta 2 Card */}
            <div className="bg-blue-950/40 rounded-xl p-5 border border-blue-800/20 shadow-inner group/q hover:border-blue-700/40 transition-all duration-300">
              <p className="text-sm font-semibold text-blue-300 mb-3 flex items-center gap-2">
                <span className="w-6 h-6 rounded-full bg-blue-600/30 flex items-center justify-center text-xs text-blue-100 font-bold border border-blue-500/20 group-hover/q:scale-110 transition-transform">2</span>
                ¿Recomendaría nuestros servicios a familiares y amigos?
              </p>
              <div className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg font-bold text-lg shadow-sm
                ${pregunta2 === 'Definitivamente sí' ? 'bg-green-600/20 text-green-400 border border-green-500/30' : 
                  pregunta2 === 'Probablemente sí' ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30' : 
                  'bg-orange-600/20 text-orange-400 border border-orange-500/30'}`}>
                {pregunta2}
              </div>
            </div>
          </div>
          
          <div className="bg-blue-900/10 px-6 py-4 border-t border-blue-900/30 text-right">
            <p className="text-xs md:text-sm text-blue-300 font-medium italic">
              Registrado el: {fechaSolo}
            </p>
          </div>
        </div>
      )}
    </div>
  );
};

export default function MedicalHistoryView() {
  const location = useLocation();
  const [history, setHistory] = useState([]);
  const [hospitalization, setHospitalization] = useState([]); // Nueva variable de estado para hospitalización
  const [attachments, setAttachments] = useState([]);
  const [surgeries, setSurgeries] = useState([]); // Nuevo estado para cirugías
  const [diagnosticSupport, setDiagnosticSupport] = useState([]); // Nuevo estado para Apoyos Diagnósticos
  const [loading, setLoading] = useState(true);
  const [selectedIngreso, setSelectedIngreso] = useState(null);
  const [activeTab, setActiveTab] = useState(1); // 1: Consulta Externa, 2: Apoyos Diagnósticos, 3: Cirugía
  const [permissions, setPermissions] = useState([]);
  const [surgeryViewMode, setSurgeryViewMode] = useState("procedures"); // 'procedures' | 'histories'
  const [sendingEmail, setSendingEmail] = useState(false);
  const { user } = useUser();

  // Effect para resetear la vista si la ubicación cambia (ej. clic en menú Historial Médico)
  // Se usa location.key (que cambia en cada push) o location.pathname si se navega a la misma ruta
  useEffect(() => {
    setSelectedIngreso(null);
  }, [location.pathname, location.key]);

  useEffect(() => {
    loadHistory();
    loadPermissions();
  }, []);

  useEffect(() => {
    if (activeTab === 5) loadAttachments();
    if (activeTab === 3) loadSurgeries();
    if (activeTab === 4) loadHospitalization();
    if (activeTab === 2) loadDiagnosticSupport();
  }, [activeTab]);

  const loadPermissions = async () => {
    try {
      const data = await historyService.getPermissions();
      // La API retorna { success: true, list: [...], data: {...} }
      // Usamos 'list' que contiene el array de permisos
      let list = [];
      if (data && data.list && Array.isArray(data.list)) {
        list = data.list;
      } else if (Array.isArray(data)) {
        list = data;
      }
      setPermissions(list);

      // Si el tab actual (1 por defecto) está inactivo, buscar el primero activo
      const currentTabPerm = list.find((p) => p.id == activeTab);
      if (currentTabPerm && currentTabPerm.estado === "0") {
        const firstActive = list.find((p) => p.estado === "1" && p.id <= 5);
        if (firstActive) setActiveTab(parseInt(firstActive.id));
      }
    } catch (e) {
      console.error("Error loading permissions", e);
    }
  };

  const getModulePermissions = (moduleIdOrTab) => {
    // Mapping user tabs to DB IDs
    let dbId = moduleIdOrTab;
    // Tabs 1, 2, 3, 4, 5 map exactly to DB IDs now.
    // 1: CONSULTA_EXTERNA, 2: APOYOS, 3: CIRUGIA, 4: HOSPITALIZACION, 5: ADJUNTOS

    // Usar comparación '==' para evitar problemas de tipos (string vs number)
    const p = permissions.find((x) => x.id == dbId);

    // Return true by default if not loaded or not found to avoid blocking
    if (!p) return { sw_imprime: true, sw_correo: true };

    // Convert output to boolean. "1", 1, true, "VERDADERO" are considered true.
    const isTrue = (val) =>
      val === 1 || val === "1" || val === true || val === "VERDADERO";
    return {
      sw_imprime: isTrue(p.sw_imprime),
      sw_correo: isTrue(p.sw_correo),
      estado: p.estado ?? "1",
    };
  };

  const handleSendDiagnosticEmail = async (resultadoId, examenNombre) => {
    const result = await Swal.fire({
      title: "¿Enviar resultado por correo?",
      text: `Se enviará el resultado de "${examenNombre}" al correo registrado: ${
        user?.paciente?.email || "N/A"
      }.`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Sí, enviar",
      cancelButtonText: "Cancelar",
      background: "#1e293b",
      color: "#fff",
    });

    if (!result.isConfirmed) return;

    setSendingEmail(true);
    try {
      await historyService.sendDiagnosticEmail(resultadoId);
      Swal.fire({
        title: "¡Enviado!",
        text: "El resultado ha sido enviado exitosamente a tu correo.",
        icon: "success",
        confirmButtonColor: "#10b981",
        background: "#1e293b",
        color: "#fff",
      });
    } catch (error) {
      Swal.fire({
        title: "Error",
        text: "Error enviando el correo.",
        icon: "error",
        background: "#1e293b",
        color: "#fff",
      });
    } finally {
      setSendingEmail(false);
    }
  };

  const loadHistory = async () => {
    setLoading(true);
    try {
      const data = await historyService.getHistory();
      if (data.success) setHistory(data.data);
    } catch (error) {
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const loadAttachments = async () => {
    setLoading(true);
    try {
      const data = await historyService.getAttachments();
      if (data.success) setAttachments(data.data);
    } catch (error) {
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const loadSurgeries = async () => {
    setLoading(true);
    try {
      // Backend now handles fetching ALL surgeries for the patient if auth user is a patient.
      // We pass '0' as dummy ID because the route expects a parameter.
      const res = await historyService.getSurgeries(0);
      if (res.success && Array.isArray(res.data)) {
        setSurgeries(res.data);
      } else {
        setSurgeries([]);
      }
    } catch (error) {
      console.error(error);
      setSurgeries([]);
    } finally {
      setLoading(false);
    }
  };

  const loadDiagnosticSupport = async () => {
    setLoading(true);
    try {
      const data = await historyService.getDiagnosticSupport();
      if (data.success) {
        setDiagnosticSupport(data.data);
      } else {
        setDiagnosticSupport([]);
      }
    } catch (error) {
      console.error(error);
      setDiagnosticSupport([]);
    } finally {
      setLoading(false);
    }
  };

  const loadHospitalization = async () => {
    setLoading(true);
    try {
      const data = await historyService.getHospitalization();
      if (data.success) {
        setHospitalization(data.data);
      } else {
        setHospitalization([]);
      }
    } catch (error) {
      console.error(error);
      setHospitalization([]);
    } finally {
      setLoading(false);
    }
  };

  const handleSurvey = async (item) => {
    const { value: formValues } = await Swal.fire({
      title: "Encuesta de Satisfacción",
      html: `
        <div class="text-left space-y-4">
          <p class="text-sm text-gray-400 mb-4">Para continuar viendo su historia clínica, por favor califique nuestro servicio en este ingreso (${item.ingreso}).</p>
          
          <div class="space-y-2">
            <label class="block text-sm font-medium text-white">1. ¿Cómo califica la atención recibida por parte del personal médico?</label>
            <select id="p1" class="w-full p-2 rounded bg-slate-700 border border-slate-600 text-white focus:ring-2 focus:ring-blue-500">
              <option value="">Seleccione...</option>
              <option value="Excelente">Excelente</option>
              <option value="Bueno">Bueno</option>
              <option value="Regular">Regular</option>
              <option value="Malo">Malo</option>
            </select>
          </div>

          <div class="space-y-2 pt-2">
            <label class="block text-sm font-medium text-white">2. ¿Recomendaría nuestros servicios a familiares y amigos?</label>
            <select id="p2" class="w-full p-2 rounded bg-slate-700 border border-slate-600 text-white focus:ring-2 focus:ring-blue-500">
              <option value="">Seleccione...</option>
              <option value="Definitivamente sí">Definitivamente sí</option>
              <option value="Probablemente sí">Probablemente sí</option>
              <option value="No estoy seguro">No estoy seguro</option>
              <option value="No">No</option>
            </select>
          </div>
        </div>
      `,
      focusConfirm: false,
      showCancelButton: true,
      confirmButtonText: "Enviar y Continuar",
      cancelButtonText: "Cancelar",
      background: "#1e293b",
      color: "#fff",
      confirmButtonColor: "#3b82f6",
      preConfirm: () => {
        const p1 = document.getElementById("p1").value;
        const p2 = document.getElementById("p2").value;
        if (!p1 || !p2) {
          Swal.showValidationMessage("Por favor responda ambas preguntas");
          return false;
        }
        return { p1, p2 };
      },
    });

    if (formValues) {
      try {
        const payload = {
          ingreso: item.ingreso,
          pregunta_1: formValues.p1,
          pregunta_2: formValues.p2,
        };
        const res = await historyService.saveSurvey(payload);
        if (res.success) {
          Swal.fire({
            icon: "success",
            title: "¡Gracias!",
            text: "Su respuesta ha sido registrada. Ya puede ver su información.",
            timer: 2000,
            showConfirmButton: false,
            background: "#1e293b",
            color: "#fff",
          });
          // Recargar datos para actualizar el flag de encuesta_completada
          loadHistory();
          loadHospitalization();
          loadDiagnosticSupport();
          loadSurgeries();

          // Solo abrir el detalle automáticamente para Consulta Externa (1) u Hospitalización (4)
          // Para Apoyos y Cirugía nos quedamos en el listado para mostrar la tarjeta de resultados
          if (activeTab === 1 || activeTab === 4) {
            setSelectedIngreso(item.ingreso);
          }
        }
      } catch (error) {
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "No se pudo guardar la encuesta. Intente nuevamente.",
          background: "#1e293b",
          color: "#fff",
        });
      }
    }
  };

  if (selectedIngreso) {
    return (
      <HistoryDetail
        ingresoId={selectedIngreso}
        onBack={() => setSelectedIngreso(null)}
        permissions={getModulePermissions(activeTab)}
      />
    );
  }

  // Filtrar historial según tab activa
  // Nota: tipo_consulta_id puede venir como número o string "1"/"2"
  const filteredHistory = history.filter((item) => {
    return parseInt(item.tipo_consulta_id) === activeTab;
  });

  const getFileUrl = (item) => {
    if (!item || !item.nombre_asignado) return "#";
    const baseUrl = import.meta.env.VITE_LEGACY_REPO_URL;
    return `${baseUrl}/${item.tipo_id_paciente}-${item.paciente_id}/${item.nombre_asignado}`;
  };

    // Devuelve la URL legacy SOLO para Apoyos Diagnósticos
  const getDiagnosticSupportFileUrl = (item) => {
    if (!item || !item.nombre_archivo_carpeta) return "#";
    const baseUrl = import.meta.env.VITE_LEGACY_REPO_URL;
    return `${baseUrl}/${item.nombre_archivo_carpeta}`;
  };

  return (
    <div className="space-y-6">
      <div className="space-y-4">
        <h2 className="text-2xl font-bold flex items-center gap-2 text-white">
          <FileText className="text-blue-400" /> Historial Médico
        </h2>

        {/* Tabs de Tipo de Consulta - Aligned Right */}
        <div className="flex justify-end gap-3 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-blue-600/20 scrollbar-track-transparent">
          {[
            { id: 1, label: "Consulta Externa" },
            { id: 2, label: "Apoyos Diagnósticos" },
            { id: 3, label: "Cirugía" },
            { id: 4, label: "Hospitalización" },
            { id: 5, label: "Adjuntos Generales" },
          ].map((tab) => {
            const perm = getModulePermissions(tab.id);
            if (perm.estado === "0") return null;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`shrink-0 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border ${
                  activeTab === tab.id
                    ? "bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20"
                    : "bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white"
                }`}
              >
                {tab.label}
              </button>
            );
          })}
        </div>
      </div>

      {/* Lista de Tarjetas (Agrupadas por Ingreso) */}
      <div className="grid grid-cols-1 gap-4">
        {loading ? (
          <div className="text-center py-8 text-gray-400">
            Cargando registros...
          </div>
        ) : activeTab === 5 ? (
          attachments.length === 0 ? (
            <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
              <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
              <p className="text-gray-400">No hay adjuntos para mostrar.</p>
            </div>
          ) : (
            attachments.map((item, i) => (
              <div
                key={i}
                className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group"
              >
                <div className="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                  <div className="flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-3 text-sm text-blue-300 font-semibold uppercase tracking-wider">
                      <div className="px-2 py-1 rounded text-sm bg-purple-500/20 text-purple-400">
                        Adjunto #{item.archivo_id}
                      </div>
                      <span className="flex items-center gap-1">
                        <Calendar size={14} /> {item.fecha_registro}
                      </span>
                    </div>
                    <h3 className="text-base font-bold text-white group-hover:text-blue-400 transition-colors">
                      {item.nombre_original}
                    </h3>
                    {item.observacion && (
                      <p className="text-sm text-gray-400 italic border-l-2 border-gray-600 pl-2">
                        {item.observacion}
                      </p>
                    )}
                  </div>

                  <div className="flex items-center gap-2 w-full md:w-auto">
                    <a
                      href={getFileUrl(item)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 flex-1 justify-center bg-blue-600 hover:bg-blue-500 text-white"
                    >
                      <Eye size={18} /> Ver Archivo
                    </a>
                  </div>
                </div>
              </div>
            ))
          )
        ) : activeTab === 2 ? (
          /* ================= APOYOS DIAGNÓSTICOS (LISTA DEDICADA) ================= */
          diagnosticSupport.length === 0 ? (
            <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
              <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
              <p className="text-gray-400">
                No hay registros de Apoyos Diagnósticos para mostrar.
              </p>
            </div>
          ) : (
            diagnosticSupport.map(function (item, i) {
              return (
                <div
                  key={i}
                  className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group"
                >
                  <div className="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div className="flex-1 space-y-2">
                      <div className="flex flex-wrap items-center gap-3 text-sm text-blue-300 font-semibold uppercase tracking-wider">
                        <div className="px-2 py-1 rounded text-sm bg-cyan-500/20 text-cyan-400">
                          Orden #
                          {item.numero_cumplimiento || item.numero_orden_id}
                        </div>
                        <span className="flex items-center gap-1">
                          <Calendar size={14} /> {item.fecha_cumplimiento}
                        </span>
                        <div className="flex items-center gap-2 text-blue-300 text-sm border-l border-white/10 pl-3 ml-1 font-bold">
                          <User size={14} className="text-blue-500" />
                          <span>
                            {item.nombre_profesional || "PROFESIONAL"}
                          </span>
                        </div>
                      </div>
                      <h3 className="text-sm font-bold text-white group-hover:text-blue-400 transition-colors">
                        {item.cargo ? `${item.cargo} - ` : ""}{item.descripcion || "EXAMEN DIAGNÓSTICO"}
                      </h3>
                      <p className="text-xs text-gray-400">
                        {item.servicio_descripcion || item.servicio}
                      </p>
                    </div>

                    <div className="flex items-center gap-2 w-full md:w-auto">
                      <button
                        onClick={() => {
                          if (parseInt(item.encuesta_completada) === 1) {
                            historyService.printDiagnosticSupport(item.resultado_id);
                          } else {
                            handleSurvey(item);
                          }
                        }}
                        title="Ver/Imprimir Resultado PDF"
                        className="px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 justify-center bg-blue-600 hover:bg-blue-500 text-white"
                      >
                        <Printer size={18} /> Ver Resultado
                      </button>

                      {getModulePermissions(2).sw_correo && (
                        <button
                          onClick={() => {
                            if (parseInt(item.encuesta_completada) === 1) {
                              handleSendDiagnosticEmail(item.resultado_id, item.descripcion);
                            } else {
                              handleSurvey(item);
                            }
                          }}
                          disabled={sendingEmail}
                          title="Enviar Resultado al Correo"
                          className="px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 justify-center bg-emerald-600 hover:bg-emerald-500 text-white disabled:opacity-50"
                        >
                          <Mail size={18} />{" "}
                          {sendingEmail ? "Enviando..." : "Enviar Correo"}
                        </button>
                      )}

                      <button
                        onClick={() => {
                          if (parseInt(item.encuesta_completada) === 1) {
                            window.open(getDiagnosticSupportFileUrl(item), '_blank');
                          } else {
                            handleSurvey(item);
                          }
                        }}
                        className="px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 justify-center bg-purple-700 hover:bg-purple-600 text-white"
                        title="Imprimir Archivo Adjunto (Original)"
                        style={{ pointerEvents: !item.nombre_archivo_carpeta ? 'none' : 'auto', opacity: !item.nombre_archivo_carpeta ? 0.5 : 1 }}
                      >
                        <Printer size={18} /> Ver Detalle
                      </button>
                    </div>
                  </div>
                  {/* Encuesta de Satisfacción Integrada */}
                  <div className="px-6 pb-6">
                    <EncuestaCard item={item} />
                  </div>
                </div>
              );
            })
          )
        ) : activeTab === 3 ? (
          <div>
            {/* Navegación interna de Cirugía */}
            <div className="flex gap-4 mb-6 border-b border-white/10 pb-2">
              <button
                onClick={() => setSurgeryViewMode("procedures")}
                className={`pb-2 text-sm font-semibold transition-colors ${surgeryViewMode === "procedures" ? "text-blue-400 border-b-2 border-blue-400" : "text-gray-400 hover:text-white"}`}
              >
                Procedimientos / Notas
              </button>
            </div>

            {surgeries.length === 0 ? (
              <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
                <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
                <p className="text-gray-400">
                  No hay registros de Cirugías para mostrar.
                </p>
              </div>
            ) : (
              surgeries.map((item, i) => (
                <div
                  key={i}
                  className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group mb-4"
                >
                  <div className="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div className="flex-1 space-y-2">
                      {/* ... (Contenido Tarjeta Cirugía) ... */}
                      <div className="flex flex-wrap items-center gap-3 text-sm text-blue-300 font-semibold uppercase tracking-wider">
                        <div className="px-2 py-1 rounded text-sm bg-red-500/20 text-red-400">
                          Cirugía #{item.hc_nota_operatoria_cirugia_id}
                        </div>
                        <span className="flex items-center gap-1">
                          <Calendar size={14} />{" "}
                          {item.fecha_hora || item.hora_inicio}
                        </span>
                        <div className="flex items-center gap-2 text-blue-300 text-sm border-l border-white/10 pl-3 ml-1 font-bold">
                          <User size={14} className="text-blue-500" />
                          <span>{item.cirujano_nombre}</span>
                        </div>
                      </div>
                      <h3 className="text-base font-bold text-white group-hover:text-blue-400 transition-colors">
                        {item.procedimiento_principal ||
                          item.tipo_cirugia ||
                          "PROCEDIMIENTO QUIRÚRGICO"}
                      </h3>
                      <p className="text-sm text-gray-400">
                        Quirófano: {item.nom_quirofano} | Evolución ID:{" "}
                        {item.evolucion_id}
                      </p>
                    </div>

                    <div className="flex items-center gap-2 w-full md:w-auto">
                      {getModulePermissions(3).sw_imprime && (
                        <button
                          onClick={() => {
                            if (parseInt(item.encuesta_completada) === 1) {
                              historyService.printHistoryComplete(item.ingreso);
                            } else {
                              handleSurvey(item);
                            }
                          }}
                          className="px-4 py-2 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-600"
                          title="Imprimir Historia Clínica del Ingreso"
                        >
                          <FileText size={16} /> Historia
                        </button>
                      )}

                      <button
                        onClick={() => {
                          if (parseInt(item.encuesta_completada) === 1) {
                            historyService.printNotaOperatoria(item.hc_nota_operatoria_cirugia_id);
                          } else {
                            handleSurvey(item);
                          }
                        }}
                        className="px-4 py-2 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white"
                        title="Imprimir Nota Operatoria"
                      >
                        <Printer size={16} /> Imprimir Nota Operatoria
                      </button>

                      {getModulePermissions(3).sw_correo && (
                        <button
                          onClick={async () => {
                            if (parseInt(item.encuesta_completada) !== 1) {
                              handleSurvey(item);
                              return;
                            }
                            const result = await Swal.fire({
                              title: "¿Enviar Nota Operatoria?",
                              text: `Se enviará la Nota Operatoria al correo registrado: ${
                                user?.paciente?.email || "N/A"
                              }.`,
                              icon: "question",
                              showCancelButton: true,
                              confirmButtonText: "Sí, enviar",
                              background: "#1e293b",
                              color: "#fff",
                            });
                            if (result.isConfirmed) {
                              try {
                                await historyService.sendSurgeryEmail(
                                  item.hc_nota_operatoria_cirugia_id,
                                );
                                Swal.fire({
                                  title: "Enviado",
                                  text: "El reporte ha sido enviado exitosamente a tu correo.",
                                  icon: "success",
                                  background: "#1e293b",
                                  color: "#fff",
                                });
                              } catch (e) {
                                Swal.fire({
                                  title: "Error",
                                  text: "No se pudo enviar",
                                  icon: "error",
                                  background: "#1e293b",
                                  color: "#fff",
                                });
                              }
                            }
                          }}
                          className="px-4 py-2 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white"
                          title="Enviar por Correo"
                        >
                          <Send size={16} /> Enviar
                        </button>
                      )}
                    </div>
                  </div>
                  {/* Encuesta de Satisfacción Integrada */}
                  <div className="px-6 pb-6">
                    <EncuestaCard item={item} />
                  </div>
                </div>
              ))
            )}
          </div>
        ) : activeTab === 4 ? (
          /* ================= HOSPITIZACIÓN (LISTA DEDICADA) ================= */
          hospitalization.length === 0 ? (
            <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
              <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
              <p className="text-gray-400">
                No hay registros de Hospitalización para mostrar.
              </p>
            </div>
          ) : (
            hospitalization.map((item, i) => (
              <div
                key={i}
                className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group"
              >
                <div className="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                  <div className="flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-3 text-sm text-blue-300 font-semibold uppercase tracking-wider">
                      <div
                        className={`px-2 py-1 rounded text-sm ${item.estado === "1" ? "bg-green-500/20 text-green-400" : "bg-gray-500/20 text-gray-400"}`}
                      >
                        Ingreso #{item.ingreso}
                      </div>
                      <span className="flex items-center gap-1">
                        <Calendar size={14} /> {item.fecha}
                      </span>
                      <div className="flex items-center gap-2 text-blue-300 text-sm border-l border-white/10 pl-3 ml-1 font-bold">
                        <User size={14} className="text-blue-500" />
                        <span>{item.profesional_nombre || "INSTITUCIÓN"}</span>
                      </div>
                    </div>
                    {/* <h3 className="text-base font-bold text-white group-hover:text-blue-400 transition-colors">
                                    {item.codigo_servicio ? `${item.codigo_servicio} - ` : ''}
                                    {item.servicio || "ATENCIÓN HOSPITALARIA / URGENCIAS"}
                                </h3> */}
                  </div>

                  <div className="flex items-center gap-2 w-full md:w-auto">
                    <button
                      onClick={() => {
                        if (parseInt(item.encuesta_completada) === 1) {
                          setSelectedIngreso(item.ingreso);
                        } else {
                          handleSurvey(item);
                        }
                      }}
                      className="px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 flex-1 justify-center bg-blue-600 hover:bg-blue-500 text-white"
                    >
                      <Eye size={18} /> Ver Ordenes y Solicitudes
                    </button>
                  </div>
                </div>
              </div>
            ))
          )
        ) : filteredHistory.length === 0 ? (
          <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
            <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
            <p className="text-gray-400">
              No hay registros de{" "}
              {activeTab === 1
                ? "Consulta Externa"
                : activeTab === 2
                  ? "Apoyos Diagnósticos"
                  : activeTab === 4
                    ? "Hospitalización"
                    : "Registros"}{" "}
              para mostrar.
            </p>
          </div>
        ) : (
          filteredHistory.map((item, i) => (
            <div
              key={i}
              className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group"
            >
              <div className="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                {/* Info Principal */}
                <div className="flex-1 space-y-2">
                  <div className="flex flex-wrap items-center gap-3 text-sm text-blue-300 font-semibold uppercase tracking-wider">
                    <div
                      className={`px-2 py-1 rounded text-sm ${item.estado === "1" ? "bg-green-500/20 text-green-400" : "bg-gray-500/20 text-gray-400"}`}
                    >
                      Ingreso #{item.ingreso}
                    </div>
                    <span className="flex items-center gap-1">
                      <Calendar size={14} /> {item.fecha}
                    </span>

                    {/* Professional Name moved here */}
                    <div className="flex items-center gap-2 text-blue-300 text-sm border-l border-white/10 pl-3 ml-1 font-bold">
                      <User size={14} className="text-blue-500" />
                      <span>{"PROFESIONAL: " + item.profesional_nombre}</span>
                    </div>
                  </div>
                  <h3 className="text-base font-bold text-white group-hover:text-blue-400 transition-colors">
                    {item.codigo_servicio ? `${item.codigo_servicio} - ` : ""}
                    {item.servicio || "ATENCIÓN MÉDICA GENERAL"}
                  </h3>
                </div>

                {/* Botón Acción */}
                <div className="flex items-center gap-2 w-full md:w-auto">
                  <button
                    onClick={() => {
                      if (parseInt(item.encuesta_completada) === 1) {
                        setSelectedIngreso(item.ingreso);
                      } else {
                        handleSurvey(item);
                      }
                    }}
                    className={`px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 flex-1 justify-center ${
                      activeTab === 1
                        ? "bg-blue-600 hover:bg-blue-500 text-white"
                        : "bg-purple-600 hover:bg-purple-500 text-white"
                    }`}
                  >
                    <Eye size={18} /> Ver Ordenes y Solicitudes
                  </button>
                </div>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

function HistoryDetail({ ingresoId, onBack, permissions }) {
  const [details, setDetails] = useState({
    medicamentos: [],
    solicitudes: [],
    incapacidades: [],
    procedimientos_no_qx: [], // Agregado para soportar procedimientos no quirúrgicos
    encuesta: null, // Nuevo estado para la encuesta de satisfacción
  }); // Ahora incluye incapacidades
  const [loading, setLoading] = useState(true);
  const [sendingEmail, setSendingEmail] = useState(false);
  const { user } = useUser();

  useEffect(() => {
    historyService.getDetail(ingresoId).then((data) => {
      if (data.success) setDetails(data.data);
      setLoading(false);
    });
  }, [ingresoId]);

  const handleSendEmail = async (type = "all", evolucionId = null) => {
    let msg = `Se enviará el reporte completo`;
    if (type === "formula") msg = `Se enviará la fórmula médica`;
    if (type === "ordenes") msg = `Se enviarán las órdenes médicas`;

    const result = await Swal.fire({
      title: "¿Enviar reporte por correo?",
      text: `${msg} al correo registrado: ${user?.paciente?.email || "N/A"}.`,
      icon: "question",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "Sí, enviar",
      cancelButtonText: "Cancelar",
      background: "#1e293b",
      color: "#fff",
    });

    if (!result.isConfirmed) return;

    setSendingEmail(true);
    try {
      await historyService.sendReportEmail(ingresoId, type, evolucionId);
      Swal.fire({
        title: "¡Enviado!",
        text: "El reporte ha sido enviado exitosamente a tu correo.",
        icon: "success",
        confirmButtonColor: "#10b981",
        background: "#1e293b",
        color: "#fff",
      });
    } catch (error) {
      Swal.fire({
        title: "Error",
        text: "Error enviando el correo.",
        icon: "error",
        background: "#1e293b",
        color: "#fff",
      });
      console.error(error);
    } finally {
      setSendingEmail(false);
    }
  };

  if (loading)
    return (
      <div className="text-white text-center py-10">Cargando detalles...</div>
    );

  // Helper para agrupar medicamentos por 'evolucion_id' (aunque suelen venir juntos, por si acaso hay multiples evoluciones en un ingreso)
  const renderMedicamentos = () => {
    if (!details.medicamentos || details.medicamentos.length === 0) return null;

    const grouped = details.medicamentos.reduce((acc, curr) => {
      (acc[curr.evolucion_id] = acc[curr.evolucion_id] || []).push(curr);
      return acc;
    }, {});

    return Object.entries(grouped).map(([evolucionId, meds]) => (
      <div
        key={`med-${evolucionId}`}
        className="bg-[#1e293b] rounded-xl border border-blue-900/30 overflow-hidden shadow-md mb-5"
      >
        {/* Header */}
        <div className="bg-blue-900/20 px-4 py-2 border-b border-blue-900/30 flex justify-between items-center">
          <h4 className="font-bold text-blue-300 flex items-center gap-2 uppercase text-base">
            <Pill size={18} /> Medicamentos Formulados
          </h4>
          <span className="text-sm text-blue-400 font-semibold">
            Ref: {evolucionId}
          </span>
        </div>

        {/* Items */}
        <div className="divide-y divide-blue-900/30">
          {meds.map((med, i) => (
            <div key={i} className="p-3 hover:bg-white/5 transition-colors">
              {/* ✅ UNA SOLA LÍNEA: Producto + Principio Activo */}
              <div className="flex items-center gap-2 w-full">
                <span className="text-sm font-bold text-blue-100 flex-1 min-w-0 truncate">
                  {med.producto}
                  {med.principio_activo ? (
                    <span className="font-normal text-gray-300 italic">
                      {" "}
                      — {med.principio_activo}
                    </span>
                  ) : null}
                </span>
              </div>

              {/* Chips compactos */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-2 mt-2 text-sm text-gray-200">
                <span className="bg-blue-500/10 px-3 py-1 rounded border border-blue-500/20">
                  Dosis:{" "}
                  <span className="font-bold text-white">
                    {med.dosis} {med.unidad_dosificacion}
                  </span>
                </span>

                <span className="bg-blue-500/10 px-3 py-1 rounded border border-blue-500/20">
                  Frec:{" "}
                  <span className="font-bold text-white">{med.frecuencia}</span>
                </span>

                <span className="bg-blue-500/10 px-3 py-1 rounded border border-blue-500/20">
                  Cant:{" "}
                  <span className="font-bold text-white">{med.cantidad}</span>
                </span>
              </div>

              {/* Nota compacta */}
              {!!med.observacion && (
                <div className="text-xs text-yellow-500/90 mt-2 font-medium line-clamp-2">
                  Nota: {med.observacion}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Footer acciones */}
        <div className="bg-blue-950/30 p-2 text-center border-t border-blue-900/30">
          <div className="flex flex-wrap justify-center gap-x-6 gap-y-2">
            <button
              onClick={() => historyService.printFormula(evolucionId)}
              className="text-blue-400 hover:text-blue-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide"
            >
              <Printer size={14} /> Imprimir Fórmula
            </button>

            {permissions?.sw_correo && (
              <button
                onClick={() => handleSendEmail("formula", evolucionId)}
                disabled={sendingEmail}
                className="text-blue-400 hover:text-blue-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide"
              >
                <Mail size={14} /> Enviar al Correo
              </button>
            )}
          </div>
        </div>
      </div>
    ));
  };

  const renderSolicitudes = () => {
    if (!details.solicitudes || details.solicitudes.length === 0) return null;

    const grouped = details.solicitudes.reduce((acc, curr) => {
      (acc[curr.evolucion_id] = acc[curr.evolucion_id] || []).push(curr);
      return acc;
    }, {});

    return Object.entries(grouped).map(([evolucionId, sols]) => {
      // Tomar datos del primer elemento del grupo para los parámetros (si los usas luego)
      const sol0 = sols[0];

      // Parámetros legacy (por si los necesitas)
      const tipoIdPaciente = user?.paciente?.tipo_id_paciente || "CC";
      const pacienteId = user?.paciente?.paciente_id || "";
      const nombres = user?.paciente?.nombre_completo || "";

      // Buscar ingreso asociado
      let ingreso = "";
      if (sol0?.ingreso) {
        ingreso = sol0.ingreso;
      } else {
        const found = details.solicitudes.find(
          (s) => s.evolucion_id === evolucionId,
        );
        if (found?.ingreso) ingreso = found.ingreso;
      }

      const nroImpresionTabla = 0;

      return (
        <div
          key={`sol-${evolucionId}`}
          className="bg-[#1e293b] rounded-xl border border-blue-900/30 overflow-hidden shadow-sm mb-4"
        >
          {/* Header más compacto */}
          <div className="bg-purple-900/20 px-4 py-2 border-b border-blue-900/30 flex justify-between items-center">
            <h4 className="font-bold text-purple-300 flex items-center gap-2 uppercase text-base">
              <Stethoscope size={18} /> Órdenes y Solicitudes
            </h4>
            <span className="text-sm text-purple-400 font-semibold">
              Ref: {evolucionId}
            </span>
          </div>

          <div className="divide-y divide-blue-900/30">
            {sols.map((sol, i) => (
              <div key={i} className="p-3 hover:bg-white/5 transition-colors">
                {/* UNA SOLA LÍNEA: CARGO | DESCRIPCIÓN | FECHA */}
                <div className="flex items-center gap-3 w-full">
                  {/* Cargo fijo */}
                  <span className="text-sm font-bold font-mono text-gray-300 shrink-0">
                    {sol.cargo}
                  </span>

                  {/* Descripción ocupa lo restante y se corta con ... */}
                  <span className="text-sm font-semibold text-purple-100 flex-1 min-w-0 truncate">
                    {sol.descripcion}
                  </span>

                  {/* Fecha fija a la derecha */}
                  <span className="text-sm font-mono text-gray-300 shrink-0">
                    {sol.fecha_solicitud}
                  </span>
                </div>

                {/* Observación opcional (si quieres también en 1 línea compacta) */}
                {!!sol.observacion && (
                  <div className="text-xs text-gray-300 italic truncate mt-1">
                    Obs: {sol.observacion}
                  </div>
                )}
              </div>
            ))}
          </div>

          {/* Footer compacto */}
          <div className="bg-purple-950/20 p-2 text-center border-t border-blue-900/30">
            <div className="flex flex-wrap justify-center gap-x-6 gap-y-2">
              <button
                onClick={() => historyService.printOrder(evolucionId)}
                className="text-purple-400 hover:text-purple-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide"
              >
                <Printer size={14} /> Imprimir Orden
              </button>
              {permissions?.sw_correo && (
                <button
                  onClick={() => handleSendEmail("ordenes", evolucionId)}
                  disabled={sendingEmail}
                  className="text-purple-400 hover:text-purple-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide disabled:opacity-50"
                >
                  <Mail size={14} /> Enviar al Correo
                </button>
              )}
            </div>
          </div>
        </div>
      );
    });
  };

  const renderIncapacidades = () => {
    if (!details.incapacidades || details.incapacidades.length === 0)
      return null;

    const grouped = details.incapacidades.reduce((acc, curr) => {
      (acc[curr.evolucion_id] = acc[curr.evolucion_id] || []).push(curr);
      return acc;
    }, {});

    return Object.entries(grouped).map(([evolucionId, incs]) => (
      <div
        key={`inc-${evolucionId}`}
        className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-md mb-6"
      >
        {/* Header */}
        <div className="bg-amber-900/20 px-4 py-2.5 border-b border-blue-900/30 flex justify-between items-center">
          <h4 className="font-bold text-amber-300 flex items-center gap-2 uppercase text-base">
            <Activity size={18} /> Incapacidad Médica
          </h4>
          <span className="text-sm text-amber-400 font-semibold">
            Ref: {evolucionId}
          </span>
        </div>

        {/* Items */}
        <div className="divide-y divide-blue-900/30">
          {incs.map((inc, i) => (
            <div
              key={i}
              className="px-4 py-3 hover:bg-white/5 transition-colors"
            >
              {/* LINEA ÚNICA (DÍAS + INICIO) */}
              <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2 text-sm text-gray-200 font-semibold whitespace-nowrap">
                  <span className="font-mono">
                    {inc.dias_de_incapacidad} Día(s)
                  </span>
                  <span className="text-gray-500">•</span>
                  <span className="text-gray-300 font-medium">
                    Inicio: {inc.fecha_inicio}
                  </span>
                </div>
              </div>

              {/* Diagnóstico (1 línea) */}
              <p className="mt-1 font-bold text-amber-100 text-base leading-tight truncate">
                {inc.diagnostico_nombre}
              </p>

              {/* Observación */}
              {inc.observacion_incapacidad && (
                <p className="mt-1 text-sm text-gray-300 italic leading-snug">
                  <span className="font-semibold">Obs:</span>{" "}
                  {inc.observacion_incapacidad}
                </p>
              )}
            </div>
          ))}
        </div>

        {/* Footer acciones */}
        <div className="bg-amber-950/20 px-3 py-2 border-t border-blue-900/30">
          <div className="flex flex-wrap justify-center gap-x-6 gap-y-2">
            <button
              onClick={() =>
                historyService.printIncapacidad &&
                historyService.printIncapacidad(evolucionId)
              }
              className="text-amber-400 hover:text-amber-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide"
            >
              <Printer size={14} /> Imprimir Incapacidad
            </button>

            {permissions?.sw_correo && (
              <button
                onClick={() => handleSendEmail("incapacidad", evolucionId)}
                disabled={sendingEmail}
                className={`flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide ${
                  sendingEmail
                    ? "text-gray-500 cursor-not-allowed"
                    : "text-amber-400 hover:text-amber-300 hover:underline"
                }`}
              >
                <Mail size={14} /> Enviar al Correo
              </button>
            )}
          </div>
        </div>
      </div>
    ));
  };

  const renderProcedimientosNoQx = () => {
    if (
      !details.procedimientos_no_qx ||
      details.procedimientos_no_qx.length === 0
    )
      return null;

    return (
      <div className="bg-[#1e293b] rounded-xl border border-blue-900/30 overflow-hidden shadow-md mb-6">
        {/* Header Section */}
        <div className="bg-blue-900/40 px-4 py-2 border-b border-blue-900/30 flex justify-between items-center">
          <h4 className="font-bold text-blue-300 flex items-center gap-2 uppercase text-base">
            <Activity size={18} /> Procedimientos No Quirúrgicos
          </h4>
        </div>

        {/* Content Table-like structure */}
        {details.procedimientos_no_qx.map((item, i) => (
          <div
            key={i}
            className="border-b border-blue-900/20 last:border-0 hover:bg-white/5 transition-colors"
          >
            {/* Row 1: Main Info */}
            <div className="grid grid-cols-1 md:grid-cols-12 gap-2 p-3 text-sm">
              <div className="md:col-span-2 text-gray-400 font-mono text-xs">
                <div className="uppercase text-[10px] text-blue-400">
                  Solicitud
                </div>
                {item.numero_solicitud}
              </div>
              <div className="md:col-span-2 text-gray-400 font-mono text-xs">
                <div className="uppercase text-[10px] text-blue-400">Fecha</div>
                {item.fecha}
              </div>
              <div className="md:col-span-2 text-gray-400 font-mono text-xs">
                <div className="uppercase text-[10px] text-blue-400">Cargo</div>
                {item.cargo}
              </div>
              <div className="md:col-span-6 font-semibold text-white">
                <div className="uppercase text-[10px] text-blue-400">
                  Descripción
                </div>
                {item.descripcion}
              </div>
            </div>

            {/* Row 2: Observation (if exists) */}
            <div className="px-3 pb-3">
              <div className="bg-blue-950/30 border border-blue-900/20 rounded-md p-2">
                <span className="text-[10px] font-bold text-blue-400 uppercase block mb-1">
                  Observación
                </span>
                <p className="text-sm text-gray-300 italic">
                  {item.observacion || "Sin observación registrada."}
                </p>
              </div>
            </div>

            {/* Row 3: Actions Footer (Styled like Orders) */}
            <div className="bg-blue-950/30 p-2 text-center border-t border-blue-900/30">
              <div className="flex flex-wrap justify-center gap-x-6 gap-y-2">
                <button
                  onClick={() => historyService.printNoQx(ingresoId)}
                  className="text-blue-400 hover:text-blue-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide"
                >
                  <Printer size={14} /> Imprimir Formato
                </button>

                {permissions?.sw_correo && (
                  <button
                    onClick={async () => {
                      const result = await Swal.fire({
                        title: "¿Enviar Reporte?",
                        text: `Se enviará el reporte de Procedimientos No Quirúrgicos al correo: ${user?.paciente?.email || "N/A"}.`,
                        icon: "question",
                        showCancelButton: true,
                        confirmButtonText: "Sí, enviar",
                        background: "#1e293b",
                        color: "#fff",
                      });

                      if (result.isConfirmed) {
                        try {
                          setSendingEmail(true);
                          await historyService.sendNoQxEmail(ingresoId);
                          Swal.fire({
                            title: "Enviado",
                            text: "El reporte ha sido enviado exitosamente.",
                            icon: "success",
                            background: "#1e293b",
                            color: "#fff",
                          });
                        } catch (e) {
                          console.error(e);
                          Swal.fire({
                            title: "Error",
                            text: "No se pudo enviar el correo.",
                            icon: "error",
                            background: "#1e293b",
                            color: "#fff",
                          });
                        } finally {
                          setSendingEmail(false);
                        }
                      }
                    }}
                    disabled={sendingEmail}
                    className={`flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide ${
                      sendingEmail
                        ? "text-gray-500 cursor-not-allowed"
                        : "text-blue-400 hover:text-blue-300 hover:underline"
                    }`}
                  >
                    <Mail size={14} /> Enviar al Correo
                  </button>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  };

  const renderEncuesta = () => {
    if (!details.encuesta) return null;

    return (
      <div className="bg-[#1e293b] rounded-xl border border-blue-900/30 overflow-hidden shadow-md mb-8 animate-fade-in-up">
        <div className="bg-blue-900/40 px-6 py-3 border-b border-blue-900/30 flex items-center gap-3">
          <div className="p-2 rounded-lg bg-blue-500/20">
            <CheckCircle className="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <h3 className="text-lg font-bold text-white uppercase tracking-wider">
              Encuesta de Satisfacción
            </h3>
            <p className="text-xs text-blue-400 font-medium">
              Calificación registrada para este ingreso
            </p>
          </div>
        </div>

        <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Pregunta 1 Card */}
          <div className="bg-blue-950/40 rounded-xl p-5 border border-blue-800/20 shadow-inner group hover:border-blue-700/40 transition-all duration-300">
            <p className="text-sm font-semibold text-blue-300 mb-3 flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-blue-600/30 flex items-center justify-center text-xs text-blue-100 font-bold border border-blue-500/20 group-hover:scale-110 transition-transform">1</span>
              ¿Cómo califica la atención recibida por parte del personal médico?
            </p>
            <div className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg font-bold text-lg shadow-sm
              ${details.encuesta.pregunta_1 === 'Excelente' ? 'bg-green-600/20 text-green-400 border border-green-500/30' : 
                details.encuesta.pregunta_1 === 'Bueno' ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30' : 
                'bg-orange-600/20 text-orange-400 border border-orange-500/30'}`}>
              {details.encuesta.pregunta_1}
            </div>
          </div>

          {/* Pregunta 2 Card */}
          <div className="bg-blue-950/40 rounded-xl p-5 border border-blue-800/20 shadow-inner group hover:border-blue-700/40 transition-all duration-300">
            <p className="text-sm font-semibold text-blue-300 mb-3 flex items-center gap-2">
              <span className="w-6 h-6 rounded-full bg-blue-600/30 flex items-center justify-center text-xs text-blue-100 font-bold border border-blue-500/20 group-hover:scale-110 transition-transform">2</span>
              ¿Recomendaría nuestros servicios a familiares y amigos?
            </p>
            <div className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg font-bold text-lg shadow-sm
              ${details.encuesta.pregunta_2 === 'Definitivamente sí' ? 'bg-green-600/20 text-green-400 border border-green-500/30' : 
                details.encuesta.pregunta_2 === 'Probablemente sí' ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30' : 
                'bg-orange-600/20 text-orange-400 border border-orange-500/30'}`}>
              {details.encuesta.pregunta_2}
            </div>
          </div>
        </div>

        <div className="bg-blue-900/10 px-6 py-4 border-t border-blue-900/30 text-right">
          <p className="text-sm md:text-base text-blue-300 font-medium italic">
            Registrado el: {details.encuesta.fecha_registro ? details.encuesta.fecha_registro.split(' ')[0] : ''}
          </p>
        </div>
      </div>
    );
  };

  const handlePrintEvolucion = () => {
    historyService.printHistoryComplete(ingresoId);
  };

  return (
    <div className="space-y-6 animate-fade-in-up">
      {/* Header de Navegación del Detalle */}
      <div className="flex items-center justify-between bg-blue-950/20 p-4 rounded-xl border border-blue-900/30 mb-6 backdrop-blur-sm">
        <button
          onClick={onBack}
          className="flex items-center gap-3 px-4 py-2 bg-blue-600/10 hover:bg-blue-600 text-blue-300 hover:text-white rounded-lg border border-blue-500/30 transition-all duration-300 group font-semibold"
        >
          <div className="p-1 rounded-full bg-blue-500/20 group-hover:bg-white/20 transition-colors">
            <ArrowLeft className="w-5 h-5 group-hover:-translate-x-1 transition-transform" />
          </div>
          <span>Regresar al Listado</span>
        </button>

        {/* Botones de Acción Global (Ahora integrados en el header) */}
        <div className="flex flex-wrap items-center gap-4">
          {permissions?.sw_imprime && (
            <button
              onClick={handlePrintEvolucion}
              className="flex items-center gap-2 px-4 py-2 bg-[#1e293b] hover:bg-blue-600 border border-blue-500/30 rounded-lg text-blue-400 hover:text-white font-semibold text-xs uppercase tracking-wide transition-all shadow-sm hover:shadow-blue-500/20"
            >
              <Printer size={16} />{" "}
              <span className="hidden sm:inline">Imprimir Historia</span>
            </button>
          )}
          {permissions?.sw_correo && (
            <button
              onClick={() => handleSendEmail("all")}
              disabled={sendingEmail}
              className={`flex items-center gap-2 px-4 py-2 rounded-lg border font-semibold text-xs uppercase tracking-wide transition-all shadow-sm ${
                sendingEmail
                  ? "bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed"
                  : "bg-green-600/10 hover:bg-green-600 border-green-500/30 hover:border-green-500 text-green-400 hover:text-white shadow-green-500/10 hover:shadow-green-500/30"
              }`}
            >
              {sendingEmail ? (
                <Activity className="animate-spin" size={16} />
              ) : (
                <Send size={16} />
              )}
              <span className="hidden sm:inline">Enviar Todo</span>
            </button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6">
        <div>
          {/* Nueva Sección Encuesta de Satisfacción */}
          {renderEncuesta()}
        </div>
        <div>
          {/* Sección Medicamentos */}
          {renderMedicamentos()}
        </div>
        <div>
          {/* Sección Solicitudes */}
          {renderSolicitudes()}
        </div>
        <div>
          {/* Sección Procedimientos No Quirúrgicos */}
          {renderProcedimientosNoQx()}
        </div>
        <div>
          {/* Sección Incapacidades */}
          {renderIncapacidades()}
        </div>
      </div>

      {details.medicamentos.length === 0 &&
        details.solicitudes.length === 0 &&
        details.incapacidades.length === 0 &&
        (!details.procedimientos_no_qx ||
          details.procedimientos_no_qx.length === 0) && (
          <div className="space-y-4">
            <h3 className="text-xl font-bold text-white flex items-center gap-2">
              <Layers size={24} className="text-blue-400" />
              Órdenes y Solicitudes Médicas
            </h3>
            <div className="text-center py-16 bg-gradient-to-b from-blue-900/20 to-slate-900/40 rounded-2xl border border-blue-500/20 shadow-lg backdrop-blur-sm">
              <div className="bg-blue-500/10 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner ring-1 ring-blue-400/20">
                <FileText className="w-10 h-10 text-blue-400 opacity-80" />
              </div>
              <p className="text-xl md:text-2xl font-bold text-blue-100 max-w-2xl mx-auto leading-relaxed px-4">
                No hay registros de formulaciones u órdenes para esta atención.
              </p>
            </div>
          </div>
        )}
    </div>
  );
}
