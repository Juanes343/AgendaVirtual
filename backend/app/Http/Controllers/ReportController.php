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
            ->select(
                'a.ingreso',
                DB::raw("MAX(DATE(a.fecha)) as fecha"), // Solo fecha
                DB::raw("MAX(d.nombre) as profesional_nombre"),
                DB::raw("MAX(cups.descripcion) as servicio"),
                'b.estado',
                DB::raw("MAX(tc.tipo) as tipo_consulta_id") // Se asume nombre de columna 'tipo'
            )
            ->where('b.paciente_id', $pacienteId)
            ->where('b.tipo_id_paciente', $tipoDoc)
            // Filtro: Solo mostrar ingresos que tengan Medicamentos, Solicitudes o Incapacidades
            ->where(function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hc_medicamentos_recetados_amb as med')
                        ->join('hc_evoluciones as evo_m', 'med.evolucion_id', '=', 'evo_m.evolucion_id')
                        ->whereColumn('evo_m.ingreso', 'b.ingreso');
                })
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('hc_os_solicitudes as sol')
                            ->join('hc_evoluciones as evo_s', 'sol.evolucion_id', '=', 'evo_s.evolucion_id')
                            ->whereColumn('evo_s.ingreso', 'b.ingreso');
                    })
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('hc_incapacidades as inc')
                            ->join('hc_evoluciones as evo_i', 'inc.evolucion_id', '=', 'evo_i.evolucion_id')
                            ->whereColumn('evo_i.ingreso', 'b.ingreso');
                    });
            })
            ->groupBy('a.ingreso', 'b.estado')
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

        
            // 2. Solicitudes / Ordenes
        $solicitudes = DB::table('hc_os_solicitudes as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->where('e.ingreso', $ingreso)
            ->select(
                'e.evolucion_id',
                // 'a.fecha_solicitud as fecha_solicitud',
                DB::raw("DATE(a.fecha_solicitud) as fecha_solicitud"),
                'a.cargo as cargo',
                'a.cargo as codigo', // Alias adicional para compatibilidad vistas
                'b.descripcion as descripcion',
                'b.descripcion as nombre_examen', // Alias adicional para compatibilidad vistas
                'a.hc_os_solicitud_id',
                'a.cantidad',
                DB::raw("'' as observacion")
            )
            ->get();

        // 3. Incapacidades
        $incapacidades = DB::table('hc_incapacidades as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('diagnosticos as d', 'a.diagnostico_id', '=', 'd.diagnostico_id')
            ->leftJoin('hc_tipos_incapacidad as ti', 'a.tipo_incapacidad_id', '=', 'ti.tipo_incapacidad_id')
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
                'd.diagnostico_id as codigo_diagnostico'
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

        return response()->json([
            'success' => true,
            'data' => [
                'medicamentos' => $medicamentos,
                'solicitudes' => $solicitudes,
                'incapacidades' => $incapacidades,
                'diagnosticos' => $diagnosticos,
                'notas' => $notas
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
            ->leftJoin('tipo_mpios as m', 'e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select('e.razon_social', 'e.id as nit', 'e.digito_verificacion', 'e.direccion', 'e.telefonos', 'e.website', 'e.email', 'm.municipio', 'd.departamento')
            ->where('e.sw_activa', '1')
            ->first();

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

        try {
            $pdfContentCompleto = null;
            $pdfContentFormula = null;
            $pdfContentOrden = null;

            // 3. Generar PDF consolidado en memoria (Solo si type == 'all')
            if ($type === 'all') {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->set_option('isRemoteEnabled', true);

                $html = view('reportes.hc_legacy', [ // CAMBIADO de 'reporte_completo' a 'reportes.hc_legacy'
                    'header' => $header,
                    'paciente' => $header,
                    'medicamentos' => $data->medicamentos,
                    'solicitudes' => $data->solicitudes,
                    'incapacidades' => $data->incapacidades,
                    'diagnosticos' => $data->diagnosticos ?? [],
                    'notas' => $data->notas ?? [],
                    'fecha' => $header->fecha,
                    'profesional' => $header->profesional,
                    'especialidad' => $header->especialidad,
                    'ingreso' => $ingreso,
                    'empresa' => $empresa, // Pasar empresa por si la vista lo requiere
                    'logoBase64' => $logoBase64,
                    'baseUrl' => config('app.url') // Agregamos baseUrl para la vista legacy
                ])->render();

                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdfContentCompleto = $dompdf->output();
            }

            // 3.2 Generar PDF Formula (Si hay medicamentos y corresponde el tipo)
            if (($type === 'all' || $type === 'formula') && !empty($data->medicamentos) && count($data->medicamentos) > 0) {
                $dompdfF = new \Dompdf\Dompdf();
                $dompdfF->set_option('isRemoteEnabled', true);
                $dompdfF->loadHtml(view('formula', [
                    'header' => $header,
                    'empresa' => $empresa ?? null,
                    'logoBase64' => $logoBase64 ?? null,
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
                    'edad' => isset($header->fecha_nacimiento) ? \Carbon\Carbon::parse($header->fecha_nacimiento)->age : '',
                    'numero_orden' => $numero_orden,
                    'fecha_impresion' => date('Y-m-d H:i:s')
                ])->render());
                $dompdfO->setPaper('A4', 'portrait');
                $dompdfO->render();
                $pdfContentOrden = $dompdfO->output();
            }

            // 4. Enviar Correo con Adjunto
            Mail::send('emails.medical_history_report_v2', [
                'nombre' => $header->nombre_completo,
                'fecha' => $header->fecha,
                'ingreso' => $ingreso,
                'profesional' => $header->profesional
            ], function ($message) use ($paciente, $ingreso, $pdfContentCompleto, $pdfContentFormula, $pdfContentOrden, $type) {
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

                DB::raw("DATE(a.fecha) as fecha"),
                'a.evolucion_id',
                'b.ingreso',

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


    public function generateFormulaPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // --- Empresa ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', 'e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
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
            ->first();

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

        // Diagnósticos
        $diagnosticos = DB::table('hc_diagnosticos_ingreso as a')
            ->join('diagnosticos as b', 'a.tipo_diagnostico_id', '=', 'b.diagnostico_id')
            ->where('a.evolucion_id', $evolucion_id)
            ->select('a.tipo_diagnostico_id as diagnostico_id', 'b.diagnostico_nombre')
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
        $firmaBase64 = null;

        if (!empty($header->firma)) {
            // En BD: "CC*7458529.jpg"
            // En disco/url suele estar: "CC%2A7458529.jpg"
            $fileFirmaEncoded = str_replace('*', '%2A', $header->firma);

            // 1) Intentar por FILESYSTEM (si backend comparte disco con legacy)
            $firmaPath = '/var/www/html/php74/PRUEBAS_SANDIEGO_RIPS/images/firmas_profesionales/' . $fileFirmaEncoded;

            if (file_exists($firmaPath)) {
                $ext = strtolower(pathinfo($firmaPath, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg', 'jpeg', 'png']) ? $ext : 'jpeg';
                $firmaBase64 = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($firmaPath));
            } else {
                // 2) Fallback por URL (si NO comparten disco)
                $firmaUrl = 'https://devel74.simde.com.co/PRUEBAS_SANDIEGO_RIPS/images/firmas_profesionales/' . $fileFirmaEncoded;

                try {
                    $imgResp = Http::timeout(8)->get($firmaUrl);
                    if ($imgResp->ok()) {
                        $contentType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                        $firmaBase64 = 'data:' . $contentType . ';base64,' . base64_encode($imgResp->body());
                    }
                } catch (\Throwable $e) {
                    // si falla, queda null
                }
            }
        }

        // Render HTML
        $html = view('formula', [
            'header' => $header,
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64, // ✅ NUEVO
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


    public function generateOrderPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // --- Empresa ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', 'e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
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
            ->first();

        // --- LOGO Base64 ---
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $data = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // --- Solicitudes ---
        $solicitudes = DB::table('hc_os_solicitudes as a')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.fecha_solicitud as fecha_solicitud',
                'a.cargo',
                'b.descripcion',
                'a.hc_os_solicitud_id',
                'a.cantidad'
            )
            ->get();

        $numero_orden = $solicitudes->min('hc_os_solicitud_id');
        $edad = $header->fecha_nacimiento ? \Carbon\Carbon::parse($header->fecha_nacimiento)->age : '';

        // --- Diagnósticos ---
        $diagnosticos = DB::table('hc_diagnosticos_ingreso as a')
            ->join('diagnosticos as b', 'a.tipo_diagnostico_id', '=', 'b.diagnostico_id')
            ->where('a.evolucion_id', $evolucion_id)
            ->select('a.tipo_diagnostico_id as diagnostico_id', 'b.diagnostico_nombre')
            ->get();

        $diagnostico_principal = $diagnosticos->first()
            ? $diagnosticos->first()->diagnostico_id . ' - ' . $diagnosticos->first()->diagnostico_nombre
            : '';

        // ============================
        // ✅ FIRMA PROFESIONAL Base64
        // ============================
        $firmaBase64 = null;

        if (!empty($header->firma)) {
            // En BD: "CC*7458529.jpg"
            // En disco suele estar: "CC%2A7458529.jpg"
            $fileFirmaEncoded = str_replace('*', '%2A', $header->firma);

            // 1) Intentar por FILESYSTEM (si backend comparte disco con legacy)
            $firmaPath = '/var/www/html/php74/PRUEBAS_SANDIEGO_RIPS/images/firmas_profesionales/' . $fileFirmaEncoded;

            if (file_exists($firmaPath)) {
                $ext = strtolower(pathinfo($firmaPath, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg', 'jpeg', 'png']) ? $ext : 'jpeg';
                $firmaBase64 = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($firmaPath));
            } else {
                // 2) Fallback por URL (si NO comparten disco)
                $firmaUrl = 'https://devel74.simde.com.co/PRUEBAS_SANDIEGO_RIPS/images/firmas_profesionales/' . $fileFirmaEncoded;

                try {
                    $imgResp = Http::timeout(8)->get($firmaUrl);
                    if ($imgResp->ok()) {
                        $contentType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                        $firmaBase64 = 'data:' . $contentType . ';base64,' . base64_encode($imgResp->body());
                    }
                } catch (\Throwable $e) {
                    // si falla, se queda null
                }
            }
        }

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

        // --- Empresa ---
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', 'e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
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
            ->first();

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
            ->join('diagnosticos as d', 'a.diagnostico_id', '=', 'd.diagnostico_id')
            ->leftJoin('hc_tipos_incapacidad as ti', 'a.tipo_incapacidad_id', '=', 'ti.tipo_incapacidad_id')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.fecha_inicio',
                'a.dias_de_incapacidad',
                'a.observacion_incapacidad',
                'a.sw_prorroga',
                'd.diagnostico_nombre',
                'd.diagnostico_id as codigo_diagnostico',
                'ti.descripcion as tipo_incapacidad'
            )
            ->get();

        // ============================
        // ✅ FIRMA PROFESIONAL Base64
        // ============================
        $firmaBase64 = null;

        // IMPORTANTE: esto requiere que en getHeaderData selecciones d.firma
        if (!empty($header->firma)) {
            $fileFirmaEncoded = str_replace('*', '%2A', $header->firma);

            // 1) Intentar por FILESYSTEM (si backend comparte disco con legacy)
            $firmaPath = '/var/www/html/php74/PRUEBAS_SANDIEGO_RIPS/images/firmas_profesionales/' . $fileFirmaEncoded;

            if (file_exists($firmaPath)) {
                $ext = strtolower(pathinfo($firmaPath, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg', 'jpeg', 'png']) ? $ext : 'jpeg';
                $firmaBase64 = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($firmaPath));
            } else {
                // 2) Fallback por URL (si NO comparten disco)
                $firmaUrl = 'https://devel74.simde.com.co/PRUEBAS_SANDIEGO_RIPS/images/firmas_profesionales/' . $fileFirmaEncoded;

                try {
                    $imgResp = Http::timeout(8)->get($firmaUrl);
                    if ($imgResp->ok()) {
                        $contentType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                        $firmaBase64 = 'data:' . $contentType . ';base64,' . base64_encode($imgResp->body());
                    }
                } catch (\Throwable $e) {
                    // si falla, queda null
                }
            }
        }

        // Render HTML
        $html = view('incapacidad', [
            'paciente' => $header,
            'incapacidades' => $incapacidades,
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64, // ✅ NUEVO
            'fecha' => date('Y-m-d'),
            'fecha_impresion' => date('Y-m-d H:i')
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('incapacidad_' . $evolucion_id . '.pdf');
    }


    public function generateHistoryPdf($ingreso)
    {
        try {
            $url = "https://devel74.simde.com.co/PRUEBAS_SANDIEGO_RIPS/programas/ws/hc_reporte_legacy.php";

            $resp = Http::withHeaders([
                'X-Legacy-Token' => env('LEGACY_HC_TOKEN'),
            ])->get($url, [
                'ingreso' => (int)$ingreso,
            ]);

            if (!$resp->ok()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener HTML legacy',
                    'detail'  => $resp->body(),
                ], 500);
            }

            $payload = $resp->json();
            if (empty($payload['success']) || empty($payload['html'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Legacy no devolvió HTML válido',
                    'payload' => $payload,
                ], 500);
            }

            $htmlLegacy = (string)$payload['html'];

            // ---- LIMPIEZA (evita about:blank y cosas que wkhtmltopdf intenta cargar) ----
            // Quita scripts (JS no sirve en PDF y suele causar about:blank)
            $htmlLegacy = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $htmlLegacy);

            // Quita iframes/frames por seguridad y porque suelen disparar about:blank
            $htmlLegacy = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $htmlLegacy);
            $htmlLegacy = preg_replace('#<frame\b[^>]*>.*?</frame>#is', '', $htmlLegacy);

            // Corrige href="" / href='about:blank' si existieran
            $htmlLegacy = str_ireplace(['href="about:blank"', "href='about:blank'"], 'href="#"', $htmlLegacy);

            // FIX: WKHTMLTOPDF falla con 403 si encuentra src=".../images/firmas_profesionales/" (directorio sin archivo)
            // Esto sucede si el legacy retorna la ruta sin nombre de imagen.
            // Lo reemplazamos por un pixel transparente en Base64 para evitar la petición de red fallida.
            $htmlLegacy = preg_replace(
                '/src\s*=\s*(["\'])(?:(?!\1).)*\/images\/firmas_profesionales\/\s*\1/i',
                'src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="',
                $htmlLegacy
            );
            
            // Refuerzo con str_replace para casos simples
            $htmlLegacy = str_replace('images/firmas_profesionales/"', 'images/firmas_profesionales/pixel_dummy.png"', $htmlLegacy);
            $htmlLegacy = str_replace("images/firmas_profesionales/'", "images/firmas_profesionales/pixel_dummy.png'", $htmlLegacy);

            // Base URL del legacy (para images/, css/, etc.)
            $baseUrl = "https://devel74.simde.com.co/PRUEBAS_SANDIEGO_RIPS/";

            // ---- PDF con Snappy ----
            $pdf = app('snappy.pdf.wrapper');

            $pdf->loadView('reportes.hc_legacy', [
                'html'    => $htmlLegacy,
                'baseUrl' => $baseUrl,
                'ingreso' => (int)$ingreso,
            ]);

            // Opciones clave
            $pdf->setOption('encoding', 'utf-8');
            $pdf->setOption('enable-local-file-access', true);

            // Evita que falle por recursos que no carguen (css/js/imagenes)
            $pdf->setOption('load-error-handling', 'ignore');
            $pdf->setOption('load-media-error-handling', 'ignore');

            // Recomendadas para estabilidad
            $pdf->setOption('disable-smart-shrinking', true);
            $pdf->setOption('no-stop-slow-scripts', true);

            // Márgenes
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
        $resp = Http::get(
            'https://devel74.simde.com.co/PRUEBAS_SANDIEGO_RIPS/programas/ws/hc_reporte_legacy.php',
            ['ingreso' => $ingreso]
        )->json();

        if (empty($resp['success'])) {
            abort(500, $resp['detail'] ?? 'Error generando');
        }

        $htmlLegacy = $resp['html'];

        // 2) Render Blade + PDF
        $baseUrl = 'https://devel74.simde.com.co/PRUEBAS_SANDIEGO_RIPS/';

        $pdf = app('snappy.pdf.wrapper');
        $pdf->loadView('reportes.hc_legacy', [
            'html' => $htmlLegacy,
            'baseUrl' => $baseUrl,
        ]);

        $pdf->setOption('enable-local-file-access', true);
        $pdf->setOption('encoding', 'utf-8');

        return $pdf->download("HC_{$ingreso}.pdf");
    }
}
