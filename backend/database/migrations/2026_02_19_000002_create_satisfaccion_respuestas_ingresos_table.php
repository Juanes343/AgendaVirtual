<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('satisfaccion_respuestas_ingresos', function (Blueprint $table) {
            $table->id('respuesta_id');
            $table->string('ingreso', 100); // Se usa string por flexibilidad de códigos de ingreso
            $table->string('paciente_id', 32);
            $table->string('tipo_id_paciente', 3);
            $table->foreignId('pregunta_id');
            $table->text('respuesta');
            $table->timestamp('fecha_registro')->useCurrent();

            $table->index(['ingreso', 'paciente_id', 'tipo_id_paciente'], 'idx_respuestas_satisfaccion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('satisfaccion_respuestas_ingresos');
    }
};
