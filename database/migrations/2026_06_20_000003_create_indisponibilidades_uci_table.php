<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('indisponibilidades_uci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_uci_id')->constrained('unidades_uci')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_cama')->nullable();
            $table->date('inhabilitada_desde');
            $table->date('habilitada_desde')->nullable();
            $table->string('motivo', 500);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['unidad_uci_id', 'numero_cama']);
        });

        $ahora = now();
        DB::table('unidades_uci')->whereNotNull('inhabilitada_desde')->orderBy('id')->each(function ($unidad) use ($ahora) {
            DB::table('indisponibilidades_uci')->insert([
                'unidad_uci_id' => $unidad->id, 'numero_cama' => null,
                'inhabilitada_desde' => $unidad->inhabilitada_desde,
                'motivo' => 'Cierre de unidad registrado previamente',
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        });
        DB::table('unidades_uci')->update(['inhabilitada_desde' => null]);
    }

    public function down(): void { Schema::dropIfExists('indisponibilidades_uci'); }
};
