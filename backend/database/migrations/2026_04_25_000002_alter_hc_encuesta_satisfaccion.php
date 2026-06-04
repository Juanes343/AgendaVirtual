<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Eliminar columnas redundantes y las de preguntas fijas
        Schema::table('hc_encuesta_satisfaccion', function (Blueprint $table) {
            $table->dropColumn(['paciente_id', 'tipo_id_paciente', 'pregunta_1', 'pregunta_2']);
        });

        // 2. Agregar FK de ingreso → ingresos.ingreso
        //    Se usa statement directo porque Blueprint::foreign en alter puede fallar en PG
        //    si la columna ya existe como PK sin FK declarada.
        DB::statement('
            ALTER TABLE hc_encuesta_satisfaccion
            ADD CONSTRAINT fk_encuesta_satisfaccion_ingreso
            FOREIGN KEY (ingreso)
            REFERENCES ingresos(ingreso)
            ON DELETE CASCADE
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE hc_encuesta_satisfaccion
            DROP CONSTRAINT IF EXISTS fk_encuesta_satisfaccion_ingreso
        ');

        Schema::table('hc_encuesta_satisfaccion', function (Blueprint $table) {
            $table->string('paciente_id', 32)->nullable();
            $table->string('tipo_id_paciente', 3)->nullable();
            $table->string('pregunta_1', 255)->nullable();
            $table->string('pregunta_2', 255)->nullable();
        });
    }
};
