<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f6f9; color: #333; margin: 0; padding: 0; }
        .container { width: 100%; max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background-color: #0d47a1; color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 700; letter-spacing: 0.5px; }
        .header p { margin: 5px 0 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 40px 30px; }
        .greeting { font-size: 18px; font-weight: bold; color: #1f2937; margin-bottom: 15px; text-transform: uppercase; }
        .message { color: #4b5563; line-height: 1.6; margin-bottom: 25px; }
        .info-box { background-color: #f0f9ff; border-left: 4px solid #0284c7; padding: 20px; margin: 25px 0; border-radius: 4px; }
        .info-row { margin-bottom: 10px; font-size: 15px; color: #374151; }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { font-weight: bold; color: #111827; display: inline-block; width: 150px; }
        .btn-container { text-align: center; margin-top: 35px; margin-bottom: 10px; }
        .btn { background-color: #1e40af; color: #ffffff !important; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; display: inline-block; transition: background-color 0.3s; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn:hover { background-color: #1e3a8a; }
        .footer { background-color: #1f2937; color: #9ca3af; text-align: center; padding: 20px; font-size: 12px; line-height: 1.5; border-top: 1px solid #374151; }
        .footer p { margin: 5px 0; }
        .legal { font-size: 11px; color: #6b7280; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SanDi&bull;Med</h1>
            <p>Reporte de Procedimientos No Quirúrgicos</p>
        </div>
        <div class="content">
            <div class="greeting">Hola, {{ $paciente->nombre_completo }}</div>
            <p class="message">
                A solicitud tuya, adjunto encontrarás el documento PDF con la Solicitud de Procedimientos No Quirúrgicos correspondiente a tu atención médica.
            </p>
            
            <div class="info-box">
                <div class="info-row"><span class="info-label">Fecha del reporte:</span> {{ $fecha }}</div>
                <div class="info-row"><span class="info-label">Nro. Ingreso:</span> #{{ $ingreso }}</div>
                @if(isset($profesional) && $profesional)
                    <div class="info-row"><span class="info-label">Profesional:</span> {{ $profesional->nombre }}</div>
                @endif
                <div class="info-row"><span class="info-label">Documento:</span> Solicitud No Quirúrgica</div>
            </div>

            <p class="message">
                Este documento contiene información confidencial de tu historia clínica. Por favor consérvalo de manera segura y no lo compartas con terceros no autorizados.
            </p>

            <div class="btn-container">
                <a href="{{ env('FRONTEND_URL') }}" class="btn">Ingresar al Portal</a>
            </div>
        </div>
        <div class="footer">
            <p>Este mensaje y sus adjuntos pueden contener información confidencial sometida a secreto profesional.</p>
            <p class="legal">Si usted no es el destinatario, por favor notifique al remitente y elimine este mensaje.</p>
            <p>&copy; {{ date('Y') }} SanDi-Med. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>