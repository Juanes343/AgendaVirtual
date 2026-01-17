import React, { useState, useEffect } from 'react';
import { Calendar, User, Users, Activity, Clock, ChevronLeft, ChevronRight, CheckCircle, AlertCircle, FileText } from 'lucide-react';
import appointmentService from '../services/appointmentService';
import { useUser } from '../../../contexts/UserContext/UserContext';
import Swal from 'sweetalert2';

export default function ScheduleAppointmentView() {
  const { user } = useUser();
  
  // Selectors State
  const [plans, setPlans] = useState([]);
  const [affiliateTypes, setAffiliateTypes] = useState([]); // NUEVO: Tipos de Afiliado
  const [types, setTypes] = useState([]);
  const [services, setServices] = useState([]);
  const [professionals, setProfessionals] = useState([]);
  
  // Selection State
  const [selectedPlan, setSelectedPlan] = useState('');
  const [selectedAffiliateType, setSelectedAffiliateType] = useState(''); // NUEVO
  const [selectedType, setSelectedType] = useState('');
  const [selectedService, setSelectedService] = useState('');
  const [selectedProfessional, setSelectedProfessional] = useState('');
  
  // Calendar State
  const [currentDate, setCurrentDate] = useState(new Date());
  const [availability, setAvailability] = useState([]);
  const [loading, setLoading] = useState(false);

  // 1. Cargar Planes y Tipos al iniciar (Con paciente_id)
  useEffect(() => {
    async function loadInitialData() {
      if (!user?.paciente) return;
      
      try {
        const [plansData, typesData] = await Promise.all([
          appointmentService.getPlans(user.paciente.paciente_id, user.paciente.tipo_id_paciente),
          appointmentService.getAppointmentTypes()
        ]);
        setPlans(plansData);
        setTypes(typesData);
        
        // Cargar últimos datos usados (Autoselección)
        const lastData = await appointmentService.getPatientLastData(user.paciente.paciente_id, user.paciente.tipo_id_paciente);
        if (lastData?.plan_id) {
             // Podríamos autoseleccionar plan aquí si deseamos
             // setSelectedPlan(lastData.plan_id);
        }
      } catch (error) {
        console.error("Error loading initial data", error);
      }
    }
    loadInitialData();
  }, [user]);

  // 1.1 Cargar Tipos de Afiliado al cambiar Plan
  useEffect(() => {
      setAffiliateTypes([]);
      setSelectedAffiliateType('');
      
      if(!selectedPlan) return;
      
      async function loadAffiliateTypes() {
          try {
              const data = await appointmentService.getAffiliateTypes(selectedPlan);
              setAffiliateTypes(data);
              // Preseleccionar si solo hay uno
              if(data.length === 1) setSelectedAffiliateType(data[0].id);
              
              // Intentar recuperar el último usado si coincide
              const lastData = await appointmentService.getPatientLastData(user.paciente.paciente_id, user.paciente.tipo_id_paciente);
              if (lastData?.tipo_afiliado_id) {
                   const exists = data.find(d => d.id === lastData.tipo_afiliado_id);
                   if(exists) setSelectedAffiliateType(lastData.tipo_afiliado_id);
              }

          } catch (e) { console.error(e); }
      }
      loadAffiliateTypes();
  }, [selectedPlan, user]);

  // 2. Cargar Servicios cuando Cambia Plan o Tipo
  useEffect(() => {
    // Reiniciar selectores dependientes
    setServices([]);
    setSelectedService('');
    setProfessionals([]);
    setSelectedProfessional('');
    
    if (!selectedPlan || !selectedType) {
      return;
    }

    async function loadServices() {
      try {
        // AHORA enviamos ambos IDs
        const data = await appointmentService.getServices(selectedPlan, selectedType);
        setServices(data);
      } catch (error) {
        console.error("Error loading services", error);
      }
    }
    loadServices();
  }, [selectedPlan, selectedType]);

  // 3. Cargar Profesionales cuando cambia el Servicio
  useEffect(() => {
    if (!selectedType && !selectedService) { 
       setProfessionals([]);
       return;
    }
    async function loadProfessionals() {
      try {
        const data = await appointmentService.getProfessionals(selectedType, selectedService);
        setProfessionals(data);
      } catch (error) {
        console.error("Error loading professionals", error);
      }
    }
    loadProfessionals();
  }, [selectedService, selectedType]);

  // 4. Buscar Disponibilidad
  const searchAvailability = async () => {
    if (!selectedType) return;
    setLoading(true);
    try {
      // Filtrar POR DÍA específico
      // const start = getStartOfWeek(currentDate); (LEGACY SEMANAL)
      // Ajustamos para que busque solo el día seleccionado
      
      const dayStr = formatDateForAPI(currentDate);

      const data = await appointmentService.getAvailability({
        appointment_type_id: selectedType,
        service_id: selectedService,
        professional_id: selectedProfessional,
        start_date: dayStr,
        end_date: dayStr // Busqueda cerrada en el mismo día
      });
      setAvailability(data);
    } catch (error) {
      console.error("Error availability", error);
      Swal.fire('Error', 'No se pudo cargar la disponibilidad', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
      if(selectedType) {
          searchAvailability();
      }
  }, [currentDate, selectedProfessional, selectedService]); 

  // --- Helpers ---
  // FIX: Usar fecha local para evitar problemas de UTC -1 día
  const formatDateForAPI = (date) => {
    const d = new Date(date);
    // Ajustar a zona horaria local manualmente para evitar el salto de día UTC
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const isToday = (date) => {
      const today = new Date();
      return date.getDate() === today.getDate() &&
             date.getMonth() === today.getMonth() &&
             date.getFullYear() === today.getFullYear();
  };
  
  const getStartOfWeek = (date) => {
    const d = new Date(date);
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1); 
    return new Date(d.setDate(diff));
  };

  const weekDays = [];
  const startOfWeek = getStartOfWeek(currentDate);
  for (let i = 0; i < 7; i++) {
    const d = new Date(startOfWeek);
    d.setDate(d.getDate() + i);
    weekDays.push(d);
  }

  const hours = [];
  for(let i=7; i<18; i++) {
      hours.push(`${i < 10 ? '0'+i : i}:00`);
      hours.push(`${i < 10 ? '0'+i : i}:20`);
      hours.push(`${i < 10 ? '0'+i : i}:40`);
  }

  const handleBook = async (turno) => {
      // Validar Tipo de Afiliado si hay opciones disponibles y no se ha seleccionado
      if (affiliateTypes.length > 0 && !selectedAffiliateType) {
         Swal.fire('Atención', 'Por favor selecciona un Tipo de Afiliado / Rango', 'warning');
         return;
      }

      const confirm = await Swal.fire({
          title: '¿Confirmar Cita?',
          text: `Doctor: ${turno.doctor || 'Asignado'} - Fecha: ${turno.start}`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#3085d6',
          cancelButtonColor: '#d33',
          confirmButtonText: 'Sí, agendar!'
      });

      if (confirm.isConfirmed) {
          try {
              // Obtener objeto del tipo de afiliado para sacar el rango
              const affType = affiliateTypes.find(a => a.id === selectedAffiliateType);
              
              await appointmentService.bookAppointment({
                  agenda_cita_id: turno.id,
                  agenda_turno_id: turno.agenda_turno_id,
                  paciente_id: user?.paciente?.paciente_id,
                  plan_id: selectedPlan,
                  service_id: selectedService,
                  appointment_type_id: selectedType,
                  tipo_afiliado: selectedAffiliateType,
                  rango: affType ? affType.rango : null
              });
              Swal.fire('¡Agendado!', 'Tu cita ha sido reservada.', 'success');
              searchAvailability(); 
          } catch (e) {
              console.error(e);
              Swal.fire('Error', 'No se pudo agendar la cita.', 'error');
          }
      }
  };

  const changeDay = (direction) => {
    const newDate = new Date(currentDate);
    newDate.setDate(newDate.getDate() + direction);
    
    // Validar no retroceder antes de hoy
    const today = new Date();
    today.setHours(0,0,0,0);
    if(newDate < today) {
        return; 
    }
    
    setCurrentDate(newDate);
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
         <h2 className="text-2xl font-bold text-blue-900">Agenda Médica</h2>
      </div>

      {/* Filters Card */}
      <div className="bg-white rounded-xl shadow-sm border border-blue-100 p-6">
        <div className="grid md:grid-cols-3 gap-6"> 
          
          {/* 1. Plan */}
          <div>
            <label className="block text-sm font-medium text-blue-900 mb-2">
              <FileText className="w-4 h-4 inline mr-2 text-orange-500" />
              Plan
            </label>
            <select 
                className="w-full rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500 bg-slate-50 p-2.5 text-slate-700"
                value={selectedPlan}
                onChange={(e) => setSelectedPlan(e.target.value)}
            >
              <option value="">-- SELECCIONAR --</option>
              {plans.map(p => <option key={p.id} value={p.id}>{p.label}</option>)}
            </select>
          </div>

          {/* 1.5 Tipo de Afiliado (Si aplica) */}
          {affiliateTypes.length > 0 && (
             <div>
                <label className="block text-sm font-medium text-blue-900 mb-2">
                  <Users className="w-4 h-4 inline mr-2 text-purple-500" />
                  Tipo Afiliado / Rango
                </label>
                <select 
                    className="w-full rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500 bg-slate-50 p-2.5 text-slate-700"
                    value={selectedAffiliateType}
                    onChange={(e) => setSelectedAffiliateType(e.target.value)}
                >
                  <option value="">-- SELECCIONAR --</option>
                  {affiliateTypes.map(t => <option key={t.id} value={t.id}>{t.label} {t.rango ? `(${t.rango})` : ''}</option>)}
                </select>
             </div>
          )}

          {/* 2. Tipo de Cita */}
          <div>
            <label className="block text-sm font-medium text-blue-900 mb-2">
              <Activity className="w-4 h-4 inline mr-2 text-blue-500" />
              Tipo de Cita
            </label>
            <select 
                className="w-full rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500 bg-slate-50 p-2.5 text-slate-700"
                value={selectedType}
                onChange={(e) => setSelectedType(e.target.value)}
            >
              <option value="">-- SELECCIONAR --</option>
              {types.map(t => <option key={t.id} value={t.id}>{t.label}</option>)}
            </select>
          </div>

          {/* 3. Servicios (Depende de Plan y Tipo) */}
          <div>
             <label className="block text-sm font-medium text-blue-900 mb-2">
              <CheckCircle className="w-4 h-4 inline mr-2 text-emerald-500" />
              Servicios
            </label>
            <select 
                className="w-full rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500 bg-slate-50 p-2.5 text-slate-700"
                value={selectedService}
                onChange={(e) => setSelectedService(e.target.value)}
                disabled={!selectedType || !selectedPlan}
            >
              <option value="">-- SELECCIONAR --</option>
              {services.map(s => <option key={s.id} value={s.id}>{s.label}</option>)}
            </select>
          </div>

          {/* 4. Profesionales */}
          <div>
            <label className="block text-sm font-medium text-blue-900 mb-2">
              <User className="w-4 h-4 inline mr-2 text-purple-500" />
              Profesionales
            </label>
             <select 
                className="w-full rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500 bg-slate-50 p-2.5 text-slate-700"
                value={selectedProfessional}
                onChange={(e) => setSelectedProfessional(e.target.value)}
                disabled={!selectedType} 
            >
              <option value="">-- TODOS --</option>
              {professionals.map(p => <option key={p.id} value={p.id}>{p.label}</option>)}
            </select>
          </div>
        </div>
      </div>

      {/* Agenda Grid - Estilo Legacy / Lista de Horarios */}
      {selectedType && availability.length > 0 && (
      <div className="bg-white rounded-xl shadow-lg border border-blue-200 overflow-hidden">
        {/* Header con Fecha Actual seleccionada */}
        <div className="bg-blue-900 text-white p-4 flex items-center justify-between">
            <h3 className="font-bold text-lg uppercase tracking-wider">
               Día Agenda: {currentDate.toLocaleDateString()}
            </h3>
             <div className="flex items-center gap-2">
                <button 
                    onClick={() => changeDay(-1)} 
                    disabled={isToday(currentDate)}
                    className={`p-2 rounded-lg transition-colors ${isToday(currentDate) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-800'}`}
                >
                    <ChevronLeft className="w-5 h-5 text-white" />
                </button>
                <button onClick={() => setCurrentDate(new Date())} className="text-xs bg-blue-700 px-3 py-1 rounded hover:bg-blue-600">HOY</button>
                <button onClick={() => changeDay(1)} className="p-2 hover:bg-blue-800 rounded-lg transition-colors">
                    <ChevronRight className="w-5 h-5 text-white" />
                </button>
            </div>
        </div>

        {/* Legacy Table Header */}
        <div className="grid grid-cols-12 bg-blue-700 text-white font-bold text-sm uppercase text-center border-t border-blue-800">
             <div className="col-span-2 p-3 border-r border-blue-600">Hora</div>
             <div className="col-span-4 p-3 border-r border-blue-600">Profesional</div>
             <div className="col-span-4 p-3 border-r border-blue-600">Consultorio / Sede</div>
             <div className="col-span-2 p-3">Selección</div>
        </div>

        {/* Body Slots - Listado Real */}
        <div className="max-h-[600px] overflow-y-auto">
             {availability.length === 0 ? (
                 <div className="p-8 text-center text-gray-500">No hay turnos disponibles para esta fecha.</div>
             ) : (
                 availability.map((turno, idx) => (
                    <div key={turno.id} className={`grid grid-cols-12 border-b border-gray-200 hover:bg-blue-50 transition-colors ${idx % 2 === 0 ? 'bg-gray-50' : 'bg-white'}`}>
                        {/* HORA */}
                        <div className="col-span-2 p-3 text-center font-bold text-blue-900 border-r border-gray-200 flex items-center justify-center">
                            {turno.start.split(' ')[1].slice(0, 5)}
                        </div>
                        
                        {/* MÉDICO */}
                        <div className="col-span-4 p-3 border-r border-gray-200 flex flex-col justify-center">
                            <span className="font-semibold text-gray-700 text-sm">{turno.doctor || 'PROFESIONAL DE TURNO'}</span>
                            <span className="text-xs text-green-600 font-medium">Disponible</span>
                        </div>

                         {/* SEDE (Hardcode o traer del backend si existe) */}
                        <div className="col-span-4 p-3 border-r border-gray-200 flex items-center justify-center text-xs text-gray-500">
                            SEDE PRINCIPAL
                        </div>

                        {/* CHECKBOX / BUTTON */}
                        <div className="col-span-2 p-3 flex items-center justify-center">
                            <button 
                                onClick={() => handleBook(turno)}
                                className="w-6 h-6 rounded border-2 border-blue-500 flex items-center justify-center hover:bg-blue-500 group transition-all"
                            >
                                <div className="w-3 h-3 bg-white rounded-sm opacity-0 group-hover:opacity-100 transition-opacity" />
                            </button>
                        </div>
                    </div>
                 ))
             )}
        </div>
      </div>
      )}

      {selectedType && availability.length === 0 && !loading && (
           <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center">
            <p className="text-yellow-700 font-medium">No se encontraron citas disponibles para {currentDate.toLocaleDateString()}</p>
            <div className="mt-4 flex justify-center gap-4">
                 {!isToday(currentDate) && (
                    <button onClick={() => changeDay(-1)} className="text-sm text-blue-600 underline">Ver día anterior</button>
                 )}
                 <button onClick={() => changeDay(1)} className="text-sm text-blue-600 underline">Ver día siguiente</button>
            </div>
           </div>
      )}
      
      {!selectedType && (
          <div className="text-center py-12 bg-white rounded-xl border border-dashed border-slate-300">
              <div className="text-slate-400 mb-2">
                  <Activity className="w-12 h-12 mx-auto opacity-50" />
              </div>
              <p className="text-slate-500 font-medium">Seleccione Plan y Tipo de Cita para ver la disponibilidad</p>
          </div>
      )}
    </div>
  );
}