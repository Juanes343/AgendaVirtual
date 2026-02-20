<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaccion_preguntas', function (Blueprint $table) {
            $table->id('pregunta_id');
            $table->text('enunciado');
            $table->json('opciones'); // ["Opción 1", "Opción 2"...]
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamp('fecha_registro')->useCurrent();
        });

        // Insertar las 2 preguntas solicitadas
        DB::table('satisfaccion_preguntas')->insert([
            [
                'enunciado' => '¿Cómo calificaría su experiencia global respecto a los servicios de salud que ha recibido a través de su IPS?',
                'opciones' => json_encode(['Muy buena', 'Buena', 'Regular', 'Mala', 'Muy mala', 'No responde']),
                'orden' => 1
            ],
            [
                'enunciado' => '¿Recomendaría a sus familiares y amigos esta IPS?',
                'opciones' => json_encode(['Definitivamente sí', 'Probablemente sí', 'Probablemente no', 'Definitivamente no', 'No responde']),
                'orden' => 2
            ]
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaccion_preguntas');
    }
};
