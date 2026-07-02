<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Dispositivos invasivos y eventos IAAS ─────────────────────────────
        Schema::create('dispositivos_paciente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('tipo', 20);             // cvc / sonda_vesical / vm / drain / ngt
            $table->date('fecha_inicio');
            $table->date('fecha_retiro')->nullable();
            $table->string('sitio_insercion', 80)->nullable();
            $table->string('via_acceso', 80)->nullable();
            $table->boolean('activo')->default(true);
            // Evento IAAS
            $table->boolean('evento_iaas')->default(false);
            $table->string('tipo_iaas', 30)->nullable();    // CLABSI / CAUTI / VAP / UPP / otro
            $table->string('organismo', 100)->nullable();
            $table->string('sensibilidad', 30)->nullable(); // sensible / resistente / MDR
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── Balance hídrico diario ────────────────────────────────────────────
        Schema::create('balance_hidrico_diario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->date('fecha');
            // Ingresos (mL)
            $table->integer('vol_cristaloides')->default(0);
            $table->integer('vol_coloides')->default(0);
            $table->integer('vol_hemoderivados')->default(0);
            $table->integer('vol_nutricion')->default(0);
            $table->integer('vol_medicamentos')->default(0);
            $table->integer('vol_otros_ingresos')->default(0);
            // Egresos (mL)
            $table->integer('vol_diuresis')->default(0);
            $table->integer('vol_drenajes')->default(0);
            $table->integer('vol_perdidas_insensibles')->default(0);
            $table->integer('vol_otros_egresos')->default(0);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['paciente_id', 'fecha']);
        });

        // ── Nutrición clínica diaria ──────────────────────────────────────────
        Schema::create('nutricion_diaria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->date('fecha');
            $table->string('via', 20)->nullable();           // enteral/parenteral/mixta/oral/ayuno
            $table->integer('kcal_meta')->nullable();
            $table->integer('kcal_aportadas')->nullable();
            $table->integer('proteinas_g_meta')->nullable();
            $table->integer('proteinas_g_aportadas')->nullable();
            $table->boolean('inicio_ne_hoy')->default(false); // primer día nutrición enteral
            $table->string('motivo_suspension', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['paciente_id', 'fecha']);
        });

        // ── Antibióticos UCI (stewardship) ────────────────────────────────────
        Schema::create('antibioticos_uci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->string('antibiotico', 80);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('via', 20)->default('iv');    // iv/oral/im
            $table->string('dosis', 50)->nullable();
            $table->string('indicacion', 100)->nullable();
            $table->string('foco', 60)->nullable();
            $table->boolean('activo')->default(true);
            // Stewardship
            $table->boolean('cultivo_disponible')->default(false);
            $table->string('resultado_cultivo', 100)->nullable();
            $table->boolean('de_escalado')->default(false);
            $table->date('fecha_deescalacion')->nullable();
            $table->decimal('pct_inicio', 6, 2)->nullable();    // Procalcitonina al inicio
            $table->decimal('pct_control_72h', 6, 2)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });

        // ── Goals of Care / LET ───────────────────────────────────────────────
        Schema::create('goals_of_care', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->date('fecha_conversacion');
            $table->string('nivel_esfuerzo', 20);      // maximo/limitado/confort
            $table->boolean('dnr')->default(false);
            $table->date('tiempo_limitado_hasta')->nullable();
            $table->string('quien_participo', 200)->nullable();  // texto libre
            $table->text('plan_cuidados')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals_of_care');
        Schema::dropIfExists('antibioticos_uci');
        Schema::dropIfExists('nutricion_diaria');
        Schema::dropIfExists('balance_hidrico_diario');
        Schema::dropIfExists('dispositivos_paciente');
    }
};
