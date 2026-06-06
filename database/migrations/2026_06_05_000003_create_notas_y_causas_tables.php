<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notas libres por paciente por día
        Schema::create('notas_paciente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');
            $table->date('fecha');
            $table->text('nota');
            $table->timestamps();
            $table->index(['paciente_id', 'fecha']);
        });

        // Causas de estancia prolongada (>5 días)
        Schema::create('causas_estancia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');

            // Causas booleanas (checkboxes)
            $table->boolean('pendiente_cirugia')->default(false);
            $table->boolean('condicion_clinica')->default(false);
            $table->boolean('ventilacion_mecanica')->default(false);
            $table->boolean('pendiente_cama_hospitalizacion')->default(false);
            $table->boolean('tramite_administrativo')->default(false);
            $table->boolean('homecare')->default(false);

            // Detalle libre
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Solo una causa activa por paciente (se actualiza)
            $table->unique('paciente_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('causas_estancia');
        Schema::dropIfExists('notas_paciente');
    }
};
