<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CargaArchivo;
use App\Models\TransfusionDiaria;
use App\Models\CamUci;
use App\Models\UnidadUci;
use App\Models\BalanceHidrico;
use App\Models\Dispositivo;
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

        // Ãšltimos snapshots de pacientes activos
        $sub = Snapshot::subqueryUltimoPorPaciente();
        $snapshots = Snapshot::joinSub($sub, 'lt', fn($j) => $j->on('snapshots.id', '=', 'lt.snap_id'))
            ->join('pacientes', 'pacientes.id', '=', 'snapshots.paciente_id')
            ->where('pacientes.activo', true)
            ->select('snapshots.*', 'pacientes.salida_hospitalizacion', 'pacientes.egreso_uci')
            ->get();

        // â”€â”€ Alertas clÃ­nicas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

        // â”€â”€ EstadÃ­sticas de distribuciÃ³n â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $porCriterio     = $snapshots->groupBy('criterio_atencion')->map->count();
        $porSubunidad    = $snapshots->groupBy('subunidad')->map->count()->sortKeys();
        $porVentilatorio = $snapshots->filter(fn($s) => !empty($s->soporte_ventilatorio))
            ->groupBy('soporte_ventilatorio')->map->count();
        $porHemodinamico = $snapshots->filter(fn($s) => !empty($s->soporte_hemodinamico))
            ->groupBy('soporte_hemodinamico')->map->count();
        $clasificarOcupacion = fn($s) => $s->salida_hospitalizacion && !$s->egreso_uci ? 'traslado'
            : (str_contains(strtoupper($s->criterio_atencion ?? ''), 'INTERMEDIO') || $s->subunidad === 'UCIN' ? 'ucin' : 'uci');
        $porSubunidadDetalle = $snapshots->groupBy('subunidad')->map(function ($grupo) use ($clasificarOcupacion) {
            return $grupo->reduce(function ($totales, $snapshot) use ($clasificarOcupacion) {
                $totales[$clasificarOcupacion($snapshot)]++;
                return $totales;
            }, ['uci' => 0, 'ucin' => 0, 'traslado' => 0]);
        });
        $desgloseOcupacion = $snapshots->reduce(function ($totales, $snapshot) use ($clasificarOcupacion) {
            $totales[$clasificarOcupacion($snapshot)]++;
            return $totales;
        }, ['uci' => 0, 'ucin' => 0, 'traslado' => 0]);

        // â”€â”€ Espera larga (> 4h) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $pacientesEsperaLarga = Paciente::whereNotNull('salida_hospitalizacion')
            ->whereNull('egreso_uci')->where('activo', true)
            // Se filtra en la base de datos: evita cargar pacientes que todavÃ­a
            // no cumplen cuatro horas de espera solo para descartarlos en PHP.
            ->where('salida_hospitalizacion', '<=', now()->subHours(4))
            ->with('ultimoSnapshot')
            ->orderBy('salida_hospitalizacion')
            ->get();

        // â”€â”€ Capacidades â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $unidades = UnidadUci::with('indisponibilidades')->orderBy('cama_desde')->get();
        $capacidades = $unidades->mapWithKeys(fn($u) => [$u->nombre => $u->capacidadDisponibleEn(today())])->all();
        $totalCamas = array_sum($capacidades);

        // â”€â”€ Promedios escalas â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $escalaAvg = fn(string $campo) => ($v = $snapshots->whereNotNull($campo)->avg($campo)) !== null
            ? round((float)$v, 1) : null;
        $promedios = [
            'NEWS'    => $escalaAvg('news'),
            'BARTHEL' => $escalaAvg('barthel'),
            'RASS'    => $escalaAvg('rass'),
            'EVA'     => $escalaAvg('eva'),
            'BPS'     => $escalaAvg('bps'),
        ];

        // â”€â”€ MovilizaciÃ³n temprana â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $movilizacion = [
            'temprana' => $snapshots->filter(fn($s) => str_contains($s->movilizacion ?? '', '< 48')
                || str_contains(strtolower($s->movilizacion ?? ''), 'precoz'))->count(),
            'tardia'   => $snapshots->filter(fn($s) => str_contains($s->movilizacion ?? '', '> 48')
                || str_contains(strtolower($s->movilizacion ?? ''), 'tardÃ­a'))->count(),
            'sin_dato' => $snapshots->filter(fn($s) => empty($s->movilizacion))->count(),
        ];

        // â”€â”€ OcupaciÃ³n histÃ³rica â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $inicioHistorico = now()->subDays(30)->startOfDay();
        $ocupacionHistorica = Snapshot::join('cargas_archivo', 'cargas_archivo.id', '=', 'snapshots.carga_id')
            // fecha_snapshot es siempre el corte de medianoche; no usar DATE()
            // permite que SQLite/PostgreSQL aprovechen el Ã­ndice por fecha.
            ->where('snapshots.fecha_snapshot', '>=', $inicioHistorico->toDateString())
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

        // â”€â”€ VMI y vasopresor activos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

        // â”€â”€ KPIs clÃ­nicos (Ãºltimos 30 dÃ­as) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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

        // â”€â”€ DistribuciÃ³n de riesgos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $categoriesRiesgo = [
            'CaÃ­da'            => ['caÃ­da','caida'],
            'UPP'              => ['upp','Ãºlcera presiÃ³n','ulcera presion','Ãºlcera por presiÃ³n','ulcera por presion'],
            'TVP / Trombosis'  => ['tvp','trombosis','trombo'],
            'InfecciÃ³n / IAAS' => ['infecciÃ³n','infeccion','iaas'],
            'Delirium'         => ['delirium','delirio'],
            'BroncoaspiraciÃ³n' => ['broncoaspiraciÃ³n','broncoaspiracion','broncoaspir'],
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

        // â”€â”€ CAM-UCI hoy â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $camRegistrosHoy    = CamUci::whereDate('fecha', today())->with('paciente')->get();
        $camPositivosHoy    = $camRegistrosHoy->where('resultado', 'positivo');
        $camTotalHoy        = $camRegistrosHoy->count();
        $camPctHoy          = $camTotalHoy > 0 ? round($camPositivosHoy->count() / $camTotalHoy * 100, 1) : 0;

        // â”€â”€ Transfusiones â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $transfusionesHoy         = TransfusionDiaria::whereDate('fecha', today())->count();
        $transfusionesSemana      = TransfusionDiaria::where('fecha', '>=', now()->subDays(7))->count();

        // â”€â”€ Alertas proactivas de nuevos mÃ³dulos â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        $pacientesActivos = Paciente::where('activo', true)->whereNotNull('ingreso_uci')->pluck('id');

        // Pacientes sin CAM-UCI hoy
        $conCamHoy = CamUci::whereDate('fecha', today())->pluck('paciente_id');
        $sinCamHoyIds = $pacientesActivos->diff($conCamHoy);

        // Balance hÃ­drico: positivo > 1000 mL hoy
        $balancePositivoAlto = BalanceHidrico::whereDate('fecha', today())
            ->whereIn('paciente_id', $pacientesActivos)
            ->get()
            ->filter(fn($b) => $b->balance() > 1000)
            ->map(fn($b) => ['balance' => $b, 'paciente' => Paciente::find($b->paciente_id)]);

        // IAAS registradas en los Ãºltimos 7 dÃ­as
        $iaasRecientes = Dispositivo::where('evento_iaas', true)
            ->where('updated_at', '>=', now()->subDays(7))
            ->with('paciente')
            ->orderByDesc('updated_at')
            ->get();

        // Pacientes sin Goal of Care registrado
        $sinGocIds = $pacientesActivos->diff(
            DB::table('goals_of_care')->whereIn('paciente_id', $pacientesActivos)->pluck('paciente_id')
        );
        $sinGocCount = $sinGocIds->count();

        // â”€â”€ Soporte prolongado > 2 dÃ­as â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
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
            'porCriterio', 'porSubunidad', 'porSubunidadDetalle', 'porVentilatorio', 'porHemodinamico', 'desgloseOcupacion',
            'pacientesEsperaLarga', 'capacidades', 'unidades', 'promedios', 'movilizacion',
            'alertasNews', 'alertasSofa', 'alertasDolor',
            'ocupacionHistorica', 'conVmiActivo', 'conVasopresorActivo',
            'pacientesSoporteProlongado',
            'mortalidadCruda', 'estanciaMedia', 'ocupacionPct', 'giroCama',
            'ratioVmUci', 'totalEgresos', 'totalCamas',
            'porRiesgo',
            'transfusionesHoy', 'transfusionesSemana',
            'camPositivosHoy', 'camTotalHoy', 'camPctHoy',
            'sinCamHoyIds', 'balancePositivoAlto', 'iaasRecientes', 'sinGocCount'
        ));
    }
}
