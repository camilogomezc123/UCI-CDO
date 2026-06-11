<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;

class EpidemiologiaController extends Controller
{
    // Mortalidad esperada según SOFA (basada en literatura UCI)
    private static function sofaMortalidad(int $sofa): float
    {
        return match(true) {
            $sofa <= 6  => 10.0,
            $sofa <= 9  => 15.0,
            $sofa <= 12 => 40.0,
            $sofa <= 14 => 55.0,
            default     => 80.0,
        };
    }

    // Extrae primer número de un string
    private static function extraerNumero(?string $val): ?int
    {
        if ($val === null || $val === '') return null;
        preg_match('/(-?\d+)/', $val, $m);
        return isset($m[1]) ? (int)$m[1] : null;
    }

    public function index()
    {
        // ── Universo: último snapshot de cada paciente (activos + egresados) ──
        $sub = Snapshot::subqueryUltimoPorPaciente();

        $ultimosSnaps = Snapshot::joinSub($sub, 's', fn($j) => $j->on('snapshots.id', '=', 's.snap_id'))
            ->select('snapshots.*')
            ->get();

        $todosPacientes = Paciente::all()->keyBy('id');

        // ── 1. DISTRIBUCIÓN POR EDAD ──────────────────────────────────────────
        $gruposEdad = ['0-30' => 0, '31-50' => 0, '51-65' => 0, '66-75' => 0, '>75' => 0];
        foreach ($todosPacientes as $p) {
            $e = $p->edad;
            if ($e <= 30)      $gruposEdad['0-30']++;
            elseif ($e <= 50)  $gruposEdad['31-50']++;
            elseif ($e <= 65)  $gruposEdad['51-65']++;
            elseif ($e <= 75)  $gruposEdad['66-75']++;
            else               $gruposEdad['>75']++;
        }

        // ── 2. DISTRIBUCIÓN POR SEXO ──────────────────────────────────────────
        $porSexo = [
            'Femenino'      => $todosPacientes->where('sexo', 'F')->count(),
            'Masculino'     => $todosPacientes->where('sexo', 'M')->count(),
            'No registrado' => $todosPacientes->whereNotIn('sexo', ['F','M'])->count(),
        ];

        // ── 3. TOP 10 CIE-10 ──────────────────────────────────────────────────
        $cie10Raw = $ultimosSnaps->whereNotNull('cie10')
            ->flatMap(fn($s) => collect(preg_split('/[\n,;\/]+/', $s->cie10))
                ->map(fn($c) => strtoupper(trim($c)))
                ->filter(fn($c) => preg_match('/^[A-Z]\d/', $c)))
            ->countBy()
            ->sortDesc()
            ->take(10);

        // ── 4. TOP ESPECIALIDADES ────────────────────────────────────────────
        $topEspecialidades = $ultimosSnaps->whereNotNull('especialidad')
            ->groupBy(fn($s) => trim($s->especialidad))
            ->map->count()
            ->sortDesc()
            ->take(10);

        // ── 5. TOP EAPB ──────────────────────────────────────────────────────
        $topEapb = $todosPacientes->whereNotNull('eapb')
            ->groupBy(fn($p) => trim($p->eapb))
            ->map->count()
            ->sortDesc()
            ->take(10);

        // ── 6. DISTRIBUCIÓN DÍAS DE ESTANCIA ─────────────────────────────────
        $rangosEstancia = ['1-3d' => 0, '4-7d' => 0, '8-14d' => 0, '15-30d' => 0, '>30d' => 0];
        foreach ($todosPacientes->whereNotNull('ingreso_uci') as $p) {
            $d = $p->diasEnUci();
            if ($d <= 3)       $rangosEstancia['1-3d']++;
            elseif ($d <= 7)   $rangosEstancia['4-7d']++;
            elseif ($d <= 14)  $rangosEstancia['8-14d']++;
            elseif ($d <= 30)  $rangosEstancia['15-30d']++;
            else               $rangosEstancia['>30d']++;
        }

        // ── 7. MORTALIDAD ─────────────────────────────────────────────────────
        $egresados       = $todosPacientes->whereNotNull('tipo_egreso');
        $totalEgresados  = $egresados->count();
        $fallecidos      = $egresados->where('tipo_egreso', 'fallecimiento')->count();
        $traslados       = $egresados->where('tipo_egreso', 'traslado')->count();
        $mejoria         = $egresados->whereIn('tipo_egreso', ['mejoria', 'alta_casa'])->count();
        $mortalidadBruta = $totalEgresados > 0 ? round($fallecidos / $totalEgresados * 100, 1) : 0;

        // Mortalidad esperada por SOFA — promedio de todos los pacientes con SOFA registrado
        // (activos + históricos). Refleja el riesgo real de la población UCI atendida.
        $sofaTotalSum = 0;
        $sofaCount    = 0;
        foreach ($ultimosSnaps as $s) {
            $sofa = self::extraerNumero($s->sofa);
            if ($sofa !== null) {
                $sofaTotalSum += self::sofaMortalidad($sofa);
                $sofaCount++;
            }
        }
        $mortalidadEsperada = $sofaCount > 0 ? round($sofaTotalSum / $sofaCount, 1) : null;

        // ── 8. CRITICIDAD POR SUBUNIDAD (con mortalidad esperada por SOFA) ────
        $criticidadPorSub = $ultimosSnaps
            ->filter(fn($s) => $s->subunidad)
            ->groupBy('subunidad')
            ->map(function ($snaps) {
                $newsVals  = $snaps->map(fn($s) => self::extraerNumero($s->news))->filter(fn($v) => $v !== null);
                $sofaVals  = $snaps->map(fn($s) => self::extraerNumero($s->sofa))->filter(fn($v) => $v !== null);
                $total     = $snaps->count();

                $avgNews   = $newsVals->isNotEmpty() ? round($newsVals->avg(), 1) : null;
                $avgSofa   = $sofaVals->isNotEmpty() ? round($sofaVals->avg(), 1) : null;
                $pctNews5  = $total > 0 ? round($newsVals->filter(fn($v) => $v >= 5)->count() / $total * 100, 1) : 0;
                $pctSofa10 = $total > 0 ? round($sofaVals->filter(fn($v) => $v >= 10)->count() / $total * 100, 1) : 0;

                // Mortalidad esperada por SOFA para esta subunidad
                $mortalidadEsperadaSub = null;
                if ($sofaVals->isNotEmpty()) {
                    $suma = $sofaVals->sum(fn($sofa) => self::sofaMortalidad($sofa));
                    $mortalidadEsperadaSub = round($suma / $sofaVals->count(), 1);
                }

                // Índice de criticidad: promedio normalizado de NEWS (máx≈10) + SOFA (máx≈24)
                $idxNews = $avgNews !== null ? ($avgNews / 10) * 50 : 0;
                $idxSofa = $avgSofa !== null ? ($avgSofa / 24) * 50 : 0;
                $indiceCriticidad = round($idxNews + $idxSofa, 1);

                return compact('total', 'avgNews', 'avgSofa', 'pctNews5', 'pctSofa10', 'indiceCriticidad', 'mortalidadEsperadaSub');
            })
            ->sortByDesc('indiceCriticidad');

        // ── 9. EVOLUCIÓN MORTALIDAD MENSUAL (últimos 6 meses) ────────────────
        $mortalidadMensual = Paciente::whereNotNull('egreso_uci')
            ->whereNotNull('tipo_egreso')
            ->where('egreso_uci', '>=', now()->subMonths(6))
            ->get()
            ->groupBy(fn($p) => $p->egreso_uci->format('Y-m'))
            ->map(fn($g) => [
                'mes'        => $g->first()->egreso_uci->format('M Y'),
                'total'      => $g->count(),
                'fallecidos' => $g->where('tipo_egreso', 'fallecimiento')->count(),
                'tasa'       => $g->count() > 0
                    ? round($g->where('tipo_egreso', 'fallecimiento')->count() / $g->count() * 100, 1)
                    : 0,
            ])
            ->sortKeys()
            ->values();

        // ── 10. ESTADÍSTICOS RÁPIDOS ──────────────────────────────────────────
        $estadisticos = [
            'totalPacientes'   => $todosPacientes->count(),
            'totalActivos'     => $todosPacientes->where('activo', true)->count(),
            'totalEgresados'   => $totalEgresados,
            'edadPromedio'     => round($todosPacientes->avg('edad'), 1),
            'edadMediana'      => $this->mediana($todosPacientes->pluck('edad')),
            'estanciaPromedio' => round($todosPacientes->whereNotNull('ingreso_uci')
                ->avg(fn($p) => $p->diasEnUci()), 1),
        ];

        return view('epidemiologia.index', compact(
            'gruposEdad', 'porSexo', 'cie10Raw', 'topEspecialidades', 'topEapb',
            'rangosEstancia', 'fallecidos', 'traslados', 'mejoria',
            'totalEgresados', 'mortalidadBruta', 'mortalidadEsperada',
            'criticidadPorSub', 'mortalidadMensual', 'estadisticos'
        ));
    }

    private function mediana($coleccion): float
    {
        $vals = $coleccion->filter()->sort()->values();
        $n    = $vals->count();
        if ($n === 0) return 0;
        $mid  = (int)($n / 2);
        return $n % 2 === 0 ? round(($vals[$mid - 1] + $vals[$mid]) / 2, 1) : (float)$vals[$mid];
    }
}
