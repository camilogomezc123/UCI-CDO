<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AntibioticosUci extends Model
{
    protected $table = 'antibioticos_uci';

    protected $fillable = [
        'paciente_id','usuario_id','antibiotico','fecha_inicio','fecha_fin',
        'via','dosis','indicacion','foco','activo',
        'cultivo_disponible','resultado_cultivo',
        'de_escalado','fecha_deescalacion',
        'pct_inicio','pct_control_72h','observaciones',
    ];

    protected $casts = [
        'fecha_inicio'       => 'date',
        'fecha_fin'          => 'date',
        'fecha_deescalacion' => 'date',
        'activo'             => 'boolean',
        'cultivo_disponible' => 'boolean',
        'de_escalado'        => 'boolean',
    ];

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public function diasTratamiento(): int
    {
        $fin = $this->fecha_fin ?? today();
        return max(1, (int) $this->fecha_inicio->diffInDays($fin) + 1);
    }

    public function respuestasPct(): ?string
    {
        if ($this->pct_inicio === null || $this->pct_control_72h === null) return null;
        $delta = round($this->pct_inicio - $this->pct_control_72h, 1);
        $pct   = $this->pct_inicio > 0
            ? round(($delta / $this->pct_inicio) * 100)
            : null;
        if ($pct === null) return null;
        return $pct >= 80 ? 'respuesta' : ($pct >= 50 ? 'parcial' : 'sin_respuesta');
    }

    public static function focosComunes(): array
    {
        return [
            'Neumonía / VAP','ITS / CLABSI','ITU / CAUTI',
            'Abdominal','Tejidos blandos','Endocarditis',
            'SNC','Profilaxis quirúrgica','Desconocido',
        ];
    }
}
