<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PicsRiesgo extends Model
{
    protected $table = 'pics_riesgo';

    protected $fillable = [
        'paciente_id', 'fecha_calculo',
        'dias_uci', 'dias_vm', 'dias_delirium', 'edad',
        'score_total', 'nivel_riesgo', 'factores',
    ];

    protected $casts = [
        'fecha_calculo' => 'date',
        'factores'      => 'array',
    ];

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function colorNivel(): string
    {
        return match($this->nivel_riesgo) {
            'alto'  => '#dc3545',
            'medio' => '#d97706',
            default => '#198754',
        };
    }

    public function badgeClass(): string
    {
        return match($this->nivel_riesgo) {
            'alto'  => 'danger',
            'medio' => 'warning',
            default => 'success',
        };
    }
}
