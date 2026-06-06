<?php

namespace App\Console\Commands;

use App\Models\Paciente;
use App\Models\Snapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPacientesActivos extends Command
{
    protected $signature   = 'uci:fix-activos {--dry-run : Mostrar qué se haría sin cambiar nada}';
    protected $description = 'Por cada cama, deja activo solo el paciente con el snapshot más reciente. Marca los demás como egresados.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        $this->info('Cargando pacientes activos con su último snapshot...');

        // Carga todos los pacientes activos con su último snapshot
        $activos = Paciente::where('activo', true)
            ->with('ultimoSnapshot')
            ->get()
            ->filter(fn($p) => $p->ultimoSnapshot !== null);

        $this->info("Pacientes activos con snapshot: {$activos->count()}");

        // Agrupa por la cama del último snapshot
        $porCama = $activos->groupBy(fn($p) => $p->ultimoSnapshot->ubicacion);

        $camas    = $porCama->count();
        $conflictos = $porCama->filter(fn($g) => $g->count() > 1)->count();
        $this->info("Camas únicas: {$camas} — Camas con más de 1 activo: {$conflictos}");

        if ($conflictos === 0) {
            $this->info('No hay conflictos. Nada que corregir.');
            return 0;
        }

        $this->newLine();
        $egresados = 0;

        DB::transaction(function () use ($porCama, $dry, &$egresados) {
            foreach ($porCama as $cama => $pacientesDeCama) {
                if ($pacientesDeCama->count() <= 1) continue;

                // Ordenar: primero el snapshot más reciente (fecha DESC, luego id DESC)
                $ordenados = $pacientesDeCama->sortByDesc(function ($p) {
                    return $p->ultimoSnapshot->fecha_snapshot . '_'
                        . str_pad((string)$p->ultimoSnapshot->id, 12, '0', STR_PAD_LEFT);
                })->values();

                $actual     = $ordenados->first();  // El que se queda activo
                $desplazados = $ordenados->slice(1); // El resto sale

                $this->line("Cama <comment>{$cama}</comment> — activo: <info>{$actual->nombre}</info> ({$actual->ultimoSnapshot->fecha_snapshot})");

                foreach ($desplazados as $p) {
                    $this->line("  → Egresar: {$p->nombre} (último snap: {$p->ultimoSnapshot->fecha_snapshot})");

                    if (!$dry) {
                        $actualizacion = ['activo' => false];
                        // Poner fecha de egreso = fecha del snapshot del paciente que lo reemplazó
                        if (is_null($p->egreso_uci)) {
                            $actualizacion['egreso_uci'] = $actual->ultimoSnapshot->fecha_snapshot;
                        }
                        $p->update($actualizacion);
                    }

                    $egresados++;
                }
            }
        });

        $this->newLine();
        if ($dry) {
            $this->warn("Modo dry-run: se habrían egresado {$egresados} paciente(s).");
        } else {
            $this->info("Corrección completada: {$egresados} paciente(s) marcados como egresados.");
        }

        return 0;
    }
}
