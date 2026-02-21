<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifique su Identidad</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 5px solid #0056b3; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: #0056b3; margin: 0; font-size: 28px; }
        .content { line-height: 1.6; }
        .greeting { font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #0056b3; }
        .button { display: inline-block; padding: 15px 30px; background-color: #007bff; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 25px 0; box-shadow: 0 4px 10px rgba(0,123,255,0.3); transition: transform 0.2s ease; }
        .footer { text-align: center; font-size: 12px; color: #777; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 20px; }
        .highlight { color: #0056b3; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>SanDi•Med</h1>
        </div>
        <div class="content">
            <div class="greeting">Hola, {{ $nombre }}</div>
            <p>Hemos recibido una solicitud para registrar su cuenta en nuestro portal web asociado al documento: <span class="highlight">{{ $documento }}</span>.</p>
            <p>Para garantizar la seguridad de su información, necesitamos que confirme su identidad haciendo clic en el siguiente enlace:</p>
            
            <div style="text-align: center;">
                <a href="{{ $activationUrl }}" class="button">Validar Identidad y Continuar</a>
            </div>

            <p>Este enlace es válido por las próximas <b>24 horas</b>. Si usted no realizó esta solicitud, puede ignorar este mensaje.</p>
            
            <p>Atentamente,<br><strong>El equipo de SanDi•Med</strong></p>
        </div>
        <div class="footer">
            © {{ date('Y') }} SanDi•Med - Portal del Paciente. Todos los derechos reservados.<br>
            Este es un correo automático, por favor no responda.
        </div>
    </div>
</body>
</html>