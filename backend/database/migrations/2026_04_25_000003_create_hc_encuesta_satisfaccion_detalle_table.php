<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hc_encuesta_satisfaccion_detalle', function (Blueprint $table) {
            $table->id('detalle_id');
            $table->integer('ingreso');
            $table->integer('pregunta_id'); // sin FK: encuesta_satisfaccion_preguntas no tiene PK formal en PG
            $table->text('respuesta');
            $table->timestamp('fecha_registro')->useCurrent();

            $table->foreign('ingreso')
                ->references('ingreso')->on('hc_encuesta_satisfaccion')
                ->onDelete('cascade');

            $table->unique(['ingreso', 'pregunta_id'], 'uq_detalle_ingreso_pregunta');
            $table->index('ingreso', 'idx_detalle_ingreso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hc_encuesta_satisfaccion_detalle');
    }
};
