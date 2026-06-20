<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trazadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');

            // Tipo extensible: 'sepsis', futuras patologías sin cambiar esquema
            $table->string('tipo_trazador')->default('sepsis');

            // Máquina de estados (string, no ENUM)
            $table->string('estado')->default('TRAZADOR_INICIAL');

            // Fechas del ciclo de vida
            $table->timestamp('fecha_guardado_inicial')->nullable();
            $table->timestamp('fecha_objetivo_despues')->nullable();
            $table->timestamp('fecha_cierre')->nullable();

            // Todo el contenido del formulario en un bloque JSON por patología
            // (datos_paciente, fase1, fase2, fase3, metas, abcdef, encuesta_antes, encuesta_despues)
            $table->json('datos')->nullable();

            // Indicadores calculados (S1-S8, ABCDEF, semáforo, escalas, comparativo)
            $table->json('resultados')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trazadores');
    }
};
