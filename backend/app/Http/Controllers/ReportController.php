<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            ->select(
                'a.ingreso',
                DB::raw("MAX(DATE(a.fecha)) as fecha"), // Solo fecha
                DB::raw("MAX(d.nombre) as profesional_nombre"),
                DB::raw("MAX(cups.descripcion) as servicio"),
                'b.estado'
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
    
    // Helper privado
    private function getHeaderData($evolucion_id) {
        return DB::table('hc_evoluciones as a')
            ->join('ingresos as b', 'a.ingreso', '=', 'b.ingreso')
            ->join('pacientes as p', function($join) {
                $join->on('b.paciente_id', '=', 'p.paciente_id')
                     ->on('b.tipo_id_paciente', '=', 'p.tipo_id_paciente');
            })
            ->join('profesionales_usuarios as c', 'a.usuario_id', '=', 'c.usuario_id')
            ->join('profesionales as d', function($join) {
                $join->on('c.tercero_id', '=', 'd.tercero_id')
                     ->on('c.tipo_tercero_id', '=', 'd.tipo_id_tercero');
            })
            // Corrección: Usar la tabla intermedia 'profesionales_especialidades'
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
                'a.fecha as fecha', 
                'd.nombre as profesional',
                'd.tarjeta_profesional', // Útil para formula
                'esp.descripcion as especialidad' // Útil para cabecera
            )
            ->first();
    }

    public function generateFormulaPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // Corrección: Usar hc_medicamentos_recetados_amb
        $medicamentos = DB::table('hc_medicamentos_recetados_amb as a')
            ->join('inventarios_productos as i', 'a.codigo_producto', '=', 'i.codigo_producto') 
            ->leftJoin('medicamentos as b', 'a.codigo_producto', '=', 'b.codigo_medicamento')
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'a.codigo_producto as codigo_medicamento', 
                'i.descripcion as producto', 
                'b.cod_principio_activo as principio_activo',
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
            'paciente' => $header, 
            'medicamentos' => $medicamentos, 
            'fecha' => $header->fecha, 
            'profesional' => $header->profesional,
            'registro_medico' => $header->tarjeta_profesional
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