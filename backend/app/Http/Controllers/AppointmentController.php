<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    /**
     * Obtener lista de Planes activos
     * Ajustado: Filtra planes tipo_cliente=25 y el último plan usado por el paciente (si existe).
     */
    public function getPlans(Request $request)
    {
        $documento = $request->query('paciente_id');
        $tipoDoc = $request->query('tipo_doc'); 
        
        $usedPlanIds = [];

        if ($documento) {
            // Buscar historial de planes usados en agenda_citas_asignadas
            $queryHistory = DB::table('agenda_citas_asignadas')
                ->where('paciente_id', $documento);
                
            if ($tipoDoc) {
                $queryHistory->where('tipo_id_paciente', $tipoDoc);
            }
            
            // Obtener todos los IDs de planes distintos que ha usado el paciente
            $usedPlanIds = $queryHistory
                ->select('plan_id')
                ->distinct()
                ->pluck('plan_id')
                ->toArray();
        }

        $query = "
            SELECT 
                plan_id as id,
                plan_descripcion as label
            FROM planes
            WHERE estado = '1'
        ";
        
        // Condición: tipo_cliente = '25' O plan_id IN (usedPlanIds)
        if (!empty($usedPlanIds)) {
            // Formatear IDs para cláusula IN ('1','2')
            $idsList = implode("','", $usedPlanIds);
            $query .= " AND (tipo_cliente = '25' OR plan_id IN ('$idsList'))";
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
            AND (ac.sw_separada != '1' OR ac.sw_separada IS NULL)
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
            -- LEFT JOIN os_maestro om ON om.numero_orden_id = oc.numero_orden_id
            LEFT JOIN os_maestro om ON om.numero_orden_id = oc.numero_orden_id AND om.sw_estado != '1'
            
            LEFT JOIN planes E ON B.plan_id = E.plan_id
            LEFT JOIN tipos_consulta G ON c.tipo_consulta_id = G.tipo_consulta_id
            
            -- Profesionales y Terceros
            LEFT JOIN profesionales P ON C.profesional_id = P.tercero_id AND C.tipo_id_profesional = P.tipo_id_tercero
            LEFT JOIN terceros T ON P.tercero_id = T.tercero_id AND P.tipo_id_tercero = T.tipo_id_tercero
            
            LEFT JOIN cups F ON F.cargo = B.cargo_cita
            
            WHERE A.sw_estado = '1' -- Asignada
            AND b.paciente_id = ?
            AND b.tipo_id_paciente = ?
            AND C.fecha_turno >= CURRENT_DATE
            AND AC.agenda_cita_asignada_id IS NULL
            
            -- FIX: No filtrar por sw_estado de orden aquí, o usar LEFT JOIN permisivo
            -- El filtro de ordenes anuladas ya se haría en el LEFT JOIN de os_maestro si fuera necesario
            -- pero si la orden no existe, igual debe salir la cita (ej particular)
            
            ORDER BY C.fecha_turno, a.hora ASC
        ";

        $citas = DB::select($query, [$pacienteId, $tipoDoc]);
        
        // Filtrar por estado de orden si es necesario (Replicando logica legacy: if(sw_estado == '1'))
        // Nota: Si no hay cruce (om es null), en legacy parece que no entraría en el if($value['sw_estado'] == '1')
        // Sin embargo, asumiremos que si sw_estado viene, debe ser 1. Si no viene, es cita directa?
        // Revisando legacy: foreach... if($value['sw_estado'] == '1').
        // ERROR: Esto descarta citas particulares o sin orden asociada (orden_estado es null).
        // Ajuste: Permitir si es '1' O si es NULL/vacio.
        
        $citasFiltradas = [];
        foreach($citas as $cita) {
             if ($cita->orden_estado == '1' || empty($cita->orden_estado)) {
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

        // Validar si ya tiene cita activa (Mismo paciente y mismo tipo documento)
        $bindings = [$params['paciente_id']];
        $sqlCheck = "
            SELECT count(*) as total
            FROM agenda_citas A
            JOIN agenda_citas_asignadas B ON A.agenda_cita_id = B.agenda_cita_id
            JOIN agenda_turnos C ON A.agenda_turno_id = C.agenda_turno_id
            LEFT JOIN agenda_citas_asignadas_cancelacion AC ON AC.agenda_cita_asignada_id = B.agenda_cita_asignada_id
            
            LEFT JOIN os_cruce_citas OC ON B.agenda_cita_asignada_id = OC.agenda_cita_asignada_id
            LEFT JOIN os_maestro OM ON OC.numero_orden_id = OM.numero_orden_id

            WHERE A.sw_estado = '1'
            AND b.paciente_id = ?
            AND (OM.sw_estado = '1' OR OM.sw_estado IS NULL) -- Validar solo si está activa o sin orden
        ";
        
        // Si viene el tipo_doc, filtramos también por él para evitar bloqueos por homónimos/cambios doc
        if (!empty($params['tipo_doc'])) {
            $sqlCheck .= " AND b.tipo_id_paciente = ? ";
            $bindings[] = $params['tipo_doc'];
        }

        $sqlCheck .= " AND C.fecha_turno >= CURRENT_DATE AND AC.agenda_cita_asignada_id IS NULL ";

        $citasActivas = DB::select($sqlCheck, $bindings);

        /* COMENTADO POR SOLICITUD - PERMITIR MAS DE UNA CITA ACTIVA
        if ($citasActivas[0]->total > 0) {
            return response()->json(['message' => 'Ya cuenta con una cita activa vigente. Solo puede tener una cita asignada.'], 400);
        }
        */

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
                ->update([
                    'sw_estado' => '1',
                    'sw_separada' => '1',
                    'usuario_separa' => 0
                ]); 

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
                'modalidad_atencion_id' => '01',
                'sw_estado' => '1'
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
                'cargo' => $tarifarioInfo->cargo,
                'cargo_cups' => $tarifarioInfo->cargo_cups
            ]);

            DB::commit();

            // --- ENVIO CORREO CONFIRMACION ---
            $msg = 'Cita agendada con éxito.';
            
            if (!empty($paciente->email)) {
                try {
                    // Datos Agregados para el Mail
                    $horaCita = DB::table('agenda_citas')->where('agenda_cita_id', $params['agenda_cita_id'])->value('hora');
                    
                    $profesionalNombre = DB::table('agenda_turnos as a')
                        ->join('profesionales as p', function($join){
                             $join->on('a.profesional_id','=','p.tercero_id')
                                  ->on('a.tipo_id_profesional','=','p.tipo_id_tercero');
                        })
                        ->join('terceros as t', function($join){
                             $join->on('p.tercero_id','=','t.tercero_id')
                                  ->on('p.tipo_id_tercero','=','t.tipo_id_tercero');
                        })
                        ->where('a.agenda_turno_id', $params['agenda_turno_id'])
                        ->value('t.nombre_tercero');
                    
                    $servicioNombre = DB::table('cups')->where('cargo', $tarifarioInfo->cargo_cups)->value('descripcion');

                    // Obtener la SEDE (Ubicación)
                    $sedeInfo = DB::table('departamentos as d')
                        ->leftJoin('centros_utilidad as c', function($join) {
                             $join->on('d.centro_utilidad','=','c.centro_utilidad')
                                  ->on('d.empresa_id','=','c.empresa_id');
                        })
                        ->where('d.departamento', $departamento)
                        ->where('d.empresa_id', $turno->empresa_id)
                        ->select('d.ubicacion as ubic_dep', 'c.ubicacion as ubic_cu', 'c.descripcion as nombre_cu')
                        ->first();

                    $sedeFinal = 'Sede Principal';
                    if ($sedeInfo) {
                        $nombreSede = $sedeInfo->nombre_cu ?: 'Sede Principal';
                        $ubicacion = $sedeInfo->ubic_dep ?: ($sedeInfo->ubic_cu ?: '');
                        $sedeFinal = trim($nombreSede . ' ' . $ubicacion);
                    }

                    $dataMail = [
                        'nombre' => trim(($paciente->primer_nombre ?? '') . ' ' . ($paciente->primer_apellido ?? '')),
                        'fecha' => $turno->fecha_turno,
                        'hora' => $horaCita,
                        'profesional' => $profesionalNombre,
                        'servicio' => $servicioNombre,
                        'sede' => $sedeFinal,
                        'identificacion' => $paciente->paciente_id
                    ];

                    Mail::send('emails.appointment_confirmation', $dataMail, function ($message) use ($paciente) {
                        $message->to($paciente->email)
                                ->subject('Confirmación de Cita Médica - SanDi•Med');
                    });

                    $msg = 'Cita agendada con éxito. La información fue enviada al correo registrado: ' . $paciente->email;

                } catch (\Exception $exMail) {
                    // No fallamos la transacción si falla el mail, solo advertimos en log
                    \Log::error("Error enviando correo cita: " . $exMail->getMessage());
                    // Opcional: Avisar al usuario que el correo falló, o dejarlo transparente
                }
            }

            return response()->json(['message' => $msg, 'success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            $message = $e->getMessage();
            
            // Log for debugging (storage/logs/laravel.log)
            \Log::error("Agendamiento fallido: " . $message);

            // Manejar error específico de la base de datos (Trigger/Procedure)
            // Se usa búsqueda flexible por si el mensaje tiene ligeras variaciones
            if (str_contains($message, 'ASIGNADA') || str_contains($message, 'P0001')) {
                $message = 'El turno ya fue ocupado por otro paciente. Por favor, intente con otro horario.';
            } else {
                $message = 'No se pudo completar el agendamiento: ' . $message;
            }
            
            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * Obtener Tipos de Cancelación
     */
    public function getCancellationTypes()
    {
        $types = DB::select("SELECT tipo_cancelacion_id as id, descripcion as label FROM tipos_cancelacion ORDER BY descripcion");
        return response()->json($types);
    }

    /**
     * Cancelar Cita
     */
    public function cancelAppointment(Request $request)
    {
        $request->validate([
            'agenda_cita_asignada_id' => 'required',
            'paciente_id' => 'required',
            'justificacion' => 'required',
            'observacion' => 'required'
        ]);

        $params = $request->all();

        // Obtener datos para el correo ANTES de cancelar/modificar
        $citaCanceladaInfo = null;
        try {
            $citaCanceladaInfo = DB::table('agenda_citas_asignadas as aca')
            ->join('agenda_citas as ac', 'aca.agenda_cita_id', '=', 'ac.agenda_cita_id')
            ->join('agenda_turnos as at', 'ac.agenda_turno_id', '=', 'at.agenda_turno_id')
            ->join('pacientes as pa', 'aca.paciente_id', '=', 'pa.paciente_id')
            ->join('cups as c', 'aca.cargo_cita', '=', 'c.cargo')
            ->leftJoin('profesionales as pr', function($join){
                 $join->on('at.profesional_id','=','pr.tercero_id')
                      ->on('at.tipo_id_profesional','=','pr.tipo_id_tercero');
            })
            ->leftJoin('terceros as t', function($join){
                 $join->on('pr.tercero_id','=','t.tercero_id')
                      ->on('pr.tipo_id_tercero','=','t.tipo_id_tercero');
            })
            ->where('aca.agenda_cita_asignada_id', $params['agenda_cita_asignada_id'])
            ->select(
                'pa.email',
                'pa.primer_nombre', 'pa.primer_apellido',
                'at.fecha_turno',
                'ac.hora',
                't.nombre_tercero as profesional',
                'c.descripcion as servicio'
            )
            ->first();
        } catch (\Exception $e) { \Log::error("Error data mail cancel: ".$e->getMessage()); }

        DB::beginTransaction();
        try {
            // 1. Obtener Padre
            $datosCita = DB::table('agenda_citas_asignadas')
                ->where('agenda_cita_asignada_id', $params['agenda_cita_asignada_id'])
                ->where('paciente_id', $params['paciente_id'])
                ->select('agenda_cita_id_padre')
                ->first();

            if (!$datosCita) {
                return response()->json(['message' => 'Cita asignada no encontrada o no pertenece al paciente'], 404);
            }

            // 2. Insertar en Cancelacion
            $exists = DB::table('agenda_citas_asignadas_cancelacion')
                ->where('agenda_cita_asignada_id', $params['agenda_cita_asignada_id'])
                ->exists();

            if (!$exists) {
                DB::table('agenda_citas_asignadas_cancelacion')->insert([
                    'agenda_cita_asignada_id' => $params['agenda_cita_asignada_id'],
                    'tipo_cancelacion_id' => $params['justificacion'], // ID del motivo
                    'observacion' => $params['observacion'],
                    'fecha_registro' => DB::raw("NOW()"),
                    'usuario_id' => 0 // Ajustar según auth
                ]);
            }

            // 3. Update agenda_citas_asignadas (Set sw_atencion = '1')
            DB::table('agenda_citas_asignadas')
                 ->where('agenda_cita_id_padre', $datosCita->agenda_cita_id_padre)
                 ->where('paciente_id', $params['paciente_id'])
                 ->where('sw_atencion', '!=', '1')
                 ->update(['sw_atencion' => '1']);

             // 4. Update Orders
             $cruce = DB::table('os_cruce_citas')
                 ->where('agenda_cita_asignada_id', $params['agenda_cita_asignada_id'])
                 ->select('numero_orden_id')
                 ->first();
            
             if ($cruce) {
                 DB::table('os_maestro')
                     ->where('numero_orden_id', $cruce->numero_orden_id)
                     ->update(['sw_estado' => '9']); 

                 $ordenesIds = DB::table('os_maestro')
                     ->where('numero_orden_id', $cruce->numero_orden_id)
                     ->pluck('orden_servicio_id');
                     
                 if ($ordenesIds->isNotEmpty()) {
                     DB::table('os_ordenes_servicios')
                        ->whereIn('orden_servicio_id', $ordenesIds)
                        ->update(['sw_estado' => '4']);
                 }
             }

             DB::commit();

             // --- ENVIO CORREO CANCELACION ---
             $msg = 'Cita cancelada correctamente.';
             if ($citaCanceladaInfo && !empty($citaCanceladaInfo->email)) {
                 try {
                     $dataMail = [
                        'nombre' => trim(($citaCanceladaInfo->primer_nombre ?? '') . ' ' . ($citaCanceladaInfo->primer_apellido ?? '')),
                        'fecha' => $citaCanceladaInfo->fecha_turno,
                        'hora' => $citaCanceladaInfo->hora,
                        'profesional' => $citaCanceladaInfo->profesional,
                        'servicio' => $citaCanceladaInfo->servicio
                     ];
 
                     Mail::send('emails.appointment_cancellation', $dataMail, function ($message) use ($citaCanceladaInfo) {
                         $message->to($citaCanceladaInfo->email)
                                 ->subject('Cancelación de Cita - SanDi•Med');
                     });
 
                     $msg = 'Cita cancelada correctamente. La información fue enviada al correo registrado: ' . $citaCanceladaInfo->email;
                 } catch (\Exception $ex) {
                    \Log::error('Error mail cancelacion: ' . $ex->getMessage());
                 }
             }

             return response()->json(['message' => $msg, 'success' => true]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al cancelar cita: ' . $e->getMessage()], 500);
        }
    }
}
