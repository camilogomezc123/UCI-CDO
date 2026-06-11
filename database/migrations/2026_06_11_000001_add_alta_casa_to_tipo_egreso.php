<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pacientes MODIFY tipo_egreso ENUM('mejoria','traslado','fallecimiento','alta_casa') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pacientes MODIFY tipo_egreso ENUM('mejoria','traslado','fallecimiento') NULL");
    }
};
