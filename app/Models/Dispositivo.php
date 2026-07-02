<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispositivo extends Model
{
    protected $table = 'dispositivos_paciente';

    protected $fillable = [
        'paciente_id','usuario_id','tipo','fecha_inicio','fecha_retiro',
        'sitio_insercion','via_acceso','activo',
        'evento_iaas','tipo_iaas','organismo','sensibilidad','observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_retiro' => 'date',
        'activo'       => 'boolean',
        'evento_iaas'  => 'boolean',
    ];

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public static function tipos(): array
    {
        return [
            'cvc'           => ['CVC / Catéter central',    'bi-plug',           'danger'],
            'sonda_vesical' => ['Sonda vesical (Foley)',     'bi-droplet-fill',   'warning'],
            'vm'            => ['Ventilación mecánica',      'bi-lungs',          'info'],
            'drain'         => ['Drenaje quirúrgico',        'bi-arrow-down-up',  'secondary'],
            'ngt'           => ['Sonda nasogástrica',        'bi-arrow-down',     'primary'],
        ];
    }

    public static function tiposIaas(): array
    {
        return ['CLABSI', 'CAUTI', 'VAP', 'ITS', 'UPP', 'Otro'];
    }

    public function diasDispositivo(): int
    {
        $fin = $this->fecha_retiro ?? today();
        return max(1, (int) $this->fecha_inicio->diffInDays($fin) + 1);
    }

    public function etiquetaTipo(): string
    {
        return self::tipos()[$this->tipo][0] ?? $this->tipo;
    }

    public function badgeIaas(): string
    {
        return $this->evento_iaas ? 'danger' : 'secondary';
    }
}
