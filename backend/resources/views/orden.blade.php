<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Solicitud de Servicios</title>
  <style>
    @page { margin: 20px 30px; }
    body { font-family: Arial, sans-serif; font-size: 9px; color: #000; }
    table { width: 100%; border-collapse: collapse; }

    /* Encabezado estilo fórmula */
    .header-container { width: 100%; margin-bottom: 10px; }
    .logo-cell { width: 15%; vertical-align: middle; }
    .info-cell { text-align: center; font-size: 10px; font-weight: bold; }
    .logo-img { max-width: 100px; max-height: 60px; }

    /* Título tipo “FORMULA MEDICA” */
    .doc-title {
      text-align: center;
      font-weight: bold;
      margin: 10px 0;
      font-size: 11px;
      background-color: #f0f0f0;
      border: 1px solid #000;
      padding: 5px;
      text-transform: uppercase;
    }

    /* Tablas con borde negro */
    .table-datatable td, .table-datatable th {
      border: 1px solid #000;
      padding: 4px;
      vertical-align: top;
    }
    .header-label { background-color: #f0f0f0; font-weight: bold; width: 15%; }

    /* Sección tipo bloque (como medicamentos) */
    .block {
      border: 1px solid #000;
      margin-top: 5px;
    }

    /* Tabla de servicios */
    .serv-table th {
      background-color: #f0f0f0;
      font-weight: bold;
      text-transform: uppercase;
      font-size: 9px;
    }
    .serv-table td, .serv-table th {
      border: 1px solid #000;
      padding: 4px;
      vertical-align: top;
      font-size: 9px;
    }

    /* Observaciones/Diagnósticos (bloque inferior) */
    .box {
      margin-top: 12px;
      font-size: 9px;
      border: 1px solid #000;
      padding: 5px;
    }

    /* Firma */
    .footer-signature { margin-top: 50px; }
    .signature-line { border-top: 1px solid #000; width: 350px; padding-top: 5px; }
    .prof-info { font-weight: bold; line-height: 1.3; font-size: 9px; }

    /* Pie de impresión */
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

  <!-- Encabezado con Logo y Datos Clínica -->
  <table class="header-container">
    <tr>
      <td class="logo-cell">
        @if(isset($logoBase64) && $logoBase64)
          <img src="{{ $logoBase64 }}" alt="Logo" class="logo-img">
        @endif
      </td>
      <td class="info-cell">
        @if(isset($empresa) && $empresa)
          {{ $empresa->razon_social ?? '' }}<br>
          NIT {{ $empresa->nit ?? '' }}{{ !empty($empresa->digito_verificacion) ? '-'.$empresa->digito_verificacion : '' }}<br>
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

  <!-- Título -->
  <div class="doc-title">
    SOLICITUD DE SERVICIOS Nº {{ $numero_orden ?? ($header->evolucion_id ?? '') }}
  </div>

  <!-- Tabla Información (mismo estilo) -->
  <table class="table-datatable">
    <tr>
      <td class="header-label">FECHA</td>
      <td width="35%">{{ $fecha ?? '' }}</td>
      <td class="header-label">ATENDIÓ</td>
      <td>{{ $profesional ?? '' }}</td>
    </tr>

    <tr>
      <td class="header-label">IDENTIFICACIÓN</td>
      <td>{{ $paciente->tipo_id_paciente ?? '' }} {{ $paciente->paciente_id ?? '' }}</td>
      <td class="header-label">PACIENTE</td>
      <td>{{ $paciente->nombre_completo ?? '' }}</td>
    </tr>

    <tr>
      <td class="header-label">CLIENTE</td>
      <td>{{ $paciente->cliente_nombre ?? '' }}</td>
      <td class="header-label">EDAD / SEXO</td>
      <td>{{ $edad ?? '' }} Años / {{ $paciente->sexo_id ?? '' }}</td>
    </tr>

    <tr>
      <td class="header-label">PLAN</td>
      <td colspan="3">
        {{ $paciente->plan_descripcion ?? '' }}
        @if(!empty($paciente->tipo_afiliado_id))
          <span style="font-weight:normal; margin-left: 20px;">TIPO AFILIADO: {{ $paciente->tipo_afiliado_id }}</span>
        @endif
        @if(!empty($paciente->rango))
          <span style="font-weight:normal; margin-left: 20px;">RANGO: {{ $paciente->rango }}</span>
        @endif
      </td>
    </tr>

    <tr>
      <td class="header-label">ESPECIALIDAD</td>
      <td>{{ $especialidad ?? '' }}</td>
      <td class="header-label">No. ORDEN</td>
      <td>{{ $numero_orden ?? '' }}</td>
    </tr>
  </table>

  <!-- Servicios (bloque similar a medicamentos) -->
  <div class="block">
    <table class="serv-table">
      <thead>
        <tr>
          <th style="width:12%;">No. Orden</th>
          <th style="width:15%;">Cod. Servicio</th>
          <th>Descripción del servicio</th>
          <th style="width:10%;">Cantidad</th>
        </tr>
      </thead>
      <tbody>
        @forelse($solicitudes as $solicitud)
          <tr>
            <td>{{ $solicitud->hc_os_solicitud_id ?? '' }}</td>
            <td>{{ $solicitud->cargo ?? '' }}</td>
            <td>{{ $solicitud->descripcion ?? '' }}</td>
            <td style="text-align:center;">{{ $solicitud->cantidad ?? '' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="4" style="text-align:center;">SIN SERVICIOS REGISTRADOS</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <!-- Observación -->
  @if(!empty($observaciones))
    <div class="box">
      <strong>OBSERVACIÓN:</strong><br>
      {{ $observaciones }}
    </div>
  @endif

  <!-- Diagnóstico(s) + principal -->
  @if(!empty($diagnosticos) && count($diagnosticos) > 0)
    <div class="box">
      <strong>DIAGNOSTICO(S):</strong><br>
      @foreach($diagnosticos as $diag)
        {{ $diag->diagnostico_id ?? '' }} - {{ $diag->diagnostico_nombre ?? '' }}<br>
      @endforeach

      @if(isset($diagnostico_principal) && $diagnostico_principal)
        <br><strong>DIAGNOSTICO PRINCIPAL:</strong> {{ $diagnostico_principal }}
      @else
        <br><strong>DIAGNOSTICO PRINCIPAL:</strong>
        {{ $diagnosticos[0]->diagnostico_id ?? '' }} - {{ $diagnosticos[0]->diagnostico_nombre ?? '' }}
      @endif
    </div>
  @elseif(!empty($diagnostico_principal))
    <div class="box">
      <strong>DIAGNOSTICO PRINCIPAL:</strong> {{ $diagnostico_principal }}
    </div>
  @endif

  <!-- Firma -->
  <div class="footer-signature">
    <br><br><br>
    <div class="signature-line">
      <div class="prof-info">
        PROFESIONAL: {{ $profesional ?? '' }}<br>
        {{ $especialidad ?? '' }}<br>
        @if(!empty($tarjeta_profesional))
          REGISTRO MEDICO: {{ $tarjeta_profesional }}<br>
        @endif
        @if(!empty($prof_id))
          CC: {{ $prof_id }}<br>
        @endif
      </div>
    </div>
  </div>

  <div class="print-footer">
    Imprimió: Sistema - Fecha Impresión: {{ $fecha_impresion ?? date('Y-m-d H:i:s') }}
  </div>

</body>
</html>
