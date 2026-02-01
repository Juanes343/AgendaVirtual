<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Solicitud Procedimientos No Quirúrgicos</title>
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

        .med-item {
            border-bottom: 2px solid #ccc;
            padding: 5px 0;
            page-break-inside: avoid;
        }

        .med-item:last-child {
            border-bottom: none;
        }

        .med-name {
            font-weight: bold;
            font-size: 10px;
            background-color: #e9e9e9;
            padding: 3px;
            border: 1px solid #ddd;
        }

        .med-table td {
            border: none;
            padding: 2px;
        }

        .label-bold {
            font-weight: bold;
            width: 140px;
        }

        .diagnosticos {
            margin-top: 5px;
            font-size: 9px;
            border-top: 1px solid #eee;
            padding-top: 2px;
        }

        .footer-signature {
            margin-top: 50px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 350px;
            padding-top: 5px;
        }

        .prof-info {
            font-weight: bold;
            line-height: 1.3;
            font-size: 9px;
        }

        .firma-img {
            max-width: 220px;
            max-height: 80px;
            display: block;
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

    <!-- Encabezado -->
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
                {{ $empresa->email ?? '' }}
                @else
                CLÍNICA DE OFTALMOLOGÍA SANDIEGO S.A.<br>
                NIT 900.191.362-8<br>
                AVENIDA 0 # 11-140 CENTRO - CÚCUTA, NORTE DE SANTANDER<br>
                Teléfono: 607-5960150<br>
                https://clinicasandiego.com.co/
                @endif
            </td>
        </tr>
    </table>

    <div class="doc-title">SOLICITUD PROCEDIMIENTOS NO QUIRURGICOS (INGRESO: {{ $ingreso }})</div>

    <!-- Datos Paciente -->
    <table class="table-datatable">
        <tr>
            <td class="header-label">NO. INGRESO</td>
            <td width="35%">{{ $ingreso }}</td>
            <td class="header-label">FECHA SOLICITUD</td>
            <td>{{ $fecha ?? date('Y-m-d') }}</td>
        </tr>
        <tr>
            <td class="header-label">IDENTIFICACION</td>
            <td>{{ ($paciente->tipo_id_paciente ?? '') . ' ' . ($paciente->paciente_id ?? '') }}</td>
            <td class="header-label">PACIENTE</td>
            <td>{{ $paciente->nombre_completo ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-label">CLIENTE</td>
            <td>{{ $paciente->nombre_tercero ?? 'PARTICULAR' }}</td>
            <td class="header-label">EDAD / SEXO</td>
            <td>{{ $paciente->edad ?? '' }} Años / {{ $paciente->sexo_id ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-label">PLAN</td>
            <td colspan="3">
                {{ $paciente->plan_descripcion ?? '' }}
            </td>
        </tr>
    </table>
    
    <!-- Procedimientos -->
    <div style="border: 1px solid #000; margin-top:5px;">
        @foreach($procedimientos as $index => $proc)
        <div class="med-item">
            <div class="med-name">{{ $index + 1 }}. {{ $proc->descripcion }} (Cod: {{ $proc->cargo }})</div>

            <!-- Tabla Detalle Item -->
            <table width="100%" class="med-table" style="font-size: 9px; margin-top: 2px;">
                <tr>
                    <!-- Izquierda -->
                    <td style="width:15%; font-weight:bold;">CANTIDAD:</td>
                    <td style="width:35%;">{{ $proc->cantidad }}</td>

                    <!-- Derecha -->
                    <td style="width:15%; font-weight:bold;">FECHA:</td>
                    <td style="width:35%;">{{ $proc->fecha ?? $fecha }}</td>
                </tr>

                <!-- OBSERVACIÓN -->
                @if(!empty($proc->observacion))
                <tr>
                    <td style="font-weight:bold;">OBSERVACION:</td>
                    <td colspan="3">{{ $proc->observacion }}</td>
                </tr>
                @endif
                
                <!-- Diagnosticos por item -->
                @if(!empty($proc->diagnosticos) && count($proc->diagnosticos) > 0)
                <tr>
                    <td colspan="4">
                        <div class="diagnosticos">
                            <strong>Diagnósticos Asociados:</strong><br>
                            @foreach($proc->diagnosticos as $diag)
                                - {{ $diag->diagnostico_id ?? '' }} {{ $diag->diagnostico_nombre ?? '' }} ({{ $diag->tipo_diagnostico ?? 'N/A' }})<br>
                            @endforeach
                        </div>
                    </td>
                </tr>
                @endif
            </table>
        </div>
        @endforeach
    </div>

    <!-- Firma -->
    <div class="footer-signature">
        @if(!empty($firmaBase64))
        <div style="margin-bottom:5px;">
            <img src="{{ $firmaBase64 }}" alt="Firma" class="firma-img">
        </div>
        @elseif(!empty($profesional) && !empty($profesional->firma))
            {{-- Fallback por si acaso firmaBase64 falla pero hay firma raw --}}
            {{-- No hacer nada si no hay base64 listo --}}
            <br><br><br>
        @else
        <br><br><br>
        @endif

        <div class="signature-line">
            <div class="prof-info">
                @if(!empty($profesional))
                    PROFESIONAL: {{ $profesional->nombre ?? '' }}<br>
                    {{ $profesional->especialidad ?? '' }}<br>
                    REGISTRO MEDICO: {{ $profesional->tarjeta_profesional ?? '' }}<br>
                    {{-- CC si estuviera dispo --}}
                @else
                    PROFESIONAL TRATANTE<br>
                @endif
            </div>
        </div>
    </div>

    <div class="print-footer">
        Imprimió: Sistema - Fecha Impresión: {{ date('d/m/Y H:i') }}
    </div>
</body>

</html>