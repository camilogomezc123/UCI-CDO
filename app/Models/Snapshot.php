<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Snapshot extends Model
{
    /**
     * Subquery que devuelve el snapshot más reciente por paciente.
     * Ordena primero por fecha_snapshot DESC y, para empates, por id DESC.
     * Reemplaza los patrones que usaban MAX(id) a secas.
     */
    public static function subqueryUltimoPorPaciente(): \Illuminate\Database\Query\Builder
    {
        $maxFechas = DB::table('snapshots')
            ->select('paciente_id', DB::raw('MAX(fecha_snapshot) as max_fecha'))
            ->groupBy('paciente_id');

        return DB::table('snapshots as s')
            ->select('s.paciente_id', DB::raw('MAX(s.id) as snap_id'))
            ->joinSub($maxFechas, 'mf', fn($j) => $j
                ->on('s.paciente_id', '=', 'mf.paciente_id')
                ->on('s.fecha_snapshot', '=', 'mf.max_fecha')
            )
            ->groupBy('s.paciente_id');
    }

    protected $fillable = [
        'paciente_id', 'carga_id', 'fecha_snapshot',
        'ubicacion', 'numero_cama', 'subunidad',
        'cie10', 'diagnostico_oncologico', 'diagnosticos',
        'criterio_atencion', 'especialidad',
        'estado_nutricional', 'dieta',
        'soporte_hemodinamico', 'soporte_ventilatorio', 'movilizacion',
        'news', 'sofa', 'barthel', 'de_movilidad', 'rass', 'bps', 'eva', 'must',
        'riesgos', 'observaciones', 'metas_clinicas', 'tiempo_hospitalizacion_texto',
    ];

    protected function casts(): array
    {
        return ['fecha_snapshot' => 'date'];
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function carga()
    {
        return $this->belongsTo(CargaArchivo::class, 'carga_id');
    }

    public function cambios()
    {
        return $this->hasMany(CambioSnapshot::class);
    }
}
