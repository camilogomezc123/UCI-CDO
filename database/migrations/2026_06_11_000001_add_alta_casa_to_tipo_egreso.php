<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement("
                CREATE TABLE pacientes_mig_tmp (
                    id         integer      not null primary key autoincrement,
                    documento  varchar(100) not null,
                    nombre     varchar(300) not null,
                    edad       tinyint      not null default 0,
                    sexo       char(1)      null,
                    eapb       varchar(200) null,
                    ingreso_uci            datetime null,
                    salida_hospitalizacion datetime null,
                    egreso_uci             datetime null,
                    activo     tinyint(1)   not null default 1,
                    created_at datetime     null,
                    updated_at datetime     null,
                    tipo_egreso varchar(255) null
                        check (tipo_egreso in ('mejoria','traslado','fallecimiento','alta_casa'))
                )
            ");

            DB::statement("
                INSERT INTO pacientes_mig_tmp
                    (id, documento, nombre, edad, sexo, eapb,
                     ingreso_uci, salida_hospitalizacion, egreso_uci,
                     activo, created_at, updated_at, tipo_egreso)
                SELECT id, documento, nombre, edad, sexo, eapb,
                       ingreso_uci, salida_hospitalizacion, egreso_uci,
                       activo, created_at, updated_at, tipo_egreso
                FROM pacientes
            ");

            DB::statement("DROP TABLE pacientes");
            DB::statement("ALTER TABLE pacientes_mig_tmp RENAME TO pacientes");
            DB::statement("CREATE UNIQUE INDEX pacientes_documento_unique ON pacientes (documento)");

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            DB::statement("ALTER TABLE pacientes MODIFY tipo_egreso ENUM('mejoria','traslado','fallecimiento','alta_casa') NULL");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement("
                CREATE TABLE pacientes_mig_tmp (
                    id         integer      not null primary key autoincrement,
                    documento  varchar(100) not null,
                    nombre     varchar(300) not null,
                    edad       tinyint      not null default 0,
                    sexo       char(1)      null,
                    eapb       varchar(200) null,
                    ingreso_uci            datetime null,
                    salida_hospitalizacion datetime null,
                    egreso_uci             datetime null,
                    activo     tinyint(1)   not null default 1,
                    created_at datetime     null,
                    updated_at datetime     null,
                    tipo_egreso varchar(255) null
                        check (tipo_egreso in ('mejoria','traslado','fallecimiento'))
                )
            ");

            DB::statement("
                INSERT INTO pacientes_mig_tmp
                    (id, documento, nombre, edad, sexo, eapb,
                     ingreso_uci, salida_hospitalizacion, egreso_uci,
                     activo, created_at, updated_at, tipo_egreso)
                SELECT id, documento, nombre, edad, sexo, eapb,
                       ingreso_uci, salida_hospitalizacion, egreso_uci,
                       activo, created_at, updated_at, tipo_egreso
                FROM pacientes
            ");

            DB::statement("DROP TABLE pacientes");
            DB::statement("ALTER TABLE pacientes_mig_tmp RENAME TO pacientes");
            DB::statement("CREATE UNIQUE INDEX pacientes_documento_unique ON pacientes (documento)");

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            DB::statement("ALTER TABLE pacientes MODIFY tipo_egreso ENUM('mejoria','traslado','fallecimiento') NULL");
        }
    }
};
