<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('episodios_uci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->unsignedTinyInteger('numero_episodio')->default(1);
            $table->boolean('es_reingreso')->default(false);
            $table->dateTime('ingreso_uci')->nullable();
            $table->dateTime('salida_hospitalizacion')->nullable();
            $table->dateTime('egreso_uci')->nullable();
            $table->string('tipo_egreso', 20)->nullable();
            $table->timestamps();

            $table->index('paciente_id');
            $table->index('egreso_uci');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('episodios_uci');
    }
};
