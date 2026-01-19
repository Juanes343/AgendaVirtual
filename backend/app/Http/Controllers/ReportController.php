<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Paciente;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
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
            ->join('profesionales as d', function($join) {
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
            ->where(function($q) {
                $q->whereExists(function($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hc_medicamentos_recetados_amb as med')
                        ->join('hc_evoluciones as evo_m', 'med.evolucion_id', '=', 'evo_m.evolucion_id')
                        ->whereColumn('evo_m.ingreso', 'b.ingreso');
                })
                ->orWhereExists(function($sub) {
                    $sub->select(DB::raw(1))
                        ->from('hc_os_solicitudes as sol')
                        ->join('hc_evoluciones as evo_s', 'sol.evolucion_id', '=', 'evo_s.evolucion_id')
                        ->whereColumn('evo_s.ingreso', 'b.ingreso');
                })
                ->orWhereExists(function($sub) {
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
            ->where('e.ingreso', $ingreso)
            ->select(
                'e.evolucion_id',
                'e.fecha',
                'a.codigo_producto as codigo_medicamento', 
                'i.descripcion as producto', 
                'b.cod_principio_activo as principio_activo',
                'a.dosis', 
                'a.unidad_dosificacion', 
                'a.cantidadperiocidad as frecuencia', 
                'a.dias_tratamiento',
                'a.dias_tratamiento as tiempo_tratamiento', 
                'a.cantidad', 
                'a.observacion'
            )
            ->get();

        // 2. Solicitudes / Ordenes
        $solicitudes = DB::table('hc_os_solicitudes as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->where('e.ingreso', $ingreso)
            ->select(
                'e.evolucion_id',
                'a.fecha_solicitud as fecha_solicitud', 
                'a.cargo', 
                'b.descripcion', 
                'a.hc_os_solicitud_id',
                'a.cantidad'
                // 'a.observaciones as observacion'
            )
            ->get();

        // 3. Incapacidades
        $incapacidades = DB::table('hc_incapacidades as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('diagnosticos as d', 'a.diagnostico_id', '=', 'd.diagnostico_id')
            ->where('e.ingreso', $ingreso)
            ->select(
                'e.evolucion_id',
                'a.fecha_inicio',
                'a.dias_de_incapacidad',
                'a.observacion_incapacidad',
                'd.diagnostico_nombre',
                'd.diagnostico_id'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'medicamentos' => $medicamentos,
                'solicitudes' => $solicitudes,
                'incapacidades' => $incapacidades
            ]
        ]);
    }

    /**
     * Envía un correo con el reporte detallado del historial médico.
     * @param int $ingreso
     */
    public function sendHistoryEmail(Request $request, $ingreso) 
    {
        // 1. Obtener datos del ingreso 
        $detailResponse = $this->getHistoryDetail($ingreso);
        $data = $detailResponse->getData()->data; 

        // 2. Obtener datos cabecera (paciente/profesional) buscando alguna evolución del ingreso
        $unaEvolucion = DB::table('hc_evoluciones')->where('ingreso', $ingreso)->orderBy('fecha', 'desc')->first();

        if (!$unaEvolucion) {
            return response()->json(['success' => false, 'message' => 'No se encontraron registros para este ingreso.'], 404);
        }

        $header = $this->getHeaderData($unaEvolucion->evolucion_id);

        if (!$header) {
             return response()->json(['success' => false, 'message' => 'Error obteniendo datos del paciente.'], 500);
        }

        // Recuperar email del paciente
        $paciente = Paciente::where('paciente_id', $header->paciente_id)
            ->where('tipo_id_paciente', $header->tipo_id_paciente)
            ->first();

        if (!$paciente || empty($paciente->email)) {
             return response()->json(['success' => false, 'message' => 'El paciente no tiene un correo electrónico registrado.'], 400);
        }

        try {
            // 3. Generar PDF consolidado en memoria
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->set_option('isRemoteEnabled', true);
            
            // Vista unificada
            $html = view('reporte_completo', [
                'paciente' => $header,
                'medicamentos' => $data->medicamentos,
                'solicitudes' => $data->solicitudes,
                'incapacidades' => $data->incapacidades,
                'fecha' => $header->fecha,
                'profesional' => $header->profesional,
                'especialidad' => $header->especialidad,
                'ingreso' => $ingreso
            ])->render();

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdfContentCompleto = $dompdf->output();

            // 3.2 Generar PDF Formula (Si hay medicamentos)
            $pdfContentFormula = null;
            if (!empty($data->medicamentos) && count($data->medicamentos) > 0) {
                $dompdfF = new \Dompdf\Dompdf();
                $dompdfF->set_option('isRemoteEnabled', true);
                $dompdfF->loadHtml(view('formula', [
                    'paciente' => $header, 
                    'medicamentos' => $data->medicamentos, 
                    'fecha' => $header->fecha, 
                    'profesional' => $header->profesional,
                    'registro_medico' => $header->tarjeta_profesional
                ])->render());
                $dompdfF->setPaper('A4', 'portrait');
                $dompdfF->render();
                $pdfContentFormula = $dompdfF->output();
            }

            // 3.3 Generar PDF Ordenes (Si hay solicitudes)
            $pdfContentOrden = null;
            if (!empty($data->solicitudes) && count($data->solicitudes) > 0) {
                $dompdfO = new \Dompdf\Dompdf();
                $dompdfO->set_option('isRemoteEnabled', true);
                $dompdfO->loadHtml(view('orden', [
                    'paciente' => $header, 
                    'solicitudes' => $data->solicitudes, 
                    'fecha' => $header->fecha, 
                    'profesional' => $header->profesional,
                    'especialidad' => $header->especialidad
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
            ], function($message) use ($paciente, $ingreso, $pdfContentCompleto, $pdfContentFormula, $pdfContentOrden) {
                $message->to($paciente->email)
                        ->subject('Reporte Historia Clínica - Ingreso #' . $ingreso);
                
                // Adjunto 1: Reporte Completo
                $message->attachData($pdfContentCompleto, "Historia_Clinica_Completa_{$ingreso}.pdf", ['mime' => 'application/pdf']);

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

    private function maskEmail($email) {
        $parts = explode('@', $email);
        if(count($parts) < 2) return $email;
        $name = $parts[0];
        $len = strlen($name);
        $visibleLen = floor($len / 2);
        $maskedName = substr($name, 0, $visibleLen) . str_repeat('*', ($len - $visibleLen));
        return $maskedName . '@' . $parts[1];
    }
    
    // Helper privado
    private function getHeaderData($evolucion_id) {
        return DB::table('hc_evoluciones as a')
            ->join('ingresos as b', 'a.ingreso', '=', 'b.ingreso')
            ->leftJoin('cuentas as cu', 'b.ingreso', '=', 'cu.ingreso') // Join a cuentas
            ->join('pacientes as p', function($join) {
                $join->on('b.paciente_id', '=', 'p.paciente_id')
                     ->on('b.tipo_id_paciente', '=', 'p.tipo_id_paciente');
            })
            // Joins para datos de Plan y Cliente
            ->leftJoin('planes as pl', 'cu.plan_id', '=', 'pl.plan_id') // Usar cu.plan_id en vez de b.plan_id
            ->leftJoin('terceros as cli', 'pl.tercero_id', '=', 'cli.tercero_id')
            
            ->join('profesionales_usuarios as c', 'a.usuario_id', '=', 'c.usuario_id')
            ->join('profesionales as d', function($join) {
                $join->on('c.tercero_id', '=', 'd.tercero_id')
                     ->on('c.tipo_tercero_id', '=', 'd.tipo_id_tercero');
            })
            ->leftJoin('profesionales_especialidades as pe', function($join) {
                $join->on('d.tercero_id', '=', 'pe.tercero_id')
                     ->on('d.tipo_id_tercero', '=', 'pe.tipo_id_tercero');
            })
            ->leftJoin('especialidades as esp', 'pe.especialidad', '=', 'esp.especialidad') 
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'p.paciente_id', 
                'p.tipo_id_paciente', 
                DB::raw("CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_completo"), 
                'p.fecha_nacimiento',
                'p.sexo_id',
                'a.fecha as fecha',
                'a.evolucion_id',
                'b.ingreso',
                'd.nombre as profesional',
                'd.tercero_id as prof_id',
                'd.tipo_id_tercero as prof_tipo_id',
                'd.tarjeta_profesional', 
                'esp.descripcion as especialidad',
                'pl.plan_descripcion',
                'cli.nombre_tercero as cliente_nombre',
                'cu.tipo_afiliado_id',
                'cu.rango' // Asumiendo que existe en ingresos
            )
            ->first();
    }

    public function generateFormulaPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // --- Obtener datos de Empresa ---
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
            ->where('e.sw_activa', '1') // Asumiendo que hay una activa
            ->first();

        // --- Manejo de LOGO en Base64 para evitar problemas de rutas en DomPDF ---
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $data = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        // Calcular edad
        $edad = \Carbon\Carbon::parse($header->fecha_nacimiento)->age;

        // Diagnósticos (Corregido: Usar hc_diagnosticos_ingreso y seleccionar diagnostico_id correctamente)
        $diagnosticos = DB::table('hc_diagnosticos_ingreso as a')
            ->join('diagnosticos as b', 'a.tipo_diagnostico_id', '=', 'b.diagnostico_id')
            ->where('a.evolucion_id', $evolucion_id)
            ->select('a.tipo_diagnostico_id as diagnostico_id', 'b.diagnostico_nombre')
            ->get();

        // Corrección: Usar hc_medicamentos_recetados_amb
        $medicamentos = DB::table('hc_medicamentos_recetados_amb as a')
            ->join('inventarios_productos as i', 'a.codigo_producto', '=', 'i.codigo_producto') 
            ->leftJoin('medicamentos as b', 'a.codigo_producto', '=', 'b.codigo_medicamento')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.codigo_producto as codigo_medicamento', 
                'i.descripcion as producto', 
                'b.cod_principio_activo as principio_activo',
                // 'b.descripcion_comercial', // A veces el nombre comercial ayuda
                'i.descripcion_abreviada',
                'a.dosis', 
                'a.unidad_dosificacion', 
                'a.cantidadperiocidad as frecuencia', 
                'a.dias_tratamiento',
                'a.dias_tratamiento as tiempo_tratamiento', 
                'a.cantidad', 
                'a.observacion',
                'a.via_administracion_id'
            )
            ->get();

        // Renderizado HTML
        $html = view('formula', [
            'header' => $header, 
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'edad' => $edad,
            'medicamentos' => $medicamentos,
            'diagnosticos' => $diagnosticos,
            'fecha_impresion' => date('d/m/Y - h:i a')
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true); // Permitir imagenes
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('formula_'.$evolucion_id.'.pdf');
    }

    public function generateOrderPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        $solicitudes = DB::table('hc_os_solicitudes as a')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.fecha_solicitud as fecha_solicitud', 
                'a.cargo', 
                'b.descripcion', 
                'a.hc_os_solicitud_id',
                'a.cantidad'
                // 'a.observaciones as observacion'
            )
            ->get();

        $html = view('orden', [
            'paciente' => $header, 
            'solicitudes' => $solicitudes, 
            'fecha' => $header->fecha, 
            'profesional' => $header->profesional,
            'especialidad' => $header->especialidad
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('ordenes_'.$evolucion_id.'.pdf');
    }

    public function generateIncapacidadPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        $incapacidades = DB::table('hc_incapacidades as a')
            ->join('diagnosticos as d', 'a.diagnostico_id', '=', 'd.diagnostico_id')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.fecha_inicio',
                'a.dias_de_incapacidad',
                'a.observacion_incapacidad',
                'd.diagnostico_nombre',
                'd.diagnostico_id'
            )
            ->get();

        $html = view('incapacidad', [
            'paciente' => $header, 
            'incapacidades' => $incapacidades, 
            'fecha' => $header->fecha, 
            'profesional' => $header->profesional
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('incapacidad_'.$evolucion_id.'.pdf');
    }
}