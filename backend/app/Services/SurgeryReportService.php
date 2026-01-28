<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SurgeryReportService
{
    /**
     * Obtener cirugías por ingreso
     */
    public function getSurgeriesByIngreso($ingresoId)
    {
        return DB::table('hc_notas_operatorias_cirugias as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->leftJoin('qx_quirofanos as x', 'a.quirofano_id', '=', 'x.quirofano')
            ->leftJoin('qx_tipos_cirugia as c', 'a.tipo_cirugia', '=', 'c.tipo_cirugia_id')
            ->leftJoin('terceros as ter', 'a.cirujano_id', '=', 'ter.tercero_id')
            ->where('e.ingreso', $ingresoId)
            ->select(
                'a.hc_nota_operatoria_cirugia_id',
                'a.evolucion_id',
                'e.ingreso',
                DB::raw("TO_CHAR(a.hora_inicio, 'YYYY-MM-DD HH24:MI') as fecha_hora"),
                'a.hora_inicio',
                'a.hora_fin as hora_final',
                'x.descripcion as nom_quirofano',
                'c.descripcion as tipo_cirugia',
                'ter.nombre_tercero as cirujano_nombre',
                DB::raw("'1' as estado")
            )
            ->selectSub(function ($query) {
                $query->from('hc_notas_operatorias_procedimientos as nop')
                    ->join('cups', 'nop.procedimiento_qx', '=', 'cups.cargo')
                    ->whereColumn('nop.hc_nota_operatoria_cirugia_id', 'a.hc_nota_operatoria_cirugia_id')
                    ->where('nop.realizado', '1')
                    ->select(DB::raw("CONCAT(cups.cargo, ' - ', cups.descripcion)"))
                    ->limit(1);
            }, 'procedimiento_principal')
            ->orderBy('a.hora_inicio', 'desc')
            ->get();
    }

    /**
     * Obtener cirugías por Paciente (Todos los ingresos)
     */
    public function getSurgeriesByPatient($pacienteId, $tipoDoc)
    {
        return DB::table('hc_notas_operatorias_cirugias as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('ingresos as i', 'e.ingreso', '=', 'i.ingreso')
            ->leftJoin('qx_quirofanos as x', 'a.quirofano_id', '=', 'x.quirofano')
            ->leftJoin('qx_tipos_cirugia as c', 'a.tipo_cirugia', '=', 'c.tipo_cirugia_id')
            ->leftJoin('terceros as ter', 'a.cirujano_id', '=', 'ter.tercero_id')
            ->where('i.paciente_id', $pacienteId)
            ->where('i.tipo_id_paciente', $tipoDoc)
            ->select(
                'a.hc_nota_operatoria_cirugia_id',
                'a.evolucion_id',
                'e.ingreso',
                DB::raw("TO_CHAR(a.hora_inicio, 'YYYY-MM-DD HH24:MI') as fecha_hora"),
                'a.hora_inicio',
                'a.hora_fin as hora_final',
                'x.descripcion as nom_quirofano',
                'c.descripcion as tipo_cirugia',
                'ter.nombre_tercero as cirujano_nombre',
                DB::raw("'1' as estado")
            )
            ->selectSub(function ($query) {
                $query->from('hc_notas_operatorias_procedimientos as nop')
                    ->join('cups', 'nop.procedimiento_qx', '=', 'cups.cargo')
                    ->whereColumn('nop.hc_nota_operatoria_cirugia_id', 'a.hc_nota_operatoria_cirugia_id')
                    ->where('nop.realizado', '1')
                    ->select(DB::raw("CONCAT(cups.cargo, ' - ', cups.descripcion)"))
                    ->limit(1);
            }, 'procedimiento_principal')
            ->orderBy('a.hora_inicio', 'desc')
            ->get();
    }

    /**
     * Obtener datos para el PDF de la Nota Operatoria
     */
    public function getSurgeryReportData($notaId)
    {
        $nota = DB::table('hc_notas_operatorias_cirugias as a')
            ->join('hc_evoluciones as e', 'a.evolucion_id', '=', 'e.evolucion_id')
            ->join('ingresos as i', 'e.ingreso', '=', 'i.ingreso')
            ->join('pacientes as p', function($join) {
                $join->on('i.paciente_id', '=', 'p.paciente_id')
                     ->on('i.tipo_id_paciente', '=', 'p.tipo_id_paciente');
            })
            // Join con Cuentas
            ->leftJoin('cuentas as cu', 'i.ingreso', '=', 'cu.ingreso')
            ->leftJoin('planes as pl', 'cu.plan_id', '=', 'pl.plan_id')
            // Tercero del plan
            ->leftJoin('terceros as ter_plan', function($join) {
                $join->on('pl.tercero_id', '=', 'ter_plan.tercero_id')
                     ->on('pl.tipo_tercero_id', '=', 'ter_plan.tipo_id_tercero');
           })
           // Via ingreso
           ->leftJoin('vias_ingreso as vi', 'i.via_ingreso_id', '=', 'vi.via_ingreso_id')
           // Responsable (system_usuarios linked to ingreso)
           ->leftJoin('system_usuarios as su', 'i.usuario_id', '=', 'su.usuario_id')
            
            // Joins adicionales
            ->leftJoin('qx_quirofanos as x', 'a.quirofano_id', '=', 'x.quirofano')
            ->leftJoin('qx_vias_acceso as b', 'a.via_acceso', '=', 'b.via_acceso')
            ->leftJoin('qx_tipos_cirugia as c', 'a.tipo_cirugia', '=', 'c.tipo_cirugia_id')
            ->leftJoin('qx_ambitos_cirugias as d', 'a.ambito_cirugia', '=', 'd.ambito_cirugia_id')
            ->leftJoin('qx_finalidades_procedimientos as f', 'a.finalidad_procedimiento_id', '=', 'f.finalidad_procedimiento_id')
            ->leftJoin('diagnosticos as diag_comp', 'a.diagnostico_id_complicacion', '=', 'diag_comp.diagnostico_id')
             // Profesionales
            ->leftJoin('terceros as ter_cir', 'a.cirujano_id', '=', 'ter_cir.tercero_id')
            ->leftJoin('terceros as ter_ane', 'a.anestesiologo_id', '=', 'ter_ane.tercero_id')
            ->leftJoin('terceros as ter_ayu', 'a.ayudante_id', '=', 'ter_ayu.tercero_id')
            ->leftJoin('terceros as ter_ins', 'a.instrumentista_id', '=', 'ter_ins.tercero_id')
            ->leftJoin('terceros as ter_ciru', 'a.circulante_id', '=', 'ter_ciru.tercero_id')
             // Tipo Anestesia
            ->leftJoin('qx_tipos_anestesia as tanes', 'a.qx_tipo_anestesia_id', '=', 'tanes.qx_tipo_anestesia_id')

            // Firma Profesional Cirujano
            ->leftJoin('profesionales as prof_cir', function($join){
                $join->on('a.cirujano_id', '=', 'prof_cir.tercero_id')
                     ->on('a.tipo_id_cirujano', '=', 'prof_cir.tipo_id_tercero');
            })
            // Especialidad Cirujano
             ->leftJoin('profesionales_especialidades as pe', function($join){
                $join->on('prof_cir.tercero_id', '=', 'pe.tercero_id')
                     ->on('prof_cir.tipo_id_tercero', '=', 'pe.tipo_id_tercero');
             })
             ->leftJoin('especialidades as esp', 'pe.especialidad', '=', 'esp.especialidad')
            
            ->where('a.hc_nota_operatoria_cirugia_id', $notaId)
            ->select(
                'a.*',
                'e.ingreso',
                'i.fecha_ingreso',
                'cu.numerodecuenta',
                'pl.plan_descripcion',
                'pl.plan_descripcion as nombre_plan_full',
                'vi.via_ingreso_nombre',
                'ter_plan.nombre_tercero',
                'pl.tipo_tercero_id',
                'pl.tercero_id',
                'su.nombre as responsable', 
                
                'p.primer_nombre', 'p.segundo_nombre', 'p.primer_apellido', 'p.segundo_apellido', 
                'p.tipo_id_paciente', 'p.paciente_id', 'p.fecha_nacimiento', 'p.sexo_id', 'p.residencia_direccion', 'p.residencia_telefono',
                'p.email',
                'x.descripcion as nom_quirofano',
                'b.descripcion as via_acceso_nombre',
                'c.descripcion as tipo_cirugia_nombre',
                'd.descripcion as ambito_cirugia_nombre',
                'f.descripcion as finalidad_nombre',
                'tanes.descripcion as tipo_anestesia_nombre',
                'diag_comp.diagnostico_nombre as diag_nom1',

                'ter_cir.nombre_tercero as cirujano_nombre',
                'ter_ane.nombre_tercero as anestesiologo_nombre',
                'ter_ayu.nombre_tercero as ayudante_nombre',
                'ter_ins.nombre_tercero as instrumentador_nombre',
                'ter_ciru.nombre_tercero as circulante_nombre',

                // Datos Firma
                'prof_cir.firma',
                'prof_cir.tarjeta_profesional',
                'prof_cir.registro_salud_departamental',
                'esp.descripcion as especialidad_profesional'
            )
            ->first();

        if (!$nota) {
            throw new \Exception("Nota operatoria no encontrada ($notaId)");
        }

        $empresa = DB::table('empresas')->where('sw_activa', '1')->first();
        if ($empresa) {
            $nota->nombre_empresa = $empresa->razon_social;
            $nota->emp_direccion = $empresa->direccion;
            $nota->emp_telefono = $empresa->telefonos;
            $nota->nit = $empresa->id;
        }
        
        $nota->nombre_completo = trim("$nota->primer_nombre $nota->segundo_nombre $nota->primer_apellido $nota->segundo_apellido");
        $nota->nombres = trim("$nota->primer_nombre $nota->segundo_nombre");
        $nota->apellidos = trim("$nota->primer_apellido $nota->segundo_apellido");
        $nota->edad = \Carbon\Carbon::parse($nota->fecha_nacimiento)->age;
        
        if (!isset($nota->responsable)) {
             $nota->responsable = ''; // Fallback
        }
        
        $nota->cirujano = $nota->cirujano_nombre;
        $nota->anestesiologo = $nota->anestesiologo_nombre;
        $nota->ayudante = $nota->ayudante_nombre;
        $nota->instrumentador = $nota->instrumentador_nombre;
        $nota->circulante = $nota->circulante_nombre;
        
        $nota->via = $nota->via_acceso_nombre;
        $nota->tipo = $nota->tipo_cirugia_nombre;
        $nota->ambito = $nota->ambito_cirugia_nombre;
        $nota->finalidad = $nota->finalidad_nombre;
        $nota->tipo_anestesia = $nota->tipo_anestesia_nombre;
        
        // Complicacion
        $nota->tipo_diagnostico_complicacion = $nota->tipo_diagnostico_complicacion ?? null;

        $start = \Carbon\Carbon::parse($nota->hora_inicio);
        $end = \Carbon\Carbon::parse($nota->hora_fin);
        $diff = $start->diff($end); 
        $nota->duracion = $diff->format('%H:%I');

        $procedimientos = DB::table('hc_notas_operatorias_procedimientos as a')
            ->join('cups as b', 'a.procedimiento_qx', '=', 'b.cargo')
            ->where('a.hc_nota_operatoria_cirugia_id', $notaId)
            ->where('a.realizado', '1') 
            ->select(
                'a.procedimiento_qx', 
                'a.procedimiento_qx as cargo', 
                'b.descripcion as procedimiento_nombre', // legacy: descripcion
                'b.descripcion',
                'a.observaciones',
                'a.hc_nota_operatoria_cirugia_id'
            )
            ->get();
            
        // Enriquecer procedimientos con Ojos, Diagnosticos y Profesional
        foreach ($procedimientos as $proc) {
            // 1. Ojos (Evaluación)
            $proc->ojos = DB::table('qx_procedimientos_programacion as pp')
                ->join('hc_notas_operatorias_cirugias as hc', 'pp.programacion_id', '=', 'hc.programacion_id')
                ->where('hc.hc_nota_operatoria_cirugia_id', $notaId)
                ->where('pp.procedimiento_qx', $proc->procedimiento_qx)
                ->whereNotNull('pp.evaluacion_ojos')
                ->select('pp.evaluacion_ojos')
                ->get();

            // 2. Diagnosticos del procedimiento
            $proc->diagnosticos = DB::table('hc_notas_operatorias_procedimientos_diags as d')
                ->join('diagnosticos as diag', 'd.diagnostico_id', '=', 'diag.diagnostico_id')
                ->where('d.hc_nota_operatoria_cirugia_id', $notaId)
                ->where('d.procedimiento_qx', $proc->procedimiento_qx)
                ->select('d.*', 'diag.diagnostico_nombre')
                ->get();
            
            // 3. Profesional (Usamos el cirujano de la nota)
            $proc->profesional = $nota->cirujano_nombre;
        }

        $diagnosticos = DB::table('hc_notas_operatorias_procedimientos_diags as d')
            ->join('diagnosticos as diag', 'd.diagnostico_id', '=', 'diag.diagnostico_id')
            ->where('d.hc_nota_operatoria_cirugia_id', $notaId)
            ->select('d.*', 'diag.diagnostico_nombre', 'diag.diagnostico_id')
            ->distinct()
            ->get();
            
        $diagnosticosPost = DB::table('hc_diagnosticos_asociados_postqx_paciente_noc as post')
             ->join('diagnosticos as diag', 'post.diagnostico_post_qx', '=', 'diag.diagnostico_id')
             ->where('post.hc_nota_operatoria_cirugia_id', $notaId)
             ->select('post.*', 'diag.diagnostico_nombre as diagnostico_nombre_post_qx')
             ->get();

        $hallazgos = DB::table('hc_hallazgos_quirurgicos as h')
             ->leftJoin('system_usuarios as su', 'h.usuario_id', '=', 'su.usuario_id') 
             ->where('h.hc_nota_operatoria_cirugia_id', $notaId)
             ->select('h.descripcion', 'su.nombre as nombre_tercero') 
             ->get();

        $tecnicas = DB::table('hc_descripcion_cirugia as t')
             ->leftJoin('system_usuarios as su', 't.usuario_id', '=', 'su.usuario_id')
             ->where('t.hc_nota_operatoria_cirugia_id', $notaId)
             ->select('t.descripcion', 'su.nombre as nombre_tercero')
             ->get();

        // Patologias
        $patologias = DB::table('hc_patologia_quirurgicos as a')
             ->where('a.hc_nota_operatoria_cirugia_id', $notaId)
             ->select(
                 'a.*', 
                 'a.descripcion',
                 DB::raw("'Patología ' as nombre") 
             ) 
             ->orderBy('a.fecha_registro', 'desc')
             ->get();
             
        // Cultivos
        $cultivos = DB::table('hc_cultivos_quirurgicos as a')
             ->where('a.hc_nota_operatoria_cirugia_id', $notaId)
             ->select(
                 'a.*',
                 'a.descripcion',
                  DB::raw("'Cultivo ' as nombre")
             )
             ->orderBy('a.fecha_registro', 'desc')
             ->get();

        // --- ENRIQUECIMIENTO DE PATOLOGIAS (Tipos y Tejidos) ---
        $tiposPatologiasRef = DB::table('tipos_patologias')
            ->orderBy('indice_orden')
            ->orderBy('descripcion_patologia')
            ->get();

        foreach ($patologias as $pat) {
            // Tipos seleccionados (hc_tipos_patologias)
            $pat->tipos = DB::table('hc_tipos_patologias')
                ->where('patologia_id', $pat->patologia_id)
                ->get()
                ->toArray(); // Array of objects
            
            // Tejidos (hc_tipos_tejidos JOIN tipos_tejidos_qx)
            $pat->tejidos = DB::table('hc_tipos_tejidos as ht')
                ->join('tipos_tejidos_qx as tt', 'ht.tipo_tejido_qx_id', '=', 'tt.tipo_tejido_qx_id')
                ->where('ht.patologia_id', $pat->patologia_id)
                ->select('ht.*', 'tt.descripcion_tejido')
                ->get()
                ->toArray();
        }

        // --- GASES ANESTESICOS ---
        $gases = DB::table('hc_notaqx_gases_anestesicos as h')
            ->leftJoin('tipos_gases as g', 'h.tipo_gas_id', '=', 'g.tipo_gas_id')
            ->leftJoin('tipos_metodos_suministro_gases as m', 'h.tipo_suministro_id', '=', 'm.tipo_suministro_id')
            ->leftJoin('tipos_frecuencia_gases as f', 'h.frecuencia_id', '=', 'f.frecuencia_id')
            ->where('h.hc_nota_operatoria_cirugia_id', $notaId)
            ->select(
                'g.descripcion as tipo_gas',
                'm.descripcion as metodo',
                'h.frecuencia_id',
                'f.unidad as frecuencia_desc',
                'h.tiempo_suministro as minutos'
            )
            ->get();

        $logoBase64 = null;
        $pathLogo = public_path('assets/images/simde_logo.png');
        if (file_exists($pathLogo)) {
            $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
            $dataImg = file_get_contents($pathLogo);
            $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($dataImg);
        }
        
        // Firma Image
        $firmaBase64 = null;
        if (!empty($nota->firma)) {
            // Limpiar y manejar encoding
            $firmaClean = trim($nota->firma);
            $fileFirmaEncoded = str_replace('*', '%2A', $firmaClean);

            // 1) Intentar por FILESYSTEM Legacy
            $basePath = env('LEGACY_PATH');
            $pathLegacy = $basePath . '/images/firmas_profesionales/' . $fileFirmaEncoded;
            
            // 1.1) Intentar path local actual (por si acaso han movido imágenes)
            $pathLocal = public_path('images/firmas_profesionales/' . $fileFirmaEncoded);

            $finalPath = null;
            if (file_exists($pathLegacy)) {
                $finalPath = $pathLegacy;
            } elseif (file_exists($pathLocal)) {
                $finalPath = $pathLocal;
            }

            if ($finalPath) {
                $ext = strtolower(pathinfo($finalPath, PATHINFO_EXTENSION));
                $mime = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) ? $ext : 'jpeg';
                $firmaBase64 = 'data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($finalPath));
            } else {
                // 2) Fallback por URL (External request)
                $baseUrl = env('LEGACY_URL');
                $firmaUrl = $baseUrl . '/images/firmas_profesionales/' . $fileFirmaEncoded;

                try {
                    $imgResp = Http::timeout(8)->get($firmaUrl);
                    if ($imgResp->ok()) {
                        $contentType = $imgResp->header('Content-Type') ?: 'image/jpeg';
                        $firmaBase64 = 'data:' . $contentType . ';base64,' . base64_encode($imgResp->body());
                    }
                } catch (\Throwable $e) {
                    // Fallo silencioso
                    Log::error("Error fetching firma from URL: " . $e->getMessage());
                }
            }
        }
        // Prepare profesional object for blade
        $profesional = (object)[
            'nombre' => $nota->cirujano_nombre,
            'tarjeta_profesional' => $nota->tarjeta_profesional,
            'registro_salud_departamental' => $nota->registro_salud_departamental,
            'descripcion' => $nota->especialidad_profesional,
            'especialidad' => $nota->especialidad_profesional,
            'tipo_id_tercero' => $nota->tipo_id_cirujano,
            'tercero_id' => $nota->cirujano_id,
            'prof_id' => $nota->cirujano_id // Alias for blade compatibility check
        ];

        return [
            'empresa' => $empresa,
            'paciente' => $nota,
            'nota' => $nota, 
            'edad' => $nota->edad, 
            'procedimientos' => $procedimientos,
            'diagnosticos' => $diagnosticos,
            'diagnosticosPostQx' => $diagnosticosPost,
            'hallazgos' => $hallazgos,
            'descripcionesTecnicas' => $tecnicas, 
            'gases' => $gases,
            'patologias' => $patologias,
            'cultivos' => $cultivos,
            'profesional' => $profesional, 
            'firmaBase64' => $firmaBase64,
            'tips' => [], 
            'tiposPatologiasRef' => $tiposPatologiasRef, 
            'logoBase64' => $logoBase64,
            'fecha_impresion' => date('Y-m-d H:i:s')
        ];
    }
}

