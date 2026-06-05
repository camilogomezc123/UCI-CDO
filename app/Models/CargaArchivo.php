<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CargaArchivo extends Model
{
    protected $table = 'cargas_archivo';

    protected $fillable = [
        'nombre_archivo', 'fecha_archivo', 'usuario_id',
        'nuevos', 'actualizados', 'omitidos', 'errores',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function snapshots()
    {
        return $this->hasMany(Snapshot::class, 'carga_id');
    }
}
