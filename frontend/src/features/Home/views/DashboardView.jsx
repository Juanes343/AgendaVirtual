import React, { useState } from 'react';
import { Home, FileText, User, Activity, Menu, X, Bell, Calendar, LogOut } from 'lucide-react';
import { useUser } from '../../../contexts/UserContext/UserContext';
import logo from '../../../assets/images/sandi_virtual.png';

import MedicalHistoryView from '../../MedicalHistory/views/MedicalHistoryView';

export default function DashboardView() {
  const [activeTab, setActiveTab] = useState("inicio");
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const { user, logout } = useUser();


  const tabs = [
    { id: "inicio", label: "Inicio", icon: Home },
    { id: "historial", label: "Historial Médico", icon: FileText },
    { id: "datos", label: "Datos Básicos", icon: User },
    { id: "diagnosticos", label: "Apoyos Diagnósticos", icon: Activity },
  ];

  return (
    <div className="min-h-screen font-sans text-white relative" style={{ background: 'radial-gradient(circle at center, #0F3460 0%, #0a192f 100%)' }}>
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

      {/* Header */}
      <header className="bg-[#0f172a]/80 backdrop-blur-xl border-b border-blue-900/30 sticky top-0 z-50 relative shadow-lg">
        <div className="max-w-7xl mx-auto px-4 sm:px-6">
          <div className="flex items-center justify-between h-16">
            {/* Logo */}
            <div className="flex items-center gap-2">
              <img src={logo} alt="Logo" className="w-12 h-12 rounded-lg object-contain bg-blue-500/10 p-1" />
              <span className="text-2xl font-bold hidden sm:block">SanDi•Med</span>
            </div>

            {/* Desktop Navigation */}
            <nav className="hidden md:flex items-center gap-1">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id)}
                    className={`flex items-center gap-2 px-4 py-2 rounded-lg transition-all duration-200 ${
                      activeTab === tab.id
                        ? "bg-blue-600 text-white shadow-lg shadow-blue-500/20"
                        : "text-gray-400 hover:bg-white/5 hover:text-white"
                    }`}
                  >
                    <Icon className="w-4 h-4" />
                    <span className="font-medium">{tab.label}</span>
                  </button>
                );
              })}
            </nav>

            {/* User Menu */}
            <div className="flex items-center gap-4">
              <div className="relative hidden sm:block">
                  <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full border-2 border-[#0f172a]"></span>
                  <Bell className="w-5 h-5 text-gray-300 cursor-pointer hover:text-white transition-colors" />
              </div>
              <div className="flex items-center gap-3 pl-4 border-l border-blue-900/30">
                <div className="text-right hidden sm:block">
                  <p className="text-sm font-medium leading-none truncate max-w-[150px] text-gray-100" title={user?.paciente?.nombre_completo}>
                      {user?.paciente?.nombre_completo || 'Usuario'}
                  </p>
                  <p className="text-xs text-blue-300">{user?.paciente?.documento || 'ID'}</p>
                </div>
                <div className="w-9 h-9 bg-blue-600 rounded-full flex items-center justify-center text-white font-medium shrink-0 shadow-lg shadow-blue-900/20">
                  {user?.paciente?.nombre_completo ? user.paciente.nombre_completo.charAt(0) : 'U'}
                </div>
                <button title="Cerrar Sesión" onClick={logout} className="ml-2 text-gray-400 hover:text-red-400 transition-colors">
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
              {tabs.map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => {
                      setActiveTab(tab.id);
                      setIsMobileMenuOpen(false);
                    }}
                    className={`flex items-center gap-3 w-full px-4 py-3 rounded-xl transition-all ${
                      activeTab === tab.id
                        ? "bg-blue-600 text-white"
                        : "text-gray-400 hover:bg-white/5"
                    }`}
                  >
                    <Icon className="w-5 h-5" />
                    <span className="font-medium">{tab.label}</span>
                  </button>
                );
              })}
            </div>
          </div>
        )}
      </header>

      {/* Main content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 py-8 relative z-10">
        {activeTab === "inicio" && <InicioTab user={user} />}
        {activeTab === "historial" && <MedicalHistoryView />}
        {activeTab === "datos" && <DatosTab user={user} />}
        {activeTab === "diagnosticos" && <DiagnosticosTab />}
      </main>

      {/* Footer */}
      <footer className="border-t border-blue-900/30 mt-auto relative z-10 bg-[#0f172a]/50 backdrop-blur-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
          <p className="text-center text-sm text-gray-500">
            © {new Date().getFullYear()} SanDi•Med. Todos los derechos reservados.
          </p>
        </div>
      </footer>
    </div>
  );
}

function InicioTab({ user }) {
  const appointments = [
    {
      id: 1,
      doctor: "Dr. Carlos Méndez",
      specialty: "Medicina General",
      date: "15 Ene 2026",
      time: "10:00 AM",
      status: "confirmada",
    },
     // ... more data
  ];

  return (
    <div className="space-y-8">
      {/* Welcome card */}
      <div className="bg-card/80 backdrop-blur-xl rounded-2xl border border-border p-6 sm:p-8">
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
          <div className="space-y-2">
            <h1 className="text-3xl sm:text-4xl font-bold tracking-tight">
              ¡Bienvenido, {user?.paciente?.primer_nombre || 'Paciente'}!
            </h1>
            <p className="text-lg text-muted-foreground">Tu salud está en buenas manos</p>
          </div>
          <button className="bg-primary text-primary-foreground px-6 py-3 rounded-xl font-medium shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all flex items-center gap-2">
            <Calendar className="w-5 h-5" />
            Agendar cita
          </button>
        </div>
      </div>

      {/* Stats grid */}
      <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {[
          { label: "Próximas citas", value: "2", icon: Calendar, color: "text-primary" },
          { label: "Exámenes pendientes", value: "1", icon: FileText, color: "text-emerald-500" },
          { label: "Medicamentos activos", value: "3", icon: Activity, color: "text-blue-500" },
          { label: "Días hasta próxima cita", value: "2", icon: Calendar, color: "text-orange-500" },
        ].map((stat, i) => {
          const Icon = stat.icon;
          return (
            <div key={i} className="bg-card/50 border border-border p-5 rounded-2xl hover:border-primary/50 transition-colors group">
              <div className="flex justify-between items-start">
                <div>
                  <p className="text-muted-foreground font-medium">{stat.label}</p>
                  <p className="text-3xl font-bold mt-2">{stat.value}</p>
                </div>
                <div className={`p-3 rounded-xl bg-muted group-hover:bg-primary/10 transition-colors ${stat.color}`}>
                  <Icon className="w-6 h-6" />
                </div>
              </div>
            </div>
          );
        })}
      </div>

       {/* Additional sections omitted for brevity but can include more if needed */}
    </div>
  );
}

function HistorialTab() {
  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-bold">Historial Médico</h2>
      <div className="bg-card/80 backdrop-blur-xl rounded-2xl border border-border overflow-hidden p-8 text-center text-muted-foreground">
        <p>No hay registros históricos disponibles en este momento.</p>
      </div>
    </div>
  );
}

function DatosTab({ user }) {
  const p = user?.paciente || {};
  return (
    <div className="space-y-6">
      <h2 className="text-2xl font-bold">Datos Básicos</h2>
      <div className="grid lg:grid-cols-2 gap-6">
         <div className="bg-card rounded-2xl border border-border p-6 space-y-6">
            <h3 className="text-lg font-semibold flex items-center gap-2">
                <User className="w-5 h-5 text-primary" />
                Información Personal
            </h3>
            <div className="grid gap-4">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">Documento</p>
                        <p className="font-medium">{p.numero_documento}</p>
                    </div>
                     <div>
                        <p className="text-sm text-muted-foreground">Nombre</p>
                        <p className="font-medium">{p.nombre_completo}</p>
                    </div>
                     <div>
                        <p className="text-sm text-muted-foreground">Fecha Nacimiento</p>
                        <p className="font-medium">{p.fecha_nacimiento}</p>
                    </div>
                </div>
            </div>
         </div>
      </div>
    </div>
  );
}

function DiagnosticosTab() {
  return (
    <div className="space-y-6">
       <h2 className="text-2xl font-bold">Apoyos Diagnósticos</h2>
       <p className="text-muted-foreground">Sin resultados pendientes.</p>
    </div>
  );
}
