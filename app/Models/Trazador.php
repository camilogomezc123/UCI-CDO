<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Trazador extends Model
{
    protected $table = 'trazadores';

    protected $fillable = [
        'paciente_id',
        'tipo_trazador',
        'estado',
        'fecha_guardado_inicial',
        'fecha_objetivo_despues',
        'fecha_cierre',
        'datos',
        'resultados',
    ];

    protected function casts(): array
    {
        return [
            'datos'                  => 'array',
            'resultados'             => 'array',
            'fecha_guardado_inicial' => 'datetime',
            'fecha_objetivo_despues' => 'datetime',
            'fecha_cierre'           => 'datetime',
        ];
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    // ─── Scopes de estado ─────────────────────────────────────────────────────

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('estado', 'TRAZADOR_INICIAL');
    }

    public function scopeEnSeguimiento(Builder $q): Builder
    {
        return $q->where('estado', 'SEGUIMIENTO_90D');
    }

    public function scopePendientesDespues(Builder $q): Builder
    {
        return $q->where('estado', 'PENDIENTE_DESPUES');
    }

    public function scopeCerrados(Builder $q): Builder
    {
        return $q->where('estado', 'CERRADO');
    }

    // Estadísticas = SEGUIMIENTO_90D + CERRADO
    public function scopeEstadisticas(Builder $q): Builder
    {
        return $q->whereIn('estado', ['SEGUIMIENTO_90D', 'CERRADO']);
    }

    // ─── Helpers de estado ────────────────────────────────────────────────────

    public function estaActivo(): bool
    {
        return $this->estado === 'TRAZADOR_INICIAL';
    }

    public function estaCerrado(): bool
    {
        return $this->estado === 'CERRADO';
    }

    public function necesitaEncuestaDespues(): bool
    {
        return $this->estado === 'PENDIENTE_DESPUES';
    }

    public function diasRestantes(): ?int
    {
        if (!$this->fecha_objetivo_despues) return null;
        $diff = now()->diffInDays($this->fecha_objetivo_despues, false);
        return (int) $diff;
    }

    // ─── Acceso rápido a secciones del JSON de datos ─────────────────────────

    public function getDatosPaciente(): array
    {
        return $this->datos['datos_paciente'] ?? [];
    }

    public function getEncuestaAntes(): array
    {
        return $this->datos['encuesta_antes'] ?? [];
    }

    public function getEncuestaDespues(): array
    {
        return $this->datos['encuesta_despues'] ?? [];
    }

    public function getSemaforo(): array
    {
        return $this->resultados['semaforo'] ?? [];
    }

    public function getPuntuacionGlobal(): ?float
    {
        return $this->resultados['puntuacion_global_pct'] ?? null;
    }

    public function getBandaGlobal(): string
    {
        $pct = $this->getPuntuacionGlobal();
        if ($pct === null) return 'sin_dato';
        if ($pct > 90)  return 'verde';
        if ($pct >= 70) return 'amarillo';
        return 'rojo';
    }
}
