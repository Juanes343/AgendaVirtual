@extends('emails.layout')

@section('content')
    <h2 style="margin-top: 0;">Recuperación de Contraseña</h2>
    <p>Hola <strong>{{ $nombre }}</strong>,</p>
    
    <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong>SanDi•Med</strong>.</p>
    
    <p>Para continuar con el proceso, haz clic en el siguiente botón. Este enlace es válido por 60 minutos.</p>
    
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" class="btn btn-primary" style="margin: 20px 0;">
        <tr>
            <td align="center">
                <a href="{{ $link }}" target="_blank" class="btn-primary">Cambiar mi contraseña</a>
            </td>
        </tr>
    </table>
    
    <p style="font-size: 12px; color: #777;">Si no funciona el botón, copia y pega el siguiente enlace en tu navegador: <br>
    <a href="{{ $link }}" style="color: #3b82f6;">{{ $link }}</a></p>

    <p style="margin-top: 30px;">Si no solicitaste este cambio, puedes ignorar este mensaje con seguridad.</p>
    
    <p>Saludos,<br>El equipo de SanDi•Med</p>
@endsection