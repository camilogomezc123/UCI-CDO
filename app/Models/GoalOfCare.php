<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoalOfCare extends Model
{
    protected $table = 'goals_of_care';

    protected $fillable = [
        'paciente_id','usuario_id','fecha_conversacion',
        'nivel_esfuerzo','dnr','tiempo_limitado_hasta',
        'quien_participo','plan_cuidados','observaciones',
    ];

    protected $casts = [
        'fecha_conversacion'   => 'date',
        'tiempo_limitado_hasta'=> 'date',
        'dnr'                  => 'boolean',
    ];

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public static function niveles(): array
    {
        return [
            'maximo'  => ['Máximo esfuerzo terapéutico', 'success', 'bi-shield-fill-check'],
            'limitado'=> ['Limitación del esfuerzo (LET)', 'warning','bi-shield-fill-minus'],
            'confort' => ['Solo cuidados de confort',     'secondary','bi-heart-fill'],
        ];
    }

    public function badgeNivel(): string
    {
        return self::niveles()[$this->nivel_esfuerzo][1] ?? 'secondary';
    }

    public function labelNivel(): string
    {
        return self::niveles()[$this->nivel_esfuerzo][0] ?? $this->nivel_esfuerzo;
    }

    public function iconNivel(): string
    {
        return self::niveles()[$this->nivel_esfuerzo][2] ?? 'bi-circle';
    }
}
