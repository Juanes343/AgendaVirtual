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
} from "lucide-react";
import { useUser } from "../../../contexts/UserContext/UserContext";
import Swal from "sweetalert2";

export default function MedicalHistoryView() {
  const location = useLocation();
  const [history, setHistory] = useState([]);
  const [attachments, setAttachments] = useState([]);
  const [surgeries, setSurgeries] = useState([]); // Nuevo estado para cirugías
  const [loading, setLoading] = useState(true);
  const [selectedIngreso, setSelectedIngreso] = useState(null);
  const [activeTab, setActiveTab] = useState(1); // 1: Consulta Externa, 2: Apoyos Diagnósticos, 3: Cirugía
  const [permissions, setPermissions] = useState([]);
  const [surgeryViewMode, setSurgeryViewMode] = useState('procedures'); // 'procedures' | 'histories'
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
    if (activeTab === 4) loadAttachments();
    if (activeTab === 3) loadSurgeries(); 
  }, [activeTab]);

  const loadPermissions = async () => {
    try {
      const data = await historyService.getPermissions();
      // La API retorna { success: true, list: [...], data: {...} }
      // Usamos 'list' que contiene el array de permisos
      if (data && data.list && Array.isArray(data.list)) {
        setPermissions(data.list);
      } else if (Array.isArray(data)) {
        setPermissions(data);
      }
    } catch (e) {
      console.error("Error loading permissions", e);
    }
  };

  const getModulePermissions = (moduleIdOrTab) => {
    // Mapping user tabs to DB IDs
    let dbId = 1;
    if (moduleIdOrTab === 1) dbId = 1; // CONSULTA_EXTERNA
    if (moduleIdOrTab === 2) dbId = 2; // APOYOS_DIAGNOSTICOS
    if (moduleIdOrTab === 3) dbId = 3; // CIRUGIA
    if (moduleIdOrTab === 4) dbId = 5; // ADJUNTOS_GENERALES (Tab 4)

    // Usar comparación '==' para evitar problemas de tipos (string vs number)
    const p = permissions.find((x) => x.id == dbId);
    
    // Return true by default if not loaded or not found to avoid blocking
    if (!p) return { sw_imprime: true, sw_correo: true };

    // Convert output to boolean. "1", 1, true, "VERDADERO" are considered true.
    const isTrue = (val) => val === 1 || val === "1" || val === true || val === "VERDADERO";
    return {
      sw_imprime: isTrue(p.sw_imprime),
      sw_correo: isTrue(p.sw_correo),
    };
  };

  const loadHistory = async () => {
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

  return (
    <div className="space-y-6">
      <div className="space-y-4">
        <h2 className="text-2xl font-bold flex items-center gap-2 text-white">
          <FileText className="text-blue-400" /> Historial Médico
        </h2>

        {/* Tabs de Tipo de Consulta - Aligned Right */}
        <div className="flex justify-end gap-3 overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-blue-600/20 scrollbar-track-transparent">
          <button
            onClick={() => setActiveTab(1)}
            className={`shrink-0 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border ${
              activeTab === 1
                ? "bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20"
                : "bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white"
            }`}
          >
            Consulta Externa
          </button>
          <button
            onClick={() => setActiveTab(2)}
            className={`shrink-0 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border ${
              activeTab === 2
                ? "bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20"
                : "bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white"
            }`}
          >
            Apoyos Diagnósticos
          </button>
          <button
            onClick={() => setActiveTab(3)}
            className={`shrink-0 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border ${
              activeTab === 3
                ? "bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20"
                : "bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white"
            }`}
          >
            Cirugía
          </button>
          <button
            onClick={() => setActiveTab(4)}
            className={`shrink-0 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border ${
              activeTab === 4
                ? "bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20"
                : "bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white"
            }`}
          >
            Adjuntos Generales
          </button>
        </div>
      </div>

      {/* Lista de Tarjetas (Agrupadas por Ingreso) */}
      <div className="grid grid-cols-1 gap-4">
        {loading ? (
          <div className="text-center py-8 text-gray-400">
            Cargando registros...
          </div>
        ) : activeTab === 4 ? (
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
                    <h3 className="text-lg font-bold text-white group-hover:text-blue-400 transition-colors">
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
        ) : activeTab === 3 ? (
            <div>
              {/* Navegación interna de Cirugía */}
              <div className="flex gap-4 mb-6 border-b border-white/10 pb-2">
                <button
                  onClick={() => setSurgeryViewMode('procedures')}
                  className={`pb-2 text-sm font-semibold transition-colors ${surgeryViewMode === 'procedures' ? 'text-blue-400 border-b-2 border-blue-400' : 'text-gray-400 hover:text-white'}`}
                >
                  Procedimientos / Notas
                </button>
              </div>
              
              {surgeries.length === 0 ? (
                  <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
                    <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
                    <p className="text-gray-400">No hay registros de Cirugías para mostrar.</p>
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
                                <Calendar size={14} /> {item.fecha_hora || item.hora_inicio}
                              </span>
                              <div className="flex items-center gap-2 text-blue-300 text-sm border-l border-white/10 pl-3 ml-1 font-bold">
                                  <User size={14} className="text-blue-500" />
                                  <span>{item.cirujano_nombre}</span>
                              </div>
                            </div>
                            <h3 className="text-lg font-bold text-white group-hover:text-blue-400 transition-colors">
                              {item.procedimiento_principal || item.tipo_cirugia || "PROCEDIMIENTO QUIRÚRGICO"}
                            </h3>
                            <p className="text-sm text-gray-400">
                                Quirófano: {item.nom_quirofano} | Evolución ID: {item.evolucion_id}
                            </p>
                          </div>
        
                          <div className="flex items-center gap-2 w-full md:w-auto">
                            {getModulePermissions(3).sw_imprime && (
                              <button
                                onClick={() =>
                                  historyService.printHistoryComplete(item.ingreso)
                                }
                                className="px-4 py-2 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-600"
                                title="Imprimir Historia Clínica del Ingreso"
                              >
                                <FileText size={16} /> Historia
                              </button>
                            )}
  
                              <button
                                onClick={() =>
                                  historyService.printNotaOperatoria(
                                    item.hc_nota_operatoria_cirugia_id
                                  )
                                }
                                className="px-4 py-2 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white"
                                title="Imprimir Nota Operatoria"
                              >
                                <Printer size={16} /> Imprimir Nota Operatoria
                              </button>
  
                              {getModulePermissions(3).sw_correo && (
                                <button
                                  onClick={async () => {
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
                                          item.hc_nota_operatoria_cirugia_id
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
                      </div>
                  ))
              )}
            </div>
        ) : filteredHistory.length === 0 ? (
          <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
            <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
            <p className="text-gray-400">
              No hay registros de{" "}
              {activeTab === 1 ? "Consulta Externa" : "Apoyos Diagnósticos"}{" "}
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
                    {item.codigo_servicio ? `${item.codigo_servicio} - ` : ''}{item.servicio || "ATENCIÓN MÉDICA GENERAL"}
                  </h3>
                </div>

                {/* Botón Acción */}
                <div className="flex items-center gap-2 w-full md:w-auto">
                  <button
                    onClick={() => setSelectedIngreso(item.ingreso)}
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
          {/* Sección Medicamentos */}
          {renderMedicamentos()}
        </div>
        <div>
          {/* Sección Solicitudes */}
          {renderSolicitudes()}
        </div>
        <div>
          {/* Sección Incapacidades */}
          {renderIncapacidades()}
        </div>
      </div>

      {details.medicamentos.length === 0 &&
        details.solicitudes.length === 0 &&
        details.incapacidades.length === 0 && (
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
