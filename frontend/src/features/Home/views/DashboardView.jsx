import React, { useState } from 'react';
import { Home, FileText, User, Activity, Menu, X, Bell, Calendar, LogOut } from 'lucide-react';
import { useUser } from '../../../contexts/UserContext/UserContext';
import logo from '../../../assets/images/sandi_virtual.png';

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
    <div className="min-h-screen bg-background text-foreground font-sans">
      {/* Header */}
      <header className="bg-card/80 backdrop-blur-xl border-b border-border sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6">
          <div className="flex items-center justify-between h-16">
            {/* Logo */}
            <div className="flex items-center gap-2">
              <img src={logo} alt="Logo" className="w-12 h-12 rounded-lg object-contain bg-primary/10 p-1" />
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
                        ? "bg-primary text-primary-foreground shadow-lg shadow-primary/20"
                        : "text-muted-foreground hover:bg-muted hover:text-foreground"
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
                  <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full border-2 border-card"></span>
                  <Bell className="w-5 h-5 text-muted-foreground cursor-pointer hover:text-foreground transition-colors" />
              </div>
              <div className="flex items-center gap-3 pl-4 border-l border-border">
                <div className="text-right hidden sm:block">
                  <p className="text-sm font-medium leading-none">{user?.paciente?.nombre_completo || 'Usuario'}</p>
                  <p className="text-xs text-muted-foreground">{user?.paciente?.documento || 'ID'}</p>
                </div>
                <div className="w-9 h-9 bg-accent rounded-full flex items-center justify-center text-accent-foreground font-medium">
                  {user?.paciente?.nombre_completo ? user.paciente.nombre_completo.charAt(0) : 'U'}
                </div>
                <button title="Cerrar Sesión" onClick={logout} className="ml-2 text-muted-foreground hover:text-destructive">
                    <LogOut size={20} />
                </button>
              </div>

              {/* Mobile Menu Button */}
              <button
                className="md:hidden p-2 text-muted-foreground hover:bg-muted rounded-lg"
                onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              >
                {isMobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
              </button>
            </div>
          </div>
        </div>

        {/* Mobile menu */}
        {isMobileMenuOpen && (
          <div className="md:hidden border-t border-border bg-card">
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
                        ? "bg-primary text-primary-foreground"
                        : "text-muted-foreground hover:bg-muted"
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
      <main className="max-w-7xl mx-auto px-4 sm:px-6 py-8">
        {activeTab === "inicio" && <InicioTab user={user} />}
        {activeTab === "historial" && <HistorialTab />}
        {activeTab === "datos" && <DatosTab user={user} />}
        {activeTab === "diagnosticos" && <DiagnosticosTab />}
      </main>

      {/* Footer */}
      <footer className="border-t border-border mt-auto">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
          <p className="text-center text-sm text-muted-foreground">
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
