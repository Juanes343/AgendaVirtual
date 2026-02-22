<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Incapacidad Médica</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #000; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .header-table td { vertical-align: top; }
        .logo { max-width: 150px; }
        .company-info { text-align: center; font-weight: bold; font-size: 14px; text-transform: uppercase; }
        .title-box {
            text-align: center;
            font-weight: bold;
            font-size: 14px;
            border: 1px solid #000;
            padding: 5px;
            margin-bottom: 15px;
            background-color: #f0f0f0;
        }
        .section-header {
            font-weight: bold;
            border-bottom: 1px solid #000;
            margin-bottom: 5px;
            margin-top: 15px;
            font-size: 12px;
        }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11px; }
        .data-table td { border: 1px solid #000; padding: 4px; }
        .label { font-weight: bold; background-color: #f9fafb; width: 18%; }

        .signature-box { margin-top: 50px; width: 100%; }
        .sign-line {
            border-top: 1px solid #000;
            width: 80%;
            margin-bottom: 5px;
            padding-top: 5px;
            font-weight: bold;
        }
        .firma-img { max-height: 60px; display:block; margin-bottom:5px; }
    </style>
</head>
<body>

    <!-- Encabezado -->
    <table class="header-table">
        <tr>
            <td width="20%">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo">
                @endif
            </td>
            <td class="company-info">
                {{ $empresa->razon_social ?? '' }}<br>
                NIT: {{ $empresa->nit ?? '' }}{{ !empty($empresa->digito_verificacion) ? '-'.$empresa->digito_verificacion : '' }}<br>
                {{ $empresa->municipio ?? '' }}
            </td>
            <td width="20%" style="text-align: right; font-size: 10px;">
                <strong>No. Ingreso:</strong> {{ $paciente->ingreso ?? '' }}<br>
                <strong>Fecha:</strong> {{ $fecha ?? '' }}
            </td>
        </tr>
    </table>

    <div class="title-box">
        SOLICITUD DE INCAPACIDADES Y/O LICENCIAS DE MATERNIDAD
    </div>

    <!-- Información del Paciente -->
    <div class="section-header">Información del Paciente:</div>
    <table class="data-table">
        <tr>
            <td class="label">Paciente:</td>
            <td colspan="3">{{ $paciente->nombre_completo ?? $paciente->nombre_paciente ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Identificación:</td>
            <td>{{ $paciente->identificacion ?? '' }}</td>
            <td class="label">Fecha Nacimiento:</td>
            <td>{{ $paciente->fecha_nacimiento ?? '' }} ({{ $paciente->edad ?? '' }})</td>
        </tr>
        <tr>
            <td class="label">EPS / Aseguradora:</td>
            <td>{{ $paciente->nombre_aseguradora ?? '' }}</td>
            <td class="label">Tipo Afiliado:</td>
            <td>{{ $paciente->tipo_afiliado_descripcion ?? ($paciente->tipo_afiliado_id ?? '') }} - {{ $paciente->rango ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Dirección:</td>
            <td colspan="3">{{ $paciente->direccion ?? '' }} - {{ $paciente->telefono ?? '' }}</td>
        </tr>
    </table>

    <!-- Información de la Incapacidad -->
    <div class="section-header">Información Sobre la Incapacidad:</div>
    @foreach($incapacidades as $inc)
        @php
            $inicio = \Carbon\Carbon::parse($inc->fecha_inicio);
            $fin = $inicio->copy()->addDays($inc->dias_de_incapacidad - 1);
        @endphp
        <table class="data-table">
            <tr>
                <td class="label">Fecha Inicio:</td>
                <td width="20%">{{ $inicio->format('d/m/Y') }}</td>
                <td class="label">Fecha Terminación:</td>
                <td width="20%">{{ $fin->format('d/m/Y') }}</td>
                <td class="label">Duración:</td>
                <td>{{ $inc->dias_de_incapacidad }} Día(s)</td>
            </tr>
            <tr>
                <td class="label">Prórroga:</td>
                <td>{{ (isset($inc->sw_prorroga) && $inc->sw_prorroga == '1') ? 'SI' : 'NO' }}</td>
                <td class="label">Contingencia:</td>
                <td colspan="3">{{ $inc->tipo_incapacidad ?? 'ENFERMEDAD GENERAL' }}</td>
            </tr>
            <tr>
                <td class="label">Diagnóstico:</td>
                <td colspan="5">{{ $inc->codigo_diagnostico }} - {{ $inc->diagnostico_nombre }}</td>
            </tr>
            <tr>
                <td class="label">Observaciones:</td>
                <td colspan="5">{{ $inc->observacion_incapacidad }}</td>
            </tr>
        </table>
    @endforeach

    <!-- Footer Firma -->
    <table class="signature-box">
        <tr>
            <td width="50%" valign="bottom">
                @if(!empty($firmaBase64))
                    <img src="{{ $firmaBase64 }}" class="firma-img">
                @endif

                <div class="sign-line">{{ $paciente->profesional ?? '' }}</div>
                <div>{{ $paciente->especialidad ?? 'Médico General' }}</div>
                <div>Reg. Médico: {{ $paciente->registro_medico ?? $paciente->tarjeta_profesional ?? '' }}</div>
            </td>
            <td width="50%" align="right" valign="bottom">
                <div style="font-size: 10px;">Generado el: {{ $fecha_impresion ?? '' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>