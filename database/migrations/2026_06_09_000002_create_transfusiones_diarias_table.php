<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfusiones_diarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('users')->onDelete('restrict');
            $table->date('fecha');
            $table->string('productos', 200);
            $table->tinyInteger('unidades_totales')->unsigned()->default(1);
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->unique(['paciente_id', 'fecha']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfusiones_diarias');
    }
};
