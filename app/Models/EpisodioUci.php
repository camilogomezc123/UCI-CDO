<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpisodioUci extends Model
{
    protected $table = 'episodios_uci';

    protected $fillable = [
        'paciente_id', 'numero_episodio', 'es_reingreso',
        'ingreso_uci', 'salida_hospitalizacion', 'egreso_uci', 'tipo_egreso',
    ];

    protected function casts(): array
    {
        return [
            'ingreso_uci'            => 'datetime',
            'salida_hospitalizacion' => 'datetime',
            'egreso_uci'             => 'datetime',
            'es_reingreso'           => 'boolean',
        ];
    }

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function tipoEgresoLabel(): string
    {
        return match($this->tipo_egreso) {
            'mejoria'       => 'Mejoría',
            'alta_casa'     => 'Alta a casa',
            'traslado'      => 'Traslado',
            'fallecimiento' => 'Fallecimiento',
            default         => $this->tipo_egreso ?? 'Sin registrar',
        };
    }
}
