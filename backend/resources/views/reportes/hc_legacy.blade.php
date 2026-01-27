<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historia Clínica</title>

  {{-- base LOCAL para que el legacy resuelva images/... --}}
  <base href="{{ rtrim($baseUrl,'/') }}/">

  <style>
    @page { margin: 18px 22px; }

    body{
      font-family: Arial, sans-serif;
      font-size: 8.5px;
      color:#000;
      line-height: 1.15;
    }

    table{ width:100%; border-collapse: collapse; }

    /* HEADER */
    .header-container{ width:100%; margin-bottom:8px; }
    .logo-cell{ width:18%; vertical-align: middle; }
    .info-cell{
      text-align:center;
      font-size:9.5px;
      font-weight:bold;
      line-height: 1.25;
    }
    .logo-img{ max-width:110px; max-height:55px; }

    .doc-title{
      text-align:center;
      font-weight:bold;
      margin: 8px 0 10px 0;
      font-size: 10.5px;
      background:#f0f0f0;
      border:1px solid #000;
      padding:5px;
      text-transform: uppercase;
    }

    /* NORMALIZADOR LEGACY */
    .legacy-wrap table{ width:100% !important; border-collapse: collapse !important; }
    .legacy-wrap td, .legacy-wrap th{
      border:1px solid #000 !important;
      padding:3px 4px !important;
      vertical-align: top !important;
      font-size: 8.2px !important;
      line-height: 1.15 !important;
    }

    .legacy-wrap th,
    .legacy-wrap .modulo_table_title td,
    .legacy-wrap .hc_table_submodulo_list_title td{
      background:#f0f0f0 !important;
      font-weight:bold !important;
      text-transform: uppercase;
    }

    .legacy-wrap font { font-family: Arial, sans-serif !important; font-size: 8.2px !important; }
    .legacy-wrap img{ max-width: 100% !important; height: auto !important; }

    /* FIRMA PROFESIONAL */
    .firma-box{
      margin-top: 12px;
      border: 1px solid #000;
      padding: 8px;
    }
    .firma-img{
      max-height: 70px;
      max-width: 240px;
      display:block;
      margin-bottom: 6px;
    }
    .firma-line{
      border-top: 1px solid #000;
      width: 360px;
      padding-top: 5px;
      font-weight: bold;
      font-size: 8.5px;
      line-height: 1.25;
    }

    /* FOOTER ÚNICO */
    .print-footer{
      position: fixed;
      bottom: 0;
      left: 0;
      width: 100%;
      font-size: 7.5px;
      text-align: right;
      color: #555;
      padding-right: 4px;
    }
  </style>
</head>

<body>

  <table class="header-container">
    <tr>
      <td class="logo-cell">
        @if(!empty($logoBase64))
          <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo">
        @else
          {{-- <img src="images/logocliente.png" class="logo-img" alt="Logo"> --}}
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

  <div class="doc-title">Historia Clínica - Ingreso #{{ $ingreso ?? '' }}</div>

  <div class="legacy-wrap">
    {!! $html !!}
  </div>

  {{-- Firma (la ponemos nosotros, no el legacy) --}}
  @if(!empty($header))
    <div class="firma-box">
      <table>
        <tr>
          <td style="width:70%; vertical-align:bottom;">
            @if(!empty($firmaBase64))
              <img src="{{ $firmaBase64 }}" class="firma-img" alt="Firma">
            @endif

            <div class="firma-line">
              PROFESIONAL: {{ $header->profesional ?? '' }}<br>
              {{ $header->especialidad ?? '' }}<br>
              @if(!empty($header->tarjeta_profesional))
                REGISTRO MÉDICO: {{ $header->tarjeta_profesional }}<br>
              @endif
              @if(!empty($header->prof_id))
                CC: {{ $header->prof_id }}<br>
              @endif
            </div>
          </td>

          <td style="width:30%; vertical-align:bottom; text-align:right; font-size:8px;">
            Fecha impresión:<br>
            {{ date('Y-m-d H:i:s') }}
          </td>
        </tr>
      </table>
    </div>
  @endif

</body>
</html>