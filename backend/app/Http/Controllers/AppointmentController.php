<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    /**
     * Obtener lista de Planes activos
     * Ajustado: Filtra planes tipo_cliente=25 y el último plan usado por el paciente (si existe).
     */
    public function getPlans(Request $request)
    {
        $documento = $request->query('paciente_id');
        $tipoDoc = $request->query('tipo_doc'); // Front debe enviar esto si es posible, sino intentamos solo con documento
        
        $lastPlanId = null;

        if ($documento) {
            // Buscar último plan usado en os_ordenes_servicios
            $queryLast = DB::table('os_ordenes_servicios')
                ->where('paciente_id', $documento);
                
            if ($tipoDoc) {
                $queryLast->where('tipo_id_paciente', $tipoDoc);
            }
            
            $lastOrder = $queryLast->orderBy('orden_servicio_id', 'desc')
                ->select('plan_id')
                ->first();

            if ($lastOrder) {
                $lastPlanId = $lastOrder->plan_id;
            }
        }

        $query = "
            SELECT 
                plan_id as id,
                plan_descripcion as label
            FROM planes
            WHERE estado = '1'
        ";
        
        // Condición: tipo_cliente = '25' O plan_id = last_plan
        if ($lastPlanId) {
            $query .= " AND (tipo_cliente = '25' OR plan_id = '$lastPlanId')";
        } else {
            $query .= " AND tipo_cliente = '25'";
        }

        $query .= " ORDER BY plan_descripcion";
        
        $plans = DB::select($query);
        
        return response()->json($plans);
    }

    /**
     * Obtener Tipos de Afiliado por Plan (Dropdown)
     * Basado en AgendaSQL::ObtenerTiposAfiliados
     */
    public function getAffiliateTypes(Request $request)
    {
        $planId = $request->query('plan_id');
        
        if (!$planId) return response()->json([]);

        $types = DB::select("
            SELECT DISTINCT 
                TA.tipo_afiliado_nombre as label,
                TA.tipo_afiliado_id as id,
                PR.rango
            FROM tipos_afiliado TA
            JOIN planes_rangos PR ON PR.tipo_afiliado_id = TA.tipo_afiliado_id
            WHERE PR.plan_id = ?
            AND PR.estado = '1'
            ORDER BY TA.tipo_afiliado_nombre ASC
        ", [$planId]);

        return response()->json($types);
    }
    
    /**
     * Obtener Últimos Datos del Paciente (Rango y Tipo Afiliado)
     * Basado en AgendaSQL::UltimoRegisto
     */
    public function getPatientLastData(Request $request)
    {
        $documento = $request->query('paciente_id');
        $tipoDoc = $request->query('tipo_doc');
        
        if (!$documento) return response()->json(null);

        $query = DB::table('os_ordenes_servicios')
            ->where('paciente_id', $documento)
            ->select('rango', 'tipo_afiliado_id');
            
        if ($tipoDoc) {
             $query->where('tipo_id_paciente', $tipoDoc);
        }

        $data = $query->orderBy('orden_servicio_id', 'desc')->first();

        return response()->json($data);
    }

    /**
     * Obtener lista de tipos de cita (Tipos de Consulta)
     * Basado en AgendaSQL::getConsultas / TiposConsulta
     * Ajuste: Muestra Departamento/Sede para evitar duplicados visuales.
     */
    public function getAppointmentTypes()
    {
        // Se intenta unir con 'departamentos' para traer nombre de la sede.
        $types = DB::select("
            SELECT DISTINCT
                a.tipo_consulta_id as id,
                (a.descripcion || ' - ' || COALESCE(d.descripcion, a.departamento)) as label
            FROM tipos_consulta a
            JOIN tipos_consultas_cargos b ON a.tipo_consulta_id = b.tipo_consulta_id
            LEFT JOIN departamentos d ON a.departamento = d.departamento
            --WHERE b.sw_cargo_virtual = '1'
            ORDER BY label ASC
        ");

        return response()->json($types);
    }

    /**
     * Obtener servicios dado un tipo de cita (y opcionalmente un plan)
     * Basado en AgendaSQL::getCargos / getServicios
     * La imagen muestra un selector "SERVICIOS" que depende del Tipo de Cita.
     */
    public function getServices(Request $request)
    {
        $tipoConsultaId = $request->query('appointment_type_id');
        $planId = $request->query('plan_id');

        if (!$planId) {
            return response()->json([]);
        }
        
        // Consulta compleja replicada de AgendaSQL::getConsultas
        // Obtiene los cargos (servicios) cruzando planes, tarifarios y tipos de consulta
        $services = DB::select("
            SELECT DISTINCT
                ca.cargo_cups as id,
                ca.descripcion as label
            FROM (
                SELECT
                    pl.plan_id,
                    td.descripcion,
                    te.cargo_base as cargo_cups
                FROM planes as pl
                INNER JOIN plan_tarifario as pt ON pl.plan_id = pt.plan_id
                INNER JOIN tarifarios_detalle as td ON (
                    pt.tarifario_id = td.tarifario_id
                    AND pt.grupo_tarifario_id = td.grupo_tarifario_id
                    AND pt.subgrupo_tarifario_id = td.subgrupo_tarifario_id
                )
                INNER JOIN tarifarios_equivalencias te ON (
                    te.tarifario_id = td.tarifario_id 
                    AND te.cargo = td.cargo
                )
                WHERE pl.plan_id = ? 
                AND pl.estado = '1'
            ) as ca
            -- LEFT JOIN excepciones as e ... (Omitido por complejidad inicial, agregar si es crítico)
            WHERE ca.cargo_cups IN (
                SELECT b.cargo_cita
                FROM tipos_consultas_cargos A
                JOIN cargos_citas b ON A.cargo_cita = b.cargo_cita
                WHERE A.tipo_consulta_id = ?
            )
            ORDER BY ca.descripcion
        ", [$planId, $tipoConsultaId]);

        return response()->json($services);
    }

    /**
     * Obtener profesionales disponibles para un tipo de cita / servicio
     * Basado en AgendaSQL::getProfesionales / agenda_turnos
     */
    public function getProfessionals(Request $request)
    {
        $tipoConsultaId = $request->query('appointment_type_id');
        $servicioId = $request->query('service_id');
        
        // Lógica clave: Buscar en agenda_turnos qué profesionales tienen programación
        // SQL Adaptado de agendavirtualSQL.php::ObtenerProfesionales
        
        /*
          SELECT DISTINCT(d.nombre_tercero), b.tercero_id
          FROM agenda_turnos a
          JOIN profesionales b ON a.profesional_id = b.tercero_id
          JOIN terceros d ON b.tercero_id = d.tercero_id
          WHERE a.fecha_turno >= NOW()
          AND a.estado = '1'
          AND a.tipo_consulta_id = ?
        */
        
            $query = "
                SELECT DISTINCT
                    t.nombre_tercero as label,
                    p.tercero_id as id,
                    p.tipo_id_tercero
                FROM agenda_turnos a
                JOIN profesionales p ON a.profesional_id = p.tercero_id AND a.tipo_id_profesional = p.tipo_id_tercero
                JOIN terceros t ON p.tercero_id = t.tercero_id AND p.tipo_id_tercero = t.tipo_id_tercero
                WHERE a.fecha_turno >= CURRENT_DATE
                -- AND a.estado = '1' 
            ";
            
            $bindings = [];
            
            if ($tipoConsultaId) {
                $query .= " AND a.tipo_consulta_id = ?";
                $bindings[] = $tipoConsultaId;
            }

        $professionals = DB::select($query, $bindings);

        return response()->json($professionals);
    }

    /**
     * Obtener disponibilidad (turnos)
     * Basado en AgendaSQL::ObtenerAgendaProfesional
     */
    public function getAvailability(Request $request)
    {
        $profesionalId = $request->query('professional_id');
        $tipoConsultaId = $request->query('appointment_type_id');
        $fechaInicio = $request->query('start_date', date('Y-m-d'));
        $fechaFin = $request->query('end_date', date('Y-m-d', strtotime('+30 days')));

        // Buscar turnos y citas disponibles (Slots)
        // Basado en AgendaSQL::ObtenerAgendaProfesional.
        // Se une agenda_turnos con agenda_citas para obtener los slots reales.
        $query = "
            SELECT 
                ac.agenda_cita_id,
                a.agenda_turno_id,
                a.fecha_turno,
                ac.hora,
                a.duracion,
                t.nombre_tercero as doctor_nombre
            FROM agenda_turnos a
            JOIN agenda_citas ac ON a.agenda_turno_id = ac.agenda_turno_id
            JOIN profesionales p ON a.profesional_id = p.tercero_id AND a.tipo_id_profesional = p.tipo_id_tercero
            JOIN terceros t ON p.tercero_id = t.tercero_id AND p.tipo_id_tercero = t.tipo_id_tercero
            WHERE a.fecha_turno BETWEEN ? AND ?
            AND ac.sw_estado = '0' -- 0: Disponible
            -- AND ac.sw_bloqueada != '1'
        ";
        
        $bindings = [$fechaInicio, $fechaFin];
        
        if ($profesionalId) {
             $query .= " AND a.profesional_id = ?";
             $bindings[] = $profesionalId;
        }

        if ($tipoConsultaId) {
            $query .= " AND a.tipo_consulta_id = ?";
            $bindings[] = $tipoConsultaId;
        }
        
        $query .= " ORDER BY a.fecha_turno, ac.hora";
        
        $citas = DB::select($query, $bindings);
        
        $availability = [];
        
        foreach ($citas as $cita) {
            // Calcular Inicio y Fin combinando fecha y hora
            $start = $cita->fecha_turno . ' ' . $cita->hora;
            
            // Calcular Fin sumando duración (minutos)
            $duracion = $cita->duracion ? (int)$cita->duracion : 20; // Default 20 min si null
            $endTime = date('Y-m-d H:i:s', strtotime("+$duracion minutes", strtotime($start)));

            $availability[] = [
                'id' => $cita->agenda_cita_id, // Usamos ID de la cita para agendar
                'agenda_turno_id' => $cita->agenda_turno_id,
                'title' => 'Disponible',
                'start' => $start,
                'end' => $endTime,
                'doctor' => $cita->doctor_nombre,
                'available_slots' => 1
            ];
        }

        return response()->json($availability);
    }

    /**
     * Obtener citas ya asignadas al paciente (Vigentes)
     */
    public function getAssignedAppointments(Request $request)
    {
        $pacienteId = $request->query('paciente_id');
        $tipoDoc = $request->query('tipo_doc');
        
        if (!$pacienteId || !$tipoDoc) {
            return response()->json([]);
        }

        /*
           Adaptación de query CitasAsignadasPaciente:
           Se busca listar citas futuras o del día, activas (sw_estado=1), no canceladas.
           Se valida opcionalmente estado sw_estado de os_maestro si existe cruce.
        */
        $query = "
            SELECT DISTINCT
                C.fecha_turno,
                a.hora,
                e.plan_descripcion,
                G.tipo_consulta_id,
                G.descripcion AS tipos_consulta,
                f.cargo,
                f.descripcion,
                T.nombre_tercero as profesional,
                a.agenda_cita_id,
                b.agenda_cita_asignada_id,
                CASE WHEN B.sw_tipo_atencion = '1' THEN 'PRESENCIAL' ELSE 'TELECONSULTA' END AS atencion,
                om.sw_estado as orden_estado
            FROM agenda_citas A
            JOIN agenda_citas_asignadas B ON A.agenda_cita_id = B.agenda_cita_id
            JOIN agenda_turnos C ON A.agenda_turno_id = C.agenda_turno_id
            
            -- Para verificar cancelaciones
            LEFT JOIN agenda_citas_asignadas_cancelacion AC ON AC.agenda_cita_asignada_id = B.agenda_cita_asignada_id
            
            -- Para verificar estado de orden (si aplica)
            LEFT JOIN os_cruce_citas oc ON b.agenda_cita_asignada_id = oc.agenda_cita_asignada_id
            LEFT JOIN os_maestro om ON om.numero_orden_id = oc.numero_orden_id
            
            LEFT JOIN planes E ON B.plan_id = E.plan_id
            LEFT JOIN tipos_consulta G ON c.tipo_consulta_id = G.tipo_consulta_id
            
            -- Profesionales y Terceros
            LEFT JOIN profesionales P ON C.profesional_id = P.tercero_id AND C.tipo_id_profesional = P.tipo_id_tercero
            LEFT JOIN terceros T ON P.tercero_id = T.tercero_id AND P.tipo_id_tercero = T.tipo_id_tercero
            
            JOIN cups F ON F.cargo = B.cargo_cita
            
            WHERE A.sw_estado = '1' -- Asignada
            AND b.paciente_id = ?
            AND b.tipo_id_paciente = ?
            AND C.fecha_turno >= CURRENT_DATE
            AND AC.agenda_cita_asignada_id IS NULL
            
            ORDER BY C.fecha_turno, a.hora ASC
        ";

        $citas = DB::select($query, [$pacienteId, $tipoDoc]);
        
        // Filtrar por estado de orden si es necesario (Replicando logica legacy: if(sw_estado == '1'))
        // Nota: Si no hay cruce (om es null), en legacy parece que no entraría en el if($value['sw_estado'] == '1')
        // Sin embargo, asumiremos que si sw_estado viene, debe ser 1. Si no viene, es cita directa?
        // Revisando legacy: foreach... if($value['sw_estado'] == '1').
        // Si om.sw_estado es null, la condición falla.
        // Mantenemos el filtro estricto:
        
        $citasFiltradas = [];
        foreach($citas as $cita) {
             // Si sw_estado es 1, o quizas permitir NULL si es cita sin orden (ajustar segun negocio real)
             // El usuario pidió "tal cual aca". Aca dice: if($value['sw_estado'] == '1')
             if ($cita->orden_estado == '1') {
                 $citasFiltradas[] = $cita;
             }
        }

        return response()->json($citasFiltradas);
    } 

    /**
     * Agendar Cita
     */
    public function bookAppointment(Request $request)
    {
        $request->validate([
            'agenda_cita_id' => 'required',
            'agenda_turno_id' => 'required',
            'paciente_id' => 'required',
            'plan_id' => 'required',
            'service_id' => 'required', // Es el cargo_cups
            'appointment_type_id' => 'required'
        ]);
        
        $params = $request->all();

        // 1. Obtener datos detallados del paciente
        $paciente = DB::table('pacientes')
            ->where('paciente_id', $params['paciente_id'])
            ->first();

        if (!$paciente) {
             return response()->json(['message' => 'Paciente no encontrado', 'success' => false], 404);
        }

        // 2. Obtener Info del Tipo de Consulta (Para Departamento)
        $tipoConsulta = DB::table('tipos_consulta')
            ->where('tipo_consulta_id', $params['appointment_type_id'])
            ->select('departamento')
            ->first();
            
        $departamento = $tipoConsulta ? $tipoConsulta->departamento : '0';

        // 3. Obtener Tarifario Info
        $tarifarioInfo = DB::selectOne("
                SELECT
                    pl.plan_id,
                    td.cargo,
                    te.cargo_base as cargo_cups,
                    pt.tarifario_id
                FROM planes as pl
                INNER JOIN plan_tarifario as pt ON pl.plan_id = pt.plan_id
                INNER JOIN tarifarios_detalle as td ON (
                    pt.tarifario_id = td.tarifario_id
                    AND pt.grupo_tarifario_id = td.grupo_tarifario_id
                    AND pt.subgrupo_tarifario_id = td.subgrupo_tarifario_id
                )
                INNER JOIN tarifarios_equivalencias te ON (
                    te.tarifario_id = td.tarifario_id 
                    AND te.cargo = td.cargo
                )
                WHERE pl.plan_id = ? 
                AND te.cargo_base = ?
                LIMIT 1
        ", [$params['plan_id'], $params['service_id']]);

        if (!$tarifarioInfo) {
             return response()->json(['message' => 'No se encontró información tarifaria para el servicio seleccionado', 'success' => false], 400);
        }

        // 4. Obtener Tipo Afiliado y Rango (Prioridad: Front -> Backend Default)
        $tipoAfiliadoId = isset($params['tipo_afiliado']) ? $params['tipo_afiliado'] : null;
        $rangoVal = isset($params['rango']) ? $params['rango'] : null;

        if (!$tipoAfiliadoId) {
            $datosAfiliado = DB::selectOne("
                SELECT DISTINCT TA.tipo_afiliado_id, PR.rango
                FROM tipos_afiliado TA
                JOIN planes_rangos PR ON PR.tipo_afiliado_id = TA.tipo_afiliado_id
                WHERE PR.plan_id = ?
                LIMIT 1
            ", [$params['plan_id']]);

            $tipoAfiliadoId = $datosAfiliado ? $datosAfiliado->tipo_afiliado_id : 'C'; // Default seguro
            if (!$rangoVal) {
                $rangoVal = $datosAfiliado ? $datosAfiliado->rango : 'A';
            }
        }

        // Asegurar un rango por defecto si sigue nulo
        if (!$rangoVal) $rangoVal = 'A';
        
        DB::beginTransaction();

        try {
            // A. Bloquear Cita
            DB::table('agenda_citas')
                ->where('agenda_turno_id', $params['agenda_turno_id'])
                ->where('agenda_cita_id', $params['agenda_cita_id'])
                ->update(['sw_estado' => '1']); 

            // B. Generar Autorización
            $authSeq = DB::selectOne("SELECT nextval('autorizaciones_autorizacion_seq'::regclass) AS val")->val;

            DB::table('autorizaciones')->insert([
                'autorizacion' => $authSeq,
                'fecha_autorizacion' => DB::raw("NOW()"),
                'observaciones' => 'Autorizacion Automatica Realizada para Agenda Virtual',
                'usuario_id' => 0, 
                'fecha_registro' => DB::raw("NOW()"),
                'sw_estado' => 1,
                'clase_autorizacion' => 'OS',
                'tipo_autorizacion' => 'AT',
                'tipo_autorizador' => 'A',
                'codigo_autorizacion' => $authSeq,
                'codigo_autorizacion_generador' => $authSeq,
                'descripcion_autorizacion' => 'Autorizacion Automatica Realizada para Agenda Virtual',
                'tipo_afiliado_id' => $tipoAfiliadoId,
                'semanas_cotizadas' => 0,
                'rango' => $rangoVal,
                'plan_id' => $params['plan_id']
            ]);

            // C. Cita Asignada (agenda_citas_asignadas)
            $citaAsigSeq = DB::selectOne("SELECT nextval('agenda_citas_asignadas_agenda_cita_asignada_id_seq'::regclass) AS val")->val;
            
            $turno = DB::table('agenda_turnos')->where('agenda_turno_id', $params['agenda_turno_id'])->first();
            
            DB::table('agenda_citas_asignadas')->insert([
                'agenda_cita_asignada_id' => $citaAsigSeq,
                'agenda_cita_id' => $params['agenda_cita_id'],
                'paciente_id' => $paciente->paciente_id,
                'tipo_id_paciente' => $paciente->tipo_id_paciente,
                'tipo_cita' => '01',
                'plan_id' => $params['plan_id'],
                'cargo_cita' => $tarifarioInfo->cargo_cups, 
                'usuario_id' => 0,
                'agenda_cita_id_padre' => $params['agenda_cita_id'],
                'fecha_registro' => DB::raw("NOW()"),
                'cod_autorizacion' => $authSeq,
                'fecha_deseada' => $turno->fecha_turno, 
                'observacion' => 'Agendado desde Web',
                'sw_tipo_atencion' => '1',
                'modalidad_atencion_id' => '01'
            ]);

            // D. Orden Servicio (OS)
            $osSeq = DB::selectOne("SELECT nextval('os_ordenes_servicios_orden_servicio_id_seq'::regclass) AS val")->val;

            DB::table('os_ordenes_servicios')->insert([
                'orden_servicio_id' => $osSeq,
                'autorizacion_int' => $authSeq,
                'autorizacion_ext' => $authSeq,
                'plan_id' => $params['plan_id'],
                'tipo_afiliado_id' => $tipoAfiliadoId,
                'servicio' => '3',
                'tipo_id_paciente' => $paciente->tipo_id_paciente,
                'paciente_id' => $paciente->paciente_id,
                'usuario_id' => 0,
                'fecha_registro' => DB::raw("NOW()"),
                'observacion' => 'Orden de servicio Automatica Realizada para Agenda Virtual',
                'rango' => $rangoVal,
                'departamento' => $departamento, 
                'sw_estado' => '1'
            ]);

            // E. HC OS Solicitud y Maestro
            $solicitudSeq = DB::selectOne("SELECT nextval('hc_os_solicitudes_hc_os_solicitud_id_seq'::regclass) AS val")->val;

            DB::table('hc_os_solicitudes')->insert([
                'hc_os_solicitud_id' => $solicitudSeq,
                'cargo' => $tarifarioInfo->cargo_cups,
                'plan_id' => $params['plan_id'],
                'os_tipo_solicitud_id' => 'CIT',
                'sw_estado' => '1',
                'cantidad' => 1,
                'sw_no_autorizado' => '0',
                'sw_ambulatorio' => '1',
                'paciente_id' => $paciente->paciente_id,
                'tipo_id_paciente' => $paciente->tipo_id_paciente,
                'fecha_solicitud' => DB::raw("NOW()")
            ]);

            $numOrdenSeq = DB::selectOne("SELECT nextval('os_maestro_numero_orden_id_seq'::regclass) AS val")->val;

            DB::table('os_maestro')->insert([
                'numero_orden_id' => $numOrdenSeq,
                'sw_estado' => '1',
                'orden_servicio_id' => $osSeq,
                'fecha_vencimiento' => $turno->fecha_turno,
                'cantidad' => 1,
                'hc_os_solicitud_id' => $solicitudSeq,
                'cargo_cups' => $tarifarioInfo->cargo_cups
            ]);

            // F. Tablas Relacionadas (Cruces y Detalles)
            DB::table('os_cruce_citas')->insert([
                'numero_orden_id' => $numOrdenSeq,
                'agenda_cita_asignada_id' => $citaAsigSeq
            ]);

            DB::table('hc_os_solicitudes_citas')->insert([
                'hc_os_solicitud_id' => $solicitudSeq,
                'tipo_consulta_id' => $params['appointment_type_id']
            ]);

             // Insert faltante en código anterior: hc_os_autorizaciones
             DB::table('hc_os_autorizaciones')->insert([
                'hc_os_solicitud_id' => $solicitudSeq,
                'autorizacion_int' => $authSeq,
                'autorizacion_ext' => $authSeq
            ]);

            // Insert faltante en código anterior: os_internas
            DB::table('os_internas')->insert([
                'numero_orden_id' => $numOrdenSeq,
                'cargo' => $tarifarioInfo->cargo_cups,
                'departamento' => $departamento
            ]);

            DB::table('os_maestro_cargos')->insert([
                'numero_orden_id' => $numOrdenSeq,
                'tarifario_id' => $tarifarioInfo->tarifario_id,
                'cargo' => $tarifarioInfo->cargo_cups
            ]);

            DB::commit();
            return response()->json(['message' => 'Cita agendada con éxito', 'success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al agendar cita: ' . $e->getMessage()], 500);
        }
    }
}
