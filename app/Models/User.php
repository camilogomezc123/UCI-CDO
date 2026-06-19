<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'rol', 'activo', 'solo_dashboard'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'       => 'hashed',
            'activo'         => 'boolean',
            'solo_dashboard' => 'boolean',
        ];
    }

    public function esMaster(): bool
    {
        return $this->rol === 'master';
    }

    public function esOperativo(): bool
    {
        return $this->rol === 'operativo';
    }

    public function esVisual(): bool
    {
        return (bool) $this->solo_dashboard;
    }

    public function cargas()
    {
        return $this->hasMany(CargaArchivo::class, 'usuario_id');
    }
}
