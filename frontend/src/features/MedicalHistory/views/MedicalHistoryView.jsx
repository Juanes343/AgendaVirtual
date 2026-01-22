import React, { useEffect, useState } from 'react';
import { useLocation } from 'react-router-dom';
import historyService from '../services/historyService';
import { Eye, Printer, ArrowLeft, FileText, Activity, Layers, Calendar, User, Search, Stethoscope, Pill, Mail, CheckCircle, Send } from 'lucide-react';
import { useUser } from '../../../contexts/UserContext/UserContext';
import Swal from 'sweetalert2';

export default function MedicalHistoryView() {
    const location = useLocation();
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedIngreso, setSelectedIngreso] = useState(null);
    const [activeTab, setActiveTab] = useState(1); // 1: Consulta Externa, 2: Apoyos Diagnósticos
    const { user } = useUser();

    // Effect para resetear la vista si la ubicación cambia (ej. clic en menú Historial Médico)
    // Se usa location.key (que cambia en cada push) o location.pathname si se navega a la misma ruta
    useEffect(() => {
        setSelectedIngreso(null);
    }, [location.pathname, location.key]);

    useEffect(() => { loadHistory(); }, []);

    const loadHistory = async () => {
        try {
            const data = await historyService.getHistory();
            if (data.success) setHistory(data.data);
        } catch (error) { console.error(error); } finally { setLoading(false); }
    };



    if (selectedIngreso) {
        return <HistoryDetail ingresoId={selectedIngreso} onBack={() => setSelectedIngreso(null)} />;
    }

    // Filtrar historial según tab activa
    // Nota: tipo_consulta_id puede venir como número o string "1"/"2"
    const filteredHistory = history.filter(item => {
        return parseInt(item.tipo_consulta_id) === activeTab;
    });

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
                                ? 'bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20' 
                                : 'bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white'
                        }`}
                    >
                        Consulta Externa
                    </button>
                    <button
                        onClick={() => setActiveTab(2)}
                        className={`shrink-0 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border ${
                            activeTab === 2 
                                ? 'bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20' 
                                : 'bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white'
                        }`}
                    >
                        Apoyos Diagnósticos
                    </button>
                    <button
                        onClick={() => setActiveTab(3)}
                        className={`shrink-0 px-5 py-2.5 rounded-xl text-sm font-semibold transition-all border ${
                            activeTab === 3
                                ? 'bg-blue-600 border-blue-500 text-white shadow-lg shadow-blue-500/20' 
                                : 'bg-[#1e293b] border-white/5 text-gray-400 hover:border-blue-500/50 hover:text-white'
                        }`}
                    >
                        Cirugía
                    </button>
                </div>
            </div>

            {/* Lista de Tarjetas (Agrupadas por Ingreso) */}
             <div className="grid grid-cols-1 gap-4">
                {loading ? (
                    <div className="text-center py-8 text-gray-400">Cargando registros...</div>
                ) : filteredHistory.length === 0 ? (
                    <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
                        <Activity className="w-12 h-12 text-gray-600 mx-auto mb-3" />
                        <p className="text-gray-400">No hay registros de {activeTab === 1 ? 'Consulta Externa' : 'Apoyos Diagnósticos'} para mostrar.</p>
                    </div>
                ) : filteredHistory.map((item, i) => (
                    <div key={i} className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 group">
                        <div className="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                            
                            {/* Info Principal */}
                            <div className="flex-1 space-y-2">
                                <div className="flex flex-wrap items-center gap-3 text-sm text-blue-300 font-semibold uppercase tracking-wider">
                                    <div className={`px-2 py-1 rounded text-sm ${item.estado === '1' ? 'bg-green-500/20 text-green-400' : 'bg-gray-500/20 text-gray-400'}`}>
                                        Ingreso #{item.ingreso}
                                    </div>
                                    <span className="flex items-center gap-1"><Calendar size={14} /> {item.fecha}</span>
                                    
                                    {/* Professional Name moved here */}
                                    <div className="flex items-center gap-2 text-blue-300 text-sm border-l border-white/10 pl-3 ml-1 font-bold">
                                        <User size={14} className="text-blue-500" />
                                        <span>{'PROFESIONAL: ' + item.profesional_nombre}</span>
                                    </div>  
                                </div>
                                <h3 className="text-lg font-bold text-white group-hover:text-blue-400 transition-colors">
                                    {item.servicio || 'ATENCIÓN MÉDICA GENERAL'}
                                </h3>
                            </div>

                            {/* Botón Acción */}
                            <div className="flex items-center gap-2 w-full md:w-auto">
                                <button 
                                    onClick={() => setSelectedIngreso(item.ingreso)} 
                                    className={`px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 flex-1 justify-center ${
                                        activeTab === 1 
                                            ? 'bg-blue-600 hover:bg-blue-500 text-white' 
                                            : 'bg-purple-600 hover:bg-purple-500 text-white'
                                    }`}
                                >
                                    <Eye size={18} /> Ver Detalles
                                </button>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function HistoryDetail({ ingresoId, onBack }) {
    const [details, setDetails] = useState({ medicamentos: [], solicitudes: [], incapacidades: [] }); // Ahora incluye incapacidades
    const [loading, setLoading] = useState(true);
    const [sendingEmail, setSendingEmail] = useState(false);
    const { user } = useUser();

    useEffect(() => {
        historyService.getDetail(ingresoId).then(data => { if(data.success) setDetails(data.data); setLoading(false); });
    }, [ingresoId]);

    const handleSendEmail = async (type = 'all', evolucionId = null) => {
        let msg = `Se enviará el reporte completo`;
        if (type === 'formula') msg = `Se enviará la fórmula médica`;
        if (type === 'ordenes') msg = `Se enviarán las órdenes médicas`;

        const result = await Swal.fire({
            title: '¿Enviar reporte por correo?',
            text: `${msg} al correo registrado: ${user?.paciente?.email || 'N/A'}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar',
             background: '#1e293b',
             color: '#fff'
        });

        if (!result.isConfirmed) return;

        setSendingEmail(true);
        try {
            await historyService.sendReportEmail(ingresoId, type, evolucionId); 
            Swal.fire({
                title: '¡Enviado!',
                text: 'El reporte ha sido enviado exitosamente a tu correo.',
                icon: 'success',
                confirmButtonColor: '#10b981',
                background: '#1e293b',
                color: '#fff'
            });
        } catch (error) {
            Swal.fire({ title: 'Error', text: 'Error enviando el correo.', icon: 'error', background: '#1e293b', color: '#fff' });
            console.error(error);
        } finally {
            setSendingEmail(false);
        }
    };

    if(loading) return <div className="text-white text-center py-10">Cargando detalles...</div>;

    // Helper para agrupar medicamentos por 'evolucion_id' (aunque suelen venir juntos, por si acaso hay multiples evoluciones en un ingreso)
    const renderMedicamentos = () => {
        if (details.medicamentos.length === 0) return null;
        
        // Agrupar por ID de evolución para botón de imprimir único por bloque
        const grouped = details.medicamentos.reduce((acc, curr) => {
            (acc[curr.evolucion_id] = acc[curr.evolucion_id] || []).push(curr);
            return acc;
        }, {});

        return Object.entries(grouped).map(([evolucionId, meds]) => (
            <div key={`med-${evolucionId}`} className="bg-[#1e293b] rounded-xl border border-blue-900/30 overflow-hidden shadow-md mb-6">
                <div className="bg-blue-900/20 px-4 py-3 border-b border-blue-900/30 flex justify-between items-center">
                    <h4 className="font-bold text-blue-300 flex items-center gap-2 uppercase text-sm"><Pill size={16} /> Medicamentos Formulados</h4>
                    <span className="text-xs text-blue-400/50">Ref: {evolucionId}</span>
                </div>
                <div className="divide-y divide-blue-900/30">
                    {meds.map((med, i) => (
                        <div key={i} className="p-4 hover:bg-white/5 transition-colors">
                            <div className="flex justify-between items-start gap-4">
                                <div>
                                    <p className="font-bold text-blue-100 text-sm">{med.producto}</p>
                                    <p className="text-xs text-gray-400 mt-0.5 mb-2 italic">{med.principio_activo}</p>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs text-gray-300">
                                        <span className="bg-blue-500/10 px-2 py-1 rounded">Dosis: <span className="font-bold text-white">{med.dosis} {med.unidad_dosificacion}</span></span>
                                        <span className="bg-blue-500/10 px-2 py-1 rounded">Frec: <span className="font-bold text-white">{med.frecuencia}</span></span>
                                        <span className="bg-blue-500/10 px-2 py-1 rounded">Cant: <span className="font-bold text-white">{med.cantidad}</span></span>
                                    </div>
                                    {med.observacion && <p className="text-xs text-yellow-500/80 mt-2">Nota: {med.observacion}</p>}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="bg-blue-950/30 p-2 text-center border-t border-blue-900/30 flex flex-col gap-2">
                    <div className="flex flex-wrap justify-center gap-x-6 gap-y-2">
                        <button onClick={() => historyService.printFormula(evolucionId)} className="text-blue-400 hover:text-blue-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide">
                            <Printer size={14} /> Imprimir Fórmula
                        </button>
                        <button onClick={() => handleSendEmail('formula', evolucionId)} disabled={sendingEmail} className="text-blue-400 hover:text-blue-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide">
                            <Mail size={14} /> Enviar al Correo
                        </button>
                    </div>
                </div>
            </div>
        ));
    };

    const renderSolicitudes = () => {
        if (details.solicitudes.length === 0) return null;
        const grouped = details.solicitudes.reduce((acc, curr) => {
            (acc[curr.evolucion_id] = acc[curr.evolucion_id] || []).push(curr);
            return acc;
        }, {});

        return Object.entries(grouped).map(([evolucionId, sols]) => {
            // Tomar datos del primer elemento del grupo para los parámetros
            const sol = sols[0];
            // Parámetros legacy
            const tipoIdPaciente = user?.paciente?.tipo_id_paciente || 'CC';
            const pacienteId = user?.paciente?.paciente_id || '';
            const nombres = user?.paciente?.nombre_completo || '';
            // Buscar ingreso asociado
            let ingreso = '';
            if (sol && sol.ingreso) {
                ingreso = sol.ingreso;
            } else if (details.solicitudes && details.solicitudes.length > 0) {
                const found = details.solicitudes.find(s => s.evolucion_id === evolucionId);
                if (found && found.ingreso) ingreso = found.ingreso;
            }
            // nroImpresionTabla: usar 0 por defecto (ajustar si tienes agrupación real)
            const nroImpresionTabla = 0;
            
            return (
                <div key={`sol-${evolucionId}`} className="bg-[#1e293b] rounded-xl border border-blue-900/30 overflow-hidden shadow-md mb-6">
                    <div className="bg-purple-900/20 px-4 py-3 border-b border-blue-900/30 flex justify-between items-center">
                        <h4 className="font-bold text-purple-300 flex items-center gap-2 uppercase text-sm"><Stethoscope size={16} /> Órdenes y Solicitudes</h4>
                        <span className="text-xs text-purple-400/50">Ref: {evolucionId}</span>
                    </div>
                    <div className="divide-y divide-blue-900/30">
                        {sols.map((sol, i) => (
                            <div key={i} className="p-4 hover:bg-white/5 transition-colors">
                                <div className="flex flex-col gap-1">
                                    <div className="flex justify-between">
                                        <span className="text-xs font-mono text-gray-500">{sol.cargo}</span>
                                        <span className="text-xs text-gray-400">{sol.fecha_solicitud}</span>
                                    </div>
                                    <p className="font-medium text-purple-100 text-sm">{sol.descripcion}</p>
                                    {sol.observacion && <p className="text-xs text-gray-400 mt-1">Obs: {sol.observacion}</p>}
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="bg-purple-950/20 p-2 text-center border-t border-blue-900/30 flex flex-col gap-2">
                        <div className="flex flex-wrap justify-center gap-x-6 gap-y-2">
                            <button onClick={() => historyService.printOrder(evolucionId)} className="text-purple-400 hover:text-purple-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide">
                                <Printer size={14} /> Imprimir Orden
                            </button>
                            <button onClick={() => handleSendEmail('ordenes', evolucionId)} disabled={sendingEmail} className="text-purple-400 hover:text-purple-300 hover:underline flex items-center justify-center gap-2 font-bold text-xs uppercase tracking-wide">
                                <Mail size={14} /> Enviar al Correo
                            </button>
                        </div>
                    </div>
                </div>
            );
        });
    };

    // Obtener el primer evolucion_id disponible para el botón de impresión
    let primerEvolucionId = null;
    if (details.medicamentos.length > 0) {
        primerEvolucionId = details.medicamentos[0].evolucion_id;
    } else if (details.solicitudes.length > 0) {
        primerEvolucionId = details.solicitudes[0].evolucion_id;
    }

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
                {primerEvolucionId && (
                    <div className="flex flex-wrap items-center gap-4">
                        <button
                            onClick={handlePrintEvolucion}
                            className="flex items-center gap-2 px-4 py-2 bg-[#1e293b] hover:bg-blue-600 border border-blue-500/30 rounded-lg text-blue-400 hover:text-white font-semibold text-xs uppercase tracking-wide transition-all shadow-sm hover:shadow-blue-500/20"
                        >
                            <Printer size={16} /> <span className="hidden sm:inline">Imprimir Historia</span>
                        </button>
                        <button
                            onClick={() => handleSendEmail('all')}
                            disabled={sendingEmail}
                            className={`flex items-center gap-2 px-4 py-2 rounded-lg border font-semibold text-xs uppercase tracking-wide transition-all shadow-sm ${
                                sendingEmail 
                                  ? 'bg-gray-800 border-gray-700 text-gray-500 cursor-not-allowed' 
                                  : 'bg-green-600/10 hover:bg-green-600 border-green-500/30 hover:border-green-500 text-green-400 hover:text-white shadow-green-500/10 hover:shadow-green-500/30'
                            }`}
                        >
                            {sendingEmail ? <Activity className="animate-spin" size={16} /> : <Send size={16} />} 
                            <span className="hidden sm:inline">Enviar Todo</span>
                        </button>
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                     {/* Sección Medicamentos */}
                     {renderMedicamentos()}
                </div>
                <div>
                     {/* Sección Solicitudes */}
                     {renderSolicitudes()}
                </div>
            </div>

            {details.medicamentos.length === 0 && details.solicitudes.length === 0 && (
                 <div className="text-center py-12 bg-white/5 rounded-xl border border-dashed border-white/10">
                    <p className="text-gray-400">No hay registros de formulaciones u órdenes para este ingreso.</p>
                 </div>
            )}
        </div>
    );
}