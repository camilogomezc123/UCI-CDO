<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransfusionDiaria extends Model
{
    protected $table = 'transfusiones_diarias';

    protected $fillable = [
        'paciente_id', 'usuario_id', 'fecha',
        'productos', 'unidades_totales', 'observaciones',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public static function tiposDisponibles(): array
    {
        return [
            'GRE'   => 'Glóbulos Rojos Empaquetados (GRE)',
            'PFC'   => 'Plasma Fresco Congelado (PFC)',
            'PLT'   => 'Plaquetas',
            'CRIO'  => 'Crioprecipitado',
            'ALB'   => 'Albúmina',
            'OTRO'  => 'Otro hemoderivado',
        ];
    }
}
