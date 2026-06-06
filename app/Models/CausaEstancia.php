<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CausaEstancia extends Model
{
    protected $table = 'causas_estancia';

    protected $fillable = [
        'paciente_id', 'usuario_id',
        'pendiente_cirugia', 'condicion_clinica', 'ventilacion_mecanica',
        'pendiente_cama_hospitalizacion', 'tramite_administrativo', 'homecare',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'pendiente_cirugia'             => 'boolean',
            'condicion_clinica'             => 'boolean',
            'ventilacion_mecanica'          => 'boolean',
            'pendiente_cama_hospitalizacion'=> 'boolean',
            'tramite_administrativo'        => 'boolean',
            'homecare'                      => 'boolean',
        ];
    }

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public static function etiquetas(): array
    {
        return [
            'pendiente_cirugia'              => ['label' => 'Pendiente cirugía',           'icon' => 'bi-scissors',         'color' => 'danger'],
            'condicion_clinica'              => ['label' => 'Condición clínica',            'icon' => 'bi-heart-pulse',      'color' => 'warning'],
            'ventilacion_mecanica'           => ['label' => 'Ventilación mecánica',         'icon' => 'bi-lungs',            'color' => 'info'],
            'pendiente_cama_hospitalizacion' => ['label' => 'Pendiente cama hospitaliz.',   'icon' => 'bi-hospital',         'color' => 'primary'],
            'tramite_administrativo'         => ['label' => 'Trámite administrativo',       'icon' => 'bi-file-earmark-text','color' => 'secondary'],
            'homecare'                       => ['label' => 'Homecare',                     'icon' => 'bi-house-heart',      'color' => 'success'],
        ];
    }
}
