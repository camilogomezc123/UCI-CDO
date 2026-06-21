<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para las consultas de carga, histórico y dashboard.
     *
     * No cambian datos: reducen los recorridos completos de snapshots a medida
     * que se acumula el histórico clínico.
     */
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->index(['fecha_snapshot', 'subunidad', 'paciente_id'], 'snapshots_fecha_subunidad_paciente_idx');
            $table->index('carga_id', 'snapshots_carga_id_idx');
            $table->index(['paciente_id', 'fecha_snapshot', 'id'], 'snapshots_paciente_fecha_id_idx');
        });

        Schema::table('cambios_snapshot', function (Blueprint $table) {
            $table->index('snapshot_id', 'cambios_snapshot_snapshot_id_idx');
        });

        Schema::table('pacientes', function (Blueprint $table) {
            $table->index(['activo', 'salida_hospitalizacion', 'egreso_uci'], 'pacientes_activo_salida_egreso_idx');
            $table->index('ingreso_uci', 'pacientes_ingreso_uci_idx');
            $table->index('egreso_uci', 'pacientes_egreso_uci_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropIndex('pacientes_activo_salida_egreso_idx');
            $table->dropIndex('pacientes_ingreso_uci_idx');
            $table->dropIndex('pacientes_egreso_uci_idx');
        });

        Schema::table('cambios_snapshot', function (Blueprint $table) {
            $table->dropIndex('cambios_snapshot_snapshot_id_idx');
        });

        Schema::table('snapshots', function (Blueprint $table) {
            $table->dropIndex('snapshots_fecha_subunidad_paciente_idx');
            $table->dropIndex('snapshots_carga_id_idx');
            $table->dropIndex('snapshots_paciente_fecha_id_idx');
        });
    }
};
