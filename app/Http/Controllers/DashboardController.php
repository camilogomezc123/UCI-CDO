<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CargaArchivo;
use App\Models\TransfusionDiaria;
use App\Models\CamUci;
use App\Models\UnidadUci;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $ultimaCarga  = CargaArchivo::latest()->first();
        $cargaHoy     = CargaArchivo::whereDate('created_at', today())->exists();
        $totalActivos = Paciente::where('activo', true)->count();

        $pendientesEgreso = Paciente::whereNotNull('salida_hospitalizacion')
            ->whereNull('egreso_uci')->where('activo', true)->count();

        // Últimos snapshots de pacientes activos
        $sub = Snapshot::subqueryUltimoPorPaciente();
        $snapshots = Snapshot::joinSub($sub, 'lt', fn($j) => $j->on('snapshots.id', '=', 'lt.snap_id'))
            ->join('pacientes', 'pacientes.id', '=', 'snapshots.paciente_id')
            ->where('pacientes.activo', true)
            ->select('snapshots.*')
            ->get();

        // ── Alertas clínicas ──────────────────────────────────────────────────────
        $alertasNews = $snapshots->filter(fn($s) => $s->news !== null && (float)$s->news >= 5)
            ->sortByDesc('news');

        $alertasSofa = $snapshots->filter(function ($s) {
            if (!$s->sofa) return false;
            preg_match('/(\d+)/', $s->sofa, $m);
            return isset($m[1]) && (int)$m[1] >= 10;
        });

        // Alerta dolor: EVA > 4 o BPS > 6
        $alertasDolor = $snapshots->filter(fn($s) =>
            ($s->eva !== null && (float)$s->eva > 4) ||
            ($s->bps !== null && (float)$s->bps > 6)
        )->sortByDesc(fn($s) => max((float)($s->eva ?? 0), (float)($s->bps ?? 0)));

        // ── Estadísticas de distribución ─────────────────────────────────────────
        $porCriterio     = $snapshots->groupBy('criterio_atencion')->map->count();
        $porSubunidad    = $snapshots->groupBy('subunidad')->map->count()->sortKeys();
        $porVentilatorio = $snapshots->filter(fn($s) => !empty($s->soporte_ventilatorio))
            ->groupBy('soporte_ventilatorio')->map->count();
        $porHemodinamico = $snapshots->filter(fn($s) => !empty($s->soporte_hemodinamico))
            ->groupBy('soporte_hemodinamico')->map->count();
        $desgloseOcupacion = [
            'uci' => $snapshots->filter(fn($s) => str_contains(strtoupper($s->criterio_atencion ?? ''), 'INTENSIVO'))->count(),
            'ucin' => $snapshots->where('subunidad', 'UCIN')->count(),
            'hospitalizados' => $snapshots->filter(fn($s) => str_contains(strtoupper($s->criterio_atencion ?? ''), 'HOSP') || str_contains(strtoupper($s->criterio_atencion ?? ''), 'ALTA'))->count(),
        ];

        // ── Espera larga (> 4h) ───────────────────────────────────────────────────
        $pacientesEsperaLarga = Paciente::whereNotNull('salida_hospitalizacion')
            ->whereNull('egreso_uci')->where('activo', true)
            ->with('ultimoSnapshot')->get()
            ->filter(fn($p) => $p->tiempoEsperaHoras() > 4)
            ->sortByDesc(fn($p) => $p->tiempoEsperaHoras());

        // ── Capacidades ───────────────────────────────────────────────────────────
        $unidades = UnidadUci::with('indisponibilidades')->orderBy('cama_desde')->get();
        $capacidades = $unidades->mapWithKeys(fn($u) => [$u->nombre => $u->capacidadDisponibleEn(today())])->all();
        $totalCamas = array_sum($capacidades);

        // ── Promedios escalas ─────────────────────────────────────────────────────
        $escalaAvg = fn(string $campo) => ($v = $snapshots->whereNotNull($campo)->avg($campo)) !== null
            ? round((float)$v, 1) : null;
        $promedios = [
            'NEWS'    => $escalaAvg('news'),
            'BARTHEL' => $escalaAvg('barthel'),
            'RASS'    => $escalaAvg('rass'),
            'EVA'     => $escalaAvg('eva'),
            'BPS'     => $escalaAvg('bps'),
        ];

        // ── Movilización temprana ─────────────────────────────────────────────────
        $movilizacion = [
            'temprana' => $snapshots->filter(fn($s) => str_contains($s->movilizacion ?? '', '< 48')
                || str_contains(strtolower($s->movilizacion ?? ''), 'precoz'))->count(),
            'tardia'   => $snapshots->filter(fn($s) => str_contains($s->movilizacion ?? '', '> 48')
                || str_contains(strtolower($s->movilizacion ?? ''), 'tardía'))->count(),
            'sin_dato' => $snapshots->filter(fn($s) => empty($s->movilizacion))->count(),
        ];

        // ── Ocupación histórica ───────────────────────────────────────────────────
        $inicioHistorico = now()->subDays(30)->startOfDay();
        $ocupacionHistorica = Snapshot::join('cargas_archivo', 'cargas_archivo.id', '=', 'snapshots.carga_id')
            ->whereDate('snapshots.fecha_snapshot', '>=', $inicioHistorico->toDateString())
            ->get(['snapshots.fecha_snapshot', 'snapshots.paciente_id', 'snapshots.subunidad', 'cargas_archivo.created_at'])
            ->groupBy(fn($s) => Carbon::parse($s->fecha_snapshot)->toDateString())
            ->map(function ($snapshotsDia, $fecha) use ($unidades) {
                $subunidadesEsperadas = $unidades->filter(fn($u) => $u->nombre !== 'UCIN' && $u->capacidadDisponibleEn($fecha) > 0)->pluck('nombre')->all();
                $subunidades = $snapshotsDia->pluck('subunidad')->filter()->unique()->values()->all();
                $faltantes = array_values(array_diff($subunidadesEsperadas, $subunidades));
                $ultimaCarga = $snapshotsDia->max('created_at');

                return [
                    'fecha'       => $fecha,
                    'total'       => $snapshotsDia->pluck('paciente_id')->unique()->count(),
                    'medido'      => $snapshotsDia->pluck('paciente_id')->unique()->count(),
                    'hora_carga'  => Carbon::parse($ultimaCarga)->format('H:i'),
                    'faltantes'   => $faltantes,
                    'confiable'   => empty($faltantes),
                    'estimado'    => false,
                    'capacidad'   => $unidades->sum(fn($u) => $u->capacidadDisponibleEn($fecha)),
                ];
            })
            ->sortBy('fecha')
            ->values();

        $ingresos = Paciente::whereBetween('ingreso_uci', [$inicioHistorico, now()->endOfDay()])
            ->selectRaw('DATE(ingreso_uci) as fecha, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(ingreso_uci)'))
            ->pluck('total', 'fecha');
        $egresos = Paciente::whereBetween('egreso_uci', [$inicioHistorico, now()->endOfDay()])
            ->selectRaw('DATE(egreso_uci) as fecha, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(egreso_uci)'))
            ->pluck('total', 'fecha');

        $ocupacionHistorica = $ocupacionHistorica->all();
        foreach ($ocupacionHistorica as $indice => &$dia) {
            $anterior = $ocupacionHistorica[$indice - 1] ?? null;
            if (!$dia['confiable'] && $anterior && $anterior['confiable']) {
                $dia['total'] = max(0, $anterior['total'] + ($ingresos[$dia['fecha']] ?? 0) - ($egresos[$dia['fecha']] ?? 0));
                $dia['estimado'] = true;
            }
        }
        unset($dia);
        $ocupacionHistorica = collect($ocupacionHistorica);

        // ── VMI y vasopresor activos ──────────────────────────────────────────────
        $conVmiActivo = $snapshots->filter(fn($s) =>
            !empty($s->soporte_ventilatorio) &&
            (str_contains(strtolower($s->soporte_ventilatorio), 'vmi') ||
             str_contains(strtolower($s->soporte_ventilatorio), 'invasiv'))
        )->count();

        $conVasopresorActivo = $snapshots->filter(fn($s) =>
            !empty($s->soporte_hemodinamico) &&
            (str_contains(strtolower($s->soporte_hemodinamico), 'vasopresor') ||
             str_contains(strtolower($s->soporte_hemodinamico), 'norepinefrina'))
        )->count();

        // ── KPIs clínicos (últimos 30 días) ──────────────────────────────────────
        $fechaInicio = now()->subDays(30);
        $egresosRecientes = Paciente::whereNotNull('egreso_uci')
            ->where('egreso_uci', '>=', $fechaInicio)->get();

        $totalEgresos    = $egresosRecientes->count();
        $fallecidos      = $egresosRecientes->where('tipo_egreso', 'fallecimiento')->count();
        $mortalidadCruda = $totalEgresos > 0 ? round($fallecidos / $totalEgresos * 100, 1) : null;

        $estanciaMedia = $egresosRecientes
            ->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)
            ->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci));
        $estanciaMedia = $estanciaMedia ? round($estanciaMedia, 1) : null;

        $ocupacionPct = $totalCamas > 0 ? round($totalActivos / $totalCamas * 100) : null;
        $giroCama     = $totalCamas > 0 ? round($totalEgresos / $totalCamas, 1) : null;
        $ratioVmUci   = $totalActivos > 0 ? round($conVmiActivo / $totalActivos * 100) : null;

        // ── Distribución de riesgos ───────────────────────────────────────────────
        $categoriesRiesgo = [
            'Caída'            => ['caída','caida'],
            'UPP'              => ['upp','úlcera presión','ulcera presion','úlcera por presión','ulcera por presion'],
            'TVP / Trombosis'  => ['tvp','trombosis','trombo'],
            'Infección / IAAS' => ['infección','infeccion','iaas'],
            'Delirium'         => ['delirium','delirio'],
            'Broncoaspiración' => ['broncoaspiración','broncoaspiracion','broncoaspir'],
            'Flebitis'         => ['flebitis','flebit'],
            'Hemorragia'       => ['hemorragia','sangrado'],
        ];

        $porRiesgo = [];
        foreach ($categoriesRiesgo as $nombre => $keywords) {
            $count = $snapshots->filter(fn($s) =>
                !empty($s->riesgos) &&
                collect($keywords)->contains(fn($k) => str_contains(strtolower($s->riesgos), $k))
            )->count();
            if ($count > 0) {
                $porRiesgo[$nombre] = [
                    'count' => $count,
                    'pct'   => $totalActivos > 0 ? round($count / $totalActivos * 100) : 0,
                ];
            }
        }
        uasort($porRiesgo, fn($a, $b) => $b['count'] - $a['count']);

        // ── CAM-UCI hoy ───────────────────────────────────────────────────────────
        $camRegistrosHoy    = CamUci::whereDate('fecha', today())->with('paciente')->get();
        $camPositivosHoy    = $camRegistrosHoy->where('resultado', 'positivo');
        $camTotalHoy        = $camRegistrosHoy->count();
        $camPctHoy          = $camTotalHoy > 0 ? round($camPositivosHoy->count() / $camTotalHoy * 100, 1) : 0;

        // ── Transfusiones ─────────────────────────────────────────────────────────
        $transfusionesHoy         = TransfusionDiaria::whereDate('fecha', today())->count();
        $transfusionesSemana      = TransfusionDiaria::where('fecha', '>=', now()->subDays(7))->count();

        // ── Soporte prolongado > 2 días ───────────────────────────────────────────
        $subSql = "
            SELECT p.id, p.nombre, p.documento,
                COUNT(DISTINCT CASE WHEN s.soporte_ventilatorio LIKE '%VMI%'
                    OR s.soporte_ventilatorio LIKE '%invasiv%'
                    OR s.soporte_ventilatorio LIKE '%mecanic%'
                    THEN s.fecha_snapshot END) AS dias_vmi,
                COUNT(DISTINCT CASE WHEN s.soporte_hemodinamico LIKE '%vasopresor%'
                    OR s.soporte_hemodinamico LIKE '%norepinefrina%'
                    OR s.soporte_hemodinamico LIKE '%adrenalina%'
                    OR s.soporte_hemodinamico LIKE '%vasopresina%'
                    THEN s.fecha_snapshot END) AS dias_vasopresor,
                COUNT(DISTINCT CASE WHEN s.soporte_hemodinamico LIKE '%inotr%'
                    OR s.soporte_hemodinamico LIKE '%dobutamina%'
                    OR s.soporte_hemodinamico LIKE '%milrinona%'
                    OR s.soporte_hemodinamico LIKE '%levosimendan%'
                    THEN s.fecha_snapshot END) AS dias_inotropico,
                COUNT(DISTINCT CASE WHEN s.soporte_hemodinamico LIKE '%amiodar%'
                    OR s.soporte_hemodinamico LIKE '%antiarr%'
                    OR s.soporte_hemodinamico LIKE '%lidocain%'
                    OR s.soporte_hemodinamico LIKE '%propafenon%'
                    OR s.soporte_hemodinamico LIKE '%digoxin%'
                    THEN s.fecha_snapshot END) AS dias_antiarritmico
            FROM pacientes p
            INNER JOIN snapshots s ON s.paciente_id = p.id
            WHERE p.activo = 1
            GROUP BY p.id, p.nombre, p.documento
        ";

        $pacientesSoporteProlongado = DB::table(DB::raw("($subSql) AS sub"))
            ->where(fn($q) => $q
                ->where('dias_vmi', '>', 2)
                ->orWhere('dias_vasopresor', '>', 2)
                ->orWhere('dias_inotropico', '>', 2)
                ->orWhere('dias_antiarritmico', '>', 2))
            ->orderByRaw('(dias_vmi + dias_vasopresor + dias_inotropico + dias_antiarritmico) DESC')
            ->get();

        return view('dashboard.index', compact(
            'totalActivos', 'pendientesEgreso', 'ultimaCarga', 'cargaHoy',
            'porCriterio', 'porSubunidad', 'porVentilatorio', 'porHemodinamico', 'desgloseOcupacion',
            'pacientesEsperaLarga', 'capacidades', 'unidades', 'promedios', 'movilizacion',
            'alertasNews', 'alertasSofa', 'alertasDolor',
            'ocupacionHistorica', 'conVmiActivo', 'conVasopresorActivo',
            'pacientesSoporteProlongado',
            'mortalidadCruda', 'estanciaMedia', 'ocupacionPct', 'giroCama',
            'ratioVmUci', 'totalEgresos', 'totalCamas',
            'porRiesgo',
            'transfusionesHoy', 'transfusionesSemana',
            'camPositivosHoy', 'camTotalHoy', 'camPctHoy'
        ));
    }
}
