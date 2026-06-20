<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
// TransfusionDiaria is autoloaded via namespace

class Paciente extends Model
{
    protected $fillable = [
        'documento', 'nombre', 'edad', 'sexo', 'eapb',
        'ingreso_uci', 'salida_hospitalizacion', 'egreso_uci', 'tipo_egreso',
        'activo', 'numero_ingresos',
    ];

    protected function casts(): array
    {
        return [
            'ingreso_uci'            => 'datetime',
            'salida_hospitalizacion' => 'datetime',
            'egreso_uci'             => 'datetime',
            'activo'                 => 'boolean',
        ];
    }

    public function snapshots()
    {
        return $this->hasMany(Snapshot::class)->orderByDesc('fecha_snapshot');
    }

    public function ultimoSnapshot()
    {
        return $this->hasOne(Snapshot::class)->latestOfMany('fecha_snapshot');
    }

    public function notas()
    {
        return $this->hasMany(NotaPaciente::class)->orderByDesc('fecha');
    }

    public function causaEstancia()
    {
        return $this->hasOne(CausaEstancia::class);
    }

    public function camUci()
    {
        return $this->hasMany(CamUci::class)->orderByDesc('fecha');
    }

    public function bundleVentilacion()
    {
        return $this->hasMany(BundleVentilacion::class)->orderByDesc('fecha');
    }

    public function transfusiones()
    {
        return $this->hasMany(TransfusionDiaria::class)->orderByDesc('fecha');
    }

    public function episodios()
    {
        return $this->hasMany(EpisodioUci::class)->orderBy('numero_episodio');
    }

    public function trazadores()
    {
        return $this->hasMany(Trazador::class);
    }

    public function trazadorSepsis()
    {
        return $this->hasOne(Trazador::class)->where('tipo_trazador', 'sepsis')->latestOfMany();
    }

    public function esReingreso(): bool
    {
        return ($this->numero_ingresos ?? 1) > 1;
    }

    // ─── Tiempo en UCI ────────────────────────────────────────────────────────

    public function diasEnUci(): int
    {
        if (!$this->ingreso_uci) return 0;
        $fin = $this->egreso_uci ?? now();
        return (int) $this->ingreso_uci->diffInDays($fin);
    }

    public function tiempoEnUciTexto(): string
    {
        if (!$this->ingreso_uci) return 'Sin fecha de ingreso';
        $fin  = $this->egreso_uci ?? now();
        $diff = $this->ingreso_uci->diff($fin);
        $p = [];
        if ($diff->days > 0) $p[] = $diff->days . 'd';
        if ($diff->h > 0)    $p[] = $diff->h . 'h';
        $p[] = $diff->i . 'min';
        return implode(' ', $p);
    }

    public function tiempoEsperaHospitalizacion(): ?string
    {
        if (!$this->salida_hospitalizacion) return null;
        $fin  = $this->egreso_uci ?? now();
        $diff = $this->salida_hospitalizacion->diff($fin);
        if ($diff->invert) return null; // egreso registrado antes de la indicación: dato inconsistente
        $p = [];
        if ($diff->days > 0) $p[] = $diff->days . 'd';
        if ($diff->h > 0)    $p[] = $diff->h . 'h';
        $p[] = $diff->i . 'min';
        return implode(' ', $p);
    }

    public function tiempoEsperaHoras(): ?float
    {
        if (!$this->salida_hospitalizacion) return null;
        $fin     = $this->egreso_uci ?? now();
        $minutos = $this->salida_hospitalizacion->diffInMinutes($fin, false);
        if ($minutos < 0) return null;
        return round($minutos / 60, 1);
    }

    // ─── Días-dispositivo ─────────────────────────────────────────────────────
    // Lógica: si el snapshot del día NO tiene el soporte → paciente lo terminó.
    // Contar días con snapshot positivo = días reales de uso del dispositivo.

    public function diasVmi(): int
    {
        return $this->snapshots()
            ->where(fn($q) => $q
                ->where('soporte_ventilatorio', 'like', '%VMI%')
                ->orWhere('soporte_ventilatorio', 'like', '%invasiv%')
                ->orWhere('soporte_ventilatorio', 'like', '%mecánic%')
                ->orWhere('soporte_ventilatorio', 'like', '%mecanica%'))
            ->distinct('fecha_snapshot')
            ->count('fecha_snapshot');
    }

    public function diasVasopresor(): int
    {
        return $this->snapshots()
            ->where(fn($q) => $q
                ->where('soporte_hemodinamico', 'like', '%vasopresor%')
                ->orWhere('soporte_hemodinamico', 'like', '%norepinefrina%')
                ->orWhere('soporte_hemodinamico', 'like', '%adrenalina%')
                ->orWhere('soporte_hemodinamico', 'like', '%vasopresina%')
                ->orWhere('soporte_hemodinamico', 'like', '%dopamina%'))
            ->distinct('fecha_snapshot')
            ->count('fecha_snapshot');
    }

    public function diasInotropico(): int
    {
        return $this->snapshots()
            ->where(fn($q) => $q
                ->where('soporte_hemodinamico', 'like', '%inotr%')
                ->orWhere('soporte_hemodinamico', 'like', '%dobutamina%')
                ->orWhere('soporte_hemodinamico', 'like', '%milrinona%')
                ->orWhere('soporte_hemodinamico', 'like', '%levosimendan%'))
            ->distinct('fecha_snapshot')
            ->count('fecha_snapshot');
    }

    public function diasAntiarritmico(): int
    {
        return $this->snapshots()
            ->where(fn($q) => $q
                ->where('soporte_hemodinamico', 'like', '%amiodar%')
                ->orWhere('soporte_hemodinamico', 'like', '%antiarr%')
                ->orWhere('soporte_hemodinamico', 'like', '%lidocain%')
                ->orWhere('soporte_hemodinamico', 'like', '%propafenon%')
                ->orWhere('soporte_hemodinamico', 'like', '%digoxin%')
                ->orWhere('soporte_hemodinamico', 'like', '%adenosin%'))
            ->distinct('fecha_snapshot')
            ->count('fecha_snapshot');
    }

    // Cumplimiento bundle ventilador (% días con VMI que tienen bundle registrado)
    public function cumplimientoBundle(): ?int
    {
        $diasVmi = $this->diasVmi();
        if ($diasVmi === 0) return null;
        $diasConBundle = $this->bundleVentilacion()->count();
        return (int)round($diasConBundle / $diasVmi * 100);
    }

    // ─── Alertas de escalas ───────────────────────────────────────────────────

    public function tieneAlertaNews(): bool
    {
        $s = $this->ultimoSnapshot;
        return $s && $s->news !== null && $s->news >= 5;
    }

    public function tieneAlertaDolor(): bool
    {
        $s = $this->ultimoSnapshot;
        if (!$s) return false;
        return ($s->eva !== null && (float)$s->eva > 4)
            || ($s->bps !== null && (float)$s->bps > 6);
    }

    public function tieneAlertaSofa(): bool
    {
        // SOFA viene como "22-25%" o número
        $s = $this->ultimoSnapshot;
        if (!$s || !$s->sofa) return false;
        preg_match('/(\d+)/', $s->sofa, $m);
        return isset($m[1]) && (int)$m[1] >= 10;
    }
}
