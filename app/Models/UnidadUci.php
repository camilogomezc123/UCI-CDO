<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class UnidadUci extends Model
{
    protected $table = 'unidades_uci';
    protected $fillable = ['nombre', 'cama_desde', 'cama_hasta', 'capacidad', 'habilitada_desde', 'inhabilitada_desde'];
    protected function casts(): array { return ['habilitada_desde' => 'date', 'inhabilitada_desde' => 'date']; }

    public function estaHabilitadaEn($fecha): bool
    {
        $fecha = Carbon::parse($fecha)->startOfDay();
        return (!$this->habilitada_desde || $fecha->gte($this->habilitada_desde))
            && (!$this->inhabilitada_desde || $fecha->lt($this->inhabilitada_desde));
    }
}
