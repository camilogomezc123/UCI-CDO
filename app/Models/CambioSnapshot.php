<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CambioSnapshot extends Model
{
    protected $table = 'cambios_snapshot';

    protected $fillable = ['snapshot_id', 'campo', 'valor_anterior', 'valor_nuevo'];

    public function snapshot()
    {
        return $this->belongsTo(Snapshot::class);
    }
}
