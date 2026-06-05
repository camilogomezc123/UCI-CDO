<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pacientes', function (Blueprint $table) {
            $table->id();
            $table->string('documento', 100)->unique();
            $table->string('nombre', 300);
            $table->tinyInteger('edad')->unsigned()->default(0);
            $table->char('sexo', 1)->nullable();
            $table->string('eapb', 200)->nullable();

            // Campos manuales del ciclo de vida UCI
            $table->dateTime('ingreso_uci')->nullable();
            $table->dateTime('salida_hospitalizacion')->nullable();
            $table->dateTime('egreso_uci')->nullable();

            // Control
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Cargas diarias de archivos
        Schema::create('cargas_archivo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo');
            $table->date('fecha_archivo')->nullable();
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');
            $table->unsignedSmallInteger('nuevos')->default(0);
            $table->unsignedSmallInteger('actualizados')->default(0);
            $table->unsignedSmallInteger('omitidos')->default(0);
            $table->text('errores')->nullable();
            $table->timestamps();
        });

        // Snapshots diarios por paciente
        Schema::create('snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->foreignId('carga_id')->constrained('cargas_archivo')->onDelete('cascade');
            $table->date('fecha_snapshot');

            $table->string('ubicacion', 20)->nullable();
            $table->unsignedSmallInteger('numero_cama')->nullable();
            $table->string('subunidad', 50)->nullable();

            $table->text('cie10')->nullable();
            $table->text('diagnostico_oncologico')->nullable();
            $table->text('diagnosticos')->nullable();
            $table->string('criterio_atencion', 100)->nullable();
            $table->text('especialidad')->nullable();
            $table->string('estado_nutricional', 100)->nullable();
            $table->string('dieta', 200)->nullable();
            $table->string('soporte_hemodinamico', 100)->nullable();
            $table->string('soporte_ventilatorio', 100)->nullable();
            $table->string('movilizacion', 100)->nullable();

            $table->decimal('news', 5, 2)->nullable();
            $table->string('sofa', 50)->nullable();
            $table->decimal('barthel', 5, 2)->nullable();
            $table->string('de_movilidad', 100)->nullable();
            $table->decimal('rass', 5, 2)->nullable();
            $table->decimal('bps', 5, 2)->nullable();
            $table->decimal('eva', 5, 2)->nullable();
            $table->decimal('must', 5, 2)->nullable();

            $table->text('riesgos')->nullable();
            $table->text('observaciones')->nullable();
            $table->text('metas_clinicas')->nullable();
            $table->string('tiempo_hospitalizacion_texto', 100)->nullable();

            $table->timestamps();

            $table->index(['paciente_id', 'fecha_snapshot']);
        });

        // Log de cambios entre snapshots
        Schema::create('cambios_snapshot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('snapshots')->onDelete('cascade');
            $table->string('campo', 80);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_snapshot');
        Schema::dropIfExists('snapshots');
        Schema::dropIfExists('cargas_archivo');
        Schema::dropIfExists('pacientes');
    }
};
