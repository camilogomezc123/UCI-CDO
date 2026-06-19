<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement("
                CREATE TABLE users_mig_tmp (
                    id             integer      not null primary key autoincrement,
                    name           varchar(255) not null,
                    email          varchar(255) not null,
                    password       varchar(255) not null,
                    rol            varchar(20)  not null default 'operativo'
                                   check (rol in ('master','operativo','visual')),
                    activo         tinyint(1)   not null default 1,
                    remember_token varchar(100) null,
                    created_at     datetime     null,
                    updated_at     datetime     null
                )
            ");

            DB::statement("
                INSERT INTO users_mig_tmp
                    (id, name, email, password, rol, activo, remember_token, created_at, updated_at)
                SELECT id, name, email, password, rol, activo, remember_token, created_at, updated_at
                FROM users
            ");

            DB::statement("DROP TABLE users");
            DB::statement("ALTER TABLE users_mig_tmp RENAME TO users");
            DB::statement("CREATE UNIQUE INDEX users_email_unique ON users (email)");

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            DB::statement("ALTER TABLE users MODIFY rol ENUM('master','operativo','visual') NOT NULL DEFAULT 'operativo'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=off');

            DB::statement("
                CREATE TABLE users_mig_tmp (
                    id             integer      not null primary key autoincrement,
                    name           varchar(255) not null,
                    email          varchar(255) not null,
                    password       varchar(255) not null,
                    rol            varchar(20)  not null default 'operativo'
                                   check (rol in ('master','operativo')),
                    activo         tinyint(1)   not null default 1,
                    remember_token varchar(100) null,
                    created_at     datetime     null,
                    updated_at     datetime     null
                )
            ");

            DB::statement("
                INSERT INTO users_mig_tmp
                    (id, name, email, password, rol, activo, remember_token, created_at, updated_at)
                SELECT id, name, email, password, rol, activo, remember_token, created_at, updated_at
                FROM users WHERE rol != 'visual'
            ");

            DB::statement("DROP TABLE users");
            DB::statement("ALTER TABLE users_mig_tmp RENAME TO users");
            DB::statement("CREATE UNIQUE INDEX users_email_unique ON users (email)");

            DB::statement('PRAGMA foreign_keys=on');
        } else {
            DB::statement("ALTER TABLE users MODIFY rol ENUM('master','operativo') NOT NULL DEFAULT 'operativo'");
        }
    }
};
