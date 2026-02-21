<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SystemUsuarioVirtual;
use App\Models\SystemUsuario;
use App\Models\Paciente;
use App\Models\TipoIdPaciente;
use App\Models\TokenAgendaVirtual;
use App\Mail\RestorePasswordMail;
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
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string|max:3',
            'usuario'  => 'required|string|max:32',
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

            $pacienteExistente = Paciente::where('paciente_id', $pacienteId)
                ->where('tipo_id_paciente', $tipoDoc)
                ->first();

            if ($pacienteExistente) {
                $usuarioVirtual = SystemUsuarioVirtual::where('paciente_id', $pacienteId)
                    ->where('tipo_documento', $tipoDoc)
                    ->first();

                if ($usuarioVirtual) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario ya se encuentra registrado en el sistema.'
                    ], 409);
                }
            } else {
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
                $paciente->usuario_id = 1;
                $paciente->ocupacion_id = 'NA';
                $paciente->fecha_registro = now();
                $paciente->save();
            }

            $usuario = new SystemUsuarioVirtual();
            $usuario->paciente_id = $pacienteId;
            $usuario->tipo_documento = $tipoDoc;
            $usuario->passwd = Hash::make($request->passwd);
            $usuario->estado = '0';
            $usuario->save();

            $token = Str::random(64);
            TokenAgendaVirtual::create([
                'incriptacion' => $token,
                'paciente_id' => $usuario->paciente_id,
                'tipo_documento' => $usuario->tipo_documento,
                'fecha_registro' => now(),
                'estado' => '1',
            ]);

            $frontendUrl = rtrim(env('FRONTEND_URL'), '/');
            $activationUrl = $frontendUrl . '/#/activar-cuenta?token=' . $token;

            $pacienteFinal = $pacienteExistente ?? $paciente;
            if (!empty($pacienteFinal->email)) {
                Mail::send('emails.welcome', [
                    'nombre' => trim("{$pacienteFinal->primer_nombre} {$pacienteFinal->primer_apellido}"),
                    'documento' => $pacienteFinal->paciente_id,
                    'activationUrl' => $activationUrl
                ], function ($message) use ($pacienteFinal) {
                    $message->to($pacienteFinal->email)
                        ->subject('¡Bienvenido a SanDi•Med! - Activa tu cuenta');
                });
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registro exitoso. Revisa tu correo para activar la cuenta.',
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
     * Endpoint para activar usuario por token de activación
     */
    public function activateAccount($token)
    {
        $tokenRow = TokenAgendaVirtual::where('incriptacion', $token)
            ->where('estado', '1')
            ->first();

        if (!$tokenRow) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido o ya utilizado.'
            ], 400);
        }

        $expira = \Carbon\Carbon::parse($tokenRow->fecha_registro)->addHours(24);
        if (now()->greaterThan($expira)) {
            $tokenRow->estado = '0';
            $tokenRow->save();
            return response()->json([
                'success' => false,
                'message' => 'El enlace de activación ha expirado.'
            ], 400);
        }

        $usuario = SystemUsuarioVirtual::where('paciente_id', $tokenRow->paciente_id)
            ->where('tipo_documento', $tokenRow->tipo_documento)
            ->first();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.'
            ], 404);
        }

        $usuario->estado = '1';
        $usuario->save();

        $tokenRow->estado = '0';
        $tokenRow->save();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta activada correctamente. Ya puedes iniciar sesión.'
        ]);
    }

    /**
     * Endpoint para Validar si el paciente existe antes del paso 2
     * POST /api/check-patient
     */
    public function checkPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string',
            'usuario'  => 'required|string',
            'email'    => 'nullable|email'
        ]);

        if ($validator->fails()) {
             return response()->json(['success' => false, 'message' => 'Faltan datos'], 400);
        }

        $pacienteId = trim($request->usuario);
        $tipoDoc = trim($request->tipo_doc);
        $inputEmail = strtolower(trim($request->email ?? ''));

        // 1. Validamos si YA TIENE CUENTA en el portal virtual
        $usuarioVirtual = SystemUsuarioVirtual::where('paciente_id', $pacienteId)
            ->where('tipo_documento', $tipoDoc)
            ->first();

        if ($usuarioVirtual) {
            return response()->json([
                'success' => true,
                'status' => 'has_account',
                'exists' => true,
                'message' => 'Usted ya tiene una cuenta activa. Por favor inicie sesión.'
            ]);
        }

        // 2. Buscamos en la tabla maestra de PACIENTES (Legacy)
        $paciente = Paciente::whereRaw('trim(paciente_id) = ?', [$pacienteId])
            ->whereRaw('trim(tipo_id_paciente) = ?', [$tipoDoc])
            ->first();

        if ($paciente) {
            // Existe como paciente pero NO tiene cuenta portal aún.
            // MODIFICACIÓN: Obligatorio validar correo ingresado vs correo en DB
            $dbEmail = strtolower(trim($paciente->email ?? ''));

            if (empty($inputEmail)) {
                 return response()->json([
                    'success' => false,
                    'status' => 'email_required',
                    'message' => 'Ingrese su correo para validar su identidad.'
                ], 400);
            }

            // Debug logger opcional (si se tiene configurado, útil para producción)
            // \Log::info("Validando registro: Input[$inputEmail] vs DB[$dbEmail]");

            if ($inputEmail !== $dbEmail) {
                return response()->json([
                    'success' => false,
                    'status' => 'email_mismatch',
                    'message' => 'El correo ingresado no coincide con nuestros registros. Por favor, solicite la actualización de su correo en el centro de atención.'
                ], 403);
            }

            // CORREO COINCIDE! - Generamos Token de Verificación
            $token = Str::random(64);
            TokenAgendaVirtual::create([
                'incriptacion' => $token,
                'paciente_id' => $pacienteId,
                'tipo_documento' => $tipoDoc,
                'fecha_registro' => now(),
                'estado' => '1',
            ]);

            $frontendUrl = rtrim(env('FRONTEND_URL'), '/');
            $verificationUrl = $frontendUrl . '/#/register?token=' . $token;

            Mail::send('emails.verify_identity', [
                'nombre' => trim("{$paciente->primer_nombre} {$paciente->primer_apellido}"),
                'documento' => $pacienteId,
                'activationUrl' => $verificationUrl
            ], function ($message) use ($paciente) {
                $message->to($paciente->email)
                    ->subject('Verifique su identidad - SanDi•Med');
            });

            return response()->json([
                'success' => true,
                'status' => 'needs_verification',
                'message' => 'Se ha enviado un correo para su verificación.',
                'email' => $paciente->email
            ]);
        }

        // 3. No existe como paciente. Puede registrarse libremente sin validar correo previo
        return response()->json([
            'success' => true,
            'status' => 'not_found',
            'exists' => false,
            'message' => 'Paciente no encontrado. Puede proceder con el registro.'
        ]);
    }

    /**
     * Endpoint para validar token de verificación de registro y devolver datos precargados
     * GET /api/verify-registration-token/{token}
     */
    public function verifyRegistrationToken($token)
    {
        $tokenRow = TokenAgendaVirtual::where('incriptacion', $token)
            ->where('estado', '1')
            ->first();

        if (!$tokenRow) {
            return response()->json(['success' => false, 'message' => 'El enlace es inválido o ya ha sido utilizado.'], 400);
        }

        if (\Carbon\Carbon::parse($tokenRow->fecha_registro)->addHours(24)->isPast()) {
             return response()->json(['success' => false, 'message' => 'El enlace ha expirado.'], 400);
        }

        $paciente = Paciente::where('paciente_id', $tokenRow->paciente_id)
            ->where('tipo_id_paciente', $tokenRow->tipo_documento)
            ->first();

        if (!$paciente) {
            return response()->json(['success' => false, 'message' => 'Paciente no encontrado.'], 404);
        }

        return response()->json([
            'success' => true,
            'paciente' => [
                'primer_nombre' => $paciente->primer_nombre,
                'segundo_nombre' => $paciente->segundo_nombre,
                'primer_apellido' => $paciente->primer_apellido,
                'segundo_apellido' => $paciente->segundo_apellido,
                'fecha_nacimiento' => $paciente->fecha_nacimiento,
                'sexo' => $paciente->sexo_id,
                'celular' => $paciente->celular_telefono,
                'email' => $paciente->email,
                'tipo_doc' => $paciente->tipo_id_paciente,
                'usuario' => $paciente->paciente_id
            ]
        ]);
    }

    /**
     * Endpoint para autenticación de usuarios (Login)
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'nullable|string',
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

        // 1. Intentar buscar en system_usuarios (Admins)
        $admin = SystemUsuario::where('usuario', $request->usuario)
            ->where('activo', '1')
            ->where('sw_admin', '1')
            ->first();

        if ($admin) {
            // Validar password MD5 para admins internos
            if (md5($request->passwd) === $admin->passwd) {
                auth()->login($admin);
                $token = $admin->createToken('auth_token')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Inicio de sesión administrativo exitoso',
                    'data' => [
                        'token' => $token,
                        'usuario' => [
                            'id' => $admin->usuario_id,
                            'usuario' => $admin->usuario,
                            'nombre' => $admin->nombre,
                            'sw_admin' => true
                        ]
                    ]
                ]);
            }
        }

        // 2. Si no es admin, buscar en SystemUsuarioVirtual (Pacientes)
        $usuario = SystemUsuarioVirtual::where('paciente_id', $request->usuario)
            ->where('tipo_documento', $request->tipo_doc)
            ->first();

        // 3. Validar password (Hash::check con fallback a MD5)
        $authenticated = false;
        if ($usuario) {
            // Solo usamos Hash::check si la contraseña almacenada parece un hash de Bcrypt ($2y$)
            $esBcrypt = strpos($usuario->passwd, '$2y$') === 0;

            if ($esBcrypt) {
                if (Hash::check($request->passwd, $usuario->passwd)) {
                    $authenticated = true;
                }
            } else {
                // Si no es Bcrypt, probamos MD5 (Compatibilidad con usuarios antiguos)
                if (md5($request->passwd) === $usuario->passwd) {
                    // Migrar a Bcrypt automáticamente para la próxima vez
                    $usuario->passwd = Hash::make($request->passwd);
                    $usuario->save();
                    $authenticated = true;
                }
            }
        }

        if (!$authenticated) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ], 401); 
        }

        if ($usuario->estado != '1') {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta aún no ha sido activada. Revisa tu correo para activarla.'
            ], 403);
        }

        auth()->login($usuario); 

        $token = $request->user()->createToken('auth_token')->plainTextToken;

        $paciente = Paciente::where('paciente_id', $usuario->paciente_id)
            ->where('tipo_id_paciente', $usuario->tipo_documento)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'data' => [
                'token' => $token,
                'usuario' => [
                    'id' => $usuario->usuario_id_virtual,
                    'documento' => $usuario->paciente_id,
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
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string',
            'usuario'  => 'required|string', 
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Por favor ingrese tipo y número de documento.'], 400);
        }

        $usuario = SystemUsuarioVirtual::where('paciente_id', $request->usuario)
            ->where('tipo_documento', $request->tipo_doc)
            ->first();

        $paciente = Paciente::where('paciente_id', $request->usuario)
            ->where('tipo_id_paciente', $request->tipo_doc)
            ->first();

        if (!$usuario || !$paciente) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        if (empty($paciente->email)) {
            return response()->json(['success' => false, 'message' => 'Este usuario no tiene un correo registrado. Contacte a soporte.'], 400);
        }

        try {
            $tokenStr = Str::random(60);
            TokenAgendaVirtual::create([
                'incriptacion'   => $tokenStr,
                'paciente_id'    => $usuario->paciente_id,
                'tipo_documento' => $usuario->tipo_documento,
                'fecha_registro' => now(),
                'estado'         => '1'
            ]);

            $frontendUrl = rtrim(env('FRONTEND_URL'), '/');
            $link = $frontendUrl . '/#/reset-password?token=' . $tokenStr;
            
            $nombrePaciente = trim("{$paciente->primer_nombre} {$paciente->primer_apellido}");
            Mail::to($paciente->email)->send(new RestorePasswordMail($nombrePaciente, $link));

            return response()->json([
                'success' => true, 
                'message' => "Se ha enviado un enlace de recuperación al correo " . $this->maskEmail($paciente->email)
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
            $tokenRecord = TokenAgendaVirtual::where('incriptacion', $request->token)
                ->where('estado', '1')
                ->first();

            if (!$tokenRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'El enlace de recuperación es inválido o ya ha sido utilizado.'
                ], 404);
            }

            $usuario = SystemUsuarioVirtual::where('paciente_id', $tokenRecord->paciente_id)
                ->where('tipo_documento', $tokenRecord->tipo_documento)
                ->first();

            if (!$usuario) {
                return response()->json(['success' => false, 'message' => 'Usuario asociado no encontrado.'], 404);
            }

            $usuario->passwd = Hash::make($request->password);
            $usuario->save();

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

    private function maskEmail($email) {
        $parts = explode('@', $email);
        if(count($parts) < 2) return $email;
        $name = $parts[0];
        $len = strlen($name);
        $visibleLen = floor($len / 2);
        $maskedName = substr($name, 0, $visibleLen) . str_repeat('*', $len - $visibleLen);
        return $maskedName . '@' . $parts[1];
    }

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

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (!$user->paciente_id) {
            return response()->json(['message' => 'Usuario no es un paciente'], 400);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'celular_telefono' => 'nullable|string',
            'direccion' => 'nullable|string',
            'primer_nombre' => 'nullable|string|max:50',
            'segundo_nombre' => 'nullable|string|max:50',
            'primer_apellido' => 'nullable|string|max:50',
            'segundo_apellido' => 'nullable|string|max:50',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El formato del correo electrónico no es válido.',
            'max' => 'El campo :attribute no debe superar los :max caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 1. Obtener datos anteriores para auditoría
            $oldPaciente = DB::table('pacientes')
                ->where('paciente_id', $user->paciente_id)
                ->where('tipo_id_paciente', $user->tipo_documento)
                ->first();

            if (!$oldPaciente) {
                throw new \Exception('Registro de paciente no encontrado sistemáticamente.');
            }

            $newEmail = $request->email;
            $newCelular = $request->celular_telefono;
            $newDireccion = $request->direccion;
            $newPrimerNombre = strtoupper($request->primer_nombre);
            $newSegundoNombre = strtoupper($request->segundo_nombre);
            $newPrimerApellido = strtoupper($request->primer_apellido);
            $newSegundoApellido = strtoupper($request->segundo_apellido);

            // 2. Auditar cada campo que cambie
            $ip = $request->ip();
            
            if (trim($oldPaciente->email) !== trim($newEmail)) {
                $this->logAudit($user->paciente_id, $user->tipo_documento, 'Email', $oldPaciente->email, $newEmail, $ip);
            }
            if (trim($oldPaciente->celular_telefono) !== trim($newCelular)) {
                $this->logAudit($user->paciente_id, $user->tipo_documento, 'Celular', $oldPaciente->celular_telefono, $newCelular, $ip);
            }
            if (trim($oldPaciente->residencia_direccion) !== trim($newDireccion)) {
                $this->logAudit($user->paciente_id, $user->tipo_documento, 'Dirección de Residencia', $oldPaciente->residencia_direccion, $newDireccion, $ip);
            }
            
            // Auditoría para Nombres y Apellidos
            if (trim($oldPaciente->primer_nombre) !== trim($newPrimerNombre) || trim($oldPaciente->segundo_nombre) !== trim($newSegundoNombre)) {
                 $nombreAnterior = trim(($oldPaciente->primer_nombre ?? '') . ' ' . ($oldPaciente->segundo_nombre ?? ''));
                 $nombreNuevo = trim("$newPrimerNombre $newSegundoNombre");
                 $this->logAudit($user->paciente_id, $user->tipo_documento, 'Nombres', $nombreAnterior, $nombreNuevo, $ip);
            }
            if (trim($oldPaciente->primer_apellido) !== trim($newPrimerApellido) || trim($oldPaciente->segundo_apellido) !== trim($newSegundoApellido)) {
                 $apellidoAnterior = trim(($oldPaciente->primer_apellido ?? '') . ' ' . ($oldPaciente->segundo_apellido ?? ''));
                 $apellidoNuevo = trim("$newPrimerApellido $newSegundoApellido");
                 $this->logAudit($user->paciente_id, $user->tipo_documento, 'Apellidos', $apellidoAnterior, $apellidoNuevo, $ip);
            }

            // 3. Actualizar datos en la tabla
            DB::table('pacientes')
                ->where('paciente_id', $user->paciente_id)
                ->where('tipo_id_paciente', $user->tipo_documento)
                ->update([
                    'email' => $newEmail,
                    'celular_telefono' => $newCelular,
                    'residencia_direccion' => $newDireccion,
                    'primer_nombre' => $newPrimerNombre,
                    'segundo_nombre' => $newSegundoNombre,
                    'primer_apellido' => $newPrimerApellido,
                    'segundo_apellido' => $newSegundoApellido,
                ]);

            DB::commit();
            
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

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'newPassword' => 'required|min:6|confirmed', 
        ], [
            'newPassword.required' => 'La nueva contraseña es obligatoria.',
            'newPassword.min' => 'La nueva contraseña debe tener al menos 6 caracteres.',
            'newPassword.confirmed' => 'La confirmación de la contraseña no coincide.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = $request->user();
        $oldPassword = $user->passwd; // Hash actual
        $newPasswordHash = Hash::make($request->newPassword);

        $user->passwd = $newPasswordHash;
        $user->save();

        // Auditoría del cambio de contraseña guardando el hash (encriptada)
        $this->logAudit($user->paciente_id, $user->tipo_documento, 'Contraseña', $oldPassword, $newPasswordHash, $request->ip());

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada correctamente']);
    }

    public function getDocumentTypes()
    {
        try {
            $types = TipoIdPaciente::orderBy('indice_de_orden', 'asc')->get();
            return response()->json($types);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error fetching document types', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Registrar auditoría de modificaciones del paciente
     */
    private function logAudit($pacienteId, $tipoId, $campo, $anterior, $nuevo, $ip)
    {
        try {
            DB::table('audit_paciente_modificaciones')->insert([
                'paciente_id' => $pacienteId,
                'tipo_id_paciente' => $tipoId,
                'campo' => $campo,
                'valor_anterior' => $anterior,
                'valor_nuevo' => $nuevo,
                'fecha_registro' => now(),
                'ip' => $ip
            ]);
        } catch (\Exception $e) {
            \Log::error("Error en auditoría ($campo): " . $e->getMessage());
        }
    }
}
