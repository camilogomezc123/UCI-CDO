<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class IndisponibilidadUci extends Model
{
    protected $table = 'indisponibilidades_uci';
    protected $fillable = ['unidad_uci_id', 'numero_cama', 'inhabilitada_desde', 'habilitada_desde', 'motivo', 'usuario_id'];
    protected function casts(): array { return ['inhabilitada_desde' => 'date', 'habilitada_desde' => 'date']; }
    public function unidad() { return $this->belongsTo(UnidadUci::class, 'unidad_uci_id'); }
    public function estaActivaEn($fecha): bool
    {
        $fecha = Carbon::parse($fecha)->startOfDay();
        return $fecha->gte($this->inhabilitada_desde) && (!$this->habilitada_desde || $fecha->lt($this->habilitada_desde));
    }
}
