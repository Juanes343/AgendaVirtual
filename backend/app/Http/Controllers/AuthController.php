<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\SystemUsuarioVirtual;
use App\Models\Paciente;
use App\Models\TipoIdPaciente; // Modelo nuevo
use App\Models\TokenAgendaVirtual; // Modelo nuevo para los tokens
use App\Mail\RestorePasswordMail;  // Correo
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use App\Mail\WelcomeMail;

class AuthController extends Controller
{
    /**
     * Endpoint para Registro de nuevos pacientes
     * POST /api/register
     */
    public function register(Request $request)
    {
        // 1. Validar
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string|max:3',
            'usuario'  => 'required|string|max:32', // Numero documento
            'primer_nombre' => 'required|string|max:20',
            'primer_apellido' => 'required|string|max:30',
            'fecha_nacimiento' => 'required|date',
            'sexo' => 'required|in:M,F',
            'passwd' => 'required|string|min:6',
            'confirm_passwd' => 'required|same:passwd',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            DB::beginTransaction();

            $pacienteId = $request->usuario;
            $tipoDoc = $request->tipo_doc;

            // 2. Verificar si Paciente ya existe
            $pacienteExistente = Paciente::where('paciente_id', $pacienteId)
                ->where('tipo_id_paciente', $tipoDoc)
                ->first();

            if ($pacienteExistente) {
                // Verificar si ya tiene usuario virtual
                $usuarioVirtual = SystemUsuarioVirtual::where('paciente_id', $pacienteId)
                    ->where('tipo_documento', $tipoDoc)
                    ->first();

                if ($usuarioVirtual) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario ya se encuentra registrado en el sistema.'
                    ], 409);
                }
                // Si existe el paciente pero no el usuario, continuamos para crear solo el usuario...
                // (Para simplificar, asumiremos que si existe el paciente, usamos sus datos y solo creamos el usuario)
            } else {
                // 3. Crear Paciente
                $paciente = new Paciente();
                $paciente->paciente_id = $pacienteId;
                $paciente->tipo_id_paciente = $tipoDoc;
                $paciente->primer_nombre = strtoupper($request->primer_nombre);
                $paciente->segundo_nombre = strtoupper($request->segundo_nombre ?? '');
                $paciente->primer_apellido = strtoupper($request->primer_apellido);
                $paciente->segundo_apellido = strtoupper($request->segundo_apellido ?? '');
                $paciente->fecha_nacimiento = $request->fecha_nacimiento;
                $paciente->sexo_id = $request->sexo;
                $paciente->celular_telefono = $request->celular ?? '';
                $paciente->email = $request->email ?? '';
                
                // Defaults requeridos
                $paciente->usuario_id = 1; // Usuario sistema o self-registered
                $paciente->fecha_registro = now();
                $paciente->save();
            }

            // 4. Crear Usuario Virtual
            $usuario = new SystemUsuarioVirtual();
            $usuario->paciente_id = $pacienteId;
            $usuario->tipo_documento = $tipoDoc;
            $usuario->passwd = md5($request->passwd); // Legacy MD5
            //$usuario->created_at = now();
            //$usuario->updated_at = now();
            $usuario->save();

            // Enviar correo de bienvenida
            try {
                // Recuperar paciente para tener el email y nombre (si ya existía, usamos $pacienteExistente)
                $pacienteFinal = $pacienteExistente ?? $paciente;
                
                if (!empty($pacienteFinal->email)) {
                    $nombreCompleto = trim("{$pacienteFinal->primer_nombre} {$pacienteFinal->primer_apellido}");
                    Mail::to($pacienteFinal->email)->send(new WelcomeMail($nombreCompleto, $pacienteFinal->paciente_id));
                }
            } catch (\Exception $e) {
                // Loguear error pero no detener registro
                \Illuminate\Support\Facades\Log::error('Error enviando WelcomeMail: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor al registrar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint para verificar existencia de paciente
     * POST /api/check-patient
     */
    public function checkPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string',
            'usuario'  => 'required|string',
        ]);

        if ($validator->fails()) {
             return response()->json(['success' => false, 'message' => 'Faltan datos'], 400);
        }

        $pacienteId = $request->usuario;
        $tipoDoc = $request->tipo_doc;

        // 1. Verificar si ya tiene cuenta virtual
        $usuarioVirtual = SystemUsuarioVirtual::where('paciente_id', $pacienteId)
            ->where('tipo_documento', $tipoDoc)
            ->first();

        if ($usuarioVirtual) {
            return response()->json([
                'success' => true,
                'status' => 'has_account',
                'message' => 'El usuario ya tiene cuenta activa.'
            ]);
        }

        // 2. Verificar si existe como paciente
        $paciente = Paciente::where('paciente_id', $pacienteId)
            ->where('tipo_id_paciente', $tipoDoc)
            ->first();

        if ($paciente) {
             return response()->json([
                'success' => true,
                'status' => 'exists',
                'data' => [
                    'primer_nombre' => $paciente->primer_nombre,
                    'segundo_nombre' => $paciente->segundo_nombre,
                    'primer_apellido' => $paciente->primer_apellido,
                    'segundo_apellido' => $paciente->segundo_apellido,
                    'fecha_nacimiento' => $paciente->fecha_nacimiento,
                    'sexo' => $paciente->sexo_id,
                    'celular' => $paciente->celular_telefono,
                    'email' => $paciente->email
                ]
            ]);
        }

        // 3. No existe ni paciente ni usuario
        return response()->json([
            'success' => true,
            'status' => 'new',
            'message' => 'Paciente nuevo'
        ]);
    }

    /**
     * Endpoint para autenticación de usuarios (Login)
     * POST /api/login
     */

    public function login(Request $request)
    {
        // 1. Validar inputs
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string',
            'usuario'  => 'required|string', 
            'passwd'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors()
            ], 400); 
        }

        // 2. Buscar usuario
        $usuario = SystemUsuarioVirtual::where('paciente_id', $request->usuario)
                                       ->where('tipo_documento', $request->tipo_doc)
                                       ->first();

        // 3. Validar password (MD5)
        if (!$usuario || md5($request->passwd) !== $usuario->passwd) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ], 401); 
        }

        // 4. Autenticar manualmente para Sanctum (Usando helper auth() para evitar error de Clase no encontrada)
        auth()->login($usuario); 

        // 5. Crear Token
        // *IMPORTANTE*: Esto requiere que SystemUsuarioVirtual use el trait HasApiTokens
        $token = $request->user()->createToken('auth_token')->plainTextToken;

        // 6. Obtener datos extra (Paciente)
        $paciente = Paciente::where('paciente_id', $usuario->paciente_id)
                            ->where('tipo_id_paciente', $usuario->tipo_documento)
                            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'data' => [
                'token' => $token, // Token generado
                'usuario' => [
                    'id' => $usuario->usuario_id_virtual,
                    'documento' => $usuario->paciente_id, // Asegurar compatibilidad
                    'tipo_documento' => $usuario->tipo_documento,
                ],
                'paciente' => $paciente ? [
                    'paciente_id' => $paciente->paciente_id,
                    'tipo_id_paciente' => $paciente->tipo_id_paciente,
                    'primer_nombre' => $paciente->primer_nombre,
                    'segundo_nombre' => $paciente->segundo_nombre,
                    'primer_apellido' => $paciente->primer_apellido,
                    'segundo_apellido' => $paciente->segundo_apellido,
                    'nombre_completo' => trim("{$paciente->primer_nombre} {$paciente->segundo_nombre} {$paciente->primer_apellido} {$paciente->segundo_apellido}"),
                    'email' => $paciente->email,
                    'fecha_nacimiento' => $paciente->fecha_nacimiento,
                    'celular' => $paciente->celular_telefono,
                    'residencia_direccion' => $paciente->residencia_direccion
                ] : null
            ]
        ]);
    }
    
    /**
     * Endpoint para restablecer contraseña
     */
    public function recoverPassword(Request $request)
    {
        // 1. Validar
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string',
            'usuario'  => 'required|string', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Por favor ingrese tipo y número de documento.'], 400);
        }

        // 2. Buscar usuario
        $usuario = SystemUsuarioVirtual::where('paciente_id', $request->usuario)
            ->where('tipo_documento', $request->tipo_doc)
            ->first();

        // Para seguridad, verificamos si existe como paciente también
        $paciente = Paciente::where('paciente_id', $request->usuario)
            ->where('tipo_id_paciente', $request->tipo_doc)
            ->first();

        if (!$usuario || !$paciente) {
            // Retornamos 404 o un mensaje genérico por seguridad
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        if (empty($paciente->email)) {
            return response()->json(['success' => false, 'message' => 'Este usuario no tiene un correo registrado. Contacte a soporte.'], 400);
        }

        try {
            // 3. Generar Token aleatorio
            $tokenStr = Str::random(60);
            
            // 4. Guardar en base de datos (tokens_agenda_virtual)
            // Se asume estado '1' = Activo
            TokenAgendaVirtual::create([
                'incriptacion'   => $tokenStr,
                'paciente_id'    => $usuario->paciente_id,
                'tipo_documento' => $usuario->tipo_documento,
                'fecha_registro' => now(), // o date('Y-m-d H:i:s')
                'estado'         => '1'
            ]);

            // 5. Construir enlace para el frontend
            // Ajustamos la URL base según tu entorno (Hardcoded temporalmente según tu screenshot)
            $baseUrl = 'https://devel82els.simde.com.co/AgendaVirtual/frontend/build';
            $link = $baseUrl . '/#/reset-password?token=' . $tokenStr;
            
            $nombrePaciente = trim("{$paciente->primer_nombre} {$paciente->primer_apellido}");
            
            // 6. Enviar correo
            Mail::to($paciente->email)->send(new RestorePasswordMail($nombrePaciente, $link));

            // Enmascarar email para mostrarlo en el mensaje
            $maskedEmail = $this->maskEmail($paciente->email);

            return response()->json([
                'success' => true, 
                'message' => "Se ha enviado un enlace de recuperación al correo {$maskedEmail}"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'Error al procesar la solicitud de recuperación.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint para cambiar la contraseña usando el token
     */
    public function resetPassword(Request $request)
    {
        // 1. Validar inputs
        $validator = Validator::make($request->all(), [
            'token'    => 'required|string',
            'password' => 'required|string|min:6',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Contraseña inválida o no coinciden.',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // 2. Buscar token válido (estado '1' = activo)
            $tokenRecord = TokenAgendaVirtual::where('incriptacion', $request->token)
                ->where('estado', '1')
                ->first();

            if (!$tokenRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'El enlace de recuperación es inválido o ya ha sido utilizado.'
                ], 404);
            }

            // Opcional: Verificar expiración (ej: 24 horas)
            // if ($tokenRecord->fecha_registro < now()->subHours(24)) { ... }

            // 3. Buscar Usuario Virtual
            $usuario = SystemUsuarioVirtual::where('paciente_id', $tokenRecord->paciente_id)
                ->where('tipo_documento', $tokenRecord->tipo_documento)
                ->first();

            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario asociado no encontrado.'], 404);
            }

            // 4. Actualizar contraseña (MD5 según lógica legacy observada)
            $usuario->passwd = md5($request->password);
            $usuario->save();

            // 5. Invalidar Token
            $tokenRecord->estado = '0';
            $tokenRecord->save();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña restablecida exitosamente. Ahora puede iniciar sesión.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al restablecer la contraseña.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper para ocultar parte del correo
    private function maskEmail($email) {
        $parts = explode('@', $email);
        if(count($parts) < 2) return $email;
        $name = $parts[0];
        $len = strlen($name);
        $visibleLen = floor($len / 2);
        $maskedName = substr($name, 0, $visibleLen) . str_repeat('*', $len - $visibleLen);
        return $maskedName . '@' . $parts[1];
    }

    /**
     * Generar y descargar el manual de usuario
     */
    public function downloadManual()
    {
        $dompdf = new Dompdf();
        $html = view('manual.usuario')->render();
        $dompdf->set_option('isRemoteEnabled', true);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="Manual_Usuario_AgendaVirtual.pdf"');
    }

    /**
     * Actualiza los datos de perfil del paciente
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (!$user->paciente_id) {
            return response()->json(['message' => 'Usuario no es un paciente'], 400);
        }

        // Validar datos básicos
        $request->validate([
            'email' => 'required|email',
            'celular' => 'nullable|string',
            'direccion' => 'nullable|string',
            'primer_nombre' => 'nullable|string|max:50',
            'segundo_nombre' => 'nullable|string|max:50',
            'primer_apellido' => 'nullable|string|max:50',
            'segundo_apellido' => 'nullable|string|max:50',
        ]);

        try {
            DB::beginTransaction();

            /* 
             // 1. Opcional: Actualizar email en tabla de usuarios virtuales si existiera columna email
             // $user->email = $request->email;
             // $user->save();
            */

            // 2. Actualizar datos en tabla pacientes
            DB::table('pacientes')
                ->where('paciente_id', $user->paciente_id)
                ->where('tipo_id_paciente', $user->tipo_documento)
                ->update([
                    'email' => $request->email,
                    'celular_telefono' => $request->celular,
                    'residencia_direccion' => $request->direccion,
                    'primer_nombre' => strtoupper($request->primer_nombre),
                    'segundo_nombre' => strtoupper($request->segundo_nombre),
                    'primer_apellido' => strtoupper($request->primer_apellido),
                    'segundo_apellido' => strtoupper($request->segundo_apellido),
                ]);

            DB::commit();
            
            // Recargar datos actualizados para responder
             $paciente = DB::table('pacientes')
                ->where('paciente_id', $user->paciente_id)
                ->where('tipo_id_paciente', $user->tipo_documento)
                ->first();

            return response()->json([
                'message' => 'Datos actualizados correctamente',
                'paciente' => $paciente 
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cambia la contraseña del usuario logueado
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'newPassword' => 'required|min:6|confirmed', 
        ]);

        $user = $request->user();
        
        // El sistema usa MD5 según endpoints anteriores
        $user->passwd = md5($request->newPassword);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada correctamente']);
    }

    /**
     * Obtener lista de tipos de documento
     */
    public function getDocumentTypes()
    {
        try {
            // Se asume que la columna es indice_de_orden según error SQL
            $types = TipoIdPaciente::orderBy('indice_de_orden', 'asc')->get();
            return response()->json($types);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching document types', 'message' => $e->getMessage()], 500);
        }
    }
}
