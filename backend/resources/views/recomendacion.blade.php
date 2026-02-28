<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Recomendaciones Médicas</title>
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

        .rec-item {
            border-bottom: 2px solid #ccc;
            padding: 10px 0;
            page-break-inside: avoid;
        }

        .rec-item:last-child {
            border-bottom: none;
        }

        .rec-title {
            font-weight: bold;
            font-size: 10px;
            background-color: #e9e9e9;
            padding: 3px;
            border: 1px solid #ddd;
            margin-bottom: 5px;
        }

        .rec-content {
            padding: 5px;
            font-size: 10px;
            margin-bottom: 5px;
        }
        
        .rec-detail-list {
             list-style-type: none; /* O disc si prefieres viñetas */
             padding-left: 10px;
             margin: 0;
        }
        
        .rec-detail-item {
            margin-bottom: 2px;
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

    <div class="doc-title">RECOMENDACIONES MÉDICAS Nº {{ $header->evolucion_id ?? '' }}</div>

    <!-- Datos Paciente -->
    <table class="table-datatable">
        <tr>
            <td class="header-label">NO. EVOLUCION</td>
            <td width="35%">{{ $header->evolucion_id ?? '' }}</td>
            <td class="header-label">FECHA INGRESO</td>
            <td>{{ $header->fecha_ingreso ?? $header->fecha ?? '' }}</td>
        </tr>
        <tr>
             <td class="header-label">TIPO DE ATENCIÓN</td>
             <td colspan="3">{{ $header->tipo_atencion_descripcion ?? '' }}</td>
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
                @if(!empty($header->tipo_afiliado_descripcion))
                    <span style="font-weight:normal; margin-left: 20px;">TIPO AFILIADO: {{ $header->tipo_afiliado_descripcion }}</span>
                @elseif(!empty($header->tipo_afiliado_id))
                    <span style="font-weight:normal; margin-left: 20px;">TIPO AFILIADO: {{ $header->tipo_afiliado_id }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="header-label">SERVICIO</td>
            <td>{{ $header->servicio_descripcion ?? 'AMBULATORIO' }}</td>
            <td class="header-label">DEPARTAMENTO</td>
            <td>{{ $header->departamento_descripcion ?? 'AMBULATORIO' }}</td>
        </tr>
    </table>
    
    <!-- Recomendaciones -->
    <div style="border: 1px solid #000; margin-top:5px; padding: 5px;">
        @forelse($recomendaciones as $index => $rec)
        <div class="rec-item">
            <!-- Si hay múltiples, numerarlas. Si no, solo titulo genérico -->
            <div class="rec-title">RECOMENDACIÓN #{{ $index + 1 }}</div>

            <!-- Texto Libre (Recomendaciones Adicionales) -->
            @if(!empty($rec->recomendaciones_adic))
            <div class="rec-content">
                <strong>RECOMENDACIONES ADICIONALES:</strong><br>
                {!! nl2br(e($rec->recomendaciones_adic)) !!}
            </div>
            @endif

            <!-- Detalles Seleccionados (Lista) -->
            @if(!empty($rec->detalles) && count($rec->detalles) > 0)
            <div class="rec-content" style="margin-top: 5px;">
                <strong>RECOMENDACIONES:</strong>
                <ul class="rec-detail-list">
                    @foreach($rec->detalles as $detalle)
                       <li class="rec-detail-item">- {{ $detalle->descripcion }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>
        @empty
        <div style="padding: 10px; text-align: center;">NO SE REGISTRARON RECOMENDACIONES EN ESTA EVOLUCIÓN.</div>
        @endforelse
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