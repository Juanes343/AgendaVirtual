<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historia Clínica Completa</title>
    <style>
        @page { margin: 20px 30px; }
        body { font-family: Arial, sans-serif; font-size: 9px; color: #000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        
        .table-datatable td, .table-datatable th { border: 1px solid #000; padding: 3px 5px; vertical-align: middle; }
        .table-datatable th { background-color: #f0f0f0; font-weight: bold; text-align: left; }
        
        .header-label { background-color: #f0f0f0; font-weight: bold; width: 15%; text-transform: uppercase;}
        
        .header-container { width: 100%; margin-bottom: 10px; border: none; }
        .logo-cell { width: 20%; vertical-align: middle; text-align: left; border: none; }
        .info-cell { text-align: center; font-size: 10px; font-weight: bold; border: none; }
        .logo-img { max-width: 120px; max-height: 70px; }
        
        .doc-title { text-align: center; font-weight: bold; margin: 10px 0; font-size: 12px; background-color: #ddd; border: 1px solid #000; padding: 5px; }
        
        .section-title { 
            font-size: 10px; 
            font-weight: bold; 
            background-color: #e0e0e0; 
            border: 1px solid #000; 
            padding: 4px; 
            margin-top: 10px; 
            margin-bottom: 0px; 
            text-transform: uppercase;
        }

        .content-block { border: 1px solid #000; border-top: none; padding: 5px; margin-bottom: 10px; }
        
        .footer-signature { margin-top: 40px; page-break-inside: avoid; }
        .signature-box { width: 100%; }
        .dist-line { border-top: 1px solid #000; width: 250px; margin-top: 40px; }
        
        .bioseguridad { font-size: 8px; text-align: justify; margin-top: 20px; border: 1px solid #ccc; padding: 5px; }
        .print-footer { position: fixed; bottom: 0; left: 0; width: 100%; font-size: 8px; text-align: right; color: #555; }
    </style>
</head>
<body>
    <!-- Encabezado con Logo y Datos Clínica -->
    <table class="header-container">
        <tr>
            <td class="logo-cell">
                @if(isset($logoBase64) && $logoBase64)
                    <img src="{{ $logoBase64 }}" alt="Logo" class="logo-img">
                @endif
            </td>
            <td class="info-cell">
                <div style="font-size: 14px; margin-bottom: 5px;">HISTORIA CLINICA</div>
                @if(isset($empresa) && $empresa)
                    {{ $empresa->razon_social ?? '' }}<br>
                    NIT {{ $empresa->nit ?? '' }}-{{ $empresa->digito_verificacion ?? '' }}<br>
                    {{ $empresa->direccion ?? '' }} - {{ $empresa->municipio ?? '' }}<br>
                    Tel: {{ $empresa->telefonos ?? '' }}
                @else
                    CLÍNICA DE OFTALMOLOGÍA SANDIEGO S.A.<br>
                    NIT 900.191.362-8
                @endif
            </td>
        </tr>
    </table>

    <div class="doc-title">RESUMEN DE ATENCIÓN - INGRESO #{{ $ingreso }}</div>

    <!-- DATOS DEL PACIENTE (Replicando estructura ResumenHC) -->
    <table class="table-datatable">
        <tr>
            <td class="header-label">PACIENTE:</td>
            <td colspan="3">{{ isset($paciente) ? $paciente->nombre_completo : '' }}</td>
            <td class="header-label">IDENTIFICACIÓN:</td>
            <td>{{ isset($paciente) ? $paciente->tipo_id_paciente.' '.$paciente->paciente_id : '' }}</td>
        </tr>
        <tr>
            <td class="header-label">FECHA NAC.:</td>
            <td>{{ isset($paciente) ? $paciente->fecha_nacimiento : '' }}</td>
            <td class="header-label" width="10%">EDAD:</td>
            <td>
                @if(isset($paciente->fecha_nacimiento))
                    {{ \Carbon\Carbon::parse($paciente->fecha_nacimiento)->age }} Años
                @endif
            </td>
            <td class="header-label">SEXO:</td>
            <td>{{ isset($paciente) ? $paciente->sexo_id : '' }}</td>
        </tr>
    </table>

    <!-- DATOS DE AFILIACIÓN -->
    <table class="table-datatable">
        <tr>
            <td class="header-label" width="15%">CLIENTE:</td>
            <td width="35%">{{ isset($paciente) ? ($paciente->cliente_nombre ?? 'PARTICULAR') : '' }}</td>
            <td class="header-label" width="15%">PLAN:</td>
            <td>{{ isset($paciente) ? $paciente->plan_descripcion : '' }}</td>
        </tr>
        <tr>
            <td class="header-label">TIPO AFILIADO:</td>
            <td>{{ isset($paciente) ? $paciente->tipo_afiliado_id : '' }}</td>
            <td class="header-label">RANGO:</td>
            <td>{{ isset($paciente) ? $paciente->rango : '' }}</td>
        </tr>
    </table>

    <!-- DATOS DE ATENCIÓN -->
    <table class="table-datatable">
        <tr>
            <td class="header-label">FECHA ATENCIÓN:</td>
            <td>{{ isset($paciente) ? $paciente->fecha : '' }}</td>
            <td class="header-label">PROFESIONAL:</td>
            <td>{{ isset($paciente) ? $paciente->profesional : '' }}</td>
        </tr>
        <tr>
            <td class="header-label">ESPECIALIDAD:</td>
            <td colspan="3">{{ isset($paciente) ? $paciente->especialidad : '' }}</td>
        </tr>
    </table>

    <!-- CONTENIDO MÉDICO -->
    
    <!-- 1. MEDICAMENTOS -->
    @if(isset($medicamentos) && count($medicamentos) > 0)
        <div class="section-title">MEDICAMENTOS FORMULADOS</div>
        <table class="table-datatable">
            <thead>
                <tr>
                    <th width="30%">Medicamento</th>
                    <th>Principio Activo</th>
                    <th>Dosis / Frecuencia / Vía</th>
                    <th width="8%">Cant.</th>
                    <th width="8%">Días</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($medicamentos as $med)
                <tr>
                    <td>
                        <b>{{ $med->producto }}</b><br>
                        <span style="font-size:8px; color:#555;">{{ $med->codigo_medicamento }}</span>
                    </td>
                    <td>{{ $med->principio_activo }}</td>
                    <td>
                        {{ $med->dosis }} {{ $med->unidad_dosificacion }}<br>
                        Cada {{ $med->frecuencia }}<br>
                        Vía: {{ $med->via_administracion_id ?? 'Oral' }}
                    </td>
                    <td align="center">{{ $med->cantidad }}</td>
                    <td align="center">{{ $med->tiempo_tratamiento }}</td>
                    <td>{{ $med->observacion }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 2. ORDENES Y SOLICITUDES -->
    @if(isset($solicitudes) && count($solicitudes) > 0)
        <div class="section-title">ÓRDENES Y AYUDAS DIAGNÓSTICAS</div>
        <table class="table-datatable">
            <thead>
                <tr>
                    <th width="15%">Código</th>
                    <th>Descripción Procedimiento/Servicio</th>
                    <th width="10%">Cantidad</th>
                    <th width="15%">Fecha Solicitud</th>
                </tr>
            </thead>
            <tbody>
                @foreach($solicitudes as $sol)
                <tr>
                    <td>{{ $sol->cargo }}</td>
                    <td>{{ $sol->descripcion }}</td>
                    <td align="center">{{ $sol->cantidad }}</td>
                    <td align="center">{{ $sol->fecha_solicitud }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 3. INCAPACIDADES -->
    @if(isset($incapacidades) && count($incapacidades) > 0)
        <div class="section-title">INCAPACIDADES MÉDICAS</div>
        <table class="table-datatable">
            <thead>
                <tr>
                    <th>Diagnóstico</th>
                    <th width="15%">Fecha Inicio</th>
                    <th width="10%">Días</th>
                    <th>Observación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($incapacidades as $inc)
                <tr>
                    <td>{{ $inc->diagnostico_id }} - {{ $inc->diagnostico_nombre }}</td>
                    <td align="center">{{ $inc->fecha_inicio }}</td>
                    <td align="center">{{ $inc->dias_de_incapacidad }}</td>
                    <td>{{ $inc->observacion_incapacidad }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- FIRMAS -->
    <div class="footer-signature">
        <table border="0" width="100%">
            <tr>
                <td width="50%" valign="top">
                    <!-- Espacio Firma -->
                     <br><br>
                    <div class="dist-line"></div>
                    <b>PROFESIONAL:</b> {{ isset($paciente) ? $paciente->profesional : '' }}<br>
                    <b>REGISTRO MÉDICO:</b> {{ isset($paciente) ? $paciente->tarjeta_profesional : '' }}<br>
                    <b>ESPECIALIDAD:</b> {{ isset($paciente) ? $paciente->especialidad : '' }}
                </td>
                <td width="50%" valign="top">
                    <!-- Espacio Firma Paciente (Opccional) -->
                </td>
            </tr>
        </table>
    </div>

    <!-- BIOSEGURIDAD (Texto Legal del Legacy) -->
    <div class="bioseguridad">
        <b>BIOSEGURIDAD COVID-19:</b> La atención brindada al usuario cumple con los lineamientos de bioseguridad dados por el Ministerio de Salud en cuanto al uso adecuado de elementos de protección personal, lavado de manos y medidas de higiene en general.
    </div>

    <div class="print-footer">
        Impreso por: Agenda Virtual | Fecha: {{ date('Y-m-d H:i A') }}
    </div>
</body>
</html>