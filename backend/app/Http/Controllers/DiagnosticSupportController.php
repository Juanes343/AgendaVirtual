<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use Carbon\Carbon;

class DiagnosticSupportController extends Controller
{
    /**
     * Obtiene el listado de Apoyos Diagnósticos (Exámenes Transcritos Firmados)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $pacienteId = $user->paciente_id;
        $tipoDoc = $user->tipo_documento;

        // Query principal adaptado de la solicitud del usuario
        // Seleccionamos q.* y datos de rs (resultados firmados)
        $sql = "
            SELECT 
                q.*,
                rs.resultado_id,
                rs.usuario_id_profesional,
                rs.usuario_id_profesional_autoriza,
                rs.nombre_profesional
            FROM (
                SELECT DISTINCT 
                    a.tipo_id_paciente, 
                    a.paciente_id, 
                    j.servicio, 
                    a.numero_cumplimiento, 
                    a.departamento, 
                    TO_CHAR(a.fecha_cumplimiento,'DD/MM/YYYY') as fecha_cumplimiento, 
                    l.evolucion_id, 
                    d.tipo_os_lista_id, 
                    k.descripcion as servicio_descripcion, 
                    i.descripcion, 
                    d.nombre_lista, 
                    a.numero_orden_id, 
                    i.cargo, 
                    oc.tarifario_id, 
                    oc.cargo as cargo_tari, 
                    oc.transaccion, 
                    btrim(c.primer_nombre||' '||c.segundo_nombre||' '|| c.primer_apellido||' '||c.segundo_apellido) as nombre, 
                    h.hc_os_solicitud_id, 
                    TO_CHAR(l.fecha_solicitud,'DD/MM/YYYY HH24:MI') as fecha_solicitud, 
                    TO_CHAR(om.fecha_resgistro,'DD/MM/YYYY HH24:MI') as fecha_solicitud_manual, 
                    om.profesional as nombre_profesional_manual, 
                    oc.os_maestro_cargos_id,
                    he.ingreso
                FROM 
                    view_os_cumplimientos_grupos a, 
                    pacientes c, 
                    tipos_os_listas_trabajo d, 
                    tipos_os_listas_trabajo_detalle e, 
                    os_maestro h, 
                    os_maestro_cargos oc, 
                    tarifarios_equivalencias te, 
                    cups i, 
                    hc_os_solicitudes l 
                    LEFT JOIN hc_os_solicitudes_manuales as om ON (l.hc_os_solicitud_id = om.hc_os_solicitud_id) 
                    LEFT JOIN hc_evoluciones he ON (l.evolucion_id = he.evolucion_id), 
                    os_ordenes_servicios j, 
                    servicios k 
                WHERE 
                    a.paciente_id = ? 
                    AND a.tipo_id_paciente = ? 
                    AND a.paciente_id = c.paciente_id 
                    AND a.tipo_id_paciente = c.tipo_id_paciente 
                    AND a.departamento = d.departamento 
                    AND a.cargo = i.cargo 
                    AND oc.cargo_cups = i.cargo 
                    AND d.tipo_os_lista_id = e.tipo_os_lista_id 
                    --AND e.tipo_os_lista_id IN ('7','8','9','10','11','12','17','20') 
                    AND a.numero_orden_id = h.numero_orden_id 
                    AND h.sw_estado IN ('1','2','3','4') 
                    AND h.orden_servicio_id = j.orden_servicio_id 
                    AND j.servicio = k.servicio 
                    AND h.numero_orden_id = oc.numero_orden_id 
                    AND oc.transaccion IS NOT NULL 
                    AND oc.tarifario_id = te.tarifario_id 
                    AND oc.cargo = te.cargo 
                    AND te.cargo_base = i.cargo 
                    AND i.grupo_tipo_cargo = e.grupo_tipo_cargo 
                    AND i.tipo_cargo = e.tipo_cargo 
                    AND h.hc_os_solicitud_id = l.hc_os_solicitud_id 
                    --AND a.grupo_departamento_id = 3 
            ) q 
            LEFT JOIN ( 
                SELECT 
                    r.resultado_id, 
                    r.cargo, 
                    b.numero_orden_id, 
                    b.usuario_id_profesional, 
                    b.usuario_id_profesional_autoriza, 
                    pr.nombre as nombre_profesional 
                FROM 
                    hc_resultados AS r, 
                    hc_resultados_sistema AS b 
                    LEFT JOIN profesionales_usuarios pu ON (b.usuario_id_profesional = pu.usuario_id) 
                    LEFT JOIN profesionales pr ON (pu.tipo_tercero_id = pr.tipo_id_tercero AND pu.tercero_id = pr.tercero_id) 
                WHERE 
                    r.resultado_id = b.resultado_id 
            ) rs ON (q.numero_orden_id = rs.numero_orden_id AND q.cargo = rs.cargo) 
            LEFT JOIN hc_encuesta_satisfaccion enc ON (q.ingreso = enc.ingreso)
            WHERE 
                rs.resultado_id IS NOT NULL 
                AND rs.usuario_id_profesional_autoriza IS NOT NULL 
            SELECT 
                q.*,
                rs.resultado_id,
                rs.usuario_id_profesional,
                rs.usuario_id_profesional_autoriza,
                rs.nombre_profesional,
                CASE WHEN enc.ingreso IS NOT NULL THEN 1 ELSE 0 END as encuesta_completada
            FROM (
                SELECT DISTINCT 
                    a.tipo_id_paciente, 
                    a.paciente_id, 
                    j.servicio, 
                    a.numero_cumplimiento, 
                    a.departamento, 
                    TO_CHAR(a.fecha_cumplimiento,'DD/MM/YYYY') as fecha_cumplimiento, 
                    l.evolucion_id, 
                    d.tipo_os_lista_id, 
                    k.descripcion as servicio_descripcion, 
                    i.descripcion, 
                    d.nombre_lista, 
                    a.numero_orden_id, 
                    i.cargo, 
                    oc.tarifario_id, 
                    oc.cargo as cargo_tari, 
                    oc.transaccion, 
                    btrim(c.primer_nombre||' '||c.segundo_nombre||' '|| c.primer_apellido||' '||c.segundo_apellido) as nombre, 
                    h.hc_os_solicitud_id, 
                    TO_CHAR(l.fecha_solicitud,'DD/MM/YYYY HH24:MI') as fecha_solicitud, 
                    TO_CHAR(om.fecha_resgistro,'DD/MM/YYYY HH24:MI') as fecha_solicitud_manual, 
                    om.profesional as nombre_profesional_manual, 
                    oc.os_maestro_cargos_id,
                    he.ingreso
                FROM 
                    view_os_cumplimientos_grupos a, 
                    pacientes c, 
                    tipos_os_listas_trabajo d, 
                    tipos_os_listas_trabajo_detalle e, 
                    os_maestro h, 
                    os_maestro_cargos oc, 
                    tarifarios_equivalencias te, 
                    cups i, 
                    hc_os_solicitudes l 
                    LEFT JOIN hc_os_solicitudes_manuales as om ON (l.hc_os_solicitud_id = om.hc_os_solicitud_id) 
                    LEFT JOIN hc_evoluciones he ON (l.evolucion_id = he.evolucion_id), 
                    os_ordenes_servicios j, 
                    servicios k 
                WHERE 
                    a.paciente_id = ? 
                    AND a.tipo_id_paciente = ? 
                    AND a.paciente_id = c.paciente_id 
                    AND a.tipo_id_paciente = c.tipo_id_paciente 
                    AND a.departamento = d.departamento 
                    AND a.cargo = i.cargo 
                    AND oc.cargo_cups = i.cargo 
                    AND d.tipo_os_lista_id = e.tipo_os_lista_id 
                    --AND e.tipo_os_lista_id IN ('7','8','9','10','11','12','17','20') 
                    AND a.numero_orden_id = h.numero_orden_id 
                    AND h.sw_estado IN ('1','2','3','4') 
                    AND h.orden_servicio_id = j.orden_servicio_id 
                    AND j.servicio = k.servicio 
                    AND h.numero_orden_id = oc.numero_orden_id 
                    AND oc.transaccion IS NOT NULL 
                    AND oc.tarifario_id = te.tarifario_id 
                    AND oc.cargo = te.cargo 
                    AND te.cargo_base = i.cargo 
                    AND i.grupo_tipo_cargo = e.grupo_tipo_cargo 
                    AND i.tipo_cargo = e.tipo_cargo 
                    AND h.hc_os_solicitud_id = l.hc_os_solicitud_id 
                    --AND a.grupo_departamento_id = 3 
            ) q 
            LEFT JOIN ( 
                SELECT 
                    r.resultado_id, 
                    r.cargo, 
                    b.numero_orden_id, 
                    b.usuario_id_profesional, 
                    b.usuario_id_profesional_autoriza, 
                    pr.nombre as nombre_profesional 
                FROM 
                    hc_resultados AS r, 
                    hc_resultados_sistema AS b 
                    LEFT JOIN profesionales_usuarios pu ON (b.usuario_id_profesional = pu.usuario_id) 
                    LEFT JOIN profesionales pr ON (pu.tipo_tercero_id = pr.tipo_id_tercero AND pu.tercero_id = pr.tercero_id) 
                WHERE 
                    r.resultado_id = b.resultado_id 
            ) rs ON (q.numero_orden_id = rs.numero_orden_id AND q.cargo = rs.cargo) 
            LEFT JOIN hc_encuesta_satisfaccion enc ON (q.ingreso = enc.ingreso)
            WHERE 
                rs.resultado_id IS NOT NULL 
                AND rs.usuario_id_profesional_autoriza IS NOT NULL 
            ORDER BY 
                q.fecha_cumplimiento DESC
        ";

        try {
                $results = DB::select($sql, [$pacienteId, $tipoDoc]);

                // Agregar nombre_archivo_carpeta a cada resultado
                foreach ($results as &$row) {
                    $archivo = DB::selectOne(
                        "SELECT nombre_archivo_carpeta FROM hc_apoyod_resultados_subirarchivo WHERE resultado_id = ?",
                        [$row->resultado_id]
                    );
                    $row->nombre_archivo_carpeta = $archivo ? $archivo->nombre_archivo_carpeta : null;
                }
                unset($row);

                return response()->json([
                    'success' => true,
                    'data' => $results
                ]);
        } catch (\Exception $e) {
            Log::error("Error en DiagnosticSupportController@index: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error consultando Apoyos Diagnósticos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generatePdf(Request $request, $resultado_id)
    {
        try {
            $data = $this->getDiagnosticData($resultado_id, $request);
            if (!$data) {
                return response()->json(['error' => 'Result not found'], 404);
            }

            $pdf = PDF::loadView('pdf.diagnostic_support', $data);
            $pdf->setOption('enable-local-file-access', true);
            $pdf->setOption('load-error-handling', 'ignore');
            $pdf->setOption('disable-smart-shrinking', true);
            
            return $pdf->stream('resultado_apoyo_'.$resultado_id.'.pdf');
        } catch (\Exception $e) {
            Log::error("Error generating Diagnostic PDF: " . $e->getMessage());
            return response()->json(['error' => 'Error generating PDF'], 500);
        }
    }

    public function sendEmail(Request $request, $resultado_id)
    {
        try {
            $diagnosticData = $this->getDiagnosticData($resultado_id, $request);
            if (!$diagnosticData) {
                return response()->json(['success' => false, 'message' => 'Examen no encontrado'], 404);
            }

            // Obtener datos resumidos para el correo
            $mainInfo = $diagnosticData['header'];
            $paciente = DB::table('pacientes')
                ->where('paciente_id', $mainInfo->paciente_id)
                ->where('tipo_id_paciente', $mainInfo->tipo_id_paciente)
                ->first();

            if (!$paciente || empty($paciente->email)) {
                return response()->json(['success' => false, 'message' => 'El paciente no tiene un correo electrónico registrado'], 422);
            }

            // Generar PDF del Resultado
            $pdf = PDF::loadView('pdf.diagnostic_support', $diagnosticData);
            $pdf->setOption('enable-local-file-access', true);
            $pdf->setOption('load-error-handling', 'ignore');
            $pdf->setOption('disable-smart-shrinking', true);
            $pdfContent = $pdf->output();

            // Verificar si hay archivo adjunto (Ver Detalle)
            $extraAttachment = DB::selectOne(
                "SELECT nombre_archivo_carpeta FROM hc_apoyod_resultados_subirarchivo WHERE resultado_id = ?",
                [$resultado_id]
            );

            $mailData = [
                'paciente_nombre' => $diagnosticData['header']->nombre,
                'examen_nombre'   => $diagnosticData['header']->titulo,
                'fecha_examen'    => $diagnosticData['header']->fecha_cumplimiento,
                'numero_orden'    => $diagnosticData['header']->numero_orden_id,
                'has_attachment'  => !empty($extraAttachment->nombre_archivo_carpeta)
            ];

            Mail::send('emails.diagnostic_result', $mailData, function($message) use ($paciente, $pdfContent, $resultado_id, $extraAttachment) {
                $message->to($paciente->email)
                        ->subject('Resultado de Apoyo Diagnóstico - SanDi•Med')
                        ->attachData($pdfContent, 'resultado_examen_'.$resultado_id.'.pdf', [
                            'mime' => 'application/pdf',
                        ]);

                // Adjuntar archivo extra si existe
                if ($extraAttachment && !empty($extraAttachment->nombre_archivo_carpeta)) {
                    $basePath = env('LEGACY_PATH');
                    $filePath = $basePath . '/' . $extraAttachment->nombre_archivo_carpeta;
                    
                    if (file_exists($filePath)) {
                        $message->attach($filePath);
                    } else {
                        Log::warning("Archivo extra no encontrado para adjuntar al correo: " . $filePath);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Resultado enviado correctamente al correo: ' . $paciente->email
            ]);

        } catch (\Exception $e) {
            Log::error("Error enviando correo de Apoyo Diagnóstico: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error al intentar enviar el correo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getDiagnosticData($resultado_id, $request)
    {
        // 1. QUERY PRINCIPAL (Encabezado)
        $sqlMain = "
            SELECT  a.*,
                    f.razon_social as laboratorio,
                    f.tipo_id_tercero,
                    f.id,
                    f.empresa_id,
                    CASE WHEN (g.titulo_examen = '' or g.titulo_examen IS NULL)
                    THEN       h.descripcion
                    ELSE       g.titulo_examen END as titulo,
                    g.informacion,
                    btrim(n.primer_nombre||' '||n.segundo_nombre||' '|| n.primer_apellido||' '||n.segundo_apellido,'') as nombre,
                    n.sexo_id as sexo_paciente,
                    n.fecha_nacimiento,
                    l.nombre_tercero,
                    m.tarjeta_profesional,
                    m.firma,
                    r.descripcion as profesional_descripcion,
                    p.plan_descripcion,
                    pr.rango,
                    afiliacion.eps_punto_atencion_id,
                    afiliacion.eps_punto_atencion_nombre,
                    pe.especialidad,
                    es.descripcion AS especialidad_descripcion
            FROM    (
                    SELECT  a.resultado_id,
                            a.paciente_id,
                            a.tipo_id_paciente,
                            a.cargo,
                            a.tecnica_id,
                            a.fecha_realizado,
                            a.usuario_id,
                            a.observacion_prestacion_servicio,
                            a.concepto_resultado_examen_id,
                            b.numero_orden_id,
                            b.usuario_id_profesional_autoriza,
                            b.usuario_id_profesional,
                            c.orden_servicio_id,
                            d.departamento,
                            TO_CHAR(OC.fecha_cumplimiento,'DD/MM/YYYY') AS fecha_cumplimiento
                    FROM    hc_resultados as a,
                            hc_resultados_sistema as b,
                            os_maestro as c
                            LEFT JOIN os_cumplimientos_detalle OC
                            ON (OC.numero_orden_id = c.numero_orden_id) ,
                            os_internas as d
                    WHERE   a.resultado_id = ?
                    AND     b.resultado_id = a.resultado_id
                    AND     c.numero_orden_id = b.numero_orden_id
                    AND     d.numero_orden_id = c.numero_orden_id
                    ) AS a
                    LEFT JOIN
                    (
                    SELECT  ea.afiliado_tipo_id,
                            ea.afiliado_id,
                            ep.eps_punto_atencion_id,
                            ep.eps_punto_atencion_nombre
                    FROM    eps_afiliados ea,
                            eps_puntos_atencion ep
                    WHERE   ea.eps_punto_atencion_id = ep.eps_punto_atencion_id
                    ) AS afiliacion
                    ON (afiliacion.afiliado_tipo_id = a.tipo_id_paciente AND afiliacion.afiliado_id = a.paciente_id)
                    LEFT JOIN  profesionales_usuarios as k on (k.usuario_id = a.usuario_id_profesional)
                    LEFT JOIN  profesionales m ON (m.tipo_id_tercero = k.tipo_tercero_id AND     m.tercero_id = k.tercero_id )
                    LEFT JOIN  terceros as l ON (l.tipo_id_tercero = m.tipo_id_tercero  AND     l.tercero_id = m.tercero_id )
                    LEFT JOIN  tipos_profesionales r ON (r.tipo_profesional = m.tipo_profesional)
                    LEFT JOIN profesionales_especialidades pe ON (pe.tipo_id_tercero = m.tipo_id_tercero AND pe.tercero_id = k.tercero_id )
                    LEFT JOIN especialidades es ON (es.especialidad = pe.especialidad ) ,
                    departamentos as e,
                    empresas as f,
                    apoyod_cargos as g,
                    cups as h,
                    pacientes n,
                    os_ordenes_servicios o,
                    planes p,
                    planes_rangos pr
            WHERE   e.departamento = a.departamento
            AND     f.empresa_id = e.empresa_id
            AND     g.cargo = a.cargo
            AND     h.cargo = a.cargo
            AND     o.orden_servicio_id = a.orden_servicio_id
            AND     p.plan_id = o.plan_id
            AND     pr.plan_id = o.plan_id
            AND     pr.rango = o.rango
            AND     pr.tipo_afiliado_id = o.tipo_afiliado_id
            AND     n.paciente_id = a.paciente_id
            AND     n.tipo_id_paciente = a.tipo_id_paciente
        ";

        $mainInfo = DB::selectOne($sqlMain, [$resultado_id]);

        if (!$mainInfo) {
             return null;
        }

         // Edad
         $dob = Carbon::parse($mainInfo->fecha_nacimiento);
         $age = $dob->diff(Carbon::now())->format('%y Años %m Meses %d Días');
         $mainInfo->edad_paciente = $age;


        // 2. QUERY DETALLES
        $sqlDetails = "
            SELECT DISTINCT a.lab_examen_id,
                    a.resultado_id,
                    a.cargo,
                    a.tecnica_id,
                    a.resultado,
                    a.sw_alerta,
                    a.rango_min,
                    a.rango_max,
                    a.unidades,
                    b.lab_plantilla_id,
                    b.nombre_examen,
                    a.normalidades,
                    NULL as opcion_id_lab_plantilla,
                    NULL as sw_tipo,
                    NULL as numero_lab_plantilla5_lista
            FROM   hc_apoyod_resultados_detalles a,
                    hc_resultados HR,
                    lab_examenes b
            WHERE  HR.resultado_id = ?
            AND    HR.resultado_id = a.resultado_id
            AND    HR.cargo = ?
            AND    a.tecnica_id = ?
            AND    a.tecnica_id = b.tecnica_id
            AND    a.cargo = b.cargo
            AND    a.lab_examen_id = b.lab_examen_id
            ORDER BY b.lab_plantilla_id
        ";

        $details = DB::select($sqlDetails, [$resultado_id, $mainInfo->cargo, $mainInfo->tecnica_id]);

        if (empty($details)) {
             $sqlDetails5 = "
                SELECT   DISTINCT
                    a.lab_examen_id, a.resultado_id, a.cargo, a.tecnica_id,
                    a.resultado, a.sw_alerta, a.rango_min, a.rango_max, a.unidades,
                    b.lab_plantilla_id, b.nombre_examen,
                    a.numero_lab_plantilla, a.opcion_id_lab_plantilla, a.numero_lab_plantilla5_lista, 
                    a.sw_tipo as sw_tipo,
                    a.normalidades
                FROM     hc_apoyod_resultados_detalles_plantilla_cinco a, lab_examenes b
                WHERE    a.resultado_id = ? AND a.cargo = ? AND
                        a.tecnica_id = ? AND a.tecnica_id = b.tecnica_id AND
                        a.cargo = b.cargo AND a.lab_examen_id = b.lab_examen_id
                ORDER BY a.lab_examen_id, a.opcion_id_lab_plantilla ASC
             ";
             $details = DB::select($sqlDetails5, [$resultado_id, $mainInfo->cargo, $mainInfo->tecnica_id]);
        }
        
        // Procesar detalles tipo 5
        foreach ($details as &$detail) {
             $detail->nombre_opcion = '';
             $detail->valor_lista = '';
             
             if ($detail->lab_plantilla_id == '5') {
                 $optionInfo = DB::selectOne("
                    SELECT descripcion, rango, unidades
                    FROM opciones_lab_plantilla5
                    WHERE opcion_id_lab_plantilla = ?
                 ", [$detail->opcion_id_lab_plantilla]);
                 
                 if ($optionInfo) {
                     $detail->nombre_opcion = $optionInfo->descripcion;
                     if (!$detail->unidades) $detail->unidades = $optionInfo->unidades;
                 }
                 
                  if (($detail->sw_tipo == 2 || $detail->sw_tipo == 3) && $detail->numero_lab_plantilla5_lista) {
                        $listVal = DB::selectOne("SELECT opcion FROM lab_plantilla5_lista WHERE numero_lab_plantilla5_lista = ?", [$detail->numero_lab_plantilla5_lista]);
                        $detail->valor_lista = $listVal ? $listVal->opcion : '';
                  }
             }
        }
        unset($detail);

        // 3. DATOS ADICIONALES (Orden)
        $sqlAdditional = "
            SELECT d.*, g.descripcion as servicio, e.historia_prefijo, e.historia_numero
            FROM os_maestro f
            JOIN os_ordenes_servicios c ON f.orden_servicio_id = c.orden_servicio_id
            LEFT JOIN hc_os_solicitudes_manuales_datos_adicionales d ON c.orden_servicio_id = d.orden_servicio_id
            JOIN hc_os_solicitudes a ON f.hc_os_solicitud_id = a.hc_os_solicitud_id
            LEFT JOIN hc_os_solicitudes_manuales b ON a.hc_os_solicitud_id = b.hc_os_solicitud_id
            JOIN historias_clinicas e ON c.tipo_id_paciente = e.tipo_id_paciente AND c.paciente_id = e.paciente_id
            JOIN servicios g ON c.servicio = g.servicio
            WHERE f.numero_orden_id = ?
        ";
        $additionalData = DB::selectOne($sqlAdditional, [$mainInfo->numero_orden_id]);
        
        // 4. OBSERVACIONES
        $sqlObs = "
            SELECT  a.observacion_adicional,
                    a.fecha_registro_observacion,
                    c.nombre_tercero as usuario_observacion
            FROM    hc_resultados_observaciones_adicionales as a
            JOIN    profesionales_usuarios as b ON a.usuario_id = b.usuario_id
            JOIN    terceros as c ON b.tipo_tercero_id = c.tipo_id_tercero AND b.tercero_id = c.tercero_id
            WHERE   resultado_id = ?
            ORDER BY a.observacion_resultado_id
        ";
        $observations = DB::select($sqlObs, [$resultado_id]);
        
        // 5. REVIEWER (Firma Autoriza)
        $reviewer = null;
        if ($mainInfo->usuario_id_profesional_autoriza && $mainInfo->usuario_id_profesional != $mainInfo->usuario_id_profesional_autoriza) {
             $reviewSql = "
                SELECT a.nombre, b.tarjeta_profesional, es.descripcion AS especialidad_descripcion, m.firma
                FROM system_usuarios a
                LEFT JOIN profesionales_usuarios k ON k.usuario_id = a.usuario_id
                LEFT JOIN profesionales m ON m.tipo_id_tercero = k.tipo_tercero_id AND m.tercero_id = k.tercero_id
                LEFT JOIN profesionales b ON b.tipo_id_tercero = k.tipo_tercero_id AND b.tercero_id = k.tercero_id
                LEFT JOIN profesionales_especialidades pe ON pe.tipo_id_tercero = m.tipo_id_tercero AND pe.tercero_id = m.tercero_id
                LEFT JOIN especialidades es ON es.especialidad = pe.especialidad
                WHERE a.usuario_id = ?
             ";
             $reviewer = DB::selectOne($reviewSql, [$mainInfo->usuario_id_profesional_autoriza]);
        }

        // 6. CONCEPTO
        $concepto = null;
        if ($mainInfo->concepto_resultado_examen_id) {
            $conceptoSql = "SELECT descripcion_concepto_resultado FROM conceptos_resultados_examenes WHERE concepto_resultado_examen_id = ?";
            $concepto = DB::selectOne($conceptoSql, [$mainInfo->concepto_resultado_examen_id]);
        }

            // 7. DATOS EMPRESA Y LOGOS (Base64)
            $empresa = null;
            if (isset($mainInfo->empresa_id)) {
                $empresa = DB::table('empresas')->where('empresa_id', $mainInfo->empresa_id)->first();
            }

            $logoPath = public_path('assets/images/simde_logo.png');
            $logoBase64 = null;
            if (file_exists($logoPath)) {
                $type = pathinfo($logoPath, PATHINFO_EXTENSION);
                $imgData = file_get_contents($logoPath);
                $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
            }

            $firmaBase64 = null;
            if (!empty($mainInfo->firma)) {
                $fileFirmaEncoded = str_replace('*', '%2A', $mainInfo->firma);
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
                        $imgResp = \Illuminate\Support\Facades\Http::timeout(5)->get($firmaUrl);
                        if ($imgResp->ok()) {
                            $cType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                            $firmaBase64 = 'data:' . $cType . ';base64,' . base64_encode($imgResp->body());
                        }
                    } catch (\Throwable $eF) { Log::error("Error firma: " . $eF->getMessage()); }
                }
            }

            $firmaRevisorBase64 = null;
            if ($reviewer && !empty($reviewer->firma)) {
                $fileFirmaEncoded = str_replace('*', '%2A', $reviewer->firma);
                // 1) FILESYSTEM
                $basePath = env('LEGACY_PATH');
                $firmaPath = $basePath . '/images/firmas_profesionales/' . $fileFirmaEncoded;

                if (file_exists($firmaPath)) {
                    $ext = strtolower(pathinfo($firmaPath, PATHINFO_EXTENSION));
                    $mime = in_array($ext, ['jpg', 'jpeg', 'png']) ? $ext : 'jpeg';
                    $firmaRevisorBase64 = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($firmaPath));
                } else {
                    // 2) URL
                    $baseUrl = env('LEGACY_URL');
                    $firmaUrl = $baseUrl . '/images/firmas_profesionales/' . $fileFirmaEncoded;
                    try {
                        $imgResp = \Illuminate\Support\Facades\Http::timeout(5)->get($firmaUrl);
                        if ($imgResp->ok()) {
                            $cType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                            $firmaRevisorBase64 = 'data:' . $cType . ';base64,' . base64_encode($imgResp->body());
                        }
                    } catch (\Throwable $eF) { Log::error("Error firma revisor: " . $eF->getMessage()); }
                }
            }
    
            return [
                'header' => $mainInfo,
                'details' => $details,
                'additional' => $additionalData,
                'observations' => $observations,
                'reviewer' => $reviewer,
                'concepto' => $concepto,
                'empresa' => $empresa,
                'logoBase64' => $logoBase64,
                'firmaBase64' => $firmaBase64,
                'firmaRevisorBase64' => $firmaRevisorBase64,
                'current_user' => ($request->user() && $request->user()->paciente_id) ? 'Paciente' : 'Usuario Sistema', 
                'print_date' => Carbon::now()->format('Y-m-d H:i')
            ];
    }
}