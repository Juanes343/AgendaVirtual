<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\SystemUsuarioVirtual;
use App\Models\Paciente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
        // 1. Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'tipo_doc' => 'required|string',
            'usuario'  => 'required|string', // Es el número de documento (paciente_id)
            'passwd'   => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors'  => $validator->errors()
            ], 400); // Bad Request
        }

        // 2. Buscar al usuario en la tabla system_usuarios_virtual
        $usuario = SystemUsuarioVirtual::where('paciente_id', $request->usuario)
                                       ->where('tipo_documento', $request->tipo_doc)
                                       ->first();

        // 3. Verificar si el usuario existe y la contraseña es correcta (MD5 Legacy)
        if (!$usuario || md5($request->passwd) !== $usuario->passwd) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales incorrectas'
            ], 401); // Unauthorized
        }

        // 4. Obtener datos del Paciente relacionado
        $paciente = Paciente::where('paciente_id', $usuario->paciente_id)
                            ->where('tipo_id_paciente', $usuario->tipo_documento)
                            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso',
            'data' => [
                'usuario' => [
                    'id' => $usuario->usuario_id_virtual,
                    'documento' => $usuario->paciente_id,
                    'tipo_documento' => $usuario->tipo_documento,
                ],
                'paciente' => $paciente ? [
                    'nombre_completo' => "{$paciente->primer_nombre} {$paciente->segundo_nombre} {$paciente->primer_apellido} {$paciente->segundo_apellido}",
                    'email' => $paciente->email,
                ] : null
            ]
        ]);
    }
    
    /**
     * Endpoint para restablecer contraseña
     */
    public function recoverPassword(Request $request)
    {
         return response()->json(['message' => 'Funcionalidad en construcción'], 501);
    }
}
