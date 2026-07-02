<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NutricionDiaria extends Model
{
    protected $table = 'nutricion_diaria';

    protected $fillable = [
        'paciente_id','usuario_id','fecha','via',
        'kcal_meta','kcal_aportadas',
        'proteinas_g_meta','proteinas_g_aportadas',
        'inicio_ne_hoy','motivo_suspension','observaciones',
    ];

    protected $casts = [
        'fecha'         => 'date',
        'inicio_ne_hoy' => 'boolean',
    ];

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public static function vias(): array
    {
        return [
            'enteral'    => ['Enteral',     'bi-droplet',   'success'],
            'parenteral' => ['Parenteral',  'bi-capsule',   'warning'],
            'mixta'      => ['Mixta',       'bi-shuffle',   'info'],
            'oral'       => ['Oral',        'bi-cup',       'primary'],
            'ayuno'      => ['Ayuno',       'bi-x-circle',  'danger'],
        ];
    }

    public function pctKcal(): ?int
    {
        if (!$this->kcal_meta || !$this->kcal_aportadas) return null;
        return (int)round($this->kcal_aportadas / $this->kcal_meta * 100);
    }

    public function pctProteinas(): ?int
    {
        if (!$this->proteinas_g_meta || !$this->proteinas_g_aportadas) return null;
        return (int)round($this->proteinas_g_aportadas / $this->proteinas_g_meta * 100);
    }

    public function semaforoKcal(): string
    {
        $p = $this->pctKcal();
        if ($p === null) return 'secondary';
        if ($p >= 80) return 'success';
        if ($p >= 60) return 'warning';
        return 'danger';
    }

    public function semaforoProteinas(): string
    {
        $p = $this->pctProteinas();
        if ($p === null) return 'secondary';
        if ($p >= 80) return 'success';
        if ($p >= 60) return 'warning';
        return 'danger';
    }
}
