import React, { useEffect, useState } from 'react';
import historyService from '../services/historyService';
import { Eye, Printer, ArrowLeft, FileText, Activity } from 'lucide-react';

export default function MedicalHistoryView() {
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selectedEvolucion, setSelectedEvolucion] = useState(null);

    useEffect(() => { loadHistory(); }, []);

    const loadHistory = async () => {
        try {
            const data = await historyService.getHistory();
            if (data.success) setHistory(data.data);
        } catch (error) { console.error(error); } finally { setLoading(false); }
    };

    if (selectedEvolucion) {
        return <HistoryDetail evolucionId={selectedEvolucion} onBack={() => setSelectedEvolucion(null)} />;
    }

    return (
        <div className="space-y-6">
            <h2 className="text-2xl font-bold flex items-center gap-2 text-white"><FileText className="text-blue-400" /> Historial Médico</h2>
            <div className="bg-[#1e293b]/50 backdrop-blur-md rounded-xl border border-blue-900/30 overflow-hidden shadow-xl">
                <table className="w-full text-sm text-center">
                    <thead className="bg-blue-900/50 text-blue-100 uppercase text-xs font-bold tracking-wider">
                        <tr>
                            <th className="px-6 py-4 text-left">Fecha</th>
                            <th className="px-6 py-4 text-left w-1/3">Profesional</th> {/* Más ancho para el nombre */}
                            <th className="px-6 py-4">Ingreso</th>
                            <th className="px-6 py-4 text-center">Opción</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-blue-800/20 text-gray-300">
                        {loading ? (
                            <tr><td colSpan="4" className="text-center py-8 text-gray-400">Cargando registros...</td></tr>
                        ) : history.length === 0 ? (
                            <tr><td colSpan="4" className="text-center py-8 text-gray-400">No se encontraron registros históricos.</td></tr>
                        ) : history.map((item, i) => (
                            <tr key={i} className="hover:bg-blue-800/20 transition-colors duration-150">
                                <td className="px-6 py-4 text-left font-medium text-white">{item.fecha}</td>
                                <td className="px-6 py-4 text-left font-medium text-blue-200">{item.profesional_nombre}</td>
                                <td className="px-6 py-4">{item.ingreso}</td>
                                <td className="px-6 py-4 flex justify-center">
                                    <button 
                                        onClick={() => setSelectedEvolucion(item.evolucion_id)} 
                                        className="bg-blue-600/20 hover:bg-blue-600 hover:text-white text-blue-400 px-3 py-1.5 rounded-lg font-bold text-xs uppercase transition-all flex items-center gap-2"
                                    >
                                        <Eye size={16} /> Ver
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function HistoryDetail({ evolucionId, onBack }) {
    const [details, setDetails] = useState({ medicamentos: [], solicitudes: [] });
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        historyService.getDetail(evolucionId).then(data => { if(data.success) setDetails(data.data); setLoading(false); });
    }, [evolucionId]);

    if(loading) return <div>Cargando...</div>;

    return (
        <div className="space-y-6">
            <button onClick={onBack} className="flex items-center gap-2 text-muted-foreground"><ArrowLeft size={20} /> Regresar</button>
            {details.medicamentos.length > 0 && (
                <div className="space-y-2">
                    <div className="bg-primary/10 text-primary px-4 py-2 font-bold uppercase text-sm rounded-t-lg border-b border-primary/20">Medicamentos Pos Formulados</div>
                    <div className="bg-card border border-border rounded-b-lg overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-muted text-xs uppercase font-semibold"><tr><th className="px-4 py-2">Producto</th><th className="px-4 py-2">Indicaciones</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {details.medicamentos.map((med, i) => (
                                    <tr key={i}>
                                        <td className="px-4 py-3 align-top font-bold text-primary">{med.producto}<div className="text-xs text-muted-foreground">{med.principio_activo}</div></td>
                                        <td className="px-4 py-3">Dosis: {med.dosis} {med.unidad_dosificacion} | Frecuencia: {med.frecuencia} | Cantidad: {med.cantidad}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <div className="bg-muted/30 p-2 text-center border-t border-border">
                            <button onClick={() => historyService.printFormula(evolucionId)} className="text-primary hover:underline flex items-center justify-center gap-2 mx-auto font-medium text-sm"><Printer size={16} /> IMPRIMIR FÓRMULA MÉDICA</button>
                        </div>
                    </div>
                </div>
            )}
            
            {details.solicitudes.length > 0 && (
                <div className="space-y-2">
                    <div className="bg-blue-600/10 text-blue-700 px-4 py-2 font-bold uppercase text-sm rounded-t-lg">Solicitudes</div>
                    <div className="bg-card border border-border rounded-b-lg overflow-hidden">
                         <table className="w-full text-sm">
                            <thead className="bg-muted text-xs uppercase font-semibold"><tr><th className="px-4 py-2">Fecha</th><th className="px-4 py-2">Cargo</th><th className="px-4 py-2">Descripción</th></tr></thead>
                            <tbody className="divide-y divide-border">
                                {details.solicitudes.map((sol, i) => (
                                    <tr key={i}><td className="px-4 py-3">{sol.fecha_solicitud}</td><td className="px-4 py-3">{sol.cargo}</td><td className="px-4 py-3">{sol.descripcion}</td></tr>
                                ))}
                            </tbody>
                        </table>
                         <div className="bg-muted/30 p-2 text-center border-t border-border">
                            <button onClick={() => historyService.printOrder(evolucionId)} className="text-blue-600 hover:underline flex items-center justify-center gap-2 mx-auto font-medium text-sm"><Printer size={16} /> IMPRIMIR ORDEN</button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}