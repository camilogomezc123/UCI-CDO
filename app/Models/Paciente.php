<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Paciente extends Model
{
    protected $fillable = [
        'documento', 'nombre', 'edad', 'sexo', 'eapb',
        'ingreso_uci', 'salida_hospitalizacion', 'egreso_uci', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'ingreso_uci' => 'datetime',
            'salida_hospitalizacion' => 'datetime',
            'egreso_uci' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function snapshots()
    {
        return $this->hasMany(Snapshot::class)->orderByDesc('fecha_snapshot');
    }

    public function ultimoSnapshot()
    {
        return $this->hasOne(Snapshot::class)->latestOfMany('fecha_snapshot');
    }

    public function tiempoEnUciTexto(): string
    {
        if (!$this->ingreso_uci) return 'Sin fecha de ingreso';
        $fin = $this->egreso_uci ?? now();
        $diff = $this->ingreso_uci->diff($fin);
        $partes = [];
        if ($diff->days > 0) $partes[] = $diff->days . 'd';
        if ($diff->h > 0) $partes[] = $diff->h . 'h';
        $partes[] = $diff->i . 'min';
        return implode(' ', $partes);
    }

    public function tiempoEsperaHospitalizacion(): ?string
    {
        if (!$this->salida_hospitalizacion) return null;
        $fin = $this->egreso_uci ?? now();
        $diff = $this->salida_hospitalizacion->diff($fin);
        $partes = [];
        if ($diff->days > 0) $partes[] = $diff->days . 'd';
        if ($diff->h > 0) $partes[] = $diff->h . 'h';
        $partes[] = $diff->i . 'min';
        return implode(' ', $partes);
    }

    public function tiempoEsperaHoras(): ?float
    {
        if (!$this->salida_hospitalizacion) return null;
        $fin = $this->egreso_uci ?? now();
        return round($this->salida_hospitalizacion->diffInMinutes($fin) / 60, 1);
    }
}
