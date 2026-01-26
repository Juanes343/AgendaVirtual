<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateConfigReportePermisosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('config_reporte_permisos')) {
            Schema::create('config_reporte_permisos', function (Blueprint $table) {
                $table->id();
                $table->string('modulo')->unique()->comment('Nombre del módulo: CONSULTA_EXTERNA, APOYOS_DIAGNOSTICOS, CIRUGIA, HOSPITALIZACION, ADJUNTOS_GENERALES');
                $table->string('etiqueta')->nullable()->comment('Nombre legible para mostrar en el frontend');
                $table->boolean('sw_imprime')->default(1)->comment('1: Permitir Imprimir, 0: Ocultar');
                $table->boolean('sw_correo')->default(1)->comment('1: Permitir Email, 0: Ocultar');
                $table->timestamps();
            });

            // Insertar datos iniciales (Seed)
            $modulos = [
                ['modulo' => 'CONSULTA_EXTERNA', 'etiqueta' => 'Consulta Externa'],
                ['modulo' => 'APOYOS_DIAGNOSTICOS', 'etiqueta' => 'Apoyos Diagnósticos'],
                ['modulo' => 'CIRUGIA', 'etiqueta' => 'Cirugía'],
                ['modulo' => 'HOSPITALIZACION', 'etiqueta' => 'Hospitalización'],
                ['modulo' => 'ADJUNTOS_GENERALES', 'etiqueta' => 'Adjuntos Generales'],
            ];

            foreach ($modulos as $mod) {
                DB::table('config_reporte_permisos')->insert(array_merge($mod, [
                    'sw_imprime' => 1,
                    'sw_correo' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('config_reporte_permisos');
    }
}