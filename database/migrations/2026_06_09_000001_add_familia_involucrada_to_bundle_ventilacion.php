<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_ventilacion', function (Blueprint $table) {
            $table->boolean('familia_involucrada')->default(false)->after('profilaxis_upp');
        });
    }

    public function down(): void
    {
        Schema::table('bundle_ventilacion', function (Blueprint $table) {
            $table->dropColumn('familia_involucrada');
        });
    }
};
