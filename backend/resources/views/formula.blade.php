<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Fórmula Médica</title>
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
            margin-top: 15px;
            font-size: 9px;
            border: 1px solid #000;
            padding: 5px;
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
                {{ $empresa->website ?? '' }}
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

    <div class="doc-title">FORMULA MEDICA Nº {{ $header->evolucion_id ?? '' }}</div>

    <!-- Datos Paciente -->
    <table class="table-datatable">
        <tr>
            <td class="header-label">NO. EVOLUCION</td>
            <td width="35%">{{ $header->evolucion_id ?? '' }}</td>
            <td class="header-label">FECHA FORMULA</td>
            <td>{{ $header->fecha ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-label">IDENTIFICACION</td>
            <td>{{ ($header->tipo_id_paciente ?? '') . ' ' . ($header->paciente_id ?? '') }}</td>
            <td class="header-label">PACIENTE</td>
            <td>{{ $header->nombre_completo ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-label">CLIENTE</td>
            <td>{{ $header->cliente_nombre ?? 'PARTICULAR' }}</td>
            <td class="header-label">EDAD / SEXO</td>
            <td>{{ !empty($edad) ? $edad.' Años' : '' }} / {{ $header->sexo_id ?? '' }}</td>
        </tr>
        <tr>
            <td class="header-label">PLAN</td>
            <td colspan="3">
                {{ $header->plan_descripcion ?? '' }}
                <span style="font-weight:normal; margin-left: 20px;">TIPO AFILIADO: {{ $header->tipo_afiliado_id ?? '' }}</span>
                <span style="font-weight:normal; margin-left: 20px;">RANGO: {{ $header->rango ?? '' }}</span>
            </td>
        </tr>
    </table>
    
    <!-- Medicamentos -->
    <div style="border: 1px solid #000; margin-top:5px;">
        @foreach($medicamentos as $index => $med)
        <div class="med-item">
            <div class="med-name">{{ $index + 1 }}. {{ $med->producto }}</div>

            <!-- ====== 2 COLUMNAS (2 y 2) para evitar huecos ====== -->
            <table width="100%" class="med-table" style="font-size: 9px; margin-top: 2px;">
                <tr>
                    <!-- Izquierda -->
                    <td style="width:20%; font-weight:bold;">VIA DE ADMINISTRACIÓN:</td>
                    <td style="width:30%;">{{ $med->via_administracion_nombre ?? 'No especificada' }}</td>

                    <!-- Derecha -->
                    <td style="width:15%; font-weight:bold;">DOSIS:</td>
                    <td style="width:35%;">{{ $med->dosis }} {{ $med->unidad_dosificacion }} cada {{ $med->frecuencia }}</td>
                </tr>

                <tr>
                    <!-- Izquierda -->
                    <td style="font-weight:bold;">CANTIDAD:</td>
                    <td>{{ $med->cantidad }} Unidades</td>

                    <!-- Derecha -->
                    <td style="font-weight:bold;">DIAS TRATAMIENTO:</td>
                    <td>{{ $med->tiempo_tratamiento }} Días</td>
                </tr>

                <!-- OBSERVACIÓN ocupa TODO el ancho -->
                <tr>
                    <td style="font-weight:bold;">OBSERVACION:</td>
                    <td colspan="3">{{ $med->observacion }}</td>
                </tr>
            </table>
        </div>
        @endforeach
    </div>


    <!-- Diagnósticos -->
    <div class="diagnosticos">
        <strong>DIAGNOSTICO(S):</strong><br>
        @forelse($diagnosticos ?? [] as $diag)
        {{ $diag->diagnostico_id ?? '' }} - {{ $diag->diagnostico_nombre ?? '' }}<br>
        @empty
        SIN DIAGNÓSTICOS REGISTRADOS
        @endforelse

        @if(!empty($diagnosticos) && count($diagnosticos) > 0)
        <br><strong>DIAGNOSTICO PRINCIPAL:</strong>
        {{ $diagnosticos[0]->diagnostico_id ?? '' }} - {{ $diagnosticos[0]->diagnostico_nombre ?? '' }}
        @endif
    </div>

    <!-- Firma -->
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
                PROFESIONAL: {{ $header->profesional ?? '' }}<br>
                {{ $header->especialidad ?? '' }}<br>
                REGISTRO MEDICO: {{ $header->tarjeta_profesional ?? '' }}<br>
                CC: {{ $header->prof_id ?? '' }}<br>
            </div>
        </div>
    </div>

    <div class="print-footer">
        Imprimió: Sistema - Fecha Impresión: {{ $fecha_impresion ?? '' }}
    </div>
</body>

</html>