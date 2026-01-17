import React, { useEffect, useState } from 'react';
import historyService from '../services/historyService';
import { Eye, Printer, ArrowLeft, FileText, Activity, Layers, Calendar, User, Search, Stethoscope, Pill } from 'lucide-react';

export default function MedicalHistoryView() {
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedIngreso, setSelectedIngreso] = useState(null);
    const [activeTab, setActiveTab] = useState(1); // 1: Consulta Externa, 2: Apoyos Diagnósticos

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
                            <div>
                                <button 
                                    onClick={() => setSelectedIngreso(item.ingreso)} 
                                    className={`px-5 py-2.5 rounded-lg font-bold text-sm shadow-md transition-all flex items-center gap-2 w-full md:w-auto justify-center ${
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

    useEffect(() => {
        historyService.getDetail(ingresoId).then(data => { if(data.success) setDetails(data.data); setLoading(false); });
    }, [ingresoId]);

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
                <div className="bg-blue-950/30 p-2 text-center border-t border-blue-900/30">
                    <button onClick={() => historyService.printFormula(evolucionId)} className="text-blue-400 hover:text-blue-300 hover:underline flex items-center justify-center gap-2 mx-auto font-bold text-xs uppercase tracking-wide">
                        <Printer size={14} /> Imprimir Fórmula Médica
                    </button>
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

        return Object.entries(grouped).map(([evolucionId, sols]) => (
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
                <div className="bg-purple-950/20 p-2 text-center border-t border-blue-900/30">
                     <button onClick={() => historyService.printOrder(evolucionId)} className="text-purple-400 hover:text-purple-300 hover:underline flex items-center justify-center gap-2 mx-auto font-bold text-xs uppercase tracking-wide">
                        <Printer size={14} /> Imprimir Orden
                    </button>
                </div>
            </div>
        ));
    };

    return (
        <div className="space-y-6 animate-fade-in-up">
            <button onClick={onBack} className="flex items-center gap-2 text-blue-300 hover:text-white transition-colors font-medium">
                <ArrowLeft size={20} /> Regresar al Listado
            </button>
            
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