<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EncuestaSatisfaccionSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiar para evitar duplicados en la prueba
        DB::table('hc_encuesta_satisfaccion')->where('ingreso', 123456)->delete();

        // Insertar una encuesta de prueba para el ingreso 123456
        DB::table('hc_encuesta_satisfaccion')->insert([
            'tipo_id_paciente' => 'CC',
            'paciente_id'      => '12345',
            'ingreso'          => 123456,
            'pregunta_1'       => 'Excelente',
            'pregunta_2'       => 'Definitivamente sí',
            'fecha_registro'   => now()
        ]);
    }
}