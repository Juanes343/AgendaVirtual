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
        Schema::create('tokens_agenda_virtual', function (Blueprint $table) {
            $table->id('id_token');
            
            $table->string('incriptacion'); // Token hash
            $table->string('paciente_id', 32);
            $table->string('tipo_documento', 3);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->integer('estado')->default(1); // 0 inactivo, 1 activo? (segun ejemplo "0" pero nombre sugiere boolean/int)
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tokens_agenda_virtual');
    }
};
