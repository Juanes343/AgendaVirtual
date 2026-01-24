<?php
public function getAttachments(Request $request)
{
    // Obtener usuario autenticado
    // Se asume que el usuario autenticado tiene relación con el paciente
    // o que se pasan los parametros. Ajustar según tu lógica de sesión.
    // Ejemplo usando Paciente del usuario actual:
    $user = Auth::user();
    $paciente = $user->paciente; // Asumiendo relación
    
    // Si no obtienes el paciente de la sesión, valida los parametros del request
    $tipo_id = $paciente->tipo_id_paciente;
    $paciente_id = $paciente->paciente_id;

    $attachments = DB::select("
        SELECT 
            a.*, 
            TO_CHAR(a.fecha_registro,'DD/MM/YYYY') AS fecha_registro, 
            SU.nombre as usuario 
        FROM 
            hc_archivos_adjuntos a, 
            system_usuarios SU 
        WHERE 
            a.tipo_id_paciente = ? 
            AND a.paciente_id = ? 
            AND a.usuario_id = SU.usuario_id
        ORDER BY a.fecha_registro DESC
    ", [$tipo_id, $paciente_id]);

    return response()->json([
        'success' => true,
        'data' => $attachments
    ]);
}