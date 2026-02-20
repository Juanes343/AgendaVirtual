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
        Schema::create('audit_paciente_modificaciones', function (Blueprint $table) {
            $table->id('audit_id');
            $table->string('paciente_id', 32);
            $table->string('tipo_id_paciente', 3);
            $table->string('campo', 50);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->timestamp('fecha_registro')->useCurrent();
            $table->string('ip', 45)->nullable();
            
            // Index for performance
            $table->index(['paciente_id', 'tipo_id_paciente']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_paciente_modificaciones');
    }
};
