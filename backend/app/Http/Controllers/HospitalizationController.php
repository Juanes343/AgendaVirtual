<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class HospitalizationController extends Controller
{
    /**
     * Obtiene el listado de ingresos de hospitalización para el paciente autenticado.
     * Replica la lógica compleja de UNIONS del sistema legacy (CentralImpresionHospitalizacion).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $pacienteId = $user->paciente_id;
        $tipoDoc = $user->tipo_documento;

        // 1. Solicitudes con Estado '1' (hc_os_solicitudes + hc_evoluciones)
        $q1 = DB::table('hc_os_solicitudes as a')
            ->join('hc_evoluciones as i', 'i.evolucion_id', '=', 'a.evolucion_id')
            ->join('ingresos as d', 'i.ingreso', '=', 'd.ingreso')
            ->join('pacientes as pa', function ($join) {
                $join->on('d.tipo_id_paciente', '=', 'pa.tipo_id_paciente')
                     ->on('d.paciente_id', '=', 'pa.paciente_id');
            })
            ->join('departamentos as dp', 'dp.departamento', '=', 'i.departamento')
            ->join('servicios as s', 's.servicio', '=', 'dp.servicio')
            ->where('a.sw_estado', '1')
            ->whereNotIn('s.servicio', ['0', '3', '5', '99'])
            ->where('d.paciente_id', $pacienteId)
            ->where('d.tipo_id_paciente', $tipoDoc)
            ->select(
                'pa.tipo_id_paciente',
                'pa.paciente_id',
                DB::raw("CONCAT(pa.primer_nombre, ' ', pa.segundo_nombre, ' ', pa.primer_apellido, ' ', pa.segundo_apellido) as nombre"),
                'd.ingreso',
                DB::raw("TO_CHAR(d.fecha_ingreso, 'DD/MM/YYYY') as fecha_ingreso"),
                'd.fecha_ingreso as fecha_sort',
                 'd.estado'
            );

        // 2. Solicitudes con Estado '1','2','3' unidas con os_maestro
        $q2 = DB::table('hc_os_solicitudes as a')
            ->join('hc_evoluciones as i', 'i.evolucion_id', '=', 'a.evolucion_id')
            ->join('os_maestro as c', 'a.hc_os_solicitud_id', '=', 'c.hc_os_solicitud_id')
            ->join('ingresos as d', 'i.ingreso', '=', 'd.ingreso')
            ->join('pacientes as pa', function ($join) {
                $join->on('d.tipo_id_paciente', '=', 'pa.tipo_id_paciente')
                     ->on('d.paciente_id', '=', 'pa.paciente_id');
            })
            ->join('departamentos as dp', 'dp.departamento', '=', 'i.departamento')
            ->join('servicios as s', 's.servicio', '=', 'dp.servicio')
            ->whereIn('c.sw_estado', ['1', '2', '3'])
            ->whereNotIn('s.servicio', ['0', '3', '5', '99'])
            ->where('d.paciente_id', $pacienteId)
            ->where('d.tipo_id_paciente', $tipoDoc)
            ->select(
                'pa.tipo_id_paciente',
                'pa.paciente_id',
                DB::raw("CONCAT(pa.primer_nombre, ' ', pa.segundo_nombre, ' ', pa.primer_apellido, ' ', pa.segundo_apellido) as nombre"),
                'd.ingreso',
                DB::raw("TO_CHAR(d.fecha_ingreso, 'DD/MM/YYYY') as fecha_ingreso"),
                'd.fecha_ingreso as fecha_sort',
                'd.estado'
            );

        // 3. Medicamentos Recetados (hc_medicamentos_recetados_amb)
        // Nota: En legacy no une con hc_evoluciones, une directo ingreso.
        $q3 = DB::table('hc_medicamentos_recetados_amb as a')
            ->join('ingresos as d', 'a.ingreso', '=', 'd.ingreso')
            ->join('pacientes as pa', function ($join) {
                $join->on('d.tipo_id_paciente', '=', 'pa.tipo_id_paciente')
                     ->on('d.paciente_id', '=', 'pa.paciente_id');
            })
            ->join('departamentos as dp', 'dp.departamento', '=', 'd.departamento')
            ->join('servicios as s', 's.servicio', '=', 'dp.servicio')
            ->whereNotIn('s.servicio', ['0', '3', '5', '99'])
            ->where('d.paciente_id', $pacienteId)
            ->where('d.tipo_id_paciente', $tipoDoc)
            ->select(
                'pa.tipo_id_paciente',
                'pa.paciente_id',
                DB::raw("CONCAT(pa.primer_nombre, ' ', pa.segundo_nombre, ' ', pa.primer_apellido, ' ', pa.segundo_apellido) as nombre"),
                'd.ingreso',
                DB::raw("TO_CHAR(d.fecha_ingreso, 'DD/MM/YYYY') as fecha_ingreso"),
                'd.fecha_ingreso as fecha_sort',
                'd.estado'
            );

        // 4. Incapacidades (hc_incapacidades + hc_evoluciones)
        $q4 = DB::table('hc_incapacidades as a')
            ->join('hc_evoluciones as f', 'a.evolucion_id', '=', 'f.evolucion_id')
            ->join('ingresos as d', 'f.ingreso', '=', 'd.ingreso')
            ->join('pacientes as pa', function ($join) {
                $join->on('d.tipo_id_paciente', '=', 'pa.tipo_id_paciente')
                     ->on('d.paciente_id', '=', 'pa.paciente_id');
            })
            ->join('departamentos as dp', 'dp.departamento', '=', 'f.departamento')
            ->join('servicios as s', 's.servicio', '=', 'dp.servicio')
            ->whereNotIn('s.servicio', ['0', '3', '5', '99'])
            ->where('d.paciente_id', $pacienteId)
            ->where('d.tipo_id_paciente', $tipoDoc)
            ->select(
                'pa.tipo_id_paciente',
                'pa.paciente_id',
                DB::raw("CONCAT(pa.primer_nombre, ' ', pa.segundo_nombre, ' ', pa.primer_apellido, ' ', pa.segundo_apellido) as nombre"),
                'd.ingreso',
                DB::raw("TO_CHAR(d.fecha_ingreso, 'DD/MM/YYYY') as fecha_ingreso"),
                'd.fecha_ingreso as fecha_sort',
                'd.estado'
            );

        // Combinar todos con UNION DISTINCT
        $queryResult = $q1->union($q2)->union($q3)->union($q4)->orderBy('fecha_sort', 'desc')->get();

        // Formatear salida para que el Frontend la entienda
        // En ReportController devolvemos: ingreso, fecha, profesional_nombre, servicio, codigo_servicio, estado, tipo_consulta_id
        $formatted = $queryResult->map(function ($item) {
            return [
                'ingreso' => $item->ingreso,
                'fecha' => $item->fecha_ingreso, // Texto formato DD/MM/YYYY
                'fecha_iso' => date('Y-m-d', strtotime($item->fecha_sort)), // Para ordenamientos si fuera necesario
                'profesional_nombre' => 'HOSPITALIZACION / URGENCIAS', // Valor por defecto ya que el query original no trae profesional
                'servicio' => 'ATENCIÓN INSTITUCIONAL',
                'codigo_servicio' => null,
                'estado' => $item->estado,
                'tipo_consulta_id' => 4, // ID Fijo para Hospitalización
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted
        ]);
    }

    /**
     * Genera el PDF de solicitudes de procedimientos No Quirúrgicos
     *
     * @param string $ingreso
     * @return \Illuminate\Http\Response
     */
    public function generateNoQxPdf($ingreso)
    {
        $user = request()->user();
        if (!$user) {
            abort(401);
        }

        // 1. Obtener datos basicos de la empresa (CORREGIDO CON JOINs)
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', 'e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select(
                'e.razon_social', 
                'e.tipo_id_tercero', 
                'e.id as nit',
                'e.digito_verificacion',
                'e.direccion',
                'e.telefonos',
                'm.municipio',
                'd.departamento',
                'e.website',
                'e.email'
            )
            ->where('e.sw_activa', '1')
            ->first();

        // Cargar Logo
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $typeImg = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $imgData = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $typeImg . ';base64,' . base64_encode($imgData);
        }

        // 2. Obtener datos del ingreso, paciente y aseguradora
        $ingresoData = DB::table('ingresos as i')
            ->join('pacientes as p', function($join){
                $join->on('i.tipo_id_paciente', '=', 'p.tipo_id_paciente')
                     ->on('i.paciente_id', '=', 'p.paciente_id');
            })
            ->leftJoin('cuentas as c', 'i.ingreso', '=', 'c.ingreso')
            ->leftJoin('planes as pl', 'c.plan_id', '=', 'pl.plan_id')
            ->leftJoin('terceros as t', 'pl.tercero_id', '=', 't.tercero_id')
            ->where('i.ingreso', $ingreso)
            ->where('i.paciente_id', $user->paciente_id)
            ->where('i.tipo_id_paciente', $user->tipo_documento)
            ->select(
                'p.tipo_id_paciente',
                'p.paciente_id',
                DB::raw("CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) as nombre_completo"),
                'p.fecha_nacimiento',
                DB::raw("EXTRACT(YEAR FROM AGE(p.fecha_nacimiento)) as edad"),
                'p.sexo_id',
                'i.fecha_ingreso',
                'i.ingreso',
                'i.estado',
                't.nombre_tercero',
                'pl.plan_descripcion'
            )
            ->first();

        if (!$ingresoData) {
            return response()->json(['message' => 'Ingreso no encontrado'], 404);
        }

        // 3. Obtener Procedimientos No Quirúrgicos
        $procedimientos = DB::table('hc_os_solicitudes as a')
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

        if ($procedimientos->isEmpty()) {
             return response()->json(['message' => 'No existen procedimientos no quirúrgicos registrados para este ingreso.'], 404);
        }

        // 4. Adjuntar Diagnósticos y preparar datos del profesional
        $usuario_id_firma = null;
        
        foreach ($procedimientos as $proc) {
            $diagnosticos = DB::table('hc_os_solicitudes_diagnosticos as sd')
                ->join('diagnosticos as dx', 'sd.diagnostico_id', '=', 'dx.diagnostico_id')
                ->where('sd.hc_os_solicitud_id', $proc->hc_os_solicitud_id)
                ->select(
                    'sd.diagnostico_id',
                    'dx.diagnostico_nombre',
                    'sd.tipo_diagnostico', 
                    'sd.sw_principal'
                )
                ->get();
            $proc->diagnosticos = $diagnosticos;
            
            if (!$usuario_id_firma) {
                $usuario_id_firma = $proc->usuario_id;
            }
        }

        // 5. Obtener datos del Profesional (Firma)
        $profesional = null;
        $firmaBase64 = null;
        if ($usuario_id_firma) {
            $profesional = DB::table('system_usuarios as u')
                ->join('profesionales_usuarios as pu', 'u.usuario_id', '=', 'pu.usuario_id')
                ->join('profesionales as p', function($join){
                    $join->on('pu.tipo_tercero_id', '=', 'p.tipo_id_tercero')
                         ->on('pu.tercero_id', '=', 'p.tercero_id');
                })
                ->leftJoin('profesionales_especialidades as pe', function($join){
                    $join->on('p.tipo_id_tercero', '=', 'pe.tipo_id_tercero')
                         ->on('p.tercero_id', '=', 'pe.tercero_id');
                })
                ->leftJoin('especialidades as esp', 'pe.especialidad', '=', 'esp.especialidad')
                ->where('u.usuario_id', $usuario_id_firma)
                ->select(
                    'p.nombre', 
                    'p.tarjeta_profesional', 
                    'esp.descripcion as especialidad',
                    // 'p.firma_mimetype',
                    'p.firma'
                ) 
                ->first();

            if ($profesional && !empty($profesional->firma)) {
                $firmaBase64 = $this->getFirmaBase64($profesional->firma);
            }
        }

        // 6. Generar Y Retornar PDF
        $pdf = \PDF::loadView('reportes.hc_solicitud_no_qx', [
            'empresa' => $empresa,
            'paciente' => $ingresoData,
            'ingreso' => $ingresoData->ingreso,
            'fecha' => $procedimientos[0]->fecha_solicitud ?? date('Y-m-d'), 
            'profesional' => $profesional,
            'cliente' => $ingresoData, 
            'procedimientos' => $procedimientos,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64
        ]);

        return $pdf->stream('Solicitud_No_Qx_'.$ingreso.'.pdf');
    }

    /**
     * Envía el PDF de solicitudes de procedimientos No Quirúrgicos por correo
     *
     * @param Request $request
     * @param string $ingreso
     * @return \Illuminate\Http\Response
     */
    public function sendNoQxEmail(Request $request, $ingreso)
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $email = $request->input('email');

        // Si no llega el email en el request, intentar obtenerlo del paciente
        if (!$email) {
            $pacienteData = DB::table('pacientes')
                ->where('paciente_id', $user->paciente_id)
                ->where('tipo_id_paciente', $user->tipo_documento)
                ->select('email')
                ->first();

            if ($pacienteData && !empty($pacienteData->email)) {
                $email = $pacienteData->email;
            }
        }

        if (!$email) {
             return response()->json(['message' => 'Email es requerido'], 400);
        }

        // 1. Obtener datos basicos de la empresa
        $empresa = DB::table('empresas as e')
            ->leftJoin('tipo_mpios as m', 'e.tipo_mpio_id', '=', 'm.tipo_mpio_id')
            ->leftJoin('tipo_dptos as d', 'e.tipo_dpto_id', '=', 'd.tipo_dpto_id')
            ->select(
                'e.razon_social', 
                'e.tipo_id_tercero', 
                'e.id as nit', 
                'e.digito_verificacion',
                'e.direccion', 
                'e.telefonos', 
                'e.website',
                'm.municipio', 
                'd.departamento',
                'e.email'
            )
            ->where('e.sw_activa', '1')
            ->first();
        
        // Cargar Logo
        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $typeImg = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $imgData = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $typeImg . ';base64,' . base64_encode($imgData);
        } 

        // 2. Obtener datos del ingreso, paciente y aseguradora
        $ingresoData = DB::table('ingresos as i')
            ->join('pacientes as p', function($join){
                $join->on('i.tipo_id_paciente', '=', 'p.tipo_id_paciente')
                     ->on('i.paciente_id', '=', 'p.paciente_id');
            })
            ->leftJoin('cuentas as c', 'i.ingreso', '=', 'c.ingreso')
            ->leftJoin('planes as pl', 'c.plan_id', '=', 'pl.plan_id')
            ->leftJoin('terceros as t', 'pl.tercero_id', '=', 't.tercero_id')
            ->where('i.ingreso', $ingreso)
            ->where('i.paciente_id', $user->paciente_id)
            ->where('i.tipo_id_paciente', $user->tipo_documento)
            ->select(
                'p.tipo_id_paciente',
                'p.paciente_id',
                DB::raw("CONCAT(p.primer_nombre, ' ', p.segundo_nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido) as nombre_completo"),
                'p.fecha_nacimiento',
                DB::raw("EXTRACT(YEAR FROM AGE(p.fecha_nacimiento)) as edad"),
                'p.sexo_id',
                'i.fecha_ingreso',
                'i.ingreso',
                'i.estado',
                't.nombre_tercero',
                'pl.plan_descripcion'
            )
            ->first();

        if (!$ingresoData) {
            return response()->json(['message' => 'Ingreso no encontrado'], 404);
        }

        // 3. Obtener Procedimientos No Quirúrgicos
        $procedimientos = DB::table('hc_os_solicitudes as a')
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

        if ($procedimientos->isEmpty()) {
             return response()->json(['message' => 'No existen procedimientos registrados.'], 404);
        }

        // 4. Adjuntar Diagnósticos y preparar datos del profesional
        $usuario_id_firma = null;
        foreach ($procedimientos as $proc) {
            $diagnosticos = DB::table('hc_os_solicitudes_diagnosticos as sd')
                ->join('diagnosticos as dx', 'sd.diagnostico_id', '=', 'dx.diagnostico_id')
                ->where('sd.hc_os_solicitud_id', $proc->hc_os_solicitud_id)
                ->select(
                    'sd.diagnostico_id',
                    'dx.diagnostico_nombre',
                    'sd.tipo_diagnostico', 
                    'sd.sw_principal'
                )
                ->get();
            $proc->diagnosticos = $diagnosticos;
            
            if (!$usuario_id_firma) {
                $usuario_id_firma = $proc->usuario_id;
            }
        }

        // 5. Obtener datos del Profesional (Firma)
        $profesional = null;
        $firmaBase64 = null;
        if ($usuario_id_firma) {
            $profesional = DB::table('system_usuarios as u')
                ->join('profesionales_usuarios as pu', 'u.usuario_id', '=', 'pu.usuario_id')
                ->join('profesionales as p', function($join){
                    $join->on('pu.tipo_tercero_id', '=', 'p.tipo_id_tercero')
                         ->on('pu.tercero_id', '=', 'p.tercero_id');
                })
                ->leftJoin('profesionales_especialidades as pe', function($join){
                    $join->on('p.tipo_id_tercero', '=', 'pe.tipo_id_tercero')
                         ->on('p.tercero_id', '=', 'pe.tercero_id');
                })
                ->leftJoin('especialidades as esp', 'pe.especialidad', '=', 'esp.especialidad')
                ->where('u.usuario_id', $usuario_id_firma)
                ->select(
                    'p.nombre', 
                    'p.tarjeta_profesional', 
                    'esp.descripcion as especialidad',
                    // 'p.firma_mimetype',
                    'p.firma'
                )
                ->first();

            if ($profesional && !empty($profesional->firma)) {
                $firmaBase64 = $this->getFirmaBase64($profesional->firma);
            }
        }

        // 6. Generar PDF
        $pdf = \PDF::loadView('reportes.hc_solicitud_no_qx', [
            'empresa' => $empresa,
            'paciente' => $ingresoData,
            'ingreso' => $ingresoData->ingreso,
            'fecha' => $procedimientos[0]->fecha_solicitud ?? date('Y-m-d'), 
            'profesional' => $profesional,
            'cliente' => $ingresoData, 
            'procedimientos' => $procedimientos,
            'logoBase64' => $logoBase64,
            'firmaBase64' => $firmaBase64
        ]);
        $pdfContent = $pdf->output();

        // 7. Enviar Correo
        try {
            $dataMail = [
                'paciente' => $ingresoData,
                'ingreso' => $ingreso,
                'fecha' => $procedimientos[0]->fecha_solicitud ?? date('Y-m-d'),
                'profesional' => $profesional
            ];

            \Illuminate\Support\Facades\Mail::send('emails.solicitud_no_qx', $dataMail, function ($message) use ($email, $pdfContent, $ingreso, $empresa) {
                $subject = "Solicitud Procedimientos No Quirúrgicos - Ingreso $ingreso";
                if ($empresa && isset($empresa->razon_social)) {
                    $subject = $empresa->razon_social . " - " . $subject;
                }

                $message->to($email)
                        ->subject($subject)
                        ->attachData($pdfContent, "Solicitud_No_Qx_$ingreso.pdf", [
                            'mime' => 'application/pdf',
                        ]);
            });
            
            return response()->json(['success' => true, 'message' => 'Correo enviado correctamente.']);
        } catch (\Exception $e) {
            Log::error("Error enviando correo NoQx: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error al enviar el correo.'], 500);
        }
    }

    /**
     * Obtiene la firma del profesional en formato Base64.
     * Busca primero localmente y luego vía URL si es necesario.
     */
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
            } catch (\Throwable $eF) { 
                Log::error("Error descargando firma NoQx: " . $eF->getMessage()); 
            }
        }
        return null;
    }
}