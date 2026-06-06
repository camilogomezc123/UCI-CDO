<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaPaciente extends Model
{
    protected $table = 'notas_paciente';

    protected $fillable = ['paciente_id', 'usuario_id', 'fecha', 'nota'];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }
}
