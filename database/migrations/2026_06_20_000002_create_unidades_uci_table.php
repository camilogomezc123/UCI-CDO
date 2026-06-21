<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('unidades_uci', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedSmallInteger('cama_desde');
            $table->unsignedSmallInteger('cama_hasta');
            $table->unsignedSmallInteger('capacidad');
            $table->date('habilitada_desde')->nullable();
            $table->date('inhabilitada_desde')->nullable();
            $table->timestamps();
        });

        $ahora = now();
        DB::table('unidades_uci')->insert(array_map(fn($u) => [
            'nombre' => $u[0], 'cama_desde' => $u[1], 'cama_hasta' => $u[2], 'capacidad' => $u[2] - $u[1] + 1,
            'habilitada_desde' => '2020-01-01', 'inhabilitada_desde' => null, 'created_at' => $ahora, 'updated_at' => $ahora,
        ], [
            ['UCI Quirúrgica', 1, 8], ['UCI Cardiovascular', 9, 16], ['UCI Respiratoria', 22, 27],
            ['UCI General', 28, 38], ['UCI Neurovascular', 39, 46], ['UCIN', 49, 54],
            ['UCI Torre C', 55, 62], ['UCI Torre B', 63, 82],
        ]));
    }

    public function down(): void { Schema::dropIfExists('unidades_uci'); }
};
