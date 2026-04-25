<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Paciente;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
// use Barryvdh\DomPDF\Facade\Pdf; 

class ReportController extends Controller
{
    public function __construct()
    {
        // Parche manual: Registramos el proveedor de PDF si Laravel no lo detectó automáticamente
        if (class_exists(\Barryvdh\DomPDF\ServiceProvider::class)) {
            app()->register(\Barryvdh\DomPDF\ServiceProvider::class);
        }
    }

    /**
     * Obtiene los archivos adjuntos generales del paciente
     */
    public function getAttachments(Request $request)
    {
        $user = $request->user();

        // Usamos los campos del usuario virtual autenticado
        $tipo_id = $user->tipo_documento;
        $paciente_id = $user->paciente_id;

        $attachments = DB::select("
            SELECT 
                a.*, 
                TO_CHAR(a.fecha_registro,'DD/MM/YYYY') AS fecha_registro, 
                SU.nombre as usuario 
            FROM 
                hc_archivos_adjuntos a, 
                system_usuarios SU 
            WHERE 
                a.tipo_id_paciente = ? 
                AND a.paciente_id = ? 
                AND a.usuario_id = SU.usuario_id
            ORDER BY a.fecha_registro DESC
        ", [$tipo_id, $paciente_id]);

        return response()->json([
            'success' => true,
            'data' => $attachments
        ]);
    }

    /**
     * Obtiene el historial de atenciones del paciente (Agrupado por Ingreso)
     */
    public function getMedicalHistory(Request $request)
    {
        $user = $request->user();
        $pacienteId = $user->paciente_id;
        $tipoDoc = $user->tipo_documento;

        // Agrupar por Ingreso para evitar duplicados de evoluciones
        // Se toma la fecha máxima y el profesional asociado a esa fecha (aproximación)
        $historial = DB::table('hc_evoluciones as a')
            ->join('ingresos as b', 'a.ingreso', '=', 'b.ingreso')
            ->join('profesionales_usuarios as c', 'a.usuario_id', '=', 'c.usuario_id')
            ->join('profesionales as d', function ($join) {
                $join->on('c.tercero_id', '=', 'd.tercero_id')
                    ->on('c.tipo_tercero_id', '=', 'd.tipo_id_tercero');
            })
            ->leftJoin('cups', 'a.cargo_cita', '=', 'cups.cargo')
            ->leftJoin('tipos_consulta as tc', 'a.tipo_consulta_id', '=', 'tc.tipo_consulta_id')
            ->leftJoin('hc_encuesta_satisfaccion as enc', 'a.ingreso', '=', 'enc.ingreso')
            ->select(
                'a.ingreso',
                DB::raw("MAX(DATE(a.fecha)) as fecha"), // Solo fecha
                DB::raw("MAX(d.nombre) as profesional_nombre"),
                DB::raw("MAX(cups.descripcion) as servicio"),
                DB::raw("MAX(cups.cargo) as codigo_servicio"),
                'b.estado',
                DB::raw("MAX(tc.tipo) as tipo_consulta_id"), // Se asume nombre de columna 'tipo'
                DB::raw("CASE WHEN enc.ingreso IS NOT NULL THEN 1 ELSE 0 END as encuesta_completada")
            )
            ->where('b.paciente_id', $pacienteId)
            ->where('b.tipo_id_paciente', $tipoDoc)
            ->groupBy('a.ingreso', 'b.estado', 'enc.ingreso')
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $historial
        ]);
    }

    /**
     * Obtiene el detalle (medicamentos, ordenes, incapacidades) de un Ingreso
     * @param int $ingreso
     */
    public function getHistoryDetail($ingreso)
    {
        // 1. Medicamentos (Agrupados por evolución para saber cuándo se recetaron)
        // Corrección: Usar hc_medicamentos_recetados_amb en lugar de hc_formulacion_antecedentes
        $medicamentos = DB::table('hc_medicamentos_recetados_amb as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('inventarios_productos as i', 'a.codigo_producto', '=', 'i.codigo_producto')
            ->leftJoin('medicamentos as b', 'a.codigo_producto', '=', 'b.codigo_medicamento') // Opcional para principio activo
            ->leftJoin('inv_med_cod_principios_activos as pa', 'b.cod_principio_activo', '=', 'pa.cod_principio_activo') // NUEVO

            // NUEVO: frecuencia real desde hc_posologia_horario_op1
            ->leftJoin('hc_posologia_horario_op1 as ph', function ($join) {
                $join->on('ph.evolucion_id', '=', 'a.evolucion_id')
                    ->on('ph.codigo_producto', '=', 'a.codigo_producto');
            })

            ->where('e.ingreso', $ingreso)
            ->select(
                'e.evolucion_id',
                'e.fecha',
                'a.codigo_producto as codigo_medicamento',
                'a.codigo_producto as codigo', // Alias adicional para compatibilidad vistas
                'i.descripcion as producto',
                'i.descripcion as nombre_medicamento', // Alias adicional para compatibilidad vistas
                'b.cod_principio_activo as principio_activo',
                'pa.descripcion as principio_activo', // NUEVO
                'a.dosis',
                'a.unidad_dosificacion',
                'a.cantidadperiocidad as frecuencia',
                'a.dias_tratamiento',
                'a.dias_tratamiento as tiempo_tratamiento',
                'a.cantidad',
                'a.observacion',
                //  FRECUENCIA
                DB::raw("MIN(ph.periocidad_id) as periocidad_id"),
                DB::raw("MIN(ph.tiempo) as tiempo_frecuencia"),
                DB::raw("
                        CASE
                        WHEN MIN(ph.periocidad_id) IS NOT NULL AND MIN(ph.tiempo) IS NOT NULL
                        THEN CONCAT('cada ', MIN(ph.periocidad_id), ' ', MIN(ph.tiempo))
                        ELSE CAST(a.cantidadperiocidad AS TEXT)
                        END as frecuencia
                    ")
            )->groupBy(
                'e.evolucion_id',
                'e.fecha',
                'a.codigo_producto',
                'i.descripcion',
                'b.cod_principio_activo',
                'pa.descripcion',
                'a.dosis',
                'a.unidad_dosificacion',
                'a.dias_tratamiento',
                'a.cantidad',
                'a.observacion',
                'a.cantidadperiocidad'
            )
            ->get();

        
            // 2. Solicitudes / Ordenes (Agrupadas por servicio para la vista de Hospitalización)
        $solicitudes = DB::table('hc_os_solicitudes as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->leftJoin('planes as pl', 'a.plan_id', '=', 'pl.plan_id')
            ->leftJoin('os_tipos_solicitudes as ts', 'a.os_tipo_solicitud_id', '=', 'ts.os_tipo_solicitud_id')
            
            // JOINS para Apoyos Diagnosticos y Tipos
            ->leftJoin('apoyod_cargos as ac', 'a.cargo', '=', 'ac.cargo')
            ->leftJoin('apoyod_tipos as at', 'ac.apoyod_tipo_id', '=', 'at.apoyod_tipo_id') // Corrección: join con at en lugar de n

            // JOINS de Observaciones y Justificaciones (Basado en Legacy)
            ->leftJoin('hc_os_solicitudes_apoyod as obs_apo', 'a.hc_os_solicitud_id', '=', 'obs_apo.hc_os_solicitud_id')
            ->leftJoin('hc_os_solicitudes_interconsultas as obs_int', 'a.hc_os_solicitud_id', '=', 'obs_int.hc_os_solicitud_id')
            ->leftJoin('hc_os_solicitudes_no_quirurgicos as obs_noqx', 'a.hc_os_solicitud_id', '=', 'obs_noqx.hc_os_solicitud_id')
            ->leftJoin('hc_os_solicitudes_acto_qx as obs_qx', 'a.hc_os_solicitud_id', '=', 'obs_qx.hc_os_solicitud_id') // Corrección: tabla real es hc_os_solicitudes_acto_qx
            
            // JOINS para Justificaciones NO POS (Tablas separadas, no columnas en hc_os_solicitudes)
            ->leftJoin('hc_justificacion_procedimientos_no_pos_d as jd', 'a.hc_os_solicitud_id', '=', 'jd.hc_os_solicitud_id')
            ->leftJoin('hc_justificacion_procedimientos_no_pos_qx_detalle as jx', 'a.hc_os_solicitud_id', '=', 'jx.hc_os_solicitud_id')

            ->leftJoin('os_maestro as om', 'a.hc_os_solicitud_id', '=', 'om.hc_os_solicitud_id')
            ->leftJoin('os_ordenes_servicios as osv', 'om.orden_servicio_id', '=', 'osv.orden_servicio_id')
            ->leftJoin('departamentos as dpto', 'osv.departamento', '=', 'dpto.departamento')
            ->leftJoin('servicios as serv', 'dpto.servicio', '=', 'serv.servicio')
            
            // JOINS ADICIONALES para obtener servicio/depto cuando no hay orden de servicio 
            ->leftJoin('departamentos as dpto_ev', 'e.departamento', '=', 'dpto_ev.departamento')
            ->leftJoin('servicios as serv_ev', 'dpto_ev.servicio', '=', 'serv_ev.servicio')

            ->where('e.ingreso', $ingreso)
             ->select(
                'e.evolucion_id',
                DB::raw("TO_CHAR(a.fecha_solicitud, 'DD/MM/YYYY HH24:MI') as fecha_solicitud"),
                'a.fecha_registro',
                'a.cargo as cargo',
                'a.cargo as codigo',
                'b.descripcion as descar', 
                'b.descripcion as descripcion',
                'b.descripcion as nombre_examen',
                'a.hc_os_solicitud_id',
                'a.cantidad',
                'a.sw_ambulatorio',
                
                // Tipo de solicitud y descripción
                'a.os_tipo_solicitud_id',
                'ts.descripcion as desos', 
                
                // Apoyo Diagnostico Tipo (Subtipo)
                'at.apoyod_tipo_id',
                'at.descripcion as apoyod_tipo_descripcion', // Descripción del subtipo (ej. IMAGENOLOGIA)

                // Plan descripcion
                'pl.plan_descripcion',
                
                // Justificaciones NO POS (Aliases calculados si existe el ID en la tabla joined)
                DB::raw("CASE WHEN jd.hc_os_solicitud_id IS NOT NULL THEN jd.hc_os_solicitud_id ELSE NULL END as justificacion_nopos"),
                DB::raw("CASE WHEN jx.hc_os_solicitud_id IS NOT NULL THEN jx.hc_os_solicitud_id ELSE NULL END as justificacion_nopos_qx"),

                // Observaciones Específicas
                // 'a.observacion', // ERROR: No existe columna observacion en hc_os_solicitudes
                'obs_apo.observacion as obsapoyo',
                'obs_int.observacion as obsinter',
                'obs_noqx.observacion as obsnoqx',
                'obs_qx.observacion as obsqx',
                
                // Unificar observaciones en una sola columna para facilitar visualización si se quiere genérico, 
                // o dejarlas separadas como están arriba.
                // En el legacy se muestran por separado.
                DB::raw("COALESCE(obs_apo.observacion, obs_int.observacion, obs_noqx.observacion, obs_qx.observacion, '') as observacion"),

                // Servicio y Depto
                DB::raw("COALESCE(serv.descripcion, serv_ev.descripcion, 'SERVICIO NO DEFINIDO') as servicio_descripcion"),
                DB::raw("COALESCE(serv.descripcion, serv_ev.descripcion, 'SERVICIO NO DEFINIDO') as desserv"), 
                DB::raw("COALESCE(dpto.descripcion, dpto_ev.descripcion, 'DEPTO NO DEFINIDO') as departamento_descripcion"),
                DB::raw("COALESCE(dpto.descripcion, dpto_ev.descripcion, 'DEPTO NO DEFINIDO') as despto"),

                'b.grupo_tipo_cargo'
            )
            ->orderBy('a.fecha_solicitud', 'desc')
            ->get();

        // 3. Incapacidades
        $incapacidades = DB::table('hc_incapacidades as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('diagnosticos as d', 'a.diagnostico_id', '=', 'd.diagnostico_id')
            ->leftJoin('hc_tipos_incapacidad as ti', 'a.tipo_incapacidad_id', '=', 'ti.tipo_incapacidad_id')
            
            // JOINS Adicionales para el reporte detallado
            ->leftJoin('modalidad_prestacion_servicio as ms', 'a.modalidad_prestacion_servicio_id', '=', 'ms.modalidad_prestacion_servicio_id')
            ->leftJoin('hc_incapacidades_tipo_retroactiva as ir', 'a.incapacidades_tipo_retroactiva_id', '=', 'ir.incapacidades_tipo_retroactiva_id')
            ->leftJoin('uv_dependencias as dep', 'a.codigo_dependencia_id', '=', 'dep.codigo_dependencia_id')
            ->leftJoin('hc_tipos_atencion_incapacidad as v', 'a.tipo_atencion_incapacidad_id', '=', 'v.tipo_atencion_incapacidad_id')
            
            ->where('e.ingreso', $ingreso)
            ->select(
                'e.evolucion_id',
                'a.fecha_inicio',
                'a.dias_de_incapacidad',
                'a.dias_de_incapacidad as dias',
                'a.observacion_incapacidad',
                'a.observacion_incapacidad as observacion',
                'a.sw_prorroga',
                'ti.descripcion as tipo_incapacidad',
                'd.diagnostico_nombre',
                'd.diagnostico_id as codigo_diagnostico',
                
                // Campos adicionales requeridos en el reporte
                'dep.descripcion_dependencia',
                'ms.descripcion as desc_modalidad_servicio',
                'ir.descripcion as desc_inc_retroactiva',
                'v.descripcion as clase_atencion',
                'a.sw_licencia_maternidad',
                'a.fecha_posible_parto',
                'a.semanas_gestacion',
                'a.sw_embarazo_multiple',
                'a.num_nacidos_vivos'
            )
            ->get();

        // 4. Diagnosticos (Generales del Ingreso)
        $diagnosticos = DB::table('hc_diagnosticos_ingreso as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('diagnosticos as d', 'a.tipo_diagnostico_id', '=', 'd.diagnostico_id')
            ->where('e.ingreso', $ingreso)
            ->select(
                'e.evolucion_id',
                'a.tipo_diagnostico_id as codigo',
                'a.tipo_diagnostico_id as diagnostico_id', // Alias adicional para PDF formula
                'd.diagnostico_nombre as nombre',
                'd.diagnostico_nombre as diagnostico_nombre', // Alias adicional para PDF formula
                'a.sw_principal as tipo_diagnostico',
                'e.fecha' // Para ordenar
            )
            ->orderBy('e.fecha', 'desc')
            ->get();

        // 5. Notas Medicas
        $notas = DB::table('notas_medicas as a')
            ->leftJoin('system_usuarios as u', 'a.usuario_id', '=', 'u.usuario_id')
            ->where('a.ingreso', $ingreso)
            ->select(
                'a.ingreso',
                'a.nota_medica as nota',
                'a.fecha_registro as fecha_nota',
                'a.usuario_id',
                'a.evolucion_id',
                'u.nombre as nombre_usuario'
            )
            ->orderBy('a.fecha_registro', 'asc')
            ->get();

        // 6. Procedimientos No Quirúrgicos (Para visualización detallada con observaciones)
        $procedimientosNoQx = DB::table('hc_os_solicitudes as a')
            ->join('hc_os_solicitudes_no_quirurgicos as nq', 'a.hc_os_solicitud_id', '=', 'nq.hc_os_solicitud_id')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->where('e.ingreso', $ingreso)
            ->where('a.sw_ambulatorio', '1')
            ->select(
                'a.hc_os_solicitud_id',
                'a.hc_os_solicitud_id as numero_solicitud',
                DB::raw("TO_CHAR(a.fecha_solicitud, 'DD/MM/YYYY') as fecha"),
                'a.cargo',
                'b.descripcion',
                'a.cantidad',
                'nq.observacion'
            )
            ->orderBy('a.fecha_solicitud', 'desc')
            ->get();

        // 7. Encuesta de Satisfacción (Nuevo)
        $encuesta = DB::table('hc_encuesta_satisfaccion')
            ->where('ingreso', $ingreso)
            ->first();

        // 8. Recomendaciones Médicas
        $recomendaciones = DB::table('hc_recomendaciones_medicas as a')
            ->join('hc_evoluciones as b', 'a.evolucion_id', '=', 'b.evolucion_id')
            ->join('system_usuarios as u', 'b.usuario_id', '=', 'u.usuario_id')
            ->where('b.ingreso', $ingreso)
            ->select(
                'a.evolucion_id',
                'a.recomendaciones_adic',
                DB::raw("TO_CHAR(b.fecha,'DD/MM/YYYY') AS fecha_registro"),
                'u.nombre as usuario',
                'u.usuario as login_usuario'
            )
            ->orderBy('b.fecha', 'desc')
            ->get();

        // Obtener detalles de recomendaciones (items seleccionados de una lista si aplica)
        // A veces las recomendaciones son solo texto (recomendaciones_adic), otras veces items (hc_recomendaciones_medicas_detalle)
        foreach ($recomendaciones as $rec) {
             $detalles = DB::table('hc_recomendaciones_medicas_detalle as d')
                ->join('hc_recomendaciones_medicas_listado as l', 'd.recomendacion_id', '=', 'l.recomendacion_id')
                ->where('d.evolucion_id', $rec->evolucion_id)
                ->select('l.descripcion', 'l.recomendacion_id')
                ->get();
             $rec->detalles = $detalles;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'medicamentos' => $medicamentos,
                'solicitudes' => $solicitudes,
                'incapacidades' => $incapacidades,
                'diagnosticos' => $diagnosticos,
                'notas' => $notas,
                'procedimientos_no_qx' => $procedimientosNoQx,
                'encuesta' => $encuesta,
                'recomendaciones' => $recomendaciones
            ]
        ]);
    }

    /**
     * Envía un correo con el reporte detallado del historial médico.
     * @param int $ingreso
     */
    public function sendHistoryEmail(Request $request, $ingreso)
    {
        $type = $request->input('type', 'all'); // 'all', 'formula', 'ordenes'
        $evolucionIdFilter = $request->input('evolucion_id', null);
        $servicioFilter = $request->input('servicio', null);
        $idsFilter = $request->input('ids', null);

        // 1. Obtener datos del ingreso 
        $detailResponse = $this->getHistoryDetail($ingreso);
        $data = $detailResponse->getData()->data;

        // Filtrar datos si se especifica una evolución
        if ($evolucionIdFilter) {
            $data->medicamentos = array_values(array_filter($data->medicamentos, function ($m) use ($evolucionIdFilter) {
                return $m->evolucion_id == $evolucionIdFilter;
            }));
            $data->solicitudes = array_values(array_filter($data->solicitudes, function ($s) use ($evolucionIdFilter) {
                return $s->evolucion_id == $evolucionIdFilter;
            }));
            $data->incapacidades = array_values(array_filter($data->incapacidades, function ($i) use ($evolucionIdFilter) {
                return $i->evolucion_id == $evolucionIdFilter;
            }));
            // Opcional: filtrar diagnosticos si estan ligados a evolución (en hc_diagnosticos_ingreso sí lo estan)
            $data->diagnosticos = array_values(array_filter($data->diagnosticos, function ($d) use ($evolucionIdFilter) {
                return $d->evolucion_id == $evolucionIdFilter;
            }));
            $data->notas = array_values(array_filter($data->notas, function ($n) use ($evolucionIdFilter) {
                return $n->evolucion_id == $evolucionIdFilter;
            }));
            $data->recomendaciones = array_values(array_filter($data->recomendaciones, function ($r) use ($evolucionIdFilter) {
                return $r->evolucion_id == $evolucionIdFilter;
            }));
        }

        // --- NUEVO: Filtro por IDs (Prioridad) ---
        if ($idsFilter && $type === 'ordenes') {
            $ids = is_array($idsFilter) ? $idsFilter : explode(',', $idsFilter);
            $data->solicitudes = array_values(array_filter($data->solicitudes, function ($s) use ($ids) {
                return in_array($s->hc_os_solicitud_id, $ids);
            }));
        }
        // --- NUEVO: Filtro por Servicio (Fallback) ---
        elseif ($servicioFilter && $type === 'ordenes') {
            $data->solicitudes = array_values(array_filter($data->solicitudes, function ($s) use ($servicioFilter) {
                return $s->servicio_descripcion === $servicioFilter;
            }));
        }

        // 2. Obtener datos cabecera (paciente/profesional) 
        // Si hay filtro, usamos esa evolución para la cabecera. Si no, la última.
        $unaEvolucion = null;
        if ($evolucionIdFilter) {
            $header = $this->getHeaderData($evolucionIdFilter);
            $unaEvolucion = DB::table('hc_evoluciones')->where('evolucion_id', $evolucionIdFilter)->first();
        } else {
            $unaEvolucion = DB::table('hc_evoluciones')->where('ingreso', $ingreso)->orderBy('fecha', 'desc')->first();
            if (!$unaEvolucion) return response()->json(['success' => false, 'message' => 'No se encontraron registros para este ingreso.'], 404);
            $header = $this->getHeaderData($unaEvolucion->evolucion_id);
        }

        // Agregar info extra de la evolución al header, si existe
        if ($header && $unaEvolucion) {
            $header->motivo_consulta = $unaEvolucion->motivo_consulta ?? '';
            $header->enfermedad_actual = $unaEvolucion->enfermedad_actual ?? '';
            $header->analisis = $unaEvolucion->analisis ?? '';
            $header->plan = $unaEvolucion->plan ?? ($unaEvolucion->conducta ?? ''); // Algunos legacy usan conducta
        }

        if (!$header) {
            return response()->json(['success' => false, 'message' => 'Error obteniendo datos del paciente.'], 500);
        }

        // --- Obtener datos de Empresa y Logo (Faltaban en la versión anterior) ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', function($join) {
                $join->on('e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
                     ->on('e.tipo_dpto_id', '=', 'm.tipo_dpto_id');
            })
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select('e.razon_social', 'e.id as nit', 'e.digito_verificacion', 'e.direccion', 'e.telefonos', 'e.website', 'e.email', 'm.municipio', 'd.departamento')
            ->where('e.sw_activa', '1')
            ->orderBy('e.id', 'asc')
            ->first();

        // Normalizar dirección (Colapsar espacios redundantes)
        if ($empresa && !empty($empresa->direccion)) {
            $empresa->direccion = preg_replace('/\s+/', ' ', trim($empresa->direccion));
        }

        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $typeImg = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $imgData = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $typeImg . ';base64,' . base64_encode($imgData);
        }

        // Recuperar email del paciente
        $paciente = Paciente::where('paciente_id', $header->paciente_id)
            ->where('tipo_id_paciente', $header->tipo_id_paciente)
            ->first();

        if (!$paciente || empty($paciente->email)) {
            return response()->json(['success' => false, 'message' => 'El paciente no tiene un correo electrónico registrado.'], 400);
        }

        // ============================
        // ✅ FIRMA PROFESIONAL Base64
        // ============================
        $firmaBase64 = null;
        if (!empty($header->firma)) {
            $fileFirmaEncoded = str_replace('*', '%2A', $header->firma);
            // 1) FILESYSTEM
            $basePath = env('LEGACY_PATH');
            $firmaPath = $basePath . '/images/firmas_profesionales/' . $fileFirmaEncoded;

            if (file_exists($firmaPath)) {
                $ext = strtolower(pathinfo($firmaPath, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg', 'jpeg', 'png']) ? $ext : 'jpeg';
                $firmaBase64 = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($firmaPath));
            } else {
                // 2) URL
                $baseUrl = env('LEGACY_URL');
                $firmaUrl = $baseUrl . '/images/firmas_profesionales/' . $fileFirmaEncoded;
                try {
                    $imgResp = Http::timeout(5)->get($firmaUrl);
                    if ($imgResp->ok()) {
                        $cType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                        $firmaBase64 = 'data:' . $cType . ';base64,' . base64_encode($imgResp->body());
                    }
                } catch (\Throwable $eF) { Log::error("Error firma email: " . $eF->getMessage()); }
            }
        }

        try {
            $pdfContentCompleto = null;
            $pdfContentFormula = null;
            $pdfContentOrden = null;
            $pdfContentIncapacidad = null;
            $pdfContentRecomendacion = null;

            // 3. Generar PDF consolidado en memoria (Solo si type == 'all')
            if ($type === 'all') {
                // --- INICIO OBTENCIÓN HTML LEGACY ---
                $urlLegacy = env('LEGACY_WS_URL');
                $htmlLegacy = '';

                try {
                    $resp = Http::withHeaders(['X-Legacy-Token' => env('LEGACY_HC_TOKEN')])->get($urlLegacy, ['ingreso' => (int)$ingreso]);
                    if ($resp->ok()) {
                        $payload = $resp->json();
                        if (!empty($payload['html'])) {
                            $htmlLegacy = (string)$payload['html'];
                            // Limpieza básica (igual a generateHistoryPdf)
                            $htmlLegacy = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $htmlLegacy);
                            $htmlLegacy = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $htmlLegacy);
                            $htmlLegacy = preg_replace('#<frame\b[^>]*>.*?</frame>#is', '', $htmlLegacy);
                            $htmlLegacy = str_ireplace(['href="about:blank"', "href='about:blank'"], 'href="#"', $htmlLegacy);
                            $htmlLegacy = str_replace('images/firmas_profesionales/"', 'images/firmas_profesionales/pixel_dummy.png"', $htmlLegacy);
                            $htmlLegacy = str_replace("images/firmas_profesionales/'", "images/firmas_profesionales/pixel_dummy.png'", $htmlLegacy);

                            // ==========================================
                            // LIMPIEZA UNIFICADA (Email)
                            // ==========================================
                            // 1. Quitar textos de encabezado duplicados
                            $htmlLegacy = str_ireplace('SIIS - APLICACION DE PRUEBAS', '', $htmlLegacy);
                            $htmlLegacy = str_ireplace('HISTORIA CLÍNICA', '', $htmlLegacy);
                            
                            // 2. Eliminar imágenes que no sean base64 (causan la X roja o errores de carga)
                            // Reemplazamos <img ... src="algo no data" ...> por un pixel transparente
                            $htmlLegacy = preg_replace(
                                '/<img(?![^>]+src=["\']data:)[^>]+>/i', 
                                '',  // Simplemente las eliminamos para limpiar
                                $htmlLegacy
                            );
                            
                            // 3. Ocultar footer antiguo (Profesional, Imprimió, etc) para que no salga doble
                            // Buscamos patrones comunes del pie de página legacy
                            $htmlLegacy = preg_replace('/Imprimió:.*?<\/table>/is', '', $htmlLegacy); // Intento borrar bloque de impresion
                            $htmlLegacy = str_ireplace(['PROFESIONAL:', 'Registro Médico:', 'Especialidad:'], ['<!-- PROFESIONAL: -->', '<!-- Registro -->', '<!-- Esp -->'], $htmlLegacy); // Comentar etiquetas viejas

                        }
                    }
                } catch (\Exception $eLeg) {
                    Log::error("Error obteniendo HTML legacy para email: " . $eLeg->getMessage());
                }
                // --- FIN OBTENCIÓN HTML LEGACY ---

                // CAMBIO: Usar Snappy para el Correo (igual que en Imprimir) para consistencia visual
                $pdf = app('snappy.pdf.wrapper');
                $pdf->loadView('reportes.hc_legacy', [
                    'html' => $htmlLegacy,
                    'header' => $header,
                    'paciente' => $header,
                    'medicamentos' => $data->medicamentos,
                    'solicitudes' => $data->solicitudes,
                    'incapacidades' => $data->incapacidades,
                    'diagnosticos' => $data->diagnosticos ?? [],
                    'notas' => $data->notas ?? [],
                    'recomendaciones' => $data->recomendaciones ?? [], // NEW
                    'fecha' => $header->fecha,
                    'profesional' => $header->profesional,
                    'especialidad' => $header->especialidad,
                    'ingreso' => $ingreso,
                    'empresa' => $empresa,
                    'logoBase64' => $logoBase64,
                    'firmaBase64' => $firmaBase64,
                    'baseUrl' => env('LEGACY_URL') . '/'
                ]);
                
                // Opciones críticas para que se vea bien
                $pdf->setOption('enable-local-file-access', true);
                $pdf->setOption('encoding', 'utf-8');
                $pdf->setOption('load-error-handling', 'ignore');
                $pdf->setOption('disable-smart-shrinking', true);
                
                $pdfContentCompleto = $pdf->output(); // Obtener binario para adjunto
            }

            // 3.2 Generar PDF Formula (Si hay medicamentos y corresponde el tipo)
            if (($type === 'all' || $type === 'formula') && !empty($data->medicamentos) && count($data->medicamentos) > 0) {
                $dompdfF = new \Dompdf\Dompdf();
                $dompdfF->set_option('isRemoteEnabled', true);
                $dompdfF->loadHtml(view('formula', [
                    'header' => $header,
                    'empresa' => $empresa ?? null,
                    'logoBase64' => $logoBase64 ?? null,
                    'firmaBase64' => $firmaBase64,
                    'edad' => isset($header->fecha_nacimiento) ? \Carbon\Carbon::parse($header->fecha_nacimiento)->age : '',
                    'medicamentos' => $data->medicamentos ?? [],
                    'diagnosticos' => $data->diagnosticos ?? [], // Nota: GetHistoryDetail no devuelve diagnosticos actualmente en 'data', considerar agregarlo si es crítico.
                    'fecha_impresion' => date('d/m/Y - h:i a')
                ])->render());
                $dompdfF->setPaper('A4', 'portrait');
                $dompdfF->render();
                $pdfContentFormula = $dompdfF->output();
            }

            // 3.3 Generar PDF Ordenes (Si hay solicitudes y corresponde el tipo)
            if (($type === 'all' || $type === 'ordenes') && !empty($data->solicitudes) && count($data->solicitudes) > 0) {
                $dompdfO = new \Dompdf\Dompdf();
                $dompdfO->set_option('isRemoteEnabled', true);

                // Calcular numero_orden min
                $numero_orden = '';
                if (count($data->solicitudes) > 0) {
                    $ids = array_map(function ($s) {
                        return $s->hc_os_solicitud_id;
                    }, $data->solicitudes);
                    $numero_orden = min($ids);
                }

                $dompdfO->loadHtml(view('orden', [
                    'paciente' => $header,
                    'solicitudes' => $data->solicitudes,
                    'fecha' => $header->fecha,
                    'profesional' => $header->profesional,
                    'especialidad' => $header->especialidad,
                    'empresa' => $empresa ?? null,
                    'logoBase64' => $logoBase64 ?? null,
                    'firmaBase64' => $firmaBase64,
                    'edad' => isset($header->fecha_nacimiento) ? \Carbon\Carbon::parse($header->fecha_nacimiento)->age : '',
                    'numero_orden' => $numero_orden,
                    'fecha_impresion' => date('Y-m-d H:i:s')
                ])->render());
                $dompdfO->setPaper('A4', 'portrait');
                $dompdfO->render();
                $pdfContentOrden = $dompdfO->output();
            }

            // 3.4 Generar PDF Incapacidad (Si hay incapacidades y corresponde el tipo)
            if (($type === 'all' || $type === 'incapacidad') && !empty($data->incapacidades) && count($data->incapacidades) > 0) {
                $dompdfI = new \Dompdf\Dompdf();
                $dompdfI->set_option('isRemoteEnabled', true);

                $incapacidadNumeroEmail = optional(collect($data->incapacidades)->first())->hc_incapacidad_id ?? '';
                $fechaSolicitudEmail = !empty($header->fecha)
                    ? \Carbon\Carbon::parse($header->fecha)->format('d/m/Y')
                    : date('d/m/Y');
                
                $dompdfI->loadHtml(view('incapacidad', [
                    'paciente' => $header,
                    'incapacidades' => $data->incapacidades,
                    'empresa' => $empresa ?? null,
                    'logoBase64' => $logoBase64 ?? null,
                    'firmaBase64' => $firmaBase64,
                    'hc_incapacidad_id' => $incapacidadNumeroEmail,
                    'fecha_solicitud' => $fechaSolicitudEmail,
                    'fecha' => !empty($header->fecha_registro) ? date('Y-m-d', strtotime($header->fecha_registro)) : date('Y-m-d'),
                    'fecha_impresion' => date('Y-m-d H:i')
                ])->render());
                $dompdfI->setPaper('A4', 'portrait');
                $dompdfI->render();
                $pdfContentIncapacidad = $dompdfI->output();
            }

            // 3.5 Generar PDF Recomendacion (Si hay recomendaciones y corresponde el tipo)
            if (($type === 'all' || $type === 'recomendaciones') && !empty($data->recomendaciones) && count($data->recomendaciones) > 0) {
                 $dompdfR = new \Dompdf\Dompdf();
                 $dompdfR->set_option('isRemoteEnabled', true);
                 
                 $dompdfR->loadHtml(view('recomendacion', [
                    'header' => $header,
                    'recomendaciones' => $data->recomendaciones,
                    'empresa' => $empresa ?? null,
                    'logoBase64' => $logoBase64 ?? null,
                    'firmaBase64' => $firmaBase64,
                    'edad' => isset($header->fecha_nacimiento) ? \Carbon\Carbon::parse($header->fecha_nacimiento)->age : '',
                    'fecha_impresion' => date('Y-m-d H:i')
                ])->render());
                $dompdfR->setPaper('A4', 'portrait');
                $dompdfR->render();
                $pdfContentRecomendacion = $dompdfR->output();
            }

            // 3.6 Generar PDF No Quirúrgicos (Si type === 'all')
            $pdfContentNoQx = null;
            if ($type === 'all') {
                $procedimientosNoQx = DB::table('hc_os_solicitudes as a')
                    ->join('hc_os_solicitudes_no_quirurgicos as e', 'a.hc_os_solicitud_id', '=', 'e.hc_os_solicitud_id')
                    ->join('cups as b', 'a.cargo', '=', 'b.cargo')
                    ->join('hc_evoluciones as d', 'a.evolucion_id', '=', 'd.evolucion_id')
                    ->where('d.ingreso', $ingreso)
                    ->select(
                        'a.cargo',
                        'b.descripcion',
                        'a.cantidad',
                        'a.fecha_solicitud',
                        'a.fecha_solicitud as fecha',
                        'e.observacion',
                        'd.usuario_id',
                        'a.hc_os_solicitud_id',
                        DB::raw("'NO QUIRURGICO' as tipo")
                    )
                    ->orderBy('a.fecha_solicitud', 'desc')
                    ->get();
                
                if (!$procedimientosNoQx->isEmpty()) {
                     // Datos complementarios
                     $usuario_id_firma_noqx = null;
                     foreach ($procedimientosNoQx as $proc) {
                         $diagnosticos = DB::table('hc_os_solicitudes_diagnosticos as sd')
                             ->join('diagnosticos as dx', 'sd.diagnostico_id', '=', 'dx.diagnostico_id')
                             ->where('sd.hc_os_solicitud_id', $proc->hc_os_solicitud_id)
                             ->select('sd.diagnostico_id', 'dx.diagnostico_nombre', 'sd.tipo_diagnostico', 'sd.sw_principal')
                             ->get();
                         $proc->diagnosticos = $diagnosticos;
                         if (!$usuario_id_firma_noqx) $usuario_id_firma_noqx = $proc->usuario_id;
                     }

                     // Profesional Firma NoQx
                     $profesionalNoQx = null;
                     if ($usuario_id_firma_noqx) {
                         $profesionalNoQx = DB::table('system_usuarios as u')
                             ->join('profesionales_usuarios as pu', 'u.usuario_id', '=', 'pu.usuario_id')
                             ->join('profesionales as p', function($join){
                                 $join->on('pu.tipo_tercero_id', '=', 'p.tipo_id_tercero')->on('pu.tercero_id', '=', 'p.tercero_id');
                             })
                             ->leftJoin('profesionales_especialidades as pe', function($join){
                                 $join->on('p.tipo_id_tercero', '=', 'pe.tipo_id_tercero')->on('p.tercero_id', '=', 'pe.tercero_id');
                             })
                             ->leftJoin('especialidades as esp', 'pe.especialidad', '=', 'esp.especialidad')
                             ->where('u.usuario_id', $usuario_id_firma_noqx)
                             ->select('p.nombre', 'p.tarjeta_profesional', 'esp.descripcion as especialidad', 'p.firma')
                             ->first();
                     }
                     
                     // Generar PDF
                     $pdfNoQx = \PDF::loadView('reportes.hc_solicitud_no_qx', [
                        'empresa' => $empresa,
                        'paciente' => $header,
                        'ingreso' => $ingreso,
                        'fecha' => $procedimientosNoQx[0]->fecha_solicitud ?? date('Y-m-d'),
                        'profesional' => $profesionalNoQx,
                        'cliente' => $header,
                        'procedimientos' => $procedimientosNoQx,
                        'logoBase64' => $logoBase64,
                        'firmaBase64' => $firmaBase64
                    ]);
                    $pdfContentNoQx = $pdfNoQx->output();
                }
            }

            // 4. Enviar Correo con Adjunto
            Mail::send('emails.medical_history_report_v2', [
                'nombre' => $header->nombre_completo,
                'fecha' => $header->fecha,
                'ingreso' => $ingreso,
                'profesional' => $header->profesional
            ], function ($message) use ($paciente, $ingreso, $pdfContentCompleto, $pdfContentFormula, $pdfContentOrden, $pdfContentIncapacidad, $pdfContentRecomendacion, $pdfContentNoQx, $type) {
                $message->to($paciente->email)
                    ->subject('Reporte Historia Clínica - Ingreso #' . $ingreso);

                // Adjunto 1: Reporte Completo (Solo si existe)
                if ($pdfContentCompleto) {
                    $message->attachData($pdfContentCompleto, "Historia_Clinica_Completa_{$ingreso}.pdf", ['mime' => 'application/pdf']);
                }

                // Adjunto 2: Fórmula (Si existe)
                if ($pdfContentFormula) {
                    $message->attachData($pdfContentFormula, "Formula_Medica_{$ingreso}.pdf", ['mime' => 'application/pdf']);
                }

                // Adjunto 3: Ordenes (Si existe)
                if ($pdfContentOrden) {
                    $message->attachData($pdfContentOrden, "Ordenes_Medicas_{$ingreso}.pdf", ['mime' => 'application/pdf']);
                }

                // Adjunto 4: Incapacidad (Si existe)
                if ($pdfContentIncapacidad) {
                    $message->attachData($pdfContentIncapacidad, "Incapacidad_Medica_{$ingreso}.pdf", ['mime' => 'application/pdf']);
                }
                
                // Adjunto 5: Recomendacion (Si existe)
                if ($pdfContentRecomendacion) {
                    $message->attachData($pdfContentRecomendacion, "Recomendaciones_Medicas_{$ingreso}.pdf", ['mime' => 'application/pdf']);
                }
                
                // Adjunto 6: No Qx (Si existe)
                if ($pdfContentNoQx) {
                    $message->attachData($pdfContentNoQx, "Procedimientos_No_Qx_{$ingreso}.pdf", ['mime' => 'application/pdf']);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Reporte enviado correctamente a ' . $this->maskEmail($paciente->email)
            ]);
        } catch (\Exception $e) {
            Log::error("Error enviando reporte email: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el correo.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function maskEmail($email)
    {
        $parts = explode('@', $email);
        if (count($parts) < 2) return $email;
        $name = $parts[0];
        $len = strlen($name);
        $visibleLen = floor($len / 2);
        $maskedName = substr($name, 0, $visibleLen) . str_repeat('*', ($len - $visibleLen));
        return $maskedName . '@' . $parts[1];
    }

    // Helper privado

    private function getHeaderData($evolucion_id)
    {
        $header = DB::table('hc_evoluciones as a')
            ->join('ingresos as b', 'a.ingreso', '=', 'b.ingreso')
            ->leftJoin('cuentas as cu', 'b.ingreso', '=', 'cu.ingreso')
            ->leftJoin('departamentos as dep', 'b.departamento', '=', 'dep.departamento')
            ->leftJoin('servicios as ser', 'dep.servicio', '=', 'ser.servicio')
            ->join('pacientes as p', function ($join) {
                $join->on('b.paciente_id', '=', 'p.paciente_id')
                    ->on('b.tipo_id_paciente', '=', 'p.tipo_id_paciente');
            })
            ->leftJoin('planes as pl', 'cu.plan_id', '=', 'pl.plan_id')
            ->leftJoin('terceros as cli', 'pl.tercero_id', '=', 'cli.tercero_id')
            ->join('profesionales_usuarios as c', 'a.usuario_id', '=', 'c.usuario_id')
            ->join('profesionales as d', function ($join) {
                $join->on('c.tercero_id', '=', 'd.tercero_id')
                    ->on('c.tipo_tercero_id', '=', 'd.tipo_id_tercero');
            })
            ->leftJoin('profesionales_especialidades as pe', function ($join) {
                $join->on('d.tercero_id', '=', 'pe.tercero_id')
                    ->on('d.tipo_id_tercero', '=', 'pe.tipo_id_tercero');
            })
            ->leftJoin('especialidades as esp', 'pe.especialidad', '=', 'esp.especialidad')
            ->leftJoin('tipos_afiliado as ta', 'cu.tipo_afiliado_id', '=', 'ta.tipo_afiliado_id')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'p.paciente_id',
                'p.tipo_id_paciente',
                DB::raw("CONCAT(p.tipo_id_paciente, ' ', p.paciente_id) as identificacion"),
                DB::raw("CONCAT(COALESCE(p.primer_nombre,''), ' ', COALESCE(p.segundo_nombre,''), ' ', COALESCE(p.primer_apellido,''), ' ', COALESCE(p.segundo_apellido,'')) as nombre_completo"),
                DB::raw("CONCAT(COALESCE(p.primer_nombre,''), ' ', COALESCE(p.segundo_nombre,''), ' ', COALESCE(p.primer_apellido,''), ' ', COALESCE(p.segundo_apellido,'')) as nombre_paciente"),
                'p.fecha_nacimiento',
                'p.sexo_id',
                'p.sexo_id as sexo',
                'p.residencia_direccion as direccion',
                'p.residencia_telefono as telefono',

                'a.fecha_registro',
                DB::raw("DATE(a.fecha) as fecha"),
                DB::raw("DATE(b.fecha_ingreso) as fecha_ingreso"),
                'a.evolucion_id',
                'b.ingreso',
                'dep.descripcion as departamento_descripcion',
                'ser.descripcion as servicio_descripcion',

                'd.nombre as profesional',
                'd.tercero_id as prof_id',
                'd.tipo_id_tercero as prof_tipo_id',
                'd.tarjeta_profesional',
                'd.tarjeta_profesional as registro_medico',
                'd.firma', // ✅ FIRMA DEL PROFESIONAL

                'esp.descripcion as especialidad',
                'pl.plan_descripcion',
                'cli.nombre_tercero as cliente_nombre',
                'cu.tipo_afiliado_id',
                'ta.tipo_afiliado_nombre as tipo_afiliado_descripcion',
                'cu.rango'
            )
            ->first();

        if ($header && !empty($header->fecha_nacimiento)) {
            $header->edad = \Carbon\Carbon::parse($header->fecha_nacimiento)->age . ' Años';
        } elseif ($header) {
            $header->edad = '';
        }

        return $header;
    }

    private function getFirmaBase64($firma)
    {
        if (empty($firma)) return null;

        $fileFirmaEncoded = str_replace('*', '%2A', $firma);
        $basePath = env('LEGACY_PATH');
        $firmaPath = $basePath . '/images/firmas_profesionales/' . $fileFirmaEncoded;

        if (file_exists($firmaPath)) {
            $ext = strtolower(pathinfo($firmaPath, PATHINFO_EXTENSION));
            $mime = in_array($ext, ['jpg', 'jpeg', 'png']) ? $ext : 'jpeg';
            return 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($firmaPath));
        } else {
            $baseUrl = env('LEGACY_URL');
            $firmaUrl = $baseUrl . '/images/firmas_profesionales/' . $fileFirmaEncoded;
            try {
                $imgResp = Http::timeout(5)->get($firmaUrl);
                if ($imgResp->ok()) {
                    $cType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                    return 'data:' . $cType . ';base64,' . base64_encode($imgResp->body());
                }
            } catch (\Throwable $eF) { \Log::error("Error firma: " . $eF->getMessage()); }
        }
        return null;
    }


    public function generateFormulaPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // --- Empresa ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', function($join) {
                $join->on('e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
                     ->on('e.tipo_dpto_id', '=', 'm.tipo_dpto_id');
            })
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select(
                'e.razon_social',
                'e.id as nit',
                'e.digito_verificacion',
                'e.direccion',
                'e.telefonos',
                'e.website',
                'e.email',
                'm.municipio',
                'd.departamento'
            )
            ->where('e.sw_activa', '1')
            ->orderBy('e.id', 'asc')
            ->first();

        // Normalizar dirección (Colapsar espacios redundantes)
        if ($empresa && !empty($empresa->direccion)) {
            $empresa->direccion = preg_replace('/\s+/', ' ', trim($empresa->direccion));
        }

        // --- LOGO Base64 ---
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $data = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // Calcular edad
        $edad = $header->fecha_nacimiento ? \Carbon\Carbon::parse($header->fecha_nacimiento)->age : '';

        // Diagnósticos (Se buscan por ingreso para incluir todos los del episodio actual)
        $diagnosticos = DB::table('hc_diagnosticos_ingreso as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->leftJoin('diagnosticos as b', 'a.tipo_diagnostico_id', '=', 'b.diagnostico_id')
            ->where('e.ingreso', $header->ingreso)
            ->select('a.tipo_diagnostico_id as diagnostico_id', 'b.diagnostico_nombre')
            ->distinct()
            ->get();

        // Medicamentos
        $medicamentos = DB::table('hc_medicamentos_recetados_amb as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('inventarios_productos as i', 'a.codigo_producto', '=', 'i.codigo_producto')
            ->leftJoin('medicamentos as b', 'a.codigo_producto', '=', 'b.codigo_medicamento') // Opcional para principio activo
            ->leftJoin('inv_med_cod_principios_activos as pa', 'b.cod_principio_activo', '=', 'pa.cod_principio_activo') // NUEVO
            ->leftJoin('hc_vias_administracion as v', 'a.via_administracion_id', '=', 'v.via_administracion_id')
            // NUEVO: frecuencia real desde hc_posologia_horario_op1
            ->leftJoin('hc_posologia_horario_op1 as ph', function ($join) {
                $join->on('ph.evolucion_id', '=', 'a.evolucion_id')
                    ->on('ph.codigo_producto', '=', 'a.codigo_producto');
            })

            ->where('e.evolucion_id', $evolucion_id)
            ->select(
                'e.evolucion_id',
                'e.fecha',
                'a.codigo_producto as codigo_medicamento',
                'a.codigo_producto as codigo', // Alias adicional para compatibilidad vistas
                'i.descripcion as producto',
                'i.descripcion as nombre_medicamento', // Alias adicional para compatibilidad vistas
                'b.cod_principio_activo as principio_activo',
                'pa.descripcion as principio_activo', // NUEVO
                'a.dosis',
                'a.unidad_dosificacion',
                'a.cantidadperiocidad as frecuencia',
                'a.dias_tratamiento',
                'a.dias_tratamiento as tiempo_tratamiento',
                'a.cantidad',
                'a.observacion',
                'a.via_administracion_id',
                'v.nombre as via_administracion_nombre',
                //  FRECUENCIA
                DB::raw("MIN(ph.periocidad_id) as periocidad_id"),
                DB::raw("MIN(ph.tiempo) as tiempo_frecuencia"),
                DB::raw("
                        CASE
                        WHEN MIN(ph.periocidad_id) IS NOT NULL AND MIN(ph.tiempo) IS NOT NULL
                        THEN CONCAT('cada ', MIN(ph.periocidad_id), ' ', MIN(ph.tiempo))
                        ELSE CAST(a.cantidadperiocidad AS TEXT)
                        END as frecuencia
                    ")
            )->groupBy(
                'e.evolucion_id',
                'e.fecha',
                'a.codigo_producto',
                'i.descripcion',
                'b.cod_principio_activo',
                'pa.descripcion',
                'a.dosis',
                'a.unidad_dosificacion',
                'a.dias_tratamiento',
                'a.cantidad',
                'a.observacion',
                'a.via_administracion_id',
                'v.nombre',
                'a.cantidadperiocidad'
            )
            ->get();



        // ============================
        // ✅ FIRMA PROFESIONAL Base64
        // ============================
        $firmaBase64 = $this->getFirmaBase64($header->firma);

        // Render HTML
        $html = view('formula', [
            'header' => $header,
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64,
            'edad' => $edad,
            'medicamentos' => $medicamentos,
            'diagnosticos' => $diagnosticos,
            'fecha_impresion' => date('d/m/Y - h:i a')
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('formula_' . $evolucion_id . '.pdf');
    }


    public function generateOrderPdf(Request $request, $evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        $servicioFilter = $request->query('servicio');
        $tipoFilter = $request->query('tipo'); 
        
        // Nuevo: Filtro por IDs específicos (para impresión exacta de lo que ve el usuario)
        // Se espera ?ids=123,124,125
        $idsFilter = $request->query('ids'); 

        // --- Empresa ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', function($join) {
                $join->on('e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
                     ->on('e.tipo_dpto_id', '=', 'm.tipo_dpto_id');
            })
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select(
                'e.razon_social',
                'e.id as nit',
                'e.digito_verificacion',
                'e.direccion',
                'e.telefonos',
                'e.website',
                'e.email',
                'm.municipio',
                'd.departamento'
            )
            ->where('e.sw_activa', '1')
            ->orderBy('e.id', 'asc')
            ->first();

        // Normalizar dirección (Colapsar espacios redundantes)
        if ($empresa && !empty($empresa->direccion)) {
            $empresa->direccion = preg_replace('/\s+/', ' ', trim($empresa->direccion));
        }

        // --- LOGO Base64 ---
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $data = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // --- Solicitudes ---
        $query = DB::table('hc_os_solicitudes as a')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->leftJoin('planes as pl', 'a.plan_id', '=', 'pl.plan_id')
            ->leftJoin('os_tipos_solicitudes as ts', 'a.os_tipo_solicitud_id', '=', 'ts.os_tipo_solicitud_id')
            ->leftJoin('apoyod_cargos as ac', 'a.cargo', '=', 'ac.cargo')
            ->leftJoin('apoyod_tipos as at', 'ac.apoyod_tipo_id', '=', 'at.apoyod_tipo_id')
            
            // JOINS de Observaciones (Separadas para mostrarlas si existen)
            ->leftJoin('hc_os_solicitudes_apoyod as obs_apo', 'a.hc_os_solicitud_id', '=', 'obs_apo.hc_os_solicitud_id')
            ->leftJoin('hc_os_solicitudes_interconsultas as obs_int', 'a.hc_os_solicitud_id', '=', 'obs_int.hc_os_solicitud_id')
            ->leftJoin('hc_os_solicitudes_no_quirurgicos as obs_noqx', 'a.hc_os_solicitud_id', '=', 'obs_noqx.hc_os_solicitud_id')
            ->leftJoin('hc_os_solicitudes_acto_qx as obs_qx', 'a.hc_os_solicitud_id', '=', 'obs_qx.hc_os_solicitud_id')

            ->leftJoin('os_maestro as om', 'a.hc_os_solicitud_id', '=', 'om.hc_os_solicitud_id')
            ->leftJoin('os_ordenes_servicios as osv', 'om.orden_servicio_id', '=', 'osv.orden_servicio_id')
            ->leftJoin('departamentos as dpto', 'osv.departamento', '=', 'dpto.departamento')
            ->leftJoin('servicios as serv', 'dpto.servicio', '=', 'serv.servicio')
            
            ->leftJoin('departamentos as dpto_ev', 'e.departamento', '=', 'dpto_ev.departamento')
            ->leftJoin('servicios as serv_ev', 'dpto_ev.servicio', '=', 'serv_ev.servicio')

            ->where('a.evolucion_id', $evolucion_id);
            // ->where('a.sw_ambulatorio', '1');

        if ($servicioFilter) {
            $query->where(DB::raw("COALESCE(serv.descripcion, serv_ev.descripcion, 'SERVICIO NO DEFINIDO')"), $servicioFilter);
        }

        // Nuevo: Filtrar por IDs específicos si se envían (prioridad más alta)
        if ($idsFilter) {
            $ids = is_array($idsFilter) ? $idsFilter : explode(',', $idsFilter);
            $query->whereIn('a.hc_os_solicitud_id', $ids);
        }
        // Si no hay IDs, usar el filtro por Tipo como fallback
        elseif ($tipoFilter) {
            // Replicar la logica de agrupamiento del blade para filtrar
            $query->where(function($q) use ($tipoFilter) {
                // Caso 1: Tiene subtipo (ej. 'Apoyos Diagnosticos - IMAGENOLOGIA')
                $q->whereRaw("CONCAT(ts.descripcion, ' - ', at.descripcion) = ?", [$tipoFilter])
                  // Caso 2: Solo tipo (ej. 'Interconsultas')
                  ->orWhere('ts.descripcion', '=', $tipoFilter);
            });
        }

        $solicitudes = $query->select(
                'a.fecha_solicitud as fecha_solicitud',
                'a.cargo',
                'b.descripcion',
                'a.hc_os_solicitud_id',
                'a.cantidad',
                'pl.plan_descripcion',
                'ts.descripcion as tipo_solicitud_descripcion',
                'at.descripcion as apoyod_tipo_descripcion',
                'ts.os_tipo_solicitud_id',
                
                // Observaciones concatenadas o individuales según necesidad
                DB::raw("COALESCE(obs_apo.observacion, obs_int.observacion, obs_noqx.observacion, obs_qx.observacion, '') as observacion"),

                DB::raw("COALESCE(serv.descripcion, serv_ev.descripcion, 'AMBULATORIO') as servicio_descripcion"),
                DB::raw("COALESCE(dpto.descripcion, dpto_ev.descripcion, 'CONSULTA EXTERNA') as departamento_descripcion")
            )
            ->get();

        $numero_orden = $solicitudes->min('hc_os_solicitud_id');
        $edad = $header->fecha_nacimiento ? \Carbon\Carbon::parse($header->fecha_nacimiento)->age : '';

        // --- Diagnósticos (Todos los del ingreso) ---
        $diagnosticos = DB::table('hc_diagnosticos_ingreso as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->leftJoin('diagnosticos as b', 'a.tipo_diagnostico_id', '=', 'b.diagnostico_id')
            ->where('e.ingreso', $header->ingreso)
            ->select('a.tipo_diagnostico_id as diagnostico_id', 'b.diagnostico_nombre')
            ->distinct()
            ->get();

        $diagnostico_principal = $diagnosticos->first()
            ? $diagnosticos->first()->diagnostico_id . ' - ' . $diagnosticos->first()->diagnostico_nombre
            : '';

         // ============================
        // ✅ FIRMA PROFESIONAL Base64
        // ============================
        $firmaBase64 = $this->getFirmaBase64($header->firma);

        // --- Render Vista ---
        $html = view('orden', [
            'paciente' => $header,
            'solicitudes' => $solicitudes,
            'fecha' => $header->fecha,
            'profesional' => $header->profesional,
            'especialidad' => $header->especialidad,
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64, // ✅ NUEVO
            'edad' => $edad,
            'diagnosticos' => $diagnosticos,
            'diagnostico_principal' => $diagnostico_principal,
            'numero_orden' => $numero_orden,
            'tarjeta_profesional' => $header->tarjeta_profesional,
            'prof_id' => $header->prof_id,
            'fecha_impresion' => date('Y-m-d H:i:s')
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('ordenes_' . $evolucion_id . '.pdf');
    }


    public function generateIncapacidadPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // --- Datos del Ingreso (Empresa, IPS, Aseguradora) ---
        $ingresoData = DB::table('hc_evoluciones as e')
            ->leftJoin('cuentas as g', 'e.numerodecuenta', '=', 'g.numerodecuenta')
            ->leftJoin('empresas as em', 'g.empresa_id', '=', 'em.empresa_id')
            ->leftJoin('centros_utilidad as ct', function($join) {
                $join->on('em.empresa_id', '=', 'ct.empresa_id');
            })
            ->leftJoin('planes as h', 'g.plan_id', '=', 'h.plan_id')
            ->leftJoin('terceros as t', function($join) {
                $join->on('h.tipo_tercero_id', '=', 't.tipo_id_tercero')
                     ->on('h.tercero_id', '=', 't.tercero_id');
            })
            ->leftJoin('tipos_afiliado as ta', 'g.tipo_afiliado_id', '=', 'ta.tipo_afiliado_id')
            ->where('e.evolucion_id', $evolucion_id)
            ->select(
                'ct.codigo_prestador',
                DB::raw("COALESCE(NULLIF(t.nombre_tercero, ''), NULLIF(h.plan_descripcion, ''), '') as nombre_aseguradora"),
                'ta.tipo_afiliado_nombre',
                'g.rango'
            )
            ->first();

        // --- Empresa ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', function($join) {
                $join->on('e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
                     ->on('e.tipo_dpto_id', '=', 'm.tipo_dpto_id');
            })
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select(
                'e.razon_social',
                'e.id as nit',
                'e.digito_verificacion',
                'e.direccion',
                'e.telefonos',
                'e.website',
                'e.email',
                'm.municipio',
                'd.departamento'
            )
            ->where('e.sw_activa', '1')
            ->orderBy('e.id', 'asc')
            ->first();

        // Normalizar dirección (Colapsar espacios redundantes)
        if ($empresa && !empty($empresa->direccion)) {
            $empresa->direccion = preg_replace('/\s+/', ' ', trim($empresa->direccion));
        }

        // --- LOGO Base64 ---
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $data = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // --- Incapacidades ---
        $incapacidades = DB::table('hc_incapacidades as a')
            ->leftJoin('diagnosticos as d', 'a.diagnostico_id', '=', 'd.diagnostico_id')
            ->leftJoin('hc_tipos_incapacidad as ti', 'a.tipo_incapacidad_id', '=', 'ti.tipo_incapacidad_id')
            
            // JOINS Adicionales para el reporte detallado
            ->leftJoin('modalidad_prestacion_servicio as ms', 'a.modalidad_prestacion_servicio_id', '=', 'ms.modalidad_prestacion_servicio_id')
            ->leftJoin('hc_incapacidades_tipo_retroactiva as ir', 'a.incapacidades_tipo_retroactiva_id', '=', 'ir.incapacidades_tipo_retroactiva_id')
            ->leftJoin('uv_dependencias as dep', 'a.codigo_dependencia_id', '=', 'dep.codigo_dependencia_id')
            ->leftJoin('hc_tipos_atencion_incapacidad as v', 'a.tipo_atencion_incapacidad_id', '=', 'v.tipo_atencion_incapacidad_id')

            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.hc_incapacidad_id',
                'a.fecha_inicio',
                'a.dias_de_incapacidad',
                'a.observacion_incapacidad',
                'a.sw_prorroga',
                'd.diagnostico_nombre',
                'd.diagnostico_id as codigo_diagnostico',
                'ti.descripcion as tipo_incapacidad',
                
                // Campos adicionales requeridos en el reporte
                'dep.descripcion_dependencia',
                'ms.descripcion as desc_modalidad_servicio',
                'ir.descripcion as desc_inc_retroactiva',
                'v.descripcion as clase_atencion',
                'a.sw_licencia_maternidad',
                'a.fecha_posible_parto',
                'a.semanas_gestacion',
                'a.sw_embarazo_multiple',
                'a.num_nacidos_vivos'
            )
            ->get();

        $incapacidadNumero = optional($incapacidades->first())->hc_incapacidad_id ?? '';
        $fechaSolicitud = !empty($header->fecha)
            ? \Carbon\Carbon::parse($header->fecha)->format('d/m/Y')
            : date('d/m/Y');

        // ============================
        // ✅ FIRMA PROFESIONAL Base64
        // ============================
        $firmaBase64 = $this->getFirmaBase64($header->firma);

        // Render HTML
        $html = view('incapacidad', [
            'paciente' => $header,
            'incapacidades' => $incapacidades,
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64,
            'codigo_prestador' => $ingresoData->codigo_prestador ?? '',
            'nombre_aseguradora' => $ingresoData->nombre_aseguradora ?? '',
            'tipo_afiliado_nombre' => $ingresoData->tipo_afiliado_nombre ?? '',
            'rango' => $ingresoData->rango ?? '',
            'hc_incapacidad_id' => $incapacidadNumero,
            'fecha_solicitud' => $fechaSolicitud,
            'fecha' => !empty($header->fecha_registro) ? date('Y-m-d', strtotime($header->fecha_registro)) : date('Y-m-d'),
            'fecha_impresion' => date('Y-m-d H:i')
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('incapacidad_' . $evolucion_id . '.pdf');
    }


    public function generateRecomendacionPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // --- Empresa ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', function($join) {
                $join->on('e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
                     ->on('e.tipo_dpto_id', '=', 'm.tipo_dpto_id');
            })
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select(
                'e.razon_social',
                'e.id as nit',
                'e.digito_verificacion',
                'e.direccion',
                'e.telefonos',
                'e.website',
                'e.email',
                'm.municipio',
                'd.departamento'
            )
            ->where('e.sw_activa', '1')
            ->orderBy('e.id', 'asc')
            ->first();

        // Normalizar dirección (Colapsar espacios redundantes)
        if ($empresa && !empty($empresa->direccion)) {
            $empresa->direccion = preg_replace('/\s+/', ' ', trim($empresa->direccion));
        }

        // --- LOGO Base64 ---
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $data = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // --- Recomendaciones ---
        $recomendaciones = DB::table('hc_recomendaciones_medicas as a')
            ->join('hc_evoluciones as b', 'a.evolucion_id', '=', 'b.evolucion_id')
            ->leftJoin('tipos_atencion_recomendaciones as ta', 'a.tipo_atencion_recomendacion_id', '=', 'ta.tipo_atencion_recomendacion_id')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.evolucion_id', 
                'a.recomendaciones_adic',
                'ta.tipo_atencion_descripcion',
                'a.sw_ingreso',
                'a.sw_periodico',
                'a.sw_egreso',
                'a.sw_reubicacion'
            )
            ->get();

        // Calcular Tipo de Atención para el Header (tomando el primero si existe)
        $tipo_atencion_descripcion = '';
        if ($recomendaciones->isNotEmpty()) {
            $primera = $recomendaciones->first();
            // Prioridad: Descripción de la tabla maestra si existe
            if (!empty($primera->tipo_atencion_descripcion)) {
                 $tipo_atencion_descripcion = $primera->tipo_atencion_descripcion;
            }
        }
        $header->tipo_atencion_descripcion = $tipo_atencion_descripcion;

        foreach ($recomendaciones as $rec) {
             $detalles = DB::table('hc_recomendaciones_medicas_detalle as d')
                ->join('hc_recomendaciones_medicas_listado as l', 'd.recomendacion_id', '=', 'l.recomendacion_id')
                ->where('d.evolucion_id', $rec->evolucion_id)
                ->select('l.descripcion', 'l.recomendacion_id')
                ->get();
             $rec->detalles = $detalles;
        }

        // ============================
        // ✅ FIRMA PROFESIONAL Base64
        // ============================
        $firmaBase64 = $this->getFirmaBase64($header->firma);

        // Render HTML
        $html = view('recomendacion', [
            'header' => $header,
            'recomendaciones' => $recomendaciones,
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64,
            'edad' => $header->edad ?? '',
            'fecha_impresion' => date('Y-m-d H:i')
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('recomendaciones_' . $evolucion_id . '.pdf');
    }


    public function generateHistoryPdf($ingreso)
{
    try {
        $url = env('LEGACY_WS_URL');

        Log::info('🔍 generateHistoryPdf: Llamando a WS legacy', [
            'url' => $url,
            'ingreso' => $ingreso,
        ]);

        $resp = Http::withHeaders([
            'X-Legacy-Token' => env('LEGACY_HC_TOKEN'),
        ])->get($url, [
            'ingreso' => (int)$ingreso,
        ]);

        Log::info('🔍 Respuesta WS legacy (status)', [
            'status' => $resp->status(),
            'ok' => $resp->ok(),
        ]);

        if (!$resp->ok()) {
            Log::error('❌ WS legacy retornó error', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener HTML legacy',
                'detail'  => $resp->body(),
            ], 500);
        }

        $payload = $resp->json();
        
        Log::info('🔍 Payload del WS legacy', [
            'success' => $payload['success'] ?? false,
            'has_html' => !empty($payload['html']),
            'html_length' => strlen($payload['html'] ?? ''),
            'evolucion_id' => $payload['evolucion_id'] ?? null,
        ]);

        if (empty($payload['success']) || empty($payload['html'])) {
            Log::error('❌ Legacy no devolvió HTML válido', [
                'payload' => $payload,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Legacy no devolvió HTML válido',
                'payload' => $payload,
            ], 500);
        }

        $htmlLegacy = (string)$payload['html'];
        Log::info('✅ HTML obtenido del legacy', [
            'length' => strlen($htmlLegacy),
            'preview' => substr($htmlLegacy, 0, 200),
        ]);

        // ==========================================
        // LIMPIEZA BASE (evita about:blank / scripts)
        // ==========================================
        $htmlLegacy = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $htmlLegacy);
        $htmlLegacy = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $htmlLegacy);
        $htmlLegacy = preg_replace('#<frame\b[^>]*>.*?</frame>#is', '', $htmlLegacy);
        $htmlLegacy = str_ireplace(['href="about:blank"', "href='about:blank'"], 'href="#"', $htmlLegacy);

        // ==========================================================
        // FIX wkhtmltopdf: firmas_profesionales/ sin archivo (directorio)
        // ==========================================================
        $htmlLegacy = preg_replace(
            '/src\s*=\s*(["\'])(?:(?!\1).)*\/images\/firmas_profesionales\/\s*\1/i',
            'src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="',
            $htmlLegacy
        );

        $htmlLegacy = str_replace(
            ['images/firmas_profesionales/"', "images/firmas_profesionales/'"],
            ['images/firmas_profesionales/pixel_dummy.png"', "images/firmas_profesionales/pixel_dummy.png'"],
            $htmlLegacy
        );

        // ==========================================================
        // ✅ COMPACTACIÓN (quita espacios en blanco gigantes)
        // ==========================================================

        // 1) Quitar heights fijos en atributos HTML: height="180"
        $htmlLegacy = preg_replace('/\sheight\s*=\s*["\']?\d+["\']?/i', '', $htmlLegacy);

        // 2) Quitar height/min-height dentro de style="..."
        $htmlLegacy = preg_replace_callback('/style\s*=\s*(["\'])(.*?)\1/is', function ($m) {
            $style = $m[2];

            // elimina height / min-height
            $style = preg_replace('/\b(min-)?height\s*:\s*[^;]+;?/i', '', $style);

            // elimina padding-top/bottom muy grandes (opcional)
            $style = preg_replace('/\bpadding-(top|bottom)\s*:\s*(\d{2,}|[2-9]em|[2-9]rem)[^;]*;?/i', '', $style);

            // normaliza
            $style = trim(preg_replace('/\s+/', ' ', $style));
            return $style ? 'style="'.$style.'"' : '';
        }, $htmlLegacy);

        // 3) Reducir <br> repetidos (deja máximo 1)
        $htmlLegacy = preg_replace('/(?:<br\s*\/?>\s*){2,}/i', '<br>', $htmlLegacy);

        // 4) Reducir &nbsp; repetidos
        $htmlLegacy = preg_replace('/(&nbsp;\s*){3,}/i', '&nbsp;', $htmlLegacy);

        // 5) Eliminar filas TR completamente vacías (ojo: es agresivo, pero ayuda mucho)
        $htmlLegacy = preg_replace(
            '/<tr[^>]*>\s*(?:<td[^>]*>\s*(?:&nbsp;|\s|<br\s*\/?>)*<\/td>\s*)+<\/tr>/is',
            '',
            $htmlLegacy
        );

        // 6) Ocultar textos duplicados / footer viejo (tu limpieza anterior)
        $htmlLegacy = str_ireplace('SIIS - APLICACION DE PRUEBAS', '', $htmlLegacy);
        $htmlLegacy = str_ireplace('HISTORIA CLÍNICA', '', $htmlLegacy);
        $htmlLegacy = preg_replace('/Imprimió:.*?<\/table>/is', '', $htmlLegacy);

        // 7) (Opcional) remover imágenes rotas no data/pixel_dummy
        $htmlLegacy = preg_replace(
            '/<img(?![^>]+src=["\'](data:|.*pixel_dummy))[^>]+>/i',
            '',
            $htmlLegacy
        );

        // 8) Style hack para ocultar tablas/filas vacías
        $styleHack = '<style>
            tr:empty { display:none; }
            table:empty { display:none; }
        </style>';
        $htmlLegacy = $styleHack . $htmlLegacy;

        // ==========================
        // Datos para header/firma
        // ==========================
        $unaEvolucion = DB::table('hc_evoluciones')
            ->where('ingreso', $ingreso)
            ->orderBy('fecha', 'desc')
            ->first();

        $header = $this->getHeaderData($unaEvolucion ? $unaEvolucion->evolucion_id : null);

        // Logo base64
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $typeImg = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $imgData = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $typeImg . ';base64,' . base64_encode($imgData);
        }

        // Firma base64
        $firmaBase64 = ($header && !empty($header->firma))
            ? $this->getFirmaBase64($header->firma)
            : null;

        // Empresa
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', function($join) {
                $join->on('e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
                     ->on('e.tipo_dpto_id', '=', 'm.tipo_dpto_id');
            })
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select(
                'e.razon_social',
                'e.id as nit',
                'e.digito_verificacion',
                'e.direccion',
                'e.telefonos',
                'e.website',
                'e.email',
                'm.municipio',
                'd.departamento'
            )
            ->where('e.sw_activa', '1')
            ->orderBy('e.id', 'asc')
            ->first();

        if ($empresa && !empty($empresa->direccion)) {
            $empresa->direccion = preg_replace('/\s+/', ' ', trim($empresa->direccion));
        }

        // Base URL del legacy (para images/, css/, etc.)
        // Si existe LEGACY_IMAGES_URL, úsala, si no, usa LEGACY_URL
        // Ajuste: Si LEGACY_IMAGES_URL apunta a 'images/paint/', no queremos usarla como base general
        // porque duplicaría rutas si el HTML ya trae 'images/paint/'.
        // Mejor usamos LEGACY_URL como base raíz, y solo usamos LEGACY_IMAGES_URL para casos específicos si fuera necesario,
        // o asumimos que el usuario puso la raíz del proyecto en LEGACY_URL.
        
        // RECUPERAMOS LA LÓGICA ANTERIOR DE BASE URL (Raíz del proyecto Legacy)
        $baseUrl = rtrim(env('LEGACY_URL'), '/') . '/';

        // Log para depuración
        Log::info('PDF Legacy BaseUrl', ['baseUrl' => $baseUrl]);

        // Reemplazo adicional para asegurar rutas absolutas en estilos background: url(...)
        // wkhtmltopdf a veces ignora <base> en estilos inline
        if (!empty($htmlLegacy)) {
            $htmlLegacy = preg_replace_callback(
                '/background(?:\-image)?\s*:\s*url\s*\(([\'"]?)(.*?)\1\)/i', 
                function($matches) use ($baseUrl) {
                    $potentialUrl = trim($matches[2]);
                    
                    // Si ya es absoluta (http/https/data), no tocar
                    if (preg_match('/^(http|https|data):/i', $potentialUrl)) {
                        return $matches[0];
                    }
                    
                    // Limpiar comillas y slash inicial
                    $cleanUrl = ltrim(trim($potentialUrl, '\'"'), '/');
                    
                    // CASO ESPECIAL: Si la imagen viene de 'cache/', y tenemos una variable específica para imágenes,
                    // podríamos intentar usarla, pero generalmente cache está en la raíz o en images/paint/cache.
                    // Según el HTML proporcionado: "cache/snapshot3126.png" y "images/paint/cristalino.png".
                    
                    // Si la URL empieza con "cache/", es probable que esté dentro de images/paint/ (basado en la estructura de BioMicroscopia típica)
                    // O puede que esté en la raíz.
                    // Vemos el HTML del usuario: url('cache/snapshot3126.png') dentro de un div con url('/images/paint/cristalino.png').
                    // Si el sistema legacy guarda los snapshots en /images/paint/cache/, entonces debemos ajustar.
                    
                    // INTENTO DE CORRECCIÓN INTELIGENTE:
                    // 1. Si la URL ya contiene "images/paint", usamos la BaseURL normal (raíz).
                    // 2. Si la URL es "cache/..." y NO empieza con images/paint, probamos inyectarle el path de paint si así lo requiere la app.
                    
                    // Pero para no adivinar mal, usemos la variable de entorno LEGACY_IMAGES_URL SOLO para prefijar si la URL es corta (ej cache/)
                    // El usuario puso LEGACY_IMAGES_URL = .../images/paint/
                    
                    $legacyImagesUrl = env('LEGACY_IMAGES_URL');
                    
                    // Lógica específica para snapshots
                    if (str_starts_with($cleanUrl, 'cache/') && !empty($legacyImagesUrl)) {
                         // Si es un snapshot en cache y tenemos URL de imagenes configurada, probamos usar esa
                         $repoUrl = rtrim($legacyImagesUrl, '/') . '/';
                         // Si el usuario configuró mal la var LEGACY_IMAGES_URL con 'images/paint/' al final,
                         // y la imagen es 'cache/...', la URL resultante será .../images/paint/cache/... Correcto.
                         $finalUrl = $repoUrl . str_replace('images/paint/', '', $cleanUrl); 
                         return "background-image: url('{$finalUrl}')";
                    }

                    // Lógica para imágenes normales del template (que ya incluyen images/paint/...)
                    // Si la URL limpia empieza con 'images/paint/', usamos la base general (LEGACY_URL)
                    if (str_starts_with($cleanUrl, 'images/paint/')) {
                        $finalUrl = $baseUrl . $cleanUrl;
                         return "background-image: url('{$finalUrl}')";
                    }
                    
                    // Fallback
                    $newUrl = $baseUrl . $cleanUrl;
                    
                    return "background-image: url('{$newUrl}')";
                },
                $htmlLegacy
            );
        }

        // ==========================
        // PDF con Snappy
        // ==========================
        $pdf = app('snappy.pdf.wrapper');

        $pdf->loadView('reportes.hc_legacy', [
            'html'        => $htmlLegacy,
            'baseUrl'     => $baseUrl,
            'ingreso'     => (int)$ingreso,
            'header'      => $header,
            'firmaBase64' => $firmaBase64,
            'empresa'     => $empresa,
            'logoBase64'  => $logoBase64,
            'fecha'       => $header->fecha ?? date('Y-m-d'),
            'fecha_impresion' => date('Y-m-d H:i:s'),
        ]);

        $pdf->setOption('encoding', 'utf-8');
        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('load-error-handling', 'ignore');
        $pdf->setOption('load-media-error-handling', 'ignore');
        $pdf->setOption('disable-smart-shrinking', true);
        $pdf->setOption('no-stop-slow-scripts', true);

        $pdf->setOption('page-size', 'A4');
        $pdf->setOption('margin-top', 10);
        $pdf->setOption('margin-right', 10);
        $pdf->setOption('margin-bottom', 10);
        $pdf->setOption('margin-left', 10);

        return $pdf->inline("historia_clinica_{$ingreso}.pdf");
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error generando PDF historia legacy',
            'detail'  => $e->getMessage(),
        ], 500);
    }
}




    /**
     * Recupera el contenido dinámico de los submódulos asociados a una evolución.
     * Intenta inferir el nombre de la tabla basándose en el nombre del submódulo.
     */
    private function getSubmodulesContent($evolucion_id)
    {
        $data = [];

        // 1. Obtener el hc_modulo de la evolución
        $evoluta = DB::table('hc_evoluciones')->where('evolucion_id', $evolucion_id)->first();
        $hc_modulo = $evoluta ? $evoluta->hc_modulo : '';

        // 2. Replicar la Query del Legacy (Union entre existentes y persistentes)
        // Parte A: Submódulos guardados en esta evolución
        $queryEvoluciones = DB::table('hc_evoluciones_submodulos as A')
            ->select('A.submodulo')
            ->where('A.evolucion_id', $evolucion_id);

        // Parte B: Submódulos del sistema persistentes (sw_print_persist = '1')
        $querySystem = DB::table('system_hc_submodulos as A')
            ->join('historias_clinicas_templates as T', 'A.submodulo', '=', 'T.submodulo')
            ->select('A.submodulo')
            ->where('A.sw_print_persist', '1')
            ->where('T.hc_modulo', $hc_modulo);

        // Usamos union para combinar y traer únicos, el distinct ayuda a prevenir duplicados si existen en ambos lados
        $submodulosList = $queryEvoluciones->union($querySystem)->get();

        // Mapa de correcciones manuales para nombres de tabla que no siguen la convención estándar
        $manualMap = [
            'MotivoConsulta' => 'hc_motivo_consulta',
            'PlanTerapeuticoAmbulatorio' => 'hc_plan_terapeutico_ambulatorio', // Verificar si es esta o hc_plan_terapeutico
            'Apoyos_Diagnosticos_Solicitud' => 'hc_apoyos_diagnosticos_solicitud',
            'DiagnosticoI' => 'hc_diagnosticos_ingreso', // Generalmente escribe aquí
            'Antecedentes' => 'hc_antecedentes',
            'RevisionSistemas' => 'hc_revision_sistemas',
            'ExamenFisico' => 'hc_examen_fisico',
        ];

        // Módulos que ya son manejados por la lógica principal del reporte y no deben duplicarse en la sección genérica
        $ignoredModules = [
            'DiagnosticoI', // Ya se muestra en Diagnósticos
            'Apoyos_Diagnosticos_Solicitud', // Ya se muestra en Solicitudes
            'Formulacion_Antecedentes', // Ya se muestra en Medicamentos (formula)
            'Incapacidad' // Ya se muestra en Incapacidades
        ];

        foreach ($submodulosList as $reg) {
            $nombreSubmodulo = $reg->submodulo;

            if (in_array($nombreSubmodulo, $ignoredModules)) {
                continue;
            }

            $tableName = null;

            // 1. Intentar mapa manual
            if (isset($manualMap[$nombreSubmodulo])) {
                $tableName = $manualMap[$nombreSubmodulo];
            } else {
                // 2. Convención Snake Case: PlanTerapeutico -> hc_plan_terapeutico
                $suffix = Str::snake($nombreSubmodulo);
                $tableName = 'hc_' . $suffix;
            }

            // Validar existencia y consultar
            try {
                // CASO ESPECIAL: Motivo Consulta (Requiere Joins para nombres de diagnósticos)
                if ($nombreSubmodulo === 'MotivoConsulta' && Schema::hasTable('hc_motivo_consulta')) {
                    $registros = DB::table('hc_motivo_consulta as a')
                        ->leftJoin('diagnosticos as d1', 'a.motivo_diagnostico_id', '=', 'd1.diagnostico_id')
                        ->leftJoin('diagnosticos as d2', 'a.enfermedad_diagnostico_id', '=', 'd2.diagnostico_id')
                        ->where('a.evolucion_id', $evolucion_id)
                        ->select(
                            'a.*',
                            'd1.diagnostico_nombre as diagnostico_motivo',
                            'd2.diagnostico_nombre as diagnostico_enfermedad'
                        )
                        ->get();
                    if ($registros->count() > 0) {
                        $data[$nombreSubmodulo] = $registros;
                    }
                    continue; // Skip standard logic
                }

                // CASO ESPECIAL: Plan Terapeutico (Si requiere joins, añadir aqui. Por ahora consulta simple)
                // ...

                if ($tableName && Schema::hasTable($tableName)) {
                    $registros = DB::table($tableName)->where('evolucion_id', $evolucion_id)->get();
                    if ($registros->count() > 0) {
                        $data[$nombreSubmodulo] = $registros;
                    }
                } elseif ($tableName) {
                    // Fallback check: a veces schema cache falla o case logic. 
                    // Podemos intentar query directo con catch, pero Schema::hasTable es lo correcto en Laravel.
                    // Intentamos variante sin guiones bajos extra
                    $cleanName = 'hc_' . strtolower(str_replace('_', '', $nombreSubmodulo));
                    if (Schema::hasTable($cleanName)) {
                        $registros = DB::table($cleanName)->where('evolucion_id', $evolucion_id)->get();
                        if ($registros->count() > 0) {
                            $data[$nombreSubmodulo] = $registros;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log::error("Error buscando modulo $nombreSubmodulo: " . $e->getMessage());
                // Silenciosamente continuar
            }
        }

        return $data;
    }

    public function testLegacy($ingreso)
    {
        $svc = new \App\Services\LegacyHCReportService();
        $html = $svc->generarHistoriaCompleta($ingreso);

        return response($html);
    }

    public function pdfHistoriaLegacy(Request $request)
    {
        $ingreso = (int) $request->query('ingreso');

        // 1) Llamar al WS legacy
        $url = env('LEGACY_WS_URL');
        $resp = Http::get($url, ['ingreso' => $ingreso])->json();

        if (empty($resp['success'])) {
            abort(500, $resp['detail'] ?? 'Error generando');
        }

        $htmlLegacy = $resp['html'];

        // 2) Render Blade + PDF
        $baseUrl = env('LEGACY_URL') . '/';

        $pdf = app('snappy.pdf.wrapper');
        $pdf->loadView('reportes.hc_legacy', [
            'html' => $htmlLegacy,
            'baseUrl' => $baseUrl,
        ]);

        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('encoding', 'utf-8');

        return $pdf->download("HC_{$ingreso}.pdf");
    }

    /**
     * Guarda la respuesta de la encuesta de satisfacción por ingreso
     */
    public function storeSurvey(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !isset($user->paciente_id)) {
                return response()->json(['success' => false, 'message' => 'Usuario no identificado'], 401);
            }

            $validated = $request->validate([
                'ingreso' => 'required',
                'pregunta_1' => 'required|string',
                'pregunta_2' => 'required|string'
            ]);

            // Verificar si el ingreso ya tiene encuesta
            $exists = DB::table('hc_encuesta_satisfaccion')
                ->where('ingreso', $validated['ingreso'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => true, 
                    'message' => 'Ya se ha registrado una respuesta para este ingreso'
                ]);
            }

            // Insertar respuesta
            DB::table('hc_encuesta_satisfaccion')->insert([
                'tipo_id_paciente' => $user->tipo_documento,
                'paciente_id' => $user->paciente_id,
                'ingreso' => $validated['ingreso'],
                'pregunta_1' => $validated['pregunta_1'],
                'pregunta_2' => $validated['pregunta_2'],
                'fecha_registro' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Gracias por tu participación! Tus respuestas han sido guardadas'
            ]);

        } catch (\Exception $e) {
            Log::error('Error guardando encuesta de satisfacción: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la encuesta: ' . $e->getMessage()
            ], 500);
        }
    }
}
