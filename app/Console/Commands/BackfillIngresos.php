<?php

namespace App\Console\Commands;

use App\Models\Paciente;
use App\Models\Snapshot;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillIngresos extends Command
{
    protected $signature   = 'uci:backfill-ingresos {--dry-run : Mostrar qué se haría sin cambiar nada}';
    protected $description = 'Calcula ingreso_uci desde tiempo_hospitalizacion_texto del snapshot más reciente para pacientes que no lo tienen registrado.';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        // Pacientes sin ingreso_uci (activos e inactivos)
        $pacientes = Paciente::whereNull('ingreso_uci')
            ->with(['snapshots' => fn($q) => $q
                ->whereNotNull('tiempo_hospitalizacion_texto')
                ->orderByDesc('fecha_snapshot')
                ->orderByDesc('id')
                ->limit(1)
            ])
            ->get();

        $this->info("Pacientes sin ingreso_uci: {$pacientes->count()}");

        $actualizados = 0;
        $sinDato      = 0;

        foreach ($pacientes as $paciente) {
            $snap = $paciente->snapshots->first();

            if (!$snap || !$snap->tiempo_hospitalizacion_texto) {
                $sinDato++;
                continue;
            }

            $ingreso = $this->calcular($snap->tiempo_hospitalizacion_texto, $snap->fecha_snapshot);

            if (!$ingreso) {
                $sinDato++;
                continue;
            }

            $this->line(sprintf(
                '  %s (%s) → ingreso: %s  [tiempo: %s]',
                $paciente->nombre,
                $paciente->documento,
                $ingreso,
                $snap->tiempo_hospitalizacion_texto
            ));

            if (!$dry) {
                $paciente->update(['ingreso_uci' => $ingreso]);
            }

            $actualizados++;
        }

        $this->newLine();
        $this->info("Actualizados: {$actualizados} | Sin dato de tiempo: {$sinDato}");
        if ($dry) $this->warn('Modo dry-run: no se realizaron cambios.');

        return 0;
    }

    private function calcular(string $texto, $fechaSnapshot): ?string
    {
        $dias    = 0;
        $horas   = 0;
        $minutos = 0;

        if (preg_match('/(\d+)\s*dias?/i',     $texto, $m)) $dias    = (int)$m[1];
        if (preg_match('/(\d+)\s*h(?:rs?)?/i', $texto, $m)) $horas   = (int)$m[1];
        if (preg_match('/(\d+)\s*min/i',        $texto, $m)) $minutos = (int)$m[1];

        $totalMinutos = $dias * 1440 + $horas * 60 + $minutos;
        if ($totalMinutos <= 0) return null;

        try {
            return Carbon::parse($fechaSnapshot)
                ->subMinutes($totalMinutos)
                ->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
