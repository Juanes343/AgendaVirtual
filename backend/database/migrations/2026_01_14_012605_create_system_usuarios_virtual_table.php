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
        Schema::create('system_usuarios_virtual', function (Blueprint $table) {
            $table->id('usuario_id_virtual'); // Primary Key auto-incremental con nombre específico
            
            $table->string('paciente_id', 32);
            $table->string('tipo_documento', 3);
            $table->string('passwd'); // Contraseña en hash MD5 según el ejemplo viejo, pero debería ser bcrypt en Laravel
            
            //$table->timestamps();

            // Índices para búsquedas rápidas
            $table->index(['paciente_id', 'tipo_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_usuarios_virtual');
    }
};
