<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BalanceHidrico extends Model
{
    protected $table = 'balance_hidrico_diario';

    protected $fillable = [
        'paciente_id','usuario_id','fecha',
        'vol_cristaloides','vol_coloides','vol_hemoderivados',
        'vol_nutricion','vol_medicamentos','vol_otros_ingresos',
        'vol_diuresis','vol_drenajes','vol_perdidas_insensibles','vol_otros_egresos',
        'observaciones',
    ];

    protected $casts = ['fecha' => 'date'];

    public function paciente() { return $this->belongsTo(Paciente::class); }
    public function usuario()  { return $this->belongsTo(User::class, 'usuario_id'); }

    public function totalIngresos(): int
    {
        return $this->vol_cristaloides + $this->vol_coloides + $this->vol_hemoderivados
             + $this->vol_nutricion + $this->vol_medicamentos + $this->vol_otros_ingresos;
    }

    public function totalEgresos(): int
    {
        return $this->vol_diuresis + $this->vol_drenajes
             + $this->vol_perdidas_insensibles + $this->vol_otros_egresos;
    }

    public function balance(): int
    {
        return $this->totalIngresos() - $this->totalEgresos();
    }

    public function semaforo(): string
    {
        $b = $this->balance();
        if ($b > 1000)  return 'danger';
        if ($b > 500)   return 'warning';
        if ($b < -500)  return 'info';
        return 'success';
    }

    public function labelBalance(): string
    {
        $b = $this->balance();
        if ($b > 0) return "+{$b} mL (positivo)";
        if ($b < 0) return "{$b} mL (negativo)";
        return "0 mL (neutro)";
    }
}
