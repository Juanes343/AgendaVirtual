<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    {{-- Estilos heredados del legacy --}}
    <style>
        body { font-family: Arial, sans-serif; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        td, th { border: 1px solid #000; padding: 2px; vertical-align: top; }
        .normal_10N { font-weight: bold; background-color: #f0f0f0; text-transform: uppercase; font-size: 8px; }
        .normal_10 { font-size: 8px; }
        .no-border { border: none !important; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .header-title { font-size: 11px; font-weight: bold; text-align: center; margin: 6px 0; }
        
        /* Estilos de firma */
        .footer-signature { margin-top: 30px; }
        .signature-line { border-top: 1px solid #000; width: 350px; padding-top: 5px; }
        .prof-info { font-weight: bold; line-height: 1.3; font-size: 8px; }
        .firma-img { max-width: 200px; max-height: 70px; display: block; margin-bottom: 5px; }
    </style>
</head>
<body>

    {{-- ENCABEZADO LOGO Y EMPRESA --}}
    <table style="border: none !important; margin-bottom: 0;">
        <tr>
            <td style="border: none !important; width: 20%; vertical-align: middle;">
                @if(!empty($logoBase64))
                <img src="{{ $logoBase64 }}" alt="Logo" style="max-width: 150px; max-height: 60px;">
                @endif
            </td>
            <td style="border: none !important; text-align: center; font-size: 10px; font-weight: bold; vertical-align: middle;">
                @if(!empty($empresa))
                {{ $empresa->razon_social ?? '' }}<br>
                NIT {{ $empresa->nit ?? '' }}<br>
                {{ $empresa->direccion ?? '' }} - {{ $empresa->municipio ?? '' }}<br>
                Teléfono: {{ $empresa->telefonos ?? '' }}<br>
                @endif
            </td>
            <td style="border: none !important; width: 20%;"></td>
        </tr>
    </table>

    <div class="header-title">NOTA OPERATORIA</div>

    {{-- ENCABEZADO PACIENTE --}}
    <table>
        <tr>
            <td colspan="4" class="normal_10N text-center">DATOS PACIENTE</td>
        </tr>
        <tr>
            <td class="normal_10N" width="20%">Nº INGRESO</td>
            <td class="normal_10" width="30%">{{ $paciente->ingreso }}</td>
            <td class="normal_10N" width="20%">FECHA INGRESO</td>
            <td class="normal_10" width="30%">{{ $paciente->fecha_ingreso }}</td>
        </tr>
        <tr>
            <td class="normal_10N">Nº CUENTA</td>
            <td class="normal_10">{{ $paciente->numerodecuenta }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="normal_10N">PACIENTE</td>
            <td class="normal_10">{{ $paciente->tipo_id_paciente }} {{ $paciente->paciente_id }}</td>
            <td class="normal_10" colspan="2">{{ $paciente->nombres }} {{ $paciente->apellidos }}</td>
        </tr>
        <tr>
            <td class="normal_10N">EDAD</td>
            <td class="normal_10">{{ $edad }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="normal_10N">DIRECCION</td>
            <td class="normal_10">{{ $paciente->residencia_direccion }}</td>
            <td class="normal_10N">TELÉFONO</td>
            <td class="normal_10">{{ $paciente->residencia_telefono }}</td>
        </tr>
        <tr>
            <td class="normal_10N">ENTIDAD</td>
            <td class="normal_10">{{ $paciente->tipo_tercero_id }} {{ $paciente->tercero_id }}</td>
            <td class="normal_10" colspan="2">{{ $paciente->nombre_tercero }}</td>
        </tr>
        <tr>
            <td class="normal_10N">PLAN</td>
            <td class="normal_10" colspan="3">{{ $paciente->plan_descripcion }}</td>
        </tr>
        <tr>
            <td class="normal_10N">VIA DE INGRESO</td>
            <td class="normal_10">{{ $paciente->via_ingreso_nombre }}</td>
            <td class="normal_10N" colspan="2">RESPONSABLE: {{ $paciente->responsable }}</td>
        </tr>
    </table>

    {{-- DATOS PROCEDIMIENTO --}}
    <table>
        <tr>
            <td colspan="4" class="normal_10N text-center">DATOS DEL PROCEDIMIENTO</td>
        </tr>
        <tr>
            <td class="normal_10N" width="20%">FECHA INICIO</td>
            <td class="normal_10" width="30%">{{ $nota->hora_inicio }}</td>
            <td class="normal_10N" width="20%">FECHA FIN</td>
            <td class="normal_10" width="30%">{{ $nota->hora_fin }}</td>
        </tr>
        <tr>
            <td class="normal_10N">QUIROFANO</td>
            <td class="normal_10" colspan="3">{{ $nota->nom_quirofano }}</td>
        </tr>
        <tr>
            <td class="normal_10N">VIA ACCESO</td>
            <td class="normal_10">{{ $nota->via ?? 'SIN ASIGNAR' }}</td>
            <td class="normal_10N">TIPO CIRUGIA</td>
            <td class="normal_10">{{ $nota->tipo ?? 'SIN ASIGNAR' }}</td>
        </tr>
        <tr>
            <td class="normal_10N">AMBITO CIRUGIA</td>
            <td class="normal_10">{{ $nota->ambito ?? 'SIN ASIGNAR' }}</td>
            <td class="normal_10N">FINALIDAD CIRUGIA</td>
            <td class="normal_10">{{ $nota->finalidad ?? 'SIN ASIGNAR' }}</td>
        </tr>
        
        <tr><td colspan="4" class="normal_10N">EQUIPO QUIRURGICO</td></tr>
        <tr>
            <td class="normal_10N">CIRUJANO</td>
            <td class="normal_10">{{ $nota->cirujano }}</td>
            <td class="normal_10N">ANESTESIOLOGO</td>
            <td class="normal_10">{{ $nota->anestesiologo }}</td>
        </tr>
        <tr>
            <td class="normal_10N">AYUDANTE</td>
            <td class="normal_10">{{ $nota->ayudante }}</td>
            <td class="normal_10N">INSTRUMENTADOR</td>
            <td class="normal_10">{{ $nota->instrumentador }}</td>
        </tr>
        <tr>
            <td class="normal_10N">CIRCULANTE</td>
            <td class="normal_10">{{ $nota->circulante }}</td>
            <td class="normal_10N">TIPO ANESTESIA</td>
            <td class="normal_10">{{ $nota->tipo_anestesia ?? 'SIN ASIGNAR' }}</td>
        </tr>
    </table>

    {{-- GASES --}}
    @if(!empty($gases))
    <table>
        <tr><td colspan="4" class="normal_10N text-center">GASES UTILIZADOS</td></tr>
        <tr class="normal_10N">
            <td>TIPO GAS</td>
            <td>METODO SUMINISTRO</td>
            <td>FRECUENCIA</td>
            <td>MINUTOS</td>
        </tr>
        @foreach($gases as $gas)
        <tr class="normal_10">
            <td>{{ $gas->tipo_gas }}</td>
            <td>{{ $gas->metodo }}</td>
            <td>{{ $gas->frecuencia_id }} / {{ $gas->frecuencia_desc }}</td>
            <td>{{ $gas->minutos }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- PROCEDIMIENTOS --}}
    <table>
        <tr><td colspan="3" class="normal_10N text-center">PROCEDIMIENTOS REALIZADOS</td></tr>
        <tr class="normal_10N">
            <td width="20%">CARGO</td>
            <td colspan="2">DESCRIPCION</td>
        </tr>
        @foreach($procedimientos as $proc)
        <tr class="normal_10">
            <td valign="top">{{ $proc->procedimiento_qx }}</td>
            <td colspan="2">
                <b>{{ $proc->descripcion }}</b><br>
                
                {{-- Ojos --}}
                @foreach($proc->ojos as $ojo)
                     <i>OJO EVALUADO: 
                     @if($ojo->evaluacion_ojos == '1') OJO DERECHO
                     @elseif($ojo->evaluacion_ojos == '2') OJO IZQUIERDO
                     @elseif($ojo->evaluacion_ojos == '3') AMBOS OJOS
                     @endif
                     </i><br>
                @endforeach
                
                {{-- Profesional --}}
                PROFESIONAL: {{ $proc->profesional }}<br>

                @if($proc->observaciones)
                    OBSERVACIONES: {{ $proc->observaciones }}<br>
                @endif
                
                {{-- Diagnosticos --}}
                @if(!empty($proc->diagnosticos))
                    <br>
                    <table style="width:100%; margin-top:5px;">
                        <tr class="normal_10N"><td>PRE-QX</td><td>CODIGO</td><td>DIAGNOSTICO</td></tr>
                        @foreach($proc->diagnosticos as $diag)
                        <tr>
                            <td>{{ $diag->sw_principal == '1' ? 'PRINCIPAL' : 'RELACIONADO' }}</td>
                            <td>{{ $diag->diagnostico_id }}</td>
                            <td>{{ $diag->diagnostico_nombre }}</td>
                        </tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
        @endforeach
    </table>

    {{-- DIAGNOSTICOS POST QX --}}
    @if(!empty($diagnosticosPostQx) || $nota->diag_nom1)
    <table>
        <tr><td colspan="4" class="normal_10N text-center">DIAGNOSTICOS POST-QUIRURGICOS</td></tr>
        @foreach($diagnosticosPostQx as $diagPost)
        <tr>
             <td class="normal_10N">POST QX</td>
             <td class="normal_10">{{ $diagPost->diagnostico_nombre_post_qx }}</td>
             <td class="normal_10N">TIPO</td>
             <td class="normal_10">
                 @if($diagPost->tipo_diagnostico_post_qx == '1') IMPRESION DIAGNOSTICA
                 @elseif($diagPost->tipo_diagnostico_post_qx == '2') CONFIRMADO NUEVO
                 @else CONFIRMADO REPETIDO @endif
             </td>
        </tr>
        @endforeach
        @if($nota->diag_nom1)
        <tr>
             <td class="normal_10N">COMPLICACION</td>
             <td class="normal_10">{{ $nota->diag_nom1 }}</td>
             <td class="normal_10N">TIPO</td>
             <td class="normal_10">
                 @if($nota->tipo_diagnostico_complicacion == '1') IMPRESION DIAGNOSTICA
                 @elseif($nota->tipo_diagnostico_complicacion == '2') CONFIRMADO NUEVO
                 @else CONFIRMADO REPETIDO @endif
             </td>
        </tr>
        @endif
    </table>
    @endif

    {{-- HALLAZGOS --}}
    @if(!empty($hallazgos))
    <table>
        <tr><td class="normal_10N text-center">HALLAZGOS QUIRURGICOS</td></tr>
        @foreach($hallazgos as $h)
        <tr>
            <td class="normal_10">
                Por: {{ $h->nombre_tercero }}<br><br>
                {!! nl2br($h->descripcion) !!}
            </td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- DESCRIPCIONES TECNICAS --}}
    @if(!empty($descripcionesTecnicas))
    <table>
        <tr><td class="normal_10N text-center">DESCRIPCIONES TECNICAS QUIRURGICAS</td></tr>
        @foreach($descripcionesTecnicas as $h)
        <tr>
            <td class="normal_10">
                Por: {{ $h->nombre_tercero }}<br><br>
                {!! nl2br(strtoupper($h->descripcion)) !!}
            </td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- PATOLOGIAS --}}
    @if(!empty($patologias))
    <table>
        <tr><td colspan="2" class="normal_10N text-center">MATERIALES PATOLOGICOS</td></tr>
        @foreach($patologias as $pat)
        <tr>
            <td colspan="2">
                <b>{{ $pat->nombre }}</b> - ENVIADO A PATOLOGIA: {{ $pat->envio_patologico == '1' ? 'SI' : 'NO' }}<br>
                DESCRIPCION: {!! nl2br($pat->descripcion) !!}
                
                @if(isset($pat->registro_patologia))
                    <br><br>
                    <b>REGISTRO PATOLOGIA Nº: {{ $pat->registro_patologia }}</b> | BIOPSIAS PREVIAS: {{ $pat->biopsa_previa == '1' ? 'SI' : 'NO' }}
                    <br>
                    <u>TIPOS PATOLOGIA:</u>
                    @php $tiposSeleccionados = array_map(function($t){ return $t->tipo_patologia_id; }, $pat->tipos); @endphp
                    @foreach($tiposPatologiasRef as $ref)
                        [{{ in_array($ref->tipo_patologia_id, $tiposSeleccionados) ? 'X' : ' ' }}] {{ $ref->descripcion_patologia }} &nbsp;
                    @endforeach
                    <br>
                    <u>TEJIDOS:</u>
                    @foreach($pat->tejidos as $tej)
                        - {{ $tej->descripcion_tejido }}<br>
                    @endforeach
                @endif
            </td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- CULTIVOS --}}
    @if(!empty($cultivos))
    <table>
        <tr><td class="normal_10N text-center">CULTIVOS</td></tr>
        @foreach($cultivos as $cul)
        <tr>
            <td class="normal_10">
                <b>{{ $cul->nombre }}</b> - ENVIADO: {{ $cul->envio_cultivo == '1' ? 'SI' : 'NO' }}<br>
                DESCRIPCION: {!! nl2br($cul->descripcion) !!}
            </td>
        </tr>
        @endforeach
    </table>
    @endif

    {{-- FIRMA --}}
    @if($profesional)
    <div class="footer-signature">
        @if(!empty($firmaBase64))
        <div style="margin-bottom:5px;">
            <img src="{{ $firmaBase64 }}" alt="Firma" class="firma-img">
        </div>
        @else
        <br><br><br>
        @endif

        <div class="signature-line">
            <div class="prof-info">
                PROFESIONAL: {{ $profesional->nombre }}<br>
                {{ $profesional->especialidad }}<br>
                REGISTRO MEDICO: {{ $profesional->tarjeta_profesional }}<br>
                CC: {{ $profesional->tercero_id }}<br>
            </div>
        </div>
    </div>
    @endif

</body>
</html>
