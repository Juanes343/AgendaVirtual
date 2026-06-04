<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EncuestaSatisfaccionPreguntasSeeder extends Seeder
{
    public function run(): void
    {
        // Asegurar que el grupo exista antes de insertar preguntas (FK grupo_id)
        $grupoExiste = DB::table('encuesta_satisfaccion_grupos')->where('grupo_id', 1)->exists();
        if (!$grupoExiste) {
            DB::table('encuesta_satisfaccion_grupos')->insert([
                'grupo_id'         => 1,
                'descripcion_grupo' => 'Satisfacción General',
                'indice_orden'     => 1,
            ]);
        }

        $preguntas = [
            [
                'grupo_id'             => 1,
                'descripcion_pregunta' => '¿Cómo califica la atención recibida por parte del personal médico?',
                'opciones'             => json_encode(['Excelente', 'Bueno', 'Regular', 'Malo']),
                'indice_orden'         => 1,
            ],
            [
                'grupo_id'             => 1,
                'descripcion_pregunta' => '¿Recomendaría nuestros servicios a familiares y amigos?',
                'opciones'             => json_encode(['Definitivamente sí', 'Probablemente sí', 'No estoy seguro', 'No']),
                'indice_orden'         => 2,
            ],
        ];

        foreach ($preguntas as $pregunta) {
            // Actualiza opciones si la pregunta ya existe (por descripcion),
            // o inserta si es nueva. Evita duplicados al re-ejecutar.
            $existe = DB::table('encuesta_satisfaccion_preguntas')
                ->where('descripcion_pregunta', $pregunta['descripcion_pregunta'])
                ->first();

            if ($existe) {
                DB::table('encuesta_satisfaccion_preguntas')
                    ->where('pregunta_id', $existe->pregunta_id)
                    ->update(['opciones' => $pregunta['opciones'], 'indice_orden' => $pregunta['indice_orden']]);
            } else {
                DB::table('encuesta_satisfaccion_preguntas')->insert($pregunta);
            }
        }
    }
}