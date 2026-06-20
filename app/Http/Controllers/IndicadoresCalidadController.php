<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\CamUci;
use App\Models\Snapshot;
use App\Models\Trazador;
use Carbon\Carbon;
use Illuminate\Http\Request;

class IndicadoresCalidadController extends Controller
{
    // ── Definición completa de indicadores ───────────────────────────────────
    // Fuentes: SEMICYUC 2017, IHI, SCCM/ESICM, JCI
    public const DEFINICIONES = [

        // ─── CAT 1: SEGURIDAD / MORTALIDAD ───────────────────────────────────
        'IND-01' => [
            'categoria'  => 'Seguridad',
            'nombre'     => 'Mortalidad bruta UCI',
            'descripcion'=> 'Fallecidos en UCI / total egresados en el período',
            'unidad'     => '%',
            'fuente'     => 'SEMICYUC 2017 · IHI',
            'meta'       => '< 15%',
            'verde'      => [null, 10],
            'amarillo'   => [10, 20],
            'rojo'       => [20, null],
            'icono'      => 'bi-heart-pulse',
            'periodo'    => true,
        ],
        'IND-02' => [
            'categoria'  => 'Seguridad',
            'nombre'     => 'Tasa de reingreso UCI',
            'descripcion'=> 'Reingresos a UCI / total ingresos en el período (segundo ingreso o más)',
            'unidad'     => '%',
            'fuente'     => 'SEMICYUC 2017',
            'meta'       => '< 5%',
            'verde'      => [null, 3],
            'amarillo'   => [3, 6],
            'rojo'       => [6, null],
            'icono'      => 'bi-arrow-repeat',
            'periodo'    => true,
        ],
        'IND-03' => [
            'categoria'  => 'Seguridad',
            'nombre'     => 'Alta a domicilio (desenlace favorable)',
            'descripcion'=> 'Alta a casa / total egresados en el período',
            'unidad'     => '%',
            'fuente'     => 'JCI / IHI',
            'meta'       => '> 40%',
            'verde'      => [50, null],
            'amarillo'   => [30, 50],
            'rojo'       => [null, 30],
            'icono'      => 'bi-house-check',
            'periodo'    => true,
            'invertir'   => true,
        ],

        // ─── CAT 2: EFICIENCIA / FLUJO ────────────────────────────────────────
        'IND-04' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => 'Estancia media UCI',
            'descripcion'=> 'Promedio de días entre ingreso y egreso UCI en el período',
            'unidad'     => 'días',
            'fuente'     => 'SEMICYUC 2017',
            'meta'       => '< 7 días',
            'verde'      => [null, 5],
            'amarillo'   => [5, 9],
            'rojo'       => [9, null],
            'icono'      => 'bi-calendar2-range',
            'periodo'    => true,
        ],
        'IND-05' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => 'Estancias prolongadas (> 7 días)',
            'descripcion'=> 'Porcentaje de ingresos con estancia > 7 días',
            'unidad'     => '%',
            'fuente'     => 'ESICM / SEMICYUC',
            'meta'       => '< 20%',
            'verde'      => [null, 15],
            'amarillo'   => [15, 25],
            'rojo'       => [25, null],
            'icono'      => 'bi-calendar-x',
            'periodo'    => true,
        ],
        'IND-06' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => 'Tiempo espera egreso (actual)',
            'descripcion'=> 'Promedio de horas de espera entre indicación y egreso físico UCI',
            'unidad'     => 'horas',
            'fuente'     => 'JCI / Flujo hospitalario',
            'meta'       => '< 4 h',
            'verde'      => [null, 3],
            'amarillo'   => [3, 8],
            'rojo'       => [8, null],
            'icono'      => 'bi-hourglass-split',
            'periodo'    => false,
        ],
        'IND-07' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => '% Pacientes pendientes de egreso',
            'descripcion'=> 'Pacientes con indicación de salida pero aún en UCI',
            'unidad'     => '%',
            'fuente'     => 'Indicador local UCI',
            'meta'       => '< 10%',
            'verde'      => [null, 8],
            'amarillo'   => [8, 18],
            'rojo'       => [18, null],
            'icono'      => 'bi-person-walking',
            'periodo'    => false,
        ],

        // ─── CAT 3: NEUROLOGÍA / DELIRIUM ─────────────────────────────────────
        'IND-08' => [
            'categoria'  => 'Neurología',
            'nombre'     => 'Tasa de delirium (CAM-ICU hoy)',
            'descripcion'=> 'Pacientes CAM-UCI positivo / total evaluados hoy',
            'unidad'     => '%',
            'fuente'     => 'SCCM ABCDEF Bundle · ICDSC',
            'meta'       => '< 40%',
            'verde'      => [null, 30],
            'amarillo'   => [30, 50],
            'rojo'       => [50, null],
            'icono'      => 'bi-brain',
            'periodo'    => false,
        ],
        'IND-09' => [
            'categoria'  => 'Neurología',
            'nombre'     => 'Cobertura evaluación CAM-UCI',
            'descripcion'=> 'Pacientes activos con CAM-UCI registrado hoy',
            'unidad'     => '%',
            'fuente'     => 'SCCM · SEMICYUC',
            'meta'       => '> 90%',
            'verde'      => [90, null],
            'amarillo'   => [70, 90],
            'rojo'       => [null, 70],
            'icono'      => 'bi-clipboard2-check',
            'periodo'    => false,
            'invertir'   => true,
        ],

        // ─── CAT 4: DOLOR Y SEDACIÓN ──────────────────────────────────────────
        'IND-10' => [
            'categoria'  => 'Dolor y Sedación',
            'nombre'     => 'Dolor no controlado (EVA > 4 / BPS > 6)',
            'descripcion'=> 'Pacientes con dolor fuera de meta / total con evaluación',
            'unidad'     => '%',
            'fuente'     => 'SCCM PAD Guidelines 2018',
            'meta'       => '< 15%',
            'verde'      => [null, 10],
            'amarillo'   => [10, 20],
            'rojo'       => [20, null],
            'icono'      => 'bi-thermometer-high',
            'periodo'    => false,
        ],
        'IND-11' => [
            'categoria'  => 'Dolor y Sedación',
            'nombre'     => 'Sobresedación (RASS < -3)',
            'descripcion'=> 'Pacientes con sedación excesiva fuera de meta terapéutica',
            'unidad'     => '%',
            'fuente'     => 'SCCM PAD Guidelines · Bundle C',
            'meta'       => '< 20%',
            'verde'      => [null, 15],
            'amarillo'   => [15, 25],
            'rojo'       => [25, null],
            'icono'      => 'bi-moon-stars',
            'periodo'    => false,
        ],

        // ─── CAT 5: SOPORTE AVANZADO (informativos) ───────────────────────────
        'IND-12' => [
            'categoria'  => 'Soporte',
            'nombre'     => 'Pacientes en ventilación mecánica',
            'descripcion'=> 'Porcentaje de pacientes activos con soporte ventilatorio',
            'unidad'     => '%',
            'fuente'     => 'Estadística UCI',
            'meta'       => 'Informativo',
            'verde'      => null,
            'amarillo'   => null,
            'rojo'       => null,
            'icono'      => 'bi-lungs',
            'periodo'    => false,
        ],
        'IND-13' => [
            'categoria'  => 'Soporte',
            'nombre'     => 'Pacientes con soporte hemodinámico',
            'descripcion'=> 'Porcentaje de pacientes activos con vasoactivos',
            'unidad'     => '%',
            'fuente'     => 'Estadística UCI',
            'meta'       => 'Informativo',
            'verde'      => null,
            'amarillo'   => null,
            'rojo'       => null,
            'icono'      => 'bi-droplet-half',
            'periodo'    => false,
        ],

        // ─── CAT 6: ALERTAS CLÍNICAS ──────────────────────────────────────────
        'IND-14' => [
            'categoria'  => 'Alertas clínicas',
            'nombre'     => 'Pacientes con NEWS ≥ 5',
            'descripcion'=> 'Pacientes con puntuación NEWS de alerta (≥5) en último registro',
            'unidad'     => '%',
            'fuente'     => 'Royal College of Physicians 2017',
            'meta'       => '< 20%',
            'verde'      => [null, 15],
            'amarillo'   => [15, 30],
            'rojo'       => [30, null],
            'icono'      => 'bi-exclamation-triangle',
            'periodo'    => false,
        ],
        'IND-15' => [
            'categoria'  => 'Alertas clínicas',
            'nombre'     => 'Pacientes con SOFA ≥ 10',
            'descripcion'=> 'Pacientes con falla orgánica múltiple severa (SOFA ≥ 10)',
            'unidad'     => '%',
            'fuente'     => 'ESICM · Singer et al. 2016',
            'meta'       => '< 15%',
            'verde'      => [null, 10],
            'amarillo'   => [10, 20],
            'rojo'       => [20, null],
            'icono'      => 'bi-activity',
            'periodo'    => false,
        ],

        // ─── CAT 7: CALIDAD DE CUIDADO — TRAZADORES ───────────────────────────
        'IND-16' => [
            'categoria'  => 'Calidad trazadores',
            'nombre'     => 'Adherencia Código Sepsis (S1–S7)',
            'descripcion'=> 'Cumplimiento promedio del bundle de reanimación en trazadores Sepsis cerrados',
            'unidad'     => '%',
            'fuente'     => 'SEMICYUC · SSC 2021',
            'meta'       => '≥ 90%',
            'verde'      => [90, null],
            'amarillo'   => [70, 90],
            'rojo'       => [null, 70],
            'icono'      => 'bi-heart-pulse',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-17' => [
            'categoria'  => 'Calidad trazadores',
            'nombre'     => 'Adherencia Bundle ABCDEF',
            'descripcion'=> 'Cumplimiento promedio del bundle ABCDEF en trazadores Sepsis cerrados',
            'unidad'     => '%',
            'fuente'     => 'SCCM ICU Liberation Bundle',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-list-check',
            'periodo'    => false,
            'invertir'   => true,
        ],
    ];

    // ── Controlador principal ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $dias  = (int) $request->input('dias', 30);
        $dias  = in_array($dias, [7, 30, 60, 90, 180]) ? $dias : 30;
        $desde = now()->subDays($dias)->startOfDay();
        $hasta = now()->endOfDay();

        $resultados = $this->calcularTodos($desde, $hasta);
        $tendencia  = $this->tendencia6meses();
        $resumen    = $this->resumenSemaforo($resultados);

        return view('indicadores.index', compact(
            'resultados', 'tendencia', 'resumen', 'dias', 'desde', 'hasta'
        ));
    }

    // ── Cálculo de todos los indicadores ─────────────────────────────────────

    private function calcularTodos(Carbon $desde, Carbon $hasta): array
    {
        // Egresados en el período
        $egresados    = Paciente::whereNotNull('egreso_uci')->whereBetween('egreso_uci', [$desde, $hasta])->get();
        $totalEgr     = $egresados->count();
        $conEstancia  = $egresados->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci);

        // Ingresos en el período
        $ingresosPer  = Paciente::whereBetween('ingreso_uci', [$desde, $hasta])->count();
        $reingresosPer= Paciente::where('numero_ingresos', '>', 1)->whereBetween('ingreso_uci', [$desde, $hasta])->count();

        // Activos actuales con último snapshot
        $activos      = Paciente::where('activo', true)->with('ultimoSnapshot')->get();
        $totalActivos = $activos->count();

        // CAM-UCI hoy
        $camHoy       = CamUci::whereDate('fecha', today())->get();
        $camEvaluados = $camHoy->whereIn('resultado', ['positivo', 'negativo'])->count();
        $camPos       = $camHoy->where('resultado', 'positivo')->count();

        // Snapshots actuales para scores clínicos
        $conNews   = $activos->filter(fn($p) => $p->ultimoSnapshot?->news !== null);
        $conSofa   = $activos->filter(fn($p) => $p->ultimoSnapshot?->sofa !== null);
        $conRass   = $activos->filter(fn($p) => $p->ultimoSnapshot?->rass !== null);
        $conDolor  = $activos->filter(fn($p) =>
            $p->ultimoSnapshot?->eva !== null || $p->ultimoSnapshot?->bps !== null
        );

        $toNum = fn($v) => is_numeric($v) ? (float)$v
            : (preg_match('/([-]?\d+(?:[.,]\d+)?)/', str_replace(',', '.', (string)$v), $m) ? (float)$m[1] : null);

        // Pendientes de egreso
        $pendientesEgr = $activos->filter(fn($p) => $p->salida_hospitalizacion && !$p->egreso_uci)->count();

        // Espera egreso actual
        $esperaHoras = $activos
            ->filter(fn($p) => $p->salida_hospitalizacion && !$p->egreso_uci && $p->activo)
            ->map(fn($p) => $p->salida_hospitalizacion->diffInMinutes(now()) / 60)
            ->values();

        // Trazadores Sepsis cerrados
        $sepsisTotal = Trazador::cerrados()->where('tipo_trazador', 'sepsis')->get();

        // ── Valores por indicador ─────────────────────────────────────────────
        $vals = [];

        // IND-01: Mortalidad bruta
        $fall = $egresados->where('tipo_egreso', 'fallecimiento')->count();
        $vals['IND-01'] = $totalEgr > 0 ? round($fall / $totalEgr * 100, 1) : null;

        // IND-02: Reingreso
        $vals['IND-02'] = $ingresosPer > 0 ? round($reingresosPer / $ingresosPer * 100, 1) : null;

        // IND-03: Alta a domicilio
        $altaCasa = $egresados->where('tipo_egreso', 'alta_casa')->count();
        $vals['IND-03'] = $totalEgr > 0 ? round($altaCasa / $totalEgr * 100, 1) : null;

        // IND-04: Estancia media UCI
        $vals['IND-04'] = $conEstancia->isNotEmpty()
            ? round($conEstancia->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)), 1)
            : null;

        // IND-05: Estancias prolongadas > 7 días
        $prolongadas = $conEstancia->filter(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci) > 7)->count();
        $vals['IND-05'] = $conEstancia->isNotEmpty()
            ? round($prolongadas / $conEstancia->count() * 100, 1)
            : null;

        // IND-06: Tiempo espera egreso
        $vals['IND-06'] = $esperaHoras->isNotEmpty() ? round($esperaHoras->avg(), 1) : null;

        // IND-07: % Pendientes de egreso
        $vals['IND-07'] = $totalActivos > 0 ? round($pendientesEgr / $totalActivos * 100, 1) : null;

        // IND-08: Tasa de delirium
        $vals['IND-08'] = $camEvaluados > 0 ? round($camPos / $camEvaluados * 100, 1) : null;

        // IND-09: Cobertura CAM-UCI
        $vals['IND-09'] = $totalActivos > 0 ? round($camHoy->count() / $totalActivos * 100, 1) : null;

        // IND-10: Dolor no controlado
        $dolorFueraMeta = $conDolor->filter(
            fn($p) => ($p->ultimoSnapshot?->eva !== null && (float)$p->ultimoSnapshot->eva > 4)
                   || ($p->ultimoSnapshot?->bps !== null && (float)$p->ultimoSnapshot->bps > 6)
        )->count();
        $vals['IND-10'] = $conDolor->isNotEmpty() ? round($dolorFueraMeta / $conDolor->count() * 100, 1) : null;

        // IND-11: Sobresedación RASS < -3
        $sobreSed = $conRass->filter(fn($p) => ($p->ultimoSnapshot?->rass ?? 0) < -3)->count();
        $vals['IND-11'] = $conRass->isNotEmpty() ? round($sobreSed / $conRass->count() * 100, 1) : null;

        // IND-12: % Ventilación mecánica
        $vent = $activos->filter(fn($p) => !empty($p->ultimoSnapshot?->soporte_ventilatorio))->count();
        $vals['IND-12'] = $totalActivos > 0 ? round($vent / $totalActivos * 100, 1) : null;

        // IND-13: % Soporte hemodinámico
        $hemo = $activos->filter(fn($p) => !empty($p->ultimoSnapshot?->soporte_hemodinamico))->count();
        $vals['IND-13'] = $totalActivos > 0 ? round($hemo / $totalActivos * 100, 1) : null;

        // IND-14: NEWS ≥ 5
        $newsAlerta = $conNews->filter(fn($p) => (float)($p->ultimoSnapshot?->news ?? 0) >= 5)->count();
        $vals['IND-14'] = $conNews->isNotEmpty() ? round($newsAlerta / $conNews->count() * 100, 1) : null;

        // IND-15: SOFA ≥ 10
        $sofaAlerta = $conSofa->filter(function ($p) use ($toNum) {
            $v = $toNum($p->ultimoSnapshot?->sofa);
            return $v !== null && $v >= 10;
        })->count();
        $vals['IND-15'] = $conSofa->isNotEmpty() ? round($sofaAlerta / $conSofa->count() * 100, 1) : null;

        // IND-16: Adherencia Código Sepsis
        $reaProm = $sepsisTotal->avg(fn($t) => $t->resultados['adherencia_reanimacion_pct'] ?? null);
        $vals['IND-16'] = $reaProm !== null ? round($reaProm, 1) : null;

        // IND-17: Adherencia Bundle ABCDEF
        $abProm = $sepsisTotal->avg(fn($t) => $t->resultados['adherencia_abcdef_pct'] ?? null);
        $vals['IND-17'] = $abProm !== null ? round($abProm, 1) : null;

        // ── Construir resultado con semáforo ─────────────────────────────────
        $resultado = [];
        foreach (self::DEFINICIONES as $cod => $def) {
            $valor = $vals[$cod] ?? null;
            $resultado[$cod] = array_merge($def, [
                'codigo'   => $cod,
                'valor'    => $valor,
                'semaforo' => $this->semaforo($valor, $def),
                'aux'      => $this->aux($cod, $activos, $egresados, $camHoy, $sepsisTotal, $ingresosPer, $reingresosPer),
            ]);
        }

        return $resultado;
    }

    // ── Datos auxiliares contextuales por indicador ───────────────────────────
    private function aux(string $cod, $activos, $egresados, $camHoy, $sepsisTotal, int $ingresosPer, int $reingresosPer): array
    {
        $totalActivos = $activos->count();
        $totalEgr     = $egresados->count();

        return match($cod) {
            'IND-01' => ['n' => $egresados->where('tipo_egreso', 'fallecimiento')->count(), 'd' => $totalEgr],
            'IND-02' => ['n' => $reingresosPer, 'd' => $ingresosPer],
            'IND-03' => ['n' => $egresados->where('tipo_egreso', 'alta_casa')->count(), 'd' => $totalEgr],
            'IND-05' => ['n' => $egresados->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci && $p->ingreso_uci->diffInDays($p->egreso_uci) > 7)->count(),
                         'd' => $egresados->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)->count()],
            'IND-07' => ['n' => $activos->filter(fn($p) => $p->salida_hospitalizacion && !$p->egreso_uci)->count(), 'd' => $totalActivos],
            'IND-08' => ['n' => $camHoy->where('resultado','positivo')->count(),
                         'd' => $camHoy->whereIn('resultado',['positivo','negativo'])->count()],
            'IND-09' => ['n' => $camHoy->count(), 'd' => $totalActivos],
            'IND-16' => ['n' => $sepsisTotal->count(), 'd' => null],
            'IND-17' => ['n' => $sepsisTotal->count(), 'd' => null],
            default   => [],
        };
    }

    // ── Clasificación semáforo ────────────────────────────────────────────────
    private function semaforo(?float $valor, array $def): string
    {
        if ($valor === null) return 'sin_dato';
        if ($def['verde'] === null && $def['amarillo'] === null && $def['rojo'] === null) return 'informativo';

        $invertir = $def['invertir'] ?? false;

        foreach (['verde', 'amarillo', 'rojo'] as $nivel) {
            [$min, $max] = $def[$nivel];
            $enRango = ($min === null || $valor >= $min) && ($max === null || $valor < $max);
            if ($enRango) return $nivel;
        }
        return 'sin_dato';
    }

    // ── Resumen global del semáforo ───────────────────────────────────────────
    private function resumenSemaforo(array $resultados): array
    {
        $counts = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0, 'sin_dato' => 0, 'informativo' => 0];
        foreach ($resultados as $r) {
            $counts[$r['semaforo']] = ($counts[$r['semaforo']] ?? 0) + 1;
        }
        $evaluados = $counts['verde'] + $counts['amarillo'] + $counts['rojo'];
        $counts['pct_verde'] = $evaluados > 0 ? round($counts['verde'] / $evaluados * 100) : 0;
        return $counts;
    }

    // ── Tendencia mensual últimos 6 meses ─────────────────────────────────────
    private function tendencia6meses(): array
    {
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes   = now()->subMonths($i);
            $inicio = $mes->copy()->startOfMonth();
            $fin    = $mes->copy()->endOfMonth();

            $egr  = Paciente::whereNotNull('egreso_uci')->whereBetween('egreso_uci', [$inicio, $fin])->get();
            $totalEgr = $egr->count();

            $meses[] = [
                'label'       => $mes->translatedFormat('M Y'),
                'egresados'   => $totalEgr,
                'fallecidos'  => $egr->where('tipo_egreso', 'fallecimiento')->count(),
                'mortalidad'  => $totalEgr > 0
                    ? round($egr->where('tipo_egreso','fallecimiento')->count() / $totalEgr * 100, 1)
                    : null,
                'estancia_media' => $egr->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)->isNotEmpty()
                    ? round($egr->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)
                        ->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)), 1)
                    : null,
                'reingresos'  => Paciente::where('numero_ingresos', '>', 1)->whereBetween('ingreso_uci', [$inicio, $fin])->count(),
            ];
        }
        return $meses;
    }
}
