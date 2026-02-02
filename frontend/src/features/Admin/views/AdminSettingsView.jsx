import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import adminService from '../services/adminService';
import { 
    Settings, CheckCircle, XCircle, ShieldCheck, Mail, Printer,
    Home, FileText, Calendar, User, LogOut, Menu, X 
} from 'lucide-react';
import Swal from 'sweetalert2';
import { useUser } from "../../../contexts/UserContext/UserContext";
import logo from "../../../assets/images/sandi_virtual.png";

export default function AdminSettingsView() {
    const navigate = useNavigate();
    const { user, logout } = useUser();
    const [permissions, setPermissions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    const [activeTab, setActiveTab] = useState("admin");

    useEffect(() => {
        loadData();
    }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const response = await adminService.getPermissions();
            if (response && response.list) {
                setPermissions(response.list);
            }
        } catch (error) {
            console.error("Error loading admin data", error);
        } finally {
            setLoading(false);
        }
    };

    const handleToggle = async (id, field) => {
        // Encontramos el item actual
        const item = permissions.find(p => p.id === id);
        if (!item) return;

        // Calculamos el nuevo valor
        const currentValue = item[field];
        const newValue = (currentValue === '1' || currentValue === 1 || currentValue === true || currentValue === 'VERDADERO') ? '0' : '1';
        
        // Creamos el objeto actualizado para el API
        const updatedItem = { ...item, [field]: newValue };

        // 1. Feedback visual inmediato
        setPermissions(prev => prev.map(p => p.id === id ? updatedItem : p));

        // 2. Ejecución forzada del API
        try {
            const payload = {
                id: updatedItem.id,
                sw_imprime: updatedItem.sw_imprime === '1' || updatedItem.sw_imprime === 1 || updatedItem.sw_imprime === true || updatedItem.sw_imprime === 'VERDADERO',
                sw_correo: updatedItem.sw_correo === '1' || updatedItem.sw_correo === 1 || updatedItem.sw_correo === true || updatedItem.sw_correo === 'VERDADERO',
                estado: updatedItem.estado
            };

            await adminService.updatePermission(payload);

            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                background: '#1e293b',
                color: '#fff',
            });
            Toast.fire({
                icon: 'success',
                title: 'Cambio guardado'
            });
        } catch (error) {
            console.error("Error al guardar permiso", error);
            loadData(); // Revertimos si falla
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo guardar la configuración.',
                background: '#1e293b',
                color: '#fff'
            });
        }
    };

    const tabs = [
        { id: "admin", label: "ADMIN", icon: ShieldCheck },
        // Aquí se pueden agregar más pestañas de administración en el futuro
    ];

    return (
        <div
            className="min-h-screen font-sans text-white relative flex flex-col"
            style={{
                background: "radial-gradient(circle at center, #0F3460 0%, #0a192f 100%)",
            }}
        >
            {/* Background pattern */}
            <div className="fixed inset-0 opacity-10 pointer-events-none z-0">
                <svg className="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" strokeWidth="0.5" className="text-blue-500" />
                        </pattern>
                    </defs>
                    <rect width="100" height="100" fill="url(#grid)" />
                </svg>
            </div>

            {/* Header (Coherente con DashboardView) */}
            <header className="bg-[#0f172a]/80 backdrop-blur-xl border-b border-blue-900/30 sticky top-0 z-50 relative shadow-lg">
                <div className="max-w-7xl mx-auto px-4 sm:px-6">
                    <div className="flex items-center justify-between h-16">
                        {/* Logo */}
                        <div className="flex items-center gap-2">
                            <img src={logo} alt="Logo" className="w-12 h-12 rounded-lg object-contain bg-blue-500/10 p-1" />
                            <span className="text-2xl font-bold hidden sm:block">SanDi•Med</span>
                        </div>

                        {/* Navigation */}
                        <nav className="hidden md:flex items-center gap-1">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 ${
                                        activeTab === tab.id
                                            ? "bg-blue-600 text-white shadow-lg shadow-blue-500/20"
                                            : "text-gray-400 hover:bg-white/5 hover:text-white"
                                    }`}
                                >
                                    <tab.icon className="w-4 h-4" />
                                    <span className="font-medium">{tab.label}</span>
                                </button>
                            ))}
                        </nav>

                        {/* User Menu & Logout */}
                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-3 pl-4 border-l border-blue-900/30">
                                <div className="hidden sm:flex flex-col items-end text-right">
                                    <p className="text-sm font-medium text-gray-100">{user?.usuario?.nombre || "ADMINISTRADOR"}</p>
                                    <p className="text-xs text-blue-300">MODO ADMIN</p>
                                </div>
                                <div className="w-9 h-9 bg-blue-600 rounded-full flex items-center justify-center text-white font-medium shrink-0 shadow-lg shadow-blue-900/20">
                                    A
                                </div>
                                <button
                                    title="Cerrar Sesión"
                                    onClick={logout}
                                    className="ml-2 text-gray-400 hover:text-red-400 transition-colors"
                                >
                                    <LogOut size={20} />
                                </button>
                            </div>

                            {/* Mobile Menu Button */}
                            <button
                                className="md:hidden p-2 text-gray-400 hover:bg-white/5 rounded-lg"
                                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                            >
                                {isMobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
                            </button>
                        </div>
                    </div>
                </div>

                {/* Mobile menu */}
                {isMobileMenuOpen && (
                    <div className="md:hidden border-t border-blue-900/30 bg-[#0f172a]">
                        <div className="p-4 space-y-2">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.id}
                                    onClick={() => {
                                        setActiveTab(tab.id);
                                        setIsMobileMenuOpen(false);
                                    }}
                                    className={`flex items-center gap-3 w-full px-4 py-3 rounded-xl transition-all ${
                                        activeTab === tab.id ? "bg-blue-600 text-white" : "text-gray-400 hover:bg-white/5"
                                    }`}
                                >
                                    <tab.icon className="w-5 h-5" />
                                    <span className="font-medium">{tab.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </header>

            {/* Main Content */}
            <main className="flex-grow p-4 md:p-8 relative z-10">
                <div className="max-w-7xl mx-auto space-y-8 animate-in fade-in duration-500">
                    <div className="flex items-center gap-3">
                        <div className="p-3 bg-blue-600 rounded-xl shadow-lg shadow-blue-500/20">
                            <ShieldCheck className="text-white w-8 h-8" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-bold text-white">Panel de Administración</h1>
                            <p className="text-gray-400">Parametrización de módulos y reportes</p>
                        </div>
                    </div>

                    {loading ? (
                        <div className="text-center py-12 text-gray-400">Cargando configuración...</div>
                    ) : (
                        <div className="grid grid-cols-1 gap-6">
                            {permissions.map((item) => (
                                <div key={item.id} className="bg-[#1e293b]/50 backdrop-blur-md rounded-2xl border border-white/5 overflow-hidden shadow-xl hover:border-blue-500/30 transition-all duration-300">
                                    <div className="p-6">
                                        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                                            <div className="space-y-1 min-w-[200px]">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs font-bold text-blue-400 bg-blue-400/10 px-2 py-0.5 rounded uppercase tracking-wider">
                                                        ID: {item.id}
                                                    </span>
                                                    <h2 className="text-xl font-bold text-white uppercase">{item.etiqueta}</h2>
                                                </div>
                                                <p className="text-sm text-gray-500 font-mono">{item.modulo}</p>
                                            </div>

                                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 flex-1 max-w-3xl">
                                                {/* Visibility Switch */}
                                                <div className="flex flex-col gap-2">
                                                    <label className="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1">
                                                        <Settings size={12} /> Visibilidad (Pestaña)
                                                    </label>
                                                    <button 
                                                        onClick={() => handleToggle(item.id, 'estado')}
                                                        className={`px-4 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-2 border-2 ${
                                                            (item.estado === '1' || item.estado === 1) 
                                                            ? "bg-emerald-500/10 border-emerald-500/50 text-emerald-500 shadow-lg shadow-emerald-500/5" 
                                                            : "bg-rose-500/10 border-rose-500/50 text-rose-500"
                                                        }`}
                                                    >
                                                        {(item.estado === '1' || item.estado === 1) ? <CheckCircle size={14} /> : <XCircle size={14} />}
                                                        {(item.estado === '1' || item.estado === 1) ? "ACTIVO" : "INACTIVO"}
                                                    </button>
                                                </div>

                                                {/* Email Switch */}
                                                <div className="flex flex-col gap-2">
                                                    <label className="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1">
                                                        <Mail size={12} /> Permitir Correo
                                                    </label>
                                                    <button 
                                                        onClick={() => handleToggle(item.id, 'sw_correo')}
                                                        className={`px-4 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-2 border-2 ${
                                                            (item.sw_correo === '1' || item.sw_correo === 1 || item.sw_correo === true || item.sw_correo === 'VERDADERO') 
                                                            ? "bg-blue-500/10 border-blue-500/50 text-blue-500 shadow-lg shadow-blue-500/5" 
                                                            : "bg-gray-500/10 border-gray-500/50 text-gray-500"
                                                        }`}
                                                    >
                                                        {(item.sw_correo === '1' || item.sw_correo === 1 || item.sw_correo === true || item.sw_correo === 'VERDADERO') ? <CheckCircle size={14} /> : <XCircle size={14} />}
                                                        {(item.sw_correo === '1' || item.sw_correo === 1 || item.sw_correo === true || item.sw_correo === 'VERDADERO') ? "SI" : "NO"}
                                                    </button>
                                                </div>

                                                {/* Print Switch */}
                                                <div className="flex flex-col gap-2">
                                                    <label className="text-[10px] font-bold text-gray-400 uppercase tracking-widest flex items-center gap-1">
                                                        <Printer size={12} /> Permitir Imprimir
                                                    </label>
                                                    <button 
                                                        onClick={() => handleToggle(item.id, 'sw_imprime')}
                                                        className={`px-4 py-2.5 rounded-xl font-bold text-xs transition-all flex items-center justify-center gap-2 border-2 ${
                                                            (item.sw_imprime === '1' || item.sw_imprime === 1 || item.sw_imprime === true || item.sw_imprime === 'VERDADERO') 
                                                            ? "bg-indigo-500/10 border-indigo-500/50 text-indigo-500 shadow-lg shadow-indigo-500/5" 
                                                            : "bg-gray-500/10 border-gray-500/50 text-gray-500"
                                                        }`}
                                                    >
                                                        {(item.sw_imprime === '1' || item.sw_imprime === 1 || item.sw_imprime === true || item.sw_imprime === 'VERDADERO') ? <CheckCircle size={14} /> : <XCircle size={14} />}
                                                        {(item.sw_imprime === '1' || item.sw_imprime === 1 || item.sw_imprime === true || item.sw_imprime === 'VERDADERO') ? "SI" : "NO"}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </main>
        </div>
    );
}