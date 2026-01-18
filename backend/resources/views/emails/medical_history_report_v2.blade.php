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
        h1 { margin: 0; font-size: 24px; font-weight: bold; }
        .btn { display: inline-block; background: #0F52BA; color: #ffffff !important; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 15px; font-weight: bold; }
        .disclaimer { font-size: 11px; color: #777; margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SIMDE</h1>
            <p>Reporte de Historia Clínica por SIMDE</p>
        </div>
        <div class="content">
            <h2>Hola, {{ $nombre }}</h2>
            <p>A solicitud tuya, adjunto encontrarás los documentos PDF con el reporte detallado de tu atención médica (Historia Clínica Completa, Fórmula Médica y Órdenes).</p>
            
            <div class="info-box">
                <p><strong>Ingreso:</strong> #{{ $ingreso }}</p>
                <p><strong>Fecha de atención:</strong> {{ $fecha }}</p>
                <p><strong>Profesional:</strong> {{ $profesional }}</p>
            </div>

            <p>Estos documentos contienen información confidencial. Por favor consérvalos de manera segura.</p>
            
            <div style="text-align: center;">
                <a href="https://devel82els.simde.com.co/AgendaVirtual/frontend/build" class="btn">Ingresar al Portal</a>
            </div>

             <div class="disclaimer">
                <p>Este mensaje y sus adjuntos pueden contener información confidencial sometida a secreto profesional. Si usted no es el destinatario, por favor notifique al remitente y elimine este mensaje.</p>
            </div>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} SIMDE SAS. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>