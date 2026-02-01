<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Resultado Apoyo Diagnóstico</title>
    <style>
        @page {
            margin: 20px 30px;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 9px;
            color: #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-datatable td,
        .table-datatable th {
            border: 1px solid #000;
            padding: 4px;
            vertical-align: top;
        }

        .header-label {
            background-color: #f0f0f0;
            font-weight: bold;
            width: 15%;
        }

        .header-container {
            width: 100%;
            margin-bottom: 10px;
        }

        .logo-cell {
            width: 15%;
            vertical-align: middle;
        }

        .info-cell {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
        }

        .logo-img {
            max-width: 100px;
            max-height: 60px;
        }

        .doc-title {
            text-align: center;
            font-weight: bold;
            margin: 10px 0;
            font-size: 11px;
            background-color: #f0f0f0;
            border: 1px solid #000;
            padding: 5px;
        }

        .result-section {
            margin-top: 10px;
        }

        .label_error {
            color: red;
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .bold {
            font-weight: bold;
        }
        
        blockquote {
            margin: 5px 10px;
            text-align: justify;
            font-size: 9px;
        }

        .footer-signature {
            margin-top: 40px;
        }

        .signature-box {
            display: inline-block;
            vertical-align: top;
            width: 45%; 
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 90%;
            padding-top: 5px;
        }

        .prof-info {
            font-weight: bold;
            line-height: 1.3;
            font-size: 9px;
        }

        .firma-img {
            max-width: 200px;
            max-height: 70px;
            display: block;
            margin-bottom: 5px;
        }

        .print-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            font-size: 8px;
            text-align: right;
            color: #555;
        }
    </style>
</head>

<body>

    <!-- Encabezado con Logo y Empresa -->
    <table class="header-container">
        <tr>
            <td class="logo-cell">
                @if(!empty($logoBase64))
                <img src="{{ $logoBase64 }}" alt="Logo" class="logo-img">
                @endif
            </td>
            <td class="info-cell">
                @if(!empty($empresa))
                    {{ $empresa->razon_social ?? '' }}<br>
                    NIT {{ $empresa->nit ?? '' }}-{{ $empresa->digito_verificacion ?? '' }}<br>
                    {{ $empresa->direccion ?? '' }} - {{ $empresa->municipio ?? '' }}, {{ $empresa->departamento ?? '' }}<br>
                    Teléfono: {{ $empresa->telefonos ?? '' }}<br>
                    {{ $empresa->website ?? '' }}
                @else
                    {{ $header->laboratorio ?? 'CLÍNICA' }}<br>
                    NIT {{ $header->id ?? '' }}<br>
                @endif
            </td>
        </tr>
    </table>

    <div class="doc-title">RESULTADO DE APOYO DIAGNOSTICO</div>

    <!-- Datos Paciente / Orden -->
    <table class="table-datatable">
        <tr>
            <td class="header-label">NO. ORDEN</td>
            <td width="35%">{{ $header->numero_orden_id ?? '' }}</td>
            <td class="header-label">FECHA</td>
            <td>{{ $header->fecha_cumplimiento ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-label">PACIENTE</td>
            <td>{{ ($header->tipo_id_paciente ?? '') . ' ' . ($header->paciente_id ?? '') }} - {{ $header->nombre ?? '' }}</td>
            <td class="header-label">EDAD / SEXO</td>
            <td>{{ $header->edad_paciente ?? '' }} / {{ $header->sexo_paciente ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-label">PLAN / ENTIDAD</td>
            <td colspan="3">{{ $header->plan_descripcion ?? '' }} - {{ $header->laboratorio ?? '' }}</td>
        </tr>
        @if($header->eps_punto_atencion_nombre)
        <tr>
            <td class="header-label">PUNTO ATENCION</td>
            <td colspan="3">{{ $header->eps_punto_atencion_nombre }}</td>
        </tr>
        @endif
        <tr>
            <td class="header-label">EXAMEN</td>
            <td colspan="3" class="bold" style="font-size: 10px;">{{ $header->cargo ?? '' }} - {{ strtoupper($header->titulo ?? '') }}</td>
        </tr>
    </table>

    <!-- Loop Resultados -->
    <div class="result-section">
        @php
            $currentTemplate = null;
            $count = 0;
        @endphp

        @foreach($details as $index => $detail)
            @php
                 $sw = ($count == 0 || $details[$index-1]->lab_plantilla_id != $detail->lab_plantilla_id);
                 $count++;
            @endphp

            <!-- Plantilla 1: Tabla Estandar -->
            @if($detail->lab_plantilla_id == '1')
                @if($sw)
                <table class="table-datatable" style="margin-top: 10px;">
                    <tr class="header-label center">
                        <th width="20%">SUBEXAMEN</th>
                        <th width="20%">RESULTADO</th>
                        <th width="10%">V.MIN</th>
                        <th width="10%">V.MAX</th>
                        <th width="10%">UND</th>
                        <th width="5%">PAT.</th>
                        <th width="25%">NORMALIDADES</th>
                    </tr>
                @endif
                
                <tr>
                    <td>{{ strtoupper($detail->nombre_examen) }}</td>
                    <td class="center {{ $detail->sw_alerta == '1' ? 'label_error' : '' }}">
                        {{ $detail->resultado }}
                    </td>
                    <td class="center">{{ $detail->rango_min ?? 0 }}</td>
                    <td class="center">{{ $detail->rango_max }}</td>
                    <td class="center">{{ $detail->unidades }}</td>
                    <td class="center">{{ $detail->sw_alerta == '1' ? 'X' : '' }}</td>
                    <td class="center">{!! $detail->normalidades ?? '&nbsp;' !!}</td>
                </tr>

                @if($loop->last || $details[$index+1]->lab_plantilla_id != '1')
                </table>
                @endif

            <!-- Plantilla 2: Simple -->
            @elseif($detail->lab_plantilla_id == '2')
                 @if($sw)
                <table class="table-datatable" style="margin-top: 10px;">
                    <tr class="header-label center">
                        <th width="30%">SUBEXAMEN</th>
                        <th width="30%">RESULTADO</th>
                        <th width="15%">UND</th>
                        <th width="5%">PAT.</th>
                        <th width="20%">NORMALIDADES</th>
                    </tr>
                @endif

                <tr>
                    <td>{{ strtoupper($detail->nombre_examen) }}</td>
                    <td class="center {{ $detail->sw_alerta == '1' ? 'label_error' : '' }}">
                        {{ $detail->resultado }}
                    </td>
                    <td class="center">{{ $detail->unidades }}</td>
                    <td class="center">{{ $detail->sw_alerta == '1' ? 'X' : '' }}</td>
                    <td class="center">{!! $detail->normalidades ?? '&nbsp;' !!}</td>
                </tr>

                @if($loop->last || $details[$index+1]->lab_plantilla_id != '2')
                </table>
                @endif

            <!-- Plantilla 3 & 0: Texto -->
            @elseif($detail->lab_plantilla_id == '3' || $detail->lab_plantilla_id == '0')
                 @if($detail->lab_plantilla_id == '0' && $sw)
                    <div class="bold" style="margin-top: 10px; border: 1px solid #ccc; background:#f9f9f9; padding:3px;">
                        SUBEXAMEN: {{ strtoupper($detail->nombre_examen) }}
                    </div>
                 @endif

                 @if($detail->sw_alerta == '1')
                    <div class="label_error bold">RESULTADO PATOLÓGICO</div>
                 @endif

                 <blockquote>
                     {!! nl2br($detail->resultado) !!}
                 </blockquote>

                 @if(!empty($detail->normalidades))
                    <table class="table-datatable">
                        <tr><td class="header-label">NORMALIDADES</td></tr>
                        <tr><td>{{ $detail->normalidades }}</td></tr>
                    </table>
                 @endif
                 <br>

            <!-- Plantilla 6: DataLab -->
            @elseif($detail->lab_plantilla_id == '6')
                @if($sw)
                <table class="table-datatable" style="margin-top: 10px;">
                    <tr class="header-label center">
                        <th width="35%">SUBEXAMEN</th>
                        <th width="30%">RESULTADO</th>
                        <th width="10%">V.MIN</th>
                        <th width="10%">V.MAX</th>
                        <th width="10%">UND</th>
                        <th width="5%">PAT.</th>
                    </tr>
                @endif
                 <tr>
                    <td>{{ strtoupper($detail->nombre_examen) }}</td>
                    <td class="center {{ $detail->sw_alerta == '1' ? 'label_error' : '' }}">
                        {{ $detail->resultado }}
                    </td>
                    <td class="center">{{ $detail->rango_min ?? 0 }}</td>
                    <td class="center">{{ $detail->rango_max }}</td>
                    <td class="center">{{ $detail->unidades }}</td>
                    <td class="center">{{ $detail->sw_alerta == '1' ? 'X' : '' }}</td>
                </tr>
                @if($loop->last || $details[$index+1]->lab_plantilla_id != '6')
                </table>
                @endif

            <!-- Template 5: Lista Compleja -->
            @elseif($detail->lab_plantilla_id == '5')
                 @php
                    $impr = ($sw || $details[$index-1]->lab_examen_id != $detail->lab_examen_id) ? 1 : 2;
                 @endphp

                 @if($impr == 1)
                    @if(!$sw && $details[$index-1]->lab_plantilla_id == '5') </table><br> @endif
                    
                    <table class="table-datatable" style="margin-top: 10px;">
                        <tr>
                             <td colspan="3" class="center bold" style="background-color: #f0f0f0; color:blue;">
                                 SUBEXAMEN: {{ strtoupper($detail->nombre_examen) }}
                             </td>
                        </tr>
                        <tr class="header-label">
                            <th width="30%">VARIABLE</th>
                            <th width="60%">VALOR</th>
                            <th width="10%">UNIDADES</th>
                        </tr>
                 @endif

                 <tr>
                     <td>{{ strtoupper($detail->nombre_opcion) }}</td>
                     <td>
                         @if($detail->sw_tipo == 1)
                            {{ $detail->resultado }}
                         @elseif($detail->sw_tipo == 2)
                            {{ $detail->valor_lista }}
                         @elseif($detail->sw_tipo == 3)
                            {{ $detail->valor_lista }} - {{ $detail->resultado }}
                         @endif
                     </td>
                     <td class="center">{{ $detail->unidades }}</td>
                 </tr>

                 @if($loop->last || $details[$index+1]->lab_plantilla_id != '5' || $details[$index+1]->lab_examen_id != $detail->lab_examen_id)
                    </table><br>
                 @endif

            @endif <!-- End Template Switch -->
        @endforeach
    </div>

    <!-- Concepto -->
    @if($concepto)
        <table class="table-datatable" style="margin-top: 10px;">
            <tr>
                <td width="15%" class="header-label">CONCEPTO:</td>
                <td>{{ $concepto->descripcion_concepto_resultado }}</td>
            </tr>
        </table>
    @endif

    <br>

    <!-- Información / Observaciones -->
    @if($header->informacion)
        <div style="margin-bottom: 5px;">
            <strong>INFORMACIÓN:</strong> <small>{{ $header->informacion }}</small>
        </div>
    @endif

    @if($header->observacion_prestacion_servicio)
        <table class="table-datatable">
            <tr><td class="header-label">OBSERVACIÓN PRESTADOR:</td></tr>
            <tr><td>{{ $header->observacion_prestacion_servicio }}</td></tr>
        </table>
    @endif

    @if(!empty($observations))
        <br>
        <table class="table-datatable">
            <tr class="header-label">
                <th width="20%">FECHA</th>
                <th width="30%">USUARIO</th>
                <th width="50%">OBSERVACIÓN ADICIONAL</th>
            </tr>
            @foreach($observations as $obs)
            <tr>
                <td class="center">{{ $obs->fecha_registro_observacion }}</td>
                <td class="center">{{ $obs->usuario_observacion }}</td>
                <td style="text-align:justify;">{{ $obs->observacion_adicional }}</td>
            </tr>
            @endforeach
        </table>
    @endif

    <!-- Firmas Footer -->
    <div class="footer-signature">
        <table style="width:100%; border:none;">
            <tr>
                <!-- Firma Profesional -->
                <td style="width: 50%; border:none; vertical-align:top;">
                    @if(!empty($firmaBase64))
                    <div style="margin-bottom:5px;">
                        <img src="{{ $firmaBase64 }}" alt="Firma Profesional" class="firma-img">
                    </div>
                    @else
                    <br><br><br>
                    @endif
                    <div class="signature-line">
                        <div class="prof-info">
                            PROFESIONAL: {{ strtoupper($header->nombre_tercero ?? '') }}<br>
                            {{ $header->especialidad_descripcion ?? '' }}<br>
                            REGISTRO: {{ $header->tarjeta_profesional ?? '' }}<br>
                        </div>
                    </div>
                </td>

                <!-- Firma Revisor (si existe) -->
                @if(!empty($reviewer))
                <td style="width: 50%; border:none; vertical-align:top; text-align: left;">
                    @if(!empty($firmaRevisorBase64))
                    <div style="margin-bottom:5px;">
                        <img src="{{ $firmaRevisorBase64 }}" alt="Firma Revisor" class="firma-img">
                    </div>
                    @else
                    <br><br><br>
                    @endif
                    <div class="signature-line">
                        <div class="prof-info">
                            REVISADO POR: {{ strtoupper($reviewer->nombre ?? '') }}<br>
                            {{ $reviewer->especialidad_descripcion ?? '' }}<br>
                            REGISTRO: {{ $reviewer->tarjeta_profesional ?? '' }}<br>
                        </div>
                    </div>
                </td>
                @endif
            </tr>
        </table>
    </div>

    <!-- Print Footer -->
    <div class="print-footer">
        Imprimió: {{ $current_user }} - Fecha: {{ $print_date }}
        @if(isset($firmado_electronicamente) && $firmado_electronicamente) | Firmado Electrónicamente @endif
    </div>

</body>
</html>