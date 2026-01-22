import React, { useState } from 'react';
import { Home, FileText, User, Activity, Menu, X, Bell, Calendar, LogOut } from 'lucide-react';
import { useUser } from '../../../contexts/UserContext/UserContext';
import logo from '../../../assets/images/sandi_virtual.png';
import simdeLogo from '../../../assets/images/simde_logo.png';

import MedicalHistoryView from '../../MedicalHistory/views/MedicalHistoryView';
import ScheduleAppointmentView from '../../Appointments/views/ScheduleAppointmentView';
import ProfileView from '../../Profile/views/ProfileView';

export default function DashboardView() {
  const [activeTab, setActiveTab] = useState("inicio");
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
  const { user, logout } = useUser();


  const tabs = [
    { id: "inicio", label: "Inicio", icon: Home },
    { id: "historial", label: "Historial Médico", icon: FileText },
    { id: "datos", label: "Datos Básicos", icon: User },
    // { id: "diagnosticos", label: "Apoyos Diagnósticos", icon: Activity },
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
              <div className="flex items-center gap-3 pl-4 border-l border-blue-900/30">
                <div className="hidden sm:flex flex-col items-end max-w-[350px] text-right">
                  <p className="text-sm font-medium leading-tight text-gray-100 truncate w-full" title={user?.paciente?.nombre_completo}>
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
        {activeTab === "inicio" && <InicioTab user={user} setActiveTab={setActiveTab} />}
        {activeTab === "historial" && <MedicalHistoryView />}
        {activeTab === "datos" && <ProfileView />}
        {activeTab === "diagnosticos" && <DiagnosticosTab />}
        {/* Nueva vista de agendamiento */}
        {activeTab === "agendar" && <ScheduleAppointmentView />}
      </main>

      {/* Footer */}
      <footer className="border-t border-blue-900/30 mt-auto relative z-10 bg-[#0f172a]/50 backdrop-blur-sm">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6 flex items-center gap-3">
          <img src={simdeLogo} alt="SIMDE SAS" className="h-5 w-auto opacity-70 grayscale hover:grayscale-0 transition-all duration-300" />
          <p className="text-sm text-gray-500 font-medium">
             © {new Date().getFullYear()} SIMDE SAS. Todos los derechos reservados.
          </p>
        </div>
      </footer>
    </div>
  );
}

function InicioTab({ user, setActiveTab }) {
  const navCards = [
    { 
      id: "historial", 
      label: "Historial Médico", 
      desc: "Consulta tus diagnósticos, recetas y evolución clínica detallada.", 
      icon: FileText, 
      color: "text-emerald-500", 
      bg: "bg-emerald-500/10",
      border: "hover:border-emerald-500/50"
    },
    { 
      id: "datos", 
      label: "Datos Básicos", 
      desc: "Gestiona tu información personal y contactos de emergencia actualizados.", 
      icon: User, 
      color: "text-blue-500", 
      bg: "bg-blue-500/10",
      border: "hover:border-blue-500/50" 
    },
    // { 
    //   id: "diagnosticos", 
    //   label: "Resultados", 
    //   desc: "Visualiza tus apoyos diagnósticos y reportes de laboratorio recientes.", 
    //   icon: Activity, 
    //   color: "text-purple-500", 
    //   bg: "bg-purple-500/10",
    //   border: "hover:border-purple-500/50" 
    // },
  ];

  return (
    <div className="space-y-10 animate-fade-in">
      {/* Welcome card */}
      <div className="bg-slate-900/60 backdrop-blur-2xl rounded-3xl border border-white/10 p-8 sm:p-10 shadow-2xl">
        <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
          <div className="space-y-2">
            <h1 className="text-4xl sm:text-6xl font-black tracking-tight text-white italic">
              ¡Hola, {user?.paciente?.primer_nombre || 'Paciente'}!
            </h1>
            <p className="text-xl text-blue-200/60 font-semibold uppercase tracking-wide">Portal del Paciente SanDi•Med</p>
          </div>
          <button 
            onClick={() => setActiveTab('agendar')}
            className="bg-blue-600 hover:bg-blue-500 text-white px-8 py-4 rounded-2xl font-black shadow-xl shadow-blue-600/30 transition-all hover:scale-105 active:scale-95 flex items-center gap-2 text-lg"
          >
            <Calendar className="w-6 h-6" />
            Agendar Cita
          </button>
        </div>
      </div>

      {/* Navigation Grid */}
      <div className="grid sm:grid-cols-2 lg:grid-cols-2 gap-8">
        {navCards.map((card) => {
          const Icon = card.icon;
          return (
            <button
               key={card.id}
               onClick={() => setActiveTab(card.id)}
               style={{ borderRadius: '2.5rem' }}
               className={`group relative overflow-hidden bg-slate-900/30 border border-white/5 p-10 ${card.border} transition-all hover:bg-slate-900/60 text-left flex flex-col justify-between h-80 shadow-lg`}
            >
              <div className={`p-6 rounded-2xl ${card.bg} ${card.color} w-fit transition-transform group-hover:scale-125 ${card.id === 'datos' ? 'group-hover:-rotate-3' : 'group-hover:rotate-3'}`}>
                <Icon className="w-11 h-11" />
              </div>
              <div>
                <h3 className={`text-2xl font-black text-white ${card.id === 'historial' ? 'group-hover:text-emerald-400' : 'group-hover:text-blue-400'} transition-colors tracking-tight uppercase`}>{card.label}</h3>
                <p className="text-gray-400 mt-3 text-base leading-relaxed font-medium">{card.desc}</p>
              </div>
            </button>
          );
        })}
      </div>
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
       {/* <h2 className="text-2xl font-bold">Apoyos Diagnósticos</h2> */}
       <p className="text-muted-foreground">Sin resultados pendientes.</p>
    </div>
  );
}
