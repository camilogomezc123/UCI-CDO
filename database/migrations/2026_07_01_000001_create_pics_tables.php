<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Score de riesgo PICS calculado al egreso UCI
        Schema::create('pics_riesgo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->date('fecha_calculo');
            $table->unsignedSmallInteger('dias_uci')->default(0);
            $table->unsignedTinyInteger('dias_vm')->default(0);
            $table->unsignedTinyInteger('dias_delirium')->default(0);
            $table->unsignedTinyInteger('edad')->default(0);
            $table->tinyInteger('score_total')->default(0);
            $table->string('nivel_riesgo', 10)->default('bajo'); // bajo/medio/alto
            $table->json('factores')->nullable();
            $table->timestamps();

            $table->unique('paciente_id');
        });

        // Evaluaciones PICS post-UCI (paciente y cuidador familiar)
        Schema::create('pics_evaluaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('momento', ['egreso', '30d', '90d', '180d']);
            $table->enum('tipo', ['paciente', 'familia'])->default('paciente');
            $table->date('fecha_evaluacion');

            // ── Disfagia (aplica en egreso, post-extubación) ──────────────────
            $table->string('disfagia', 20)->nullable(); // pasa/falla/no_aplica/pendiente

            // ── AMT-10: Cognitivo (10 ítems Sí/No, score 0-10) ───────────────
            $table->json('amt_respuestas')->nullable();   // array[10] de bool
            $table->tinyInteger('amt_score')->nullable(); // 0-10

            // ── HADS: Ansiedad (A) + Depresión (D), 7+7 ítems × 0-3 ─────────
            $table->json('hads_respuestas')->nullable();     // array[14] de 0-3
            $table->tinyInteger('hads_ansiedad')->nullable();  // 0-21
            $table->tinyInteger('hads_depresion')->nullable(); // 0-21

            // ── PC-PTSD-5 (5 ítems Sí/No, score 0-5) ────────────────────────
            $table->json('pcptsd_respuestas')->nullable();
            $table->tinyInteger('pcptsd_score')->nullable(); // 0-5

            // ── Fatiga y Dolor crónico ────────────────────────────────────────
            $table->decimal('fatiga_score', 3, 1)->nullable();      // 0-10
            $table->decimal('dolor_reposo', 3, 1)->nullable();      // NRS 0-10
            $table->decimal('dolor_movimiento', 3, 1)->nullable();  // NRS 0-10

            // ── PTG-SF: Crecimiento Postraumático (aplica 90d y 180d) ─────────
            $table->json('ptg_respuestas')->nullable();   // array[10] de 0-5
            $table->tinyInteger('ptg_score')->nullable(); // 0-50

            // ── PICS-F: Cuidador familiar (tipo = familia) ───────────────────
            $table->json('picsf_respuestas')->nullable();
            $table->tinyInteger('picsf_distress')->nullable(); // 0-20

            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['paciente_id', 'momento', 'tipo'], 'pics_unico');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pics_evaluaciones');
        Schema::dropIfExists('pics_riesgo');
    }
};
