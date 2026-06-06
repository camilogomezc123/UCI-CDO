<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CAM-UCI: evaluación diaria de delirium
        Schema::create('cam_uci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->date('fecha');
            $table->enum('resultado', ['positivo', 'negativo', 'no_evaluable']);
            $table->integer('rass_momento')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['paciente_id', 'fecha']); // una evaluación por paciente por día
        });

        // Bundle ventilador: checklist diario para pacientes en VMI
        Schema::create('bundle_ventilacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->date('fecha');
            $table->boolean('cabecera_elevada')->default(false);    // 30-45°
            $table->boolean('higiene_oral')->default(false);        // clorhexidina
            $table->boolean('vacacion_sedacion')->default(false);   // SAT
            $table->boolean('sbt')->default(false);                 // prueba respiración espontánea
            $table->boolean('profilaxis_tvp')->default(false);      // trombosis venosa
            $table->boolean('profilaxis_upp')->default(false);      // úlcera por presión
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['paciente_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_ventilacion');
        Schema::dropIfExists('cam_uci');
    }
};
