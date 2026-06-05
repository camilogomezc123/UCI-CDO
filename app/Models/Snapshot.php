<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Snapshot extends Model
{
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
