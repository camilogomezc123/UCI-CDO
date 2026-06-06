<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CamUci extends Model
{
    protected $table = 'cam_uci';

    protected $fillable = [
        'paciente_id', 'usuario_id', 'fecha',
        'resultado', 'rass_momento', 'observacion',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public function etiqueta(): array
    {
        return match($this->resultado) {
            'positivo'     => ['POSITIVO — Delirium presente', 'danger',  'bi-exclamation-triangle-fill'],
            'negativo'     => ['NEGATIVO — Sin delirium',      'success', 'bi-check-circle-fill'],
            'no_evaluable' => ['No evaluable',                 'secondary','bi-dash-circle'],
            default        => ['—', 'secondary', 'bi-question-circle'],
        };
    }
}
