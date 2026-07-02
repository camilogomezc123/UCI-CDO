<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── bundle_ventilacion: resultados SAT/SBT + movilización + sueño ──────
        Schema::table('bundle_ventilacion', function (Blueprint $table) {
            $table->string('sat_resultado', 20)->nullable()->after('vacacion_sedacion');   // exitoso/fallido/contraindicado
            $table->string('sbt_resultado', 20)->nullable()->after('sbt');                 // exitoso/fallido/contraindicado
            $table->tinyInteger('nivel_movilizacion')->nullable()->after('profilaxis_upp');// 0-4: pasivo/activo-cama/sentado/bipedestacion/deambulacion
            $table->string('motivo_no_movilizacion', 100)->nullable()->after('nivel_movilizacion');
            $table->decimal('rcsq_score', 4, 1)->nullable()->after('motivo_no_movilizacion'); // Richards-Campbell Sleep Questionnaire 0-100
            $table->tinyInteger('interrupciones_nocturnas')->nullable()->after('rcsq_score');
            $table->boolean('familia_reunion_clinica')->default(false)->after('familia_involucrada');
        });

        // ── cam_uci: subtipo de delirium ─────────────────────────────────────
        Schema::table('cam_uci', function (Blueprint $table) {
            $table->string('subtipo_delirium', 15)->nullable()->after('resultado'); // hiperactivo/hipoactivo/mixto
        });

        // ── pacientes: RASS objetivo y score nutricional al ingreso ──────────
        Schema::table('pacientes', function (Blueprint $table) {
            $table->tinyInteger('rass_objetivo')->nullable()->after('activo');   // -5 a +2
            $table->tinyInteger('nutric_score')->nullable()->after('rass_objetivo'); // 0-9
        });
    }

    public function down(): void
    {
        Schema::table('bundle_ventilacion', function (Blueprint $table) {
            $table->dropColumn([
                'sat_resultado','sbt_resultado','nivel_movilizacion',
                'motivo_no_movilizacion','rcsq_score','interrupciones_nocturnas',
                'familia_reunion_clinica',
            ]);
        });
        Schema::table('cam_uci', function (Blueprint $table) {
            $table->dropColumn('subtipo_delirium');
        });
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn(['rass_objetivo','nutric_score']);
        });
    }
};
