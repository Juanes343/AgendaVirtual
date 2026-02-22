<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: #0F52BA; color: #ffffff; padding: 20px; text-align: center; }
        .content { padding: 30px; color: #333333; line-height: 1.6; }
        .info-box { background: #f0f7ff; border-left: 4px solid #0F52BA; padding: 15px; margin: 20px 0; }
        .footer { background: #333333; color: #ffffff; padding: 15px; text-align: center; font-size: 12px; }
        h1 { margin: 0; font-size: 24px; }
        .btn { display: inline-block; background: #0F52BA; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
        .disclaimer { font-size: 11px; color: #777; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ env('APP_DISPLAY_NAME', 'SanDi•Med') }}</h1>
            <p>Reporte de Nota Operatoria</p>
        </div>
        <div class="content">
            <h2>Hola, {{ $nombre }}</h2>
            <p>A solicitud tuya, adjunto encontrarás el documento PDF con la Nota Operatoria de tu procedimiento quirúrgico.</p>
            
            <div class="info-box">
                <p><strong>Fecha del procedimiento:</strong> {{ $fecha }}</p>
                <p><strong>Cirujano:</strong> {{ $profesional }}</p>
                <p><strong>Procedimiento:</strong> {{ $tipo_reporte }}</p>
            </div>

            <p>Este documento contiene información confidencial de tu historia clínica. Por favor consérvalo de manera segura.</p>
            
            <div style="text-align: center;">
                <a href="https://devel82els.simde.com.co/AgendaVirtual/frontend/build/#/login" class="btn" style="color: #ffffff !important; text-decoration: none;">Ingresar al Portal</a>
            </div>

             <div class="disclaimer">
                <p>Este mensaje y sus adjuntos pueden contener información confidencial sometida a secreto profesional. Si usted no es el destinatario, por favor notifique al remitente y elimine este mensaje.</p>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ env('APP_DISPLAY_NAME', 'SanDi•Med') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>