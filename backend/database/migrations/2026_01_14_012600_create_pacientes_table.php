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
        Schema::create('pacientes', function (Blueprint $table) {
            $table->string('paciente_id', 32);
            $table->string('tipo_id_paciente', 3);
            
            // Primary Key compuesta (asumiendo que paciente_id + tipo_id es lo único)
            // O si paciente_id es único por sí solo. Dejaré primary compuesta por si acaso.
            $table->primary(['paciente_id', 'tipo_id_paciente']);

            $table->string('primer_apellido', 30);
            $table->string('segundo_apellido', 30)->default('');
            $table->string('primer_nombre', 20);
            $table->string('segundo_nombre', 30)->default('');
            
            $table->date('fecha_nacimiento')->nullable();
            $table->char('fecha_nacimiento_es_calculada', 1)->default('0');
            
            $table->string('residencia_direccion', 100)->default('');
            $table->string('residencia_telefono', 30)->default('');
            $table->char('zona_residencia', 1)->default('U');
            $table->string('ocupacion_id', 4)->nullable()->default('');
            
            $table->timestamp('fecha_registro');
            
            $table->char('sexo_id', 1);
            $table->string('tipo_estado_civil_id', 6)->nullable();
            
            $table->string('foto', 256)->default('');
            
            $table->string('tipo_pais_id', 4)->nullable();
            $table->string('tipo_dpto_id', 4)->nullable();
            $table->string('tipo_mpio_id', 4)->nullable();
            
            $table->char('paciente_fallecido', 1)->default('0');
            $table->integer('usuario_id');
            
            $table->string('nombre_madre', 60)->nullable();
            $table->string('observaciones', 350)->nullable();
            
            $table->string('tipo_comuna_id', 4)->nullable();
            $table->string('tipo_barrio_id', 4)->nullable();
            $table->string('tipo_estrato_id', 2)->nullable();
            
            $table->string('lugar_expedicion_documento', 60)->nullable();
            $table->char('sw_ficha', 1)->default('0');
            
            $table->string('celular_telefono', 20)->nullable();
            $table->string('email', 300)->nullable();
            $table->string('foto_paciente', 255)->nullable();
            
            $table->string('ln_tipo_pais_id', 4)->nullable();
            $table->string('ln_tipo_dpto_id', 4)->nullable();
            $table->string('ln_tipo_mpio_id', 4)->nullable();
            
            $table->string('telefono_smart', 10)->default('0');

            //$table->timestamps(); // Created_at y updated_at opcionales, fecha_registro ya existe pero Laravel usa estos por defecto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pacientes');
    }
};
