<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hc_encuesta_satisfaccion', function (Blueprint $table) {
            $table->integer('ingreso')->primary();
            $table->string('paciente_id', 32);
            $table->string('tipo_id_paciente', 3);
            $table->string('pregunta_1')->nullable(); // Experiencia global
            $table->string('pregunta_2')->nullable(); // Recomendaría IPS
            $table->timestamp('fecha_registro')->useCurrent();

            $table->index(['paciente_id', 'tipo_id_paciente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hc_encuesta_satisfaccion');
    }
};
