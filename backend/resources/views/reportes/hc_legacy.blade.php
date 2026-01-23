<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historia Clínica</title>

  <style>
    @page { margin: 20px 30px; }

    body{
      font-family: Arial, sans-serif;
      font-size: 9px;
      color:#000;
    }

    table{ width:100%; border-collapse: collapse; }

    /* ====== HEADER (igual al estilo que te gusta) ====== */
    .header-container{ width:100%; margin-bottom:10px; }
    .logo-cell{ width:15%; vertical-align: middle; }
    .info-cell{ text-align:center; font-size:10px; font-weight:bold; line-height: 1.2; }
    .logo-img{ max-width:100px; max-height:60px; }

    .doc-title{
      text-align:center;
      font-weight:bold;
      margin: 10px 0;
      font-size: 11px;
      background:#f0f0f0;
      border:1px solid #000;
      padding:5px;
    }

    /* ====== OVERRIDES PARA HTML LEGACY ======
       El legacy trae muchas tablas con border="1", clases viejas, etc.
       Esto las “normaliza” a tu estilo.
    */
    .legacy-wrap table{ width:100% !important; border-collapse: collapse !important; }
    .legacy-wrap td, .legacy-wrap th{
      border:1px solid #000 !important;
      padding:4px !important;
      vertical-align: top !important;
      font-size:9px !important;
    }

    /* Si el legacy usa celdas tipo titulo, intentamos darles fondo */
    .legacy-wrap .modulo_table_title td,
    .legacy-wrap .hc_table_submodulo_list_title td,
    .legacy-wrap th{
      background:#f0f0f0 !important;
      font-weight:bold !important;
    }

    /* Quitar estilos raros típicos del legacy */
    .legacy-wrap font { font-family: Arial, sans-serif !important; font-size: 9px !important; }
    .legacy-wrap * { box-sizing: border-box; }

    /* Imágenes del legacy (logo, firmas) */
    .legacy-wrap img{
      max-width: 100%;
      height: auto;
    }

    /* Footer */
    .print-footer{
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

  {{-- ====== HEADER ====== --}}
  <table class="header-container">
    <tr>
      <td class="logo-cell">
        {{-- Si quieres usar el logo del backend, pásalo como base64 --}}
        @if(!empty($logoBase64))
          <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo">
        @else
          {{-- Si prefieres que lo cargue del legacy --}}
          <img src="{{ rtrim($baseUrl,'/') }}/images/logocliente.png" class="logo-img" alt="Logo">
        @endif
      </td>
      <td class="info-cell">
        {{-- Puedes poner datos fijos o venir de $empresa --}}
        @if(!empty($empresa))
          {{ $empresa->razon_social ?? '' }}<br>
          NIT {{ $empresa->nit ?? '' }}-{{ $empresa->digito_verificacion ?? '' }}<br>
          {{ $empresa->direccion ?? '' }} - {{ $empresa->municipio ?? '' }}, {{ $empresa->departamento ?? '' }}<br>
          Teléfono: {{ $empresa->telefonos ?? '' }}<br>
          {{ $empresa->website ?? '' }}
        @else
          SIIS - APLICACIÓN DE PRUEBAS<br>
          AV 1 15 04 LA PLAYA - MEDELLIN, DFG<br>
          Teléfono: 6075960150<br>
          https://clinicasandiegocucuta.com/web/
        @endif
      </td>
    </tr>
  </table>

  <div class="doc-title">HISTORIA CLÍNICA</div>

  {{-- ====== CONTENIDO LEGACY ====== --}}
  <div class="legacy-wrap">
    {!! $html !!}
  </div>

  <div class="print-footer">
    Impreso por sistema - {{ date('Y-m-d H:i:s') }}
  </div>

</body>
</html>