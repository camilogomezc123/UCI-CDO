<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PicsEvaluacion extends Model
{
    protected $table = 'pics_evaluaciones';

    protected $fillable = [
        'paciente_id', 'usuario_id', 'momento', 'tipo', 'fecha_evaluacion',
        'disfagia',
        'amt_respuestas', 'amt_score',
        'hads_respuestas', 'hads_ansiedad', 'hads_depresion',
        'pcptsd_respuestas', 'pcptsd_score',
        'fatiga_score', 'dolor_reposo', 'dolor_movimiento',
        'ptg_respuestas', 'ptg_score',
        'picsf_respuestas', 'picsf_distress',
        'observaciones',
    ];

    protected $casts = [
        'fecha_evaluacion' => 'date',
        'amt_respuestas'   => 'array',
        'hads_respuestas'  => 'array',
        'pcptsd_respuestas'=> 'array',
        'ptg_respuestas'   => 'array',
        'picsf_respuestas' => 'array',
    ];

    public function paciente()
    {
        return $this->belongsTo(Paciente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    // ── Semáforos por dominio ──────────────────────────────────────────────

    public function semaforoAmt(): string
    {
        if ($this->amt_score === null) return 'sin_dato';
        if ($this->amt_score >= 8)    return 'verde';
        if ($this->amt_score >= 6)    return 'amarillo';
        return 'rojo';
    }

    public function semaforoAnsiedad(): string
    {
        if ($this->hads_ansiedad === null) return 'sin_dato';
        if ($this->hads_ansiedad <= 7)    return 'verde';
        if ($this->hads_ansiedad <= 10)   return 'amarillo';
        return 'rojo';
    }

    public function semaforoDepresion(): string
    {
        if ($this->hads_depresion === null) return 'sin_dato';
        if ($this->hads_depresion <= 7)    return 'verde';
        if ($this->hads_depresion <= 10)   return 'amarillo';
        return 'rojo';
    }

    public function semaforoPtsd(): string
    {
        if ($this->pcptsd_score === null) return 'sin_dato';
        if ($this->pcptsd_score <= 1)     return 'verde';
        if ($this->pcptsd_score <= 2)     return 'amarillo';
        return 'rojo';
    }

    public function semaforoFatiga(): string
    {
        if ($this->fatiga_score === null) return 'sin_dato';
        if ($this->fatiga_score <= 3)     return 'verde';
        if ($this->fatiga_score <= 6)     return 'amarillo';
        return 'rojo';
    }

    public function semaforoDolor(): string
    {
        $max = max($this->dolor_reposo ?? 0, $this->dolor_movimiento ?? 0);
        if ($this->dolor_reposo === null && $this->dolor_movimiento === null) return 'sin_dato';
        if ($max <= 3)  return 'verde';
        if ($max <= 6)  return 'amarillo';
        return 'rojo';
    }

    public function semaforoPtg(): string
    {
        if ($this->ptg_score === null) return 'sin_dato';
        if ($this->ptg_score >= 30)    return 'verde';
        if ($this->ptg_score >= 15)    return 'amarillo';
        return 'rojo';
    }

    public function labelMomento(): string
    {
        return match($this->momento) {
            'egreso' => 'Al egreso UCI',
            '30d'    => '30 días',
            '90d'    => '90 días',
            '180d'   => '180 días',
            default  => $this->momento,
        };
    }

    // Semáforo global: el peor de todos los dominios evaluados
    public function semaforoGlobal(): string
    {
        $sem = [
            $this->semaforoAmt(),
            $this->semaforoAnsiedad(),
            $this->semaforoDepresion(),
            $this->semaforoPtsd(),
            $this->semaforoFatiga(),
            $this->semaforoDolor(),
        ];
        if (in_array('rojo', $sem))     return 'rojo';
        if (in_array('amarillo', $sem)) return 'amarillo';
        if (in_array('verde', $sem))    return 'verde';
        return 'sin_dato';
    }
}
