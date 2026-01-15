<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear un Paciente de prueba
        $pacienteId = '123456789';
        $tipoDoc = 'CC';

        // Verificar si ya existe pa no duplicar
        $exists = DB::table('pacientes')
            ->where('paciente_id', $pacienteId)
            ->where('tipo_id_paciente', $tipoDoc)
            ->exists();

        if (!$exists) {
            DB::table('pacientes')->insert([
                'paciente_id' => $pacienteId,
                'tipo_id_paciente' => $tipoDoc,
                'primer_apellido' => 'PEREZ',
                'segundo_apellido' => 'LOPEZ',
                'primer_nombre' => 'JUAN',
                'segundo_nombre' => 'TEST',
                'fecha_nacimiento' => '1990-01-01',
                'sexo_id' => 'M', // Changed from sexo
                'residencia_direccion' => 'Calle Falsa 123',
                'residencia_telefono' => '5551234',
                'celular_telefono' => '3001234567', // Changed from celular
                'email' => 'juan.perez@test.com',
                'tipo_dpto_id' => '11', // Changed from departamento_id
                'tipo_mpio_id' => '001', // Changed from municipio_id
                'zona_residencia' => 'U',
                'usuario_id' => 1, // Required field
                'fecha_registro' => now(), // Required field
                //'created_at' => now(),
                //'updated_at' => now(),
            ]);

            $this->command->info("Paciente creado: $tipoDoc $pacienteId");
        } else {
             $this->command->warn("Paciente ya existe: $tipoDoc $pacienteId");
        }

        // 2. Crear el usuario de sistema virtual (Login)
        // La contraseña en legacy es MD5
        $passwdRaw = '123456'; 
        
        $userExists = DB::table('system_usuarios_virtual')
            ->where('paciente_id', $pacienteId)
            ->where('tipo_documento', $tipoDoc)
            ->exists();

        if (!$userExists) {
            DB::table('system_usuarios_virtual')->insert([
                'paciente_id' => $pacienteId,
                'tipo_documento' => $tipoDoc,
                'passwd' => md5($passwdRaw), // MD5 legacy
                // 'estado' => 'A', // Column does not exist in migration
                // 'created_at' => now(), // Changed from fecha_registro
                // 'updated_at' => now(),
            ]);
            $this->command->info("Usuario virtual creado. Pass: $passwdRaw");
        } else {
             $this->command->warn("Usuario virtual ya existe.");
        }
    }
}
