<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetIngresos extends Command
{
    protected $signature   = 'uci:reset-ingresos {--dry-run : Mostrar qué se cambiaría sin aplicar cambios}';
    protected $description = 'Elimina los ingresos_uci calculados automáticamente (los que tienen hora diferente a medianoche).';

    public function handle(): int
    {
        $dry = $this->option('dry-run');

        // Los ingresos registrados manualmente desde el formulario tienen hora 00:00:00.
        // Los calculados automáticamente desde tiempo_hospitalizacion_texto tienen hora precisa.
        $query = DB::table('pacientes')
            ->whereNotNull('ingreso_uci')
            ->whereRaw("TIME(ingreso_uci) != '00:00:00'");

        $count = $query->count();

        if ($count === 0) {
            $this->info('No hay ingresos automáticos que limpiar.');
            return 0;
        }

        $this->info("Ingresos calculados automáticamente encontrados: {$count}");

        if ($dry) {
            $this->warn('Modo dry-run: no se realizaron cambios.');
            return 0;
        }

        $query->update(['ingreso_uci' => null]);
        $this->info("Se eliminaron {$count} valores de ingreso_uci calculados automáticamente.");
        $this->line('El ingreso a UCI de estos pacientes quedó vacío para ingreso manual.');

        return 0;
    }
}
