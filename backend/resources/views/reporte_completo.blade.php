<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte Historia Clínica</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { width: 100%; border-bottom: 2px solid #0F52BA; padding-bottom: 10px; margin-bottom: 20px; }
        .logo { width: 150px; }
        .info-paciente { width: 100%; margin-bottom: 20px; background: #f9fafb; padding: 10px; border-radius: 5px; }
        .info-paciente td { padding: 5px; }
        .section-title { background: #0F52BA; color: white; padding: 5px 10px; font-weight: bold; margin-top: 20px; border-radius: 3px; }
        .item { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .item-title { font-weight: bold; color: #0F52BA; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #777; border-top: 1px solid #ccc; padding-top: 5px; }
        .label { font-weight: bold; font-size: 11px; color: #555; }
    </style>
</head>
<body>
    <div class="header">
        <table width="100%">
            <tr>
                <td><h1>SanDi•Med</h1></td>
                <td style="text-align: right;">
                    <h3>Reporte de Atención</h3>
                    <p>Ingreso #{{ $ingreso }}</p>
                    <p>Fecha: {{ $fecha }}</p>
                </td>
            </tr>
        </table>
    </div>

    <div class="info-paciente">
        <table width="100%">
            <tr>
                <td><span class="label">Paciente:</span> {{ $paciente->nombre_completo }}</td>
                <td><span class="label">Documento:</span> {{ $paciente->tipo_id_paciente }} {{ $paciente->paciente_id }}</td>
            </tr>
            <tr>
                <td><span class="label">Profesional:</span> {{ $profesional }}</td>
                <td><span class="label">Especialidad:</span> {{ $especialidad ?? 'General' }}</td>
            </tr>
        </table>
    </div>

    @if(count($medicamentos) > 0)
    <div class="section-title">MEDICAMENTOS FORMULADOS</div>
    @foreach($medicamentos as $med)
        <div class="item">
            <div class="item-title">{{ $med->producto }}</div>
            <div>Principio Activo: {{ $med->principio_activo }}</div>
            <table width="100%">
                <tr>
                    <td><span class="label">Dosis:</span> {{ $med->dosis }} {{ $med->unidad_dosificacion }}</td>
                    <td><span class="label">Frecuencia:</span> {{ $med->frecuencia }}</td>
                    <td><span class="label">Cantidad:</span> {{ $med->cantidad }}</td>
                </tr>
            </table>
            @if($med->observacion)
                <div style="margin-top:5px;"><span class="label">Observaciones:</span> {{ $med->observacion }}</div>
            @endif
        </div>
    @endforeach
    @endif

    @if(count($solicitudes) > 0)
    <div class="section-title">ÓRDENES Y SOLICITUDES</div>
    @foreach($solicitudes as $sol)
        <div class="item">
            <div class="item-title">{{ $sol->descripcion }}</div>
            <div><span class="label">Código:</span> {{ $sol->cargo }}</div>
            <div><span class="label">Cantidad:</span> {{ $sol->cantidad }}</div>
            <div><span class="label">Fecha Solicitud:</span> {{ $sol->fecha_solicitud }}</div>
        </div>
    @endforeach
    @endif

    @if(count($incapacidades) > 0)
    <div class="section-title">INCAPACIDADES</div>
    @foreach($incapacidades as $inc)
        <div class="item">
            <div class="item-title">Diagnóstico: {{ $inc->diagnostico_nombre }}</div>
            <div><span class="label">Fecha Inicio:</span> {{ $inc->fecha_inicio }}</div>
            <div><span class="label">Días:</span> {{ $inc->dias_de_incapacidad }}</div>
            @if($inc->observacion_incapacidad)
                <div><span class="label">Observación:</span> {{ $inc->observacion_incapacidad }}</div>
            @endif
        </div>
    @endforeach
    @endif

    <div class="footer">
        Generado automáticamente por Agenda Virtual SanDi•Med el {{ date('Y-m-d H:i') }}
    </div>
</body>
</html>