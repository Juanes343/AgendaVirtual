<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso a tu Portal del Paciente</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333; }
        .container { max-width: 600px; margin: 20px auto; background: #ffffff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 5px solid #0056b3; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: #0056b3; margin: 0; font-size: 28px; }
        .content { line-height: 1.6; }
        .greeting { font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #0056b3; }
        .info-box { background: #f0f7ff; border-left: 4px solid #0056b3; padding: 15px 20px; margin: 20px 0; border-radius: 4px; }
        .info-box p { margin: 6px 0; }
        .button { display: inline-block; padding: 14px 30px; background-color: #0056b3; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 25px 0; box-shadow: 0 4px 10px rgba(0,86,179,0.3); }
        .highlight { color: #0056b3; font-weight: bold; }
        .footer { text-align: center; font-size: 12px; color: #777; margin-top: 30px; border-top: 1px solid #eeeeee; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>{{ $appName }}</h1>
        </div>
        <div class="content">
            <p><strong>Estimado usuario,</strong></p>
            <p>¡Bienvenido! Nos complace informarle que su servicio ha sido completado exitosamente y, como parte de este proceso, se ha creado una cuenta de usuario virtual para usted.</p>
            <p>A través de esta cuenta podrá realizar procesos de autogestión de sus servicios de manera rápida y sencilla, accediendo a las funcionalidades disponibles en nuestro software principal.</p>
            <p>Le invitamos a ingresar con sus credenciales y explorar las opciones que hemos dispuesto para facilitar su experiencia.</p>

            <div class="info-box">
                <p><strong>Su usuario de acceso es:</strong> <span class="highlight">{{ $documento }}</span></p>
                <p>Si no recuerda su contraseña, puede restablecerla desde el portal usando la opción <em>"¿Olvidé mi contraseña?"</em>.</p>
            </div>

            <div style="text-align: center;">
                <a href="{{ $portalUrl }}" class="button">Ingresar al Portal del Paciente</a>
            </div>

            <p>Gracias por confiar en nosotros.</p>

            <p>Atentamente,<br><strong>Equipo de Soporte</strong></p>
        </div>
        <div class="footer">
            © {{ date('Y') }} {{ $appName }} - Portal del Paciente. Todos los derechos reservados.<br>
            Este es un correo automático, por favor no responda.
        </div>
    </div>
</body>
</html>
