<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class UnidadUci extends Model
{
    protected $table = 'unidades_uci';
    protected $fillable = ['nombre', 'cama_desde', 'cama_hasta', 'capacidad', 'habilitada_desde', 'inhabilitada_desde'];
    protected function casts(): array { return ['habilitada_desde' => 'date', 'inhabilitada_desde' => 'date']; }

    public function indisponibilidades()
    {
        return $this->hasMany(IndisponibilidadUci::class, 'unidad_uci_id');
    }

    public function estaHabilitadaEn($fecha): bool
    {
        $fecha = Carbon::parse($fecha)->startOfDay();
        $cerrada = $this->indisponibilidades->first(fn($i) => $i->numero_cama === null && $i->estaActivaEn($fecha));
        return (!$this->habilitada_desde || $fecha->gte($this->habilitada_desde)) && !$cerrada;
    }

    public function camasInhabilitadasEn($fecha): array
    {
        return $this->indisponibilidades
            ->filter(fn($i) => $i->numero_cama !== null && $i->estaActivaEn($fecha))
            ->pluck('numero_cama')->unique()->values()->all();
    }

    public function capacidadDisponibleEn($fecha): int
    {
        return $this->estaHabilitadaEn($fecha) ? max(0, $this->capacidad - count($this->camasInhabilitadasEn($fecha))) : 0;
    }
}
