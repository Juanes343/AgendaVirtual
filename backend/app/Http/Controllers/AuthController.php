<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SystemUsuarioVirtual;
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

            $activationUrl = 'https://devel82els.simde.com.co/PortalPaciente/SERVIMEDICOS/AgendaVirtual/frontend/build/#/activar-cuenta?token=' . $token;

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
     * Endpoint para verificar existencia de paciente
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

        $paciente = Paciente::where('paciente_id', $pacienteId)
            ->where('tipo_id_paciente', $tipoDoc)
            ->first();

        if ($paciente) {
            return response()->json([
                'success' => true,
                'exists' => true,
                'paciente' => [
                    'primer_nombre' => $paciente->primer_nombre,
                    'segundo_nombre' => $paciente->segundo_nombre,
                    'primer_apellido' => $paciente->primer_apellido,
                    'segundo_apellido' => $paciente->segundo_apellido,
                    'fecha_nacimiento' => $paciente->fecha_nacimiento,
                    'sexo' => $paciente->sexo_id,
                    'celular' => $paciente->celular_telefono,
                    'email' => $paciente->email,
                ],
                'message' => 'Paciente encontrado'
            ]);
        }

        return response()->json([
            'success' => true,
            'exists' => false,
            'message' => 'Paciente nuevo'
        ]);
    }

    /**
     * Endpoint para autenticación de usuarios (Login)
     */
    public function login(Request $request)
    {
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

            $baseUrl = 'https://devel82els.simde.com.co/PortalPaciente/SERVIMEDICOS/AgendaVirtual/frontend/build';
            $link = $baseUrl . '/#/reset-password?token=' . $tokenStr;
            
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
        $request->validate([
            'newPassword' => 'required|min:6|confirmed', 
        ]);
        $user = $request->user();
        $user->passwd = Hash::make($request->newPassword);
        $user->save();
        return response()->json(['message' => 'Contraseña actualizada correctamente']);
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
}
