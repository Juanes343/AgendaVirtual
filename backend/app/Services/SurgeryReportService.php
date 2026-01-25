<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SurgeryReportService
{
    /**
     * Obtiene el listado de cirugías (notas operatorias) para un ingreso.
     */
    public function getSurgeriesByIngreso($ingresoId)
    {
        return DB::select("
            SELECT 
                noc.hc_nota_operatoria_cirugia_id,
                noc.programacion_id,
                noc.evolucion_id,
                noc.hora_inicio,
                noc.hora_fin,
                qx.descripcion as nom_quirofano,
                tc.descripcion as tipo_cirugia,
                prof_ter.nombre_tercero as cirujano_nombre,
                to_char(noc.hora_inicio, 'YYYY-MM-DD HH24:MI') as fecha_hora
            FROM hc_notas_operatorias_cirugias noc
            JOIN hc_evoluciones evo ON noc.evolucion_id = evo.evolucion_id
            LEFT JOIN qx_quirofanos qx ON noc.quirofano_id = qx.quirofano
            LEFT JOIN qx_tipos_cirugia tc ON noc.tipo_cirugia = tc.tipo_cirugia_id
            LEFT JOIN terceros prof_ter ON noc.cirujano_id = prof_ter.tercero_id 
                                       AND noc.tipo_id_cirujano = prof_ter.tipo_id_tercero
            WHERE evo.ingreso = ?
            ORDER BY noc.hora_inicio DESC
        ", [$ingresoId]);
    }

    /**
     * Obtiene todos los datos necesarios para imprimir la Nota Operatoria.
     * Replica la lógica de reporteNotaOperatoria_html.report.php
     */
    public function getSurgeryReportData($notaId)
    {
        // 1. Datos Principales de la Cirugía
        $nota = DB::selectOne("
            SELECT  a.hc_nota_operatoria_cirugia_id,
                      a.quirofano_id,
                      x.descripcion as nom_quirofano,
                      a.hora_inicio,a.hora_fin,
                      a.via_acceso,
                      b.descripcion as via,
                      a.tipo_cirugia,
                      c.descripcion as tipo,
                      a.ambito_cirugia,
                      d.descripcion as ambito,
                      a.finalidad_procedimiento_id,
                      e.descripcion as finalidad,
                      a.justificacion_norealizados,
                      a.diagnostico_id_complicacion,
                      diag1.diagnostico_nombre as diag_nom1,
                      a.tipo_diagnostico_complicacion,
                      ter.nombre_tercero as instrumentador,
                      ter1.nombre_tercero as circulante,
                      ter2.nombre_tercero as anestesiologo,
                      ter3.nombre_tercero as ayudante,
                      ter4.nombre_tercero as cirujano,
                      a.evolucion_id,evol.ingreso,
                      a.programacion_id,
                      tipo_anes.descripcion as tipo_anestesia,
                      -- Datos adicionales para el reporte
                      a.tipo_id_cirujano,
                      a.cirujano_id
            FROM    hc_notas_operatorias_cirugias a
            LEFT JOIN qx_quirofanos x ON (a.quirofano_id=x.quirofano)
            LEFT JOIN qx_vias_acceso b ON (a.via_acceso=b.via_acceso)
            LEFT JOIN qx_tipos_cirugia c ON (a.tipo_cirugia=c.tipo_cirugia_id)
            LEFT JOIN qx_ambitos_cirugias d ON (a.ambito_cirugia=d.ambito_cirugia_id)
            LEFT JOIN qx_finalidades_procedimientos e ON (a.finalidad_procedimiento_id=e.finalidad_procedimiento_id)
            LEFT JOIN diagnosticos diag1 ON (a.diagnostico_id_complicacion=diag1.diagnostico_id)
            LEFT JOIN terceros ter ON (a.tipo_id_instrumentista=ter.tipo_id_tercero AND a.instrumentista_id=ter.tercero_id)
            LEFT JOIN terceros ter1 ON (a.tipo_id_circulante=ter1.tipo_id_tercero AND a.circulante_id=ter1.tercero_id)
            LEFT JOIN terceros ter2 ON (a.tipo_id_anestesiologo=ter2.tipo_id_tercero AND a.anestesiologo_id=ter2.tercero_id)
            LEFT JOIN terceros ter3 ON (a.tipo_id_ayudante=ter3.tipo_id_tercero AND a.ayudante_id=ter3.tercero_id)
            LEFT JOIN terceros ter4 ON (a.tipo_id_cirujano=ter4.tipo_id_tercero AND a.cirujano_id=ter4.tercero_id)
            LEFT JOIN qx_tipos_anestesia tipo_anes ON (tipo_anes.qx_tipo_anestesia_id=a.qx_tipo_anestesia_id),
                    hc_evoluciones evol
            WHERE   a.hc_nota_operatoria_cirugia_id = ?
            AND     a.evolucion_id=evol.evolucion_id
        ", [$notaId]);

        if (!$nota) {
            throw new \Exception("Nota operatoria no encontrada: $notaId");
        }

        $ingresoId = $nota->ingreso;
        $programacionId = $nota->programacion_id;

        // 2. Datos del Paciente e Ingreso
        // Nota: Asumo que existe un helper o servicio para esto, pero lo incluyo aquí como en el legacy
        $paciente = DB::selectOne("
            SELECT 	PC.paciente_id,
                    PC.tipo_id_paciente,
                    PC.primer_apellido ||' '|| PC.segundo_apellido AS apellidos,
                    PC.primer_nombre ||' '|| PC.segundo_nombre AS nombres,
                    PC.fecha_nacimiento,
                    PC.residencia_direccion,
                    PC.residencia_telefono,
                    IG.ingreso,
                    TO_CHAR(IG.fecha_ingreso,'DD/MM/YYYY HH12:MI am') AS fecha_ingreso,
                    CU.numerodecuenta,
                    VI.via_ingreso_nombre,
                    TC.nombre_tercero,
                    PL.plan_descripcion,
                    PL.tipo_tercero_id,
                    PL.tercero_id,
                    SU.nombre AS responsable
            FROM	pacientes PC,
                    vias_ingreso VI,
                    ingresos IG 
                    LEFT JOIN pacientes_urgencias PU ON( IG.ingreso = PU.ingreso)
                    JOIN system_usuarios SU ON (SU.usuario_id = IG.usuario_id),
                    cuentas CU,
                    planes PL,
                    terceros TC
            WHERE	IG.paciente_id = PC.paciente_id
            AND		IG.tipo_id_paciente = PC.tipo_id_paciente
            AND		VI.via_ingreso_id = IG.via_ingreso_id
            AND		CU.ingreso = IG.ingreso
            AND		PL.plan_id = CU.plan_id
            AND		PL.tipo_tercero_id = TC.tipo_id_tercero
            AND		PL.tercero_id = TC.tercero_id
            AND		IG.ingreso = ?
        ", [$ingresoId]);

        // Calcular edad (simple approximation)
        $edad = \Carbon\Carbon::parse($paciente->fecha_nacimiento)->age . ' AÑOS';


        // 3. Gases Anestésicos
        $gases = DB::select("
             select  tg.descripcion as tipo_gas,
                     tms.descripcion as metodo,
                     h.frecuencia_id,
                     h.tiempo_suministro as minutos,
                     tfg.unidad,
                     tfg.unidad as frecuencia_desc
             from    hc_notaqx_gases_anestesicos h
             join    tipos_gases tg on h.tipo_gas_id = tg.tipo_gas_id
             join    tipos_metodos_suministro_gases tms on h.tipo_suministro_id = tms.tipo_suministro_id
             join    tipos_frecuencia_gases tfg on h.frecuencia_id = tfg.frecuencia_id
             where   h.hc_nota_operatoria_cirugia_id = ?
        ", [$notaId]);

        // 4. Procedimientos
        $procedimientos = DB::select("
            SELECT
                a.procedimiento_qx,
                b.descripcion,
                a.observaciones,
                ter.nombre_tercero as profesional,
                usuprof.tarjeta_profesional
            FROM
                hc_notas_operatorias_procedimientos a
                JOIN cups b ON a.procedimiento_qx=b.cargo
                JOIN hc_notas_operatorias_cirugias c ON c.hc_nota_operatoria_cirugia_id=a.hc_nota_operatoria_cirugia_id
                JOIN profesionales_usuarios prof ON c.usuario_id=prof.usuario_id
                JOIN terceros ter ON prof.tipo_tercero_id=ter.tipo_id_tercero AND prof.tercero_id=ter.tercero_id
                JOIN profesionales usuprof ON usuprof.tipo_id_tercero=ter.tipo_id_tercero AND usuprof.tercero_id=ter.tercero_id
            WHERE
                a.hc_nota_operatoria_cirugia_id = ?
                AND a.realizado='1'
        ", [$notaId]);

        // Enriquecer procedimientos con descripción de ojo y diagnósticos
        foreach ($procedimientos as &$proc) {
            $proc->ojos = DB::select("
                SELECT PP.evaluacion_ojos 
                FROM qx_procedimientos_programacion PP
                JOIN hc_notas_operatorias_cirugias HC ON HC.programacion_id = PP.programacion_id
                WHERE HC.hc_nota_operatoria_cirugia_id = ?
                AND PP.procedimiento_qx = ?
                AND PP.evaluacion_ojos != 'NULL'
            ", [$notaId, $proc->procedimiento_qx]);

            $proc->diagnosticos = DB::select("
                SELECT a.diagnostico_id, b.diagnostico_nombre, a.tipo_diagnostico, a.sw_principal
                FROM hc_notas_operatorias_procedimientos_diags a
                JOIN diagnosticos b ON a.diagnostico_id=b.diagnostico_id
                WHERE a.hc_nota_operatoria_cirugia_id = ?
                AND a.procedimiento_qx = ?
            ", [$notaId, $proc->procedimiento_qx]);
        }

        // 5. Diagnósticos Post-QX
        $diagnosticosPostQx = DB::select("
            SELECT hcdapqxp.diagnostico_post_qx,
                   hcdapqxp.tipo_diagnostico_post_qx,
                   b.diagnostico_nombre as diagnostico_nombre_post_qx
            FROM hc_diagnosticos_asociados_postqx_paciente_noc hcdapqxp
            JOIN diagnosticos b ON hcdapqxp.diagnostico_post_qx = b.diagnostico_id
            WHERE hcdapqxp.hc_nota_operatoria_cirugia_id = ?
        ", [$notaId]);

        // 6. Hallazgos
        $hallazgos = DB::select("
           SELECT  a.descripcion, ter.nombre_tercero
           FROM    hc_hallazgos_quirurgicos a
           JOIN    profesionales_usuarios prof ON a.usuario_id=prof.usuario_id
           JOIN    terceros ter ON prof.tipo_tercero_id=ter.tipo_id_tercero AND prof.tercero_id=ter.tercero_id
           WHERE   a.hc_nota_operatoria_cirugia_id = ?
        ", [$notaId]);

        // 7. Descripciones Técnicas
        $descripcionesTecnicas = DB::select("
           SELECT  a.descripcion, ter.nombre_tercero
           FROM    hc_descripcion_cirugia a
           JOIN    profesionales_usuarios prof ON a.usuario_id=prof.usuario_id
           JOIN    terceros ter ON prof.tipo_tercero_id=ter.tipo_id_tercero AND prof.tercero_id=ter.tercero_id
           WHERE   a.hc_nota_operatoria_cirugia_id = ?
        ", [$notaId]);

        // 8. Patologías
        $patologias = DB::select("
            SELECT  A.patologia_id,
                    A.descripcion,
                    B.nombre,
                    A.envio_patologico,
                    A.biopsa_previa,
                    A.registro_patologia
            FROM    hc_patologia_quirurgicos A
            JOIN    system_usuarios B ON B.usuario_id=A.usuario_id
            WHERE   A.hc_nota_operatoria_cirugia_id = ?
            ORDER BY A.fecha_registro DESC
        ", [$notaId]);

        // Detalle Patologías (Tipos y Tejidos)
        foreach ($patologias as &$pat) {
             if (is_numeric($pat->registro_patologia)) {
                 $pat->tejidos = DB::select("
                    SELECT TE.descripcion_tejido 
                    FROM hc_tipos_tejidos HT
                    JOIN tipos_tejidos_qx TE ON HT.tipo_tejido_qx_id = TE.tipo_tejido_qx_id
                    WHERE HT.patologia_id = ?
                 ", [$pat->patologia_id]);
                 
                 $pat->tipos = DB::select("
                    SELECT tipo_patologia_id FROM hc_tipos_patologias WHERE patologia_id = ?
                 ", [$pat->patologia_id]);
             }
        }
        
        $tiposPatologiasRef = [];
        if (!empty($patologias)) {
             $tiposPatologiasRef = DB::select("SELECT tipo_patologia_id, descripcion_patologia FROM tipos_patologias ORDER BY indice_orden");
        }


        // 9. Cultivos
        $cultivos = DB::select("
             SELECT A.descripcion, B.nombre, A.envio_cultivo
             FROM hc_cultivos_quirurgicos A
             JOIN system_usuarios B ON B.usuario_id=A.usuario_id
             WHERE A.hc_nota_operatoria_cirugia_id = ?
        ", [$notaId]);

        // 10. Profilaxis
        $profilaxis = DB::select("
             SELECT PR.descripcion_profilaxis, SU.nombre as usuario
             FROM hc_notas_operatorias_profilaxis PR
             JOIN system_usuarios SU ON PR.usuario_id = SU.usuario_id
             WHERE PR.hc_nota_operatoria_cirugia_id = ?
        ", [$notaId]);

        // 11. Datos Profesional (Firma)
        $profesional = DB::selectOne("
            SELECT PF.nombre,
                   PF.tarjeta_profesional,
                   PF.firma,
                   ES.descripcion as especialidad,
                   PF.registro_salud_departamental
            FROM hc_notas_operatorias_cirugias HC
            JOIN profesionales PF ON HC.tipo_id_cirujano = PF.tipo_id_tercero AND HC.cirujano_id = PF.tercero_id
            JOIN profesionales_especialidades PE ON PF.tipo_id_tercero = PE.tipo_id_tercero AND PF.tercero_id = PE.tercero_id
            JOIN especialidades ES ON PE.especialidad = ES.especialidad
            WHERE HC.hc_nota_operatoria_cirugia_id = ?
        ", [$notaId]);
        
        // Firma base64 
        $firmaBase64 = null;
        if ($profesional && $profesional->firma) {
             // Ajustar path según ubicación real en servidor
             $pathFirma = "/var/www/html/php74/PRUEBAS_SANDIEGO_RIPS/images/firmas_profesionales/" . $profesional->firma;
             if (file_exists($pathFirma)) {
                 $data = file_get_contents($pathFirma);
                 $type = pathinfo($pathFirma, PATHINFO_EXTENSION);
                 $firmaBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
             }
        }

        // Logo base64
        $logoBase64 = null;
        $pathLogo = "/var/www/html/php74/PRUEBAS_SANDIEGO_RIPS/images/logocliente.png"; 
        if (file_exists($pathLogo)) {
             $data = file_get_contents($pathLogo);
             $type = pathinfo($pathLogo, PATHINFO_EXTENSION);
             $logoBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        return [
            'nota' => $nota,
            'paciente' => $paciente,
            'edad' => $edad,
            'gases' => $gases,
            'procedimientos' => $procedimientos,
            'logoBase64' => $logoBase64,
            'diagnosticosPostQx' => $diagnosticosPostQx,
            'hallazgos' => $hallazgos,
            'descripcionesTecnicas' => $descripcionesTecnicas,
            'patologias' => $patologias,
            'tiposPatologiasRef' => $tiposPatologiasRef,
            'cultivos' => $cultivos,
            'profilaxis' => $profilaxis,
            'profesional' => $profesional,
            'firmaBase64' => $firmaBase64,
            'empresa' => (object)[ // Mock o traer de DB
                'razon_social' => 'CLINICA SAN DIEGO', // Ajustar
                'nit' => '900.000.000',
                'direccion' => 'AV 1 15 04 LA PLAYA',
                'municipio' => 'CUCUTA',
                'telefonos' => '6075960150'
            ]
        ];
    }
}