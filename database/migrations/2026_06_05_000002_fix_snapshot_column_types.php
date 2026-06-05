<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->text('estado_nutricional')->nullable()->change();
            $table->text('dieta')->nullable()->change();
            $table->text('movilizacion')->nullable()->change();
            $table->text('de_movilidad')->nullable()->change();
            $table->text('especialidad')->nullable()->change();
            $table->text('criterio_atencion')->nullable()->change();
            $table->text('soporte_hemodinamico')->nullable()->change();
            $table->text('soporte_ventilatorio')->nullable()->change();
        });
    }

    public function down(): void {}
};
