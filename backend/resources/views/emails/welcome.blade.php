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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SanDi•Med</h1>
            <p>Portal del Paciente</p>
        </div>
        <div class="content">
            <h2>¡Hola, {{ $nombre }}!</h2>
            <p>Tu cuenta ha sido creada exitosamente. Te damos la bienvenida a nuestra plataforma de gestión de salud.</p>
            
            <div class="info-box">
                <p><strong>Usuario / Documento:</strong> {{ $documento }}</p>
                <p>Para activar tu cuenta y poder ingresar, haz clic en el siguiente botón:</p>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $activationUrl }}" 
                   style="display: inline-block; background: #0F52BA; color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; font-family: Arial, sans-serif;" 
                   target="_blank">
                   Activar mi cuenta
                </a>
            </div>

            <p style="font-size:13px; color:#888; text-align:center;">Este enlace es válido por 24 horas. Si no solicitaste este registro, puedes ignorar este mensaje.</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} SanDi•Med. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>