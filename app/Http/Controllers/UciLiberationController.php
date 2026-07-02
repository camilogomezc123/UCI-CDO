<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CamUci;
use App\Models\BundleVentilacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UciLiberationController extends Controller
{
    // Etiquetas y descripciones del bundle ABCDEF
    public const BUNDLE = [
        'A' => ['Evaluar y manejar el Dolor',       'EVA/BPS',                'bi-emoji-frown',    'danger'],
        'B' => ['Despertar y Respiración (SAT+SBT)', 'Vacación sedación + SBT','bi-lungs',          'info'],
        'C' => ['Elección sedación/analgesia',       'RASS objetivo vs real',  'bi-capsule',        'warning'],
        'D' => ['Delirium: evaluar y prevenir',      'CAM-UCI',                'bi-brain',          'primary'],
        'E' => ['Movilización temprana',             'Nivel 0-4',              'bi-person-walking', 'success'],
        'F' => ['Familia involucrada',               'Participación activa',   'bi-people',         'secondary'],
    ];

    public function index(Request $request)
    {
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha')) : today();

        // Pacientes activos en UCI ese día con snapshot
        $activos = Paciente::where('activo', true)
            ->whereNotNull('ingreso_uci')
            ->with(['ultimoSnapshot'])
            ->orderBy('nombre')
            ->get();

        // Datos del bundle para esa fecha
        $bundles = BundleVentilacion::whereDate('fecha', $fecha)
            ->get()->keyBy('paciente_id');

        $camUcis = CamUci::whereDate('fecha', $fecha)
            ->get()->keyBy('paciente_id');

        // Snapshot más cercano a esa fecha por paciente
        $snapshots = Snapshot::where('fecha_snapshot', '<=', $fecha)
            ->whereIn('paciente_id', $activos->pluck('id'))
            ->orderByDesc('fecha_snapshot')
            ->orderByDesc('id')
            ->get()
            ->unique('paciente_id')
            ->keyBy('paciente_id');

        // Construir tabla de compliance por paciente
        $tablero = $activos->map(function ($p) use ($bundles, $camUcis, $snapshots) {
            $bundle  = $bundles[$p->id] ?? null;
            $cam     = $camUcis[$p->id] ?? null;
            $snap    = $snapshots[$p->id] ?? null;

            $eva  = (float)($snap?->eva ?? 0);
            $bps  = (float)($snap?->bps ?? 0);
            $rass = $snap?->rass ? (int)$snap->rass : null;

            $items = [
                'A' => $this->evalA($eva, $bps),
                'B' => $this->evalB($bundle),
                'C' => $this->evalC($rass, $p->rass_objetivo),
                'D' => $this->evalD($cam),
                'E' => $this->evalE($bundle, $snap),
                'F' => $this->evalF($bundle),
            ];

            $cumplidos = collect($items)->filter(fn($i) => $i['ok'] === true)->count();
            $aplicables = collect($items)->filter(fn($i) => $i['ok'] !== null)->count();
            $pct = $aplicables > 0 ? round($cumplidos / $aplicables * 100) : null;

            return compact('p', 'snap', 'bundle', 'cam', 'items', 'cumplidos', 'aplicables', 'pct');
        });

        // KPIs del día
        $evaluados = $tablero->filter(fn($r) => $r['aplicables'] > 0);
        $kpis = [
            'pacientes'       => $activos->count(),
            'bundle_completo' => $tablero->filter(fn($r) => $r['pct'] === 100)->count(),
            'pct_promedio'    => $evaluados->avg(fn($r) => $r['pct']),
            'delirium_pos'    => $camUcis->filter(fn($c) => $c->resultado === 'positivo')->count(),
            'sin_cam'         => $activos->count() - $camUcis->count(),
        ];

        // Tendencia semanal: últimos 14 días
        $tendencia = $this->tendenciaSemanal(14);

        return view('uci-liberation.index', compact('tablero', 'kpis', 'tendencia', 'fecha'));
    }

    // ── Evaluadores por componente ─────────────────────────────────────────────

    private function evalA(float $eva, float $bps): array
    {
        // ok = sin dolor (EVA ≤ 3 o BPS ≤ 5), null = sin dato
        if ($eva === 0.0 && $bps === 0.0) return ['ok' => null, 'valor' => '—', 'label' => 'Sin dato'];
        $sinDolor = ($eva > 0 && $eva <= 3) || ($bps > 0 && $bps <= 5);
        $label = [];
        if ($eva > 0) $label[] = "EVA {$eva}";
        if ($bps > 0) $label[] = "BPS {$bps}";
        return ['ok' => $sinDolor, 'valor' => implode(' / ', $label), 'label' => $sinDolor ? 'Sin dolor' : 'Dolor'];
    }

    private function evalB(?BundleVentilacion $b): array
    {
        if (!$b) return ['ok' => null, 'valor' => '—', 'label' => 'Sin registro'];
        $sat = $b->vacacion_sedacion;
        $sbt = $b->sbt;
        $satRes = $b->sat_resultado;
        $sbtRes = $b->sbt_resultado;
        if (!$sat && !$sbt) return ['ok' => null, 'valor' => 'No aplica', 'label' => 'Sin VMI'];
        $ok = ($sat && $satRes !== 'fallido') && ($sbt && $sbtRes !== 'fallido');
        $partes = [];
        if ($sat) $partes[] = 'SAT ' . ($satRes ?? '✓');
        if ($sbt) $partes[] = 'SBT ' . ($sbtRes ?? '✓');
        return ['ok' => $ok, 'valor' => implode(' + ', $partes), 'label' => $ok ? 'Exitoso' : 'Fallido/incompleto'];
    }

    private function evalC(?int $rassReal, ?int $rassObj): array
    {
        if ($rassReal === null) return ['ok' => null, 'valor' => '—', 'label' => 'Sin RASS'];
        if ($rassObj === null)  return ['ok' => null, 'valor' => "RASS {$rassReal}", 'label' => 'Sin objetivo'];
        $ok = $rassReal >= $rassObj && $rassReal <= ($rassObj + 1);
        return ['ok' => $ok, 'valor' => "RASS {$rassReal} (obj {$rassObj})", 'label' => $ok ? 'En objetivo' : 'Fuera objetivo'];
    }

    private function evalD(?CamUci $cam): array
    {
        if (!$cam) return ['ok' => null, 'valor' => '—', 'label' => 'Sin CAM-UCI'];
        $ok = $cam->resultado === 'negativo';
        $label = match($cam->resultado) {
            'positivo'    => 'Delirium ' . ($cam->subtipo_delirium ?? ''),
            'negativo'    => 'Sin delirium',
            'no_evaluable'=> 'No evaluable',
            default       => $cam->resultado,
        };
        return ['ok' => $ok, 'valor' => $label, 'label' => $label];
    }

    private function evalE(?BundleVentilacion $b, $snap): array
    {
        $nivel = $b?->nivel_movilizacion;
        if ($nivel === null) {
            // Fallback al campo texto de movilización del snapshot
            $mov = strtolower($snap?->movilizacion ?? '');
            if (!$mov) return ['ok' => null, 'valor' => '—', 'label' => 'Sin dato'];
            $ok = str_contains($mov, 'activ') || str_contains($mov, 'ambu') || str_contains($mov, 'sent');
            return ['ok' => $ok, 'valor' => $snap->movilizacion, 'label' => $ok ? 'Activa' : 'Pasiva/ninguna'];
        }
        $labels = ['Pasiva en cama', 'Activa en cama', 'Sedestación', 'Bipedestación', 'Deambulación'];
        $ok = $nivel >= 1;
        return ['ok' => $ok, 'valor' => "Nivel {$nivel}: " . ($labels[$nivel] ?? ''), 'label' => $labels[$nivel] ?? "Nivel {$nivel}"];
    }

    private function evalF(?BundleVentilacion $b): array
    {
        if (!$b) return ['ok' => null, 'valor' => '—', 'label' => 'Sin registro'];
        $reunion = $b->familia_reunion_clinica ?? false;
        $contacto = $b->familia_involucrada ?? false;
        if (!$contacto && !$reunion) return ['ok' => false, 'valor' => 'Sin contacto', 'label' => 'Sin familia'];
        $ok = $contacto || $reunion;
        $label = $reunion ? 'Reunión clínica' : 'Contacto activo';
        return ['ok' => $ok, 'valor' => $label, 'label' => $label];
    }

    // ── Tendencia semanal ──────────────────────────────────────────────────────
    private function tendenciaSemanal(int $dias): array
    {
        $resultado = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $fecha = today()->subDays($i);
            $bundles = BundleVentilacion::whereDate('fecha', $fecha)->get();
            $cams    = CamUci::whereDate('fecha', $fecha)->get();
            $total   = Paciente::where('activo', true)->whereNotNull('ingreso_uci')
                ->where('ingreso_uci', '<=', $fecha)->count();

            $resultado[] = [
                'fecha'         => $fecha->format('d/m'),
                'total'         => $total,
                'con_bundle'    => $bundles->count(),
                'delirium_pos'  => $cams->where('resultado', 'positivo')->count(),
                'sin_cam'       => max(0, $total - $cams->count()),
                'pct_bundle'    => $total > 0 ? round($bundles->count() / $total * 100) : 0,
            ];
        }
        return $resultado;
    }
}
