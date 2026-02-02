@extends('emails.layout')

@section('content')
<div style="text-align: left;">
    <h2 style="color: #0F3460; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Resultado de Apoyo Diagnóstico</h2>
    
    <p>Estimado(a) <strong>{{ $paciente_nombre }}</strong>,</p>
    
    <p>Se ha generado y enviado el resultado de su examen de apoyo diagnóstico correspondiente a:</p>
    
    <div style="background-color: #f8fafc; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0;">
        <p style="margin: 0;"><strong>Examen:</strong> {{ $examen_nombre }}</p>
        <p style="margin: 0;"><strong>Fecha:</strong> {{ $fecha_examen }}</p>
        <p style="margin: 0;"><strong>Orden:</strong> #{{ $numero_orden }}</p>
    </div>

    <p>Adjunto a este correo encontrará el documento PDF con el resultado oficial firmado por el profesional de salud.</p>
    
    @if($has_attachment)
    <p>También se ha adjuntado un archivo adicional (Detalle del Resultado) que complementa su estudio.</p>
    @endif

    <p style="margin-top: 30px;">
        Recuerde que puede consultar todo su historial médico y descargar sus resultados en cualquier momento a través de nuestro portal.
    </p>

    <div style="text-align: center; margin-top: 40px;">
        <a href="{{ env('FRONTEND_URL') }}" class="btn-primary" style="display: inline-block; background-color: #3b82f6; color: #ffffff; padding: 12px 25px; border-radius: 8px; text-decoration: none; font-weight: bold;">Ir al Portal del Paciente</a>
    </div>

    <p style="font-size: 11px; color: #64748b; margin-top: 50px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
        Este documento es confidencial y está protegido por la ley de protección de datos personales. 
        Si usted no es el destinatario de este correo, por favor elimínelo inmediatamente.
    </p>
</div>
@endsection