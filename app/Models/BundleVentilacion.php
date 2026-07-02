<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleVentilacion extends Model
{
    protected $table = 'bundle_ventilacion';

    protected $fillable = [
        'paciente_id', 'usuario_id', 'fecha',
        'cabecera_elevada', 'higiene_oral', 'vacacion_sedacion',
        'sbt', 'profilaxis_tvp', 'profilaxis_upp', 'familia_involucrada', 'observaciones',
        'sat_resultado', 'sbt_resultado', 'nivel_movilizacion', 'motivo_no_movilizacion',
        'rcsq_score', 'interrupciones_nocturnas', 'familia_reunion_clinica',
    ];

    protected function casts(): array
    {
        return [
            'fecha'               => 'date',
            'cabecera_elevada'       => 'boolean',
            'higiene_oral'           => 'boolean',
            'vacacion_sedacion'      => 'boolean',
            'sbt'                    => 'boolean',
            'profilaxis_tvp'         => 'boolean',
            'profilaxis_upp'         => 'boolean',
            'familia_involucrada'    => 'boolean',
            'familia_reunion_clinica'=> 'boolean',
            'interrupciones_nocturnas'=> 'integer',
            'rcsq_score'             => 'integer',
        ];
    }

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    // Items del bundle con etiquetas
    public static function items(): array
    {
        return [
            'cabecera_elevada'    => ['Cabecera 30–45°',               'bi-arrow-up-right'],
            'higiene_oral'        => ['Higiene oral (clorhexidina)',    'bi-droplet'],
            'vacacion_sedacion'   => ['Vacación de sedación (SAT)',     'bi-moon-stars'],
            'sbt'                 => ['Prueba resp. espontánea (SBT)',  'bi-lungs'],
            'profilaxis_tvp'      => ['Profilaxis TVP',                'bi-bandaid'],
            'profilaxis_upp'      => ['Profilaxis úlcera por presión',  'bi-shield-check'],
            'familia_involucrada' => ['Familia involucrada (F — ABCDEF)', 'bi-people'],
        ];
    }

    // Porcentaje de cumplimiento del día
    public function cumplimiento(): int
    {
        $items = array_keys(self::items());
        $total = count($items);
        $cumplidos = collect($items)->filter(fn($i) => $this->$i)->count();
        return $total > 0 ? (int)round($cumplidos / $total * 100) : 0;
    }

    // Cumplimiento completo (todos los ítems)
    public function esCumplimientoTotal(): bool
    {
        return collect(array_keys(self::items()))->every(fn($i) => $this->$i);
    }
}
