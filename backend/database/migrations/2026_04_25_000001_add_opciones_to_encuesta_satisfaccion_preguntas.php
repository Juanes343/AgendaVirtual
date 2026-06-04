<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La tabla encuesta_satisfaccion_preguntas ya existe en BD con:
        // pregunta_id, grupo_id, descripcion_pregunta, indice_orden
        // Solo agregamos la columna opciones (JSON) para las respuestas posibles
        Schema::table('encuesta_satisfaccion_preguntas', function (Blueprint $table) {
            $table->json('opciones')->nullable()->after('descripcion_pregunta');
        });
    }

    public function down(): void
    {
        Schema::table('encuesta_satisfaccion_preguntas', function (Blueprint $table) {
            $table->dropColumn('opciones');
        });
    }
};
