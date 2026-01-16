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
     * Obtiene el historial de atenciones del paciente
     */
    public function getMedicalHistory(Request $request)
    {
        $user = $request->user(); 
        $pacienteId = $user->paciente_id; 
        $tipoDoc = $user->tipo_documento;
        
        $historial = DB::table('hc_evoluciones as a')
            ->join('ingresos as b', 'a.ingreso', '=', 'b.ingreso')
            ->join('profesionales_usuarios as c', 'a.usuario_id', '=', 'c.usuario_id')
            ->join('profesionales as d', function($join) {
                $join->on('c.tercero_id', '=', 'd.tercero_id')
                     ->on('c.tipo_tercero_id', '=', 'd.tipo_id_tercero');
            })
            ->select(
                'a.fecha as fecha',
                'd.nombre as profesional_nombre', 
                'a.ingreso',
                'a.evolucion_id',
                'b.estado'
            )
            ->where('b.paciente_id', $pacienteId)
            ->where('b.tipo_id_paciente', $tipoDoc)
            ->orderBy('a.fecha', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $historial
        ]);
    }

    /**
     * Obtiene el detalle (medicamentos, ordenes) y PDFs
     */
    public function getHistoryDetail($evolucion_id)
    {
        // JOINS CORREGIDOS: medicamentos -> inventarios_productos
        $medicamentos = DB::table('hc_formulacion_antecedentes as a')
            ->join('medicamentos as b', 'a.codigo_medicamento', '=', 'b.codigo_medicamento')
            ->join('inventarios_productos as i', 'b.codigo_medicamento', '=', 'i.codigo_producto') // Nuevo Join
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'b.codigo_medicamento', 
                'i.descripcion as producto', // Ahora sacamos descripcion de inventarios_productos
                'b.cod_principio_activo as principio_activo' // Ajuste por si b.principio_activo no existe
                // 'a.dosis', 
                // 'a.unidad_dosificacion', 
                // 'a.frecuencia', 
                // 'a.tiempo_tratamiento', 
                // 'a.cantidad', 
                // 'a.observacion'
            )
            ->get();

        $solicitudes = DB::table('hc_os_solicitudes as a')
            ->join('cups as b', 'a.cargo', '=', 'b.cargo')
            ->where('a.evolucion_id', $evolucion_id)
            ->select('a.fecha_solicitud as fecha_solicitud', 'a.cargo', 'b.descripcion', 'a.hc_os_solicitud_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'medicamentos' => $medicamentos,
                'solicitudes' => $solicitudes
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
                    $join->on('c.tercero_id', '=', 'd.tercero_id')->on('c.tipo_tercero_id', '=', 'd.tipo_id_tercero');
            })
            ->where('a.evolucion_id', $evolucion_id)
            ->select('p.paciente_id', 'p.tipo_id_paciente', DB::raw("CONCAT(p.primer_nombre, ' ', p.primer_apellido) as nombre_completo"), 'a.fecha as fecha', 'd.nombre as profesional')
            ->first();
    }

    public function generateFormulaPdf($evolucion_id)
    {
        $header = $this->getHeaderData($evolucion_id);
        if (!$header) return response()->json(['error' => 'No encontrado'], 404);

        // JOINS CORREGIDOS TAMBIÉN AQUÍ
        $medicamentos = DB::table('hc_formulacion_antecedentes as a')
            ->join('medicamentos as b', 'a.codigo_medicamento', '=', 'b.codigo_medicamento')
            ->join('inventarios_productos as i', 'b.codigo_medicamento', '=', 'i.codigo_producto') // Nuevo Join
            ->where('a.evolucion_id', $evolucion_id)
            ->select(
                'b.codigo_medicamento', 
                'i.descripcion as producto', // Descripción de inventarios_productos
                'b.cod_principio_activo as principio_activo'
            )
            ->get();

        // INTENTO MANUAL SIN DEPENDER DE LARAVEL WRAPPER
        // Renderizamos la vista a HTML texto
        // Ajuste: Buscamos 'formula' en la raíz de views si no existe la carpeta reports
        $html = view('formula', [
            'paciente' => $header, 
            'medicamentos' => $medicamentos, 
            'fecha' => $header->fecha, 
            'profesional' => $header->profesional
        ])->render();

        // Usamos la librería base Dompdf directamente
        $dompdf = new \Dompdf\Dompdf();
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
            ->select('a.fecha_solicitud as fecha_solicitud', 'a.cargo', 'b.descripcion', 'a.hc_os_solicitud_id')
            ->get();

        // INTENTO MANUAL SIN DEPENDER DE LARAVEL WRAPPER
        $html = view('orden', [
            'paciente' => $header, 
            'solicitudes' => $solicitudes, 
            'fecha' => $header->fecha, 
            'profesional' => $header->profesional
        ])->render();

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->stream('ordenes_'.$evolucion_id.'.pdf');
    }
}