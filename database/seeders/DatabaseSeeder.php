<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@clinicaoccidente.com'],
            [
                'name' => 'Administrador UCI',
                'password' => Hash::make('Admin2026*'),
                'rol' => 'master',
                'activo' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'operativo@clinicaoccidente.com'],
            [
                'name' => 'Enfermero UCI',
                'password' => Hash::make('Operativo2026*'),
                'rol' => 'operativo',
                'activo' => true,
            ]
        );
    }
}
