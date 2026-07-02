<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Trazador;
use App\Services\ModeloTrazadorService;
use App\Services\IndicadoresSepsis;
use Illuminate\Http\Request;

class TrazadorController extends Controller
{
    public function __construct(
        private ModeloTrazadorService $modelo,
        private IndicadoresSepsis $indicadores,
    ) {}

    // ─── Bandejas ─────────────────────────────────────────────────────────────

    public function index()
    {
        // Promueve SEGUIMIENTO_90D → PENDIENTE_DESPUES si ya pasaron los 90 días
        Trazador::where('estado', 'SEGUIMIENTO_90D')
            ->where('fecha_objetivo_despues', '<=', now())
            ->update(['estado' => 'PENDIENTE_DESPUES']);

        // ── Catálogo de patologías activas (extensible sin tocar código) ──────
        $tiposActivos = Trazador::distinct()->pluck('tipo_trazador')->sort()->values();
        $etiquetas = [
            'sepsis'    => 'Sepsis',
            'sdra'      => 'SDRA',
            'post_paro' => 'Post-paro cardíaco',
        ];

        // ── Datos agrupados por patología ────────────────────────────────────
        $grupos = [];
        foreach ($tiposActivos as $tipo) {
            $cerr = Trazador::cerrados()->where('tipo_trazador', $tipo)->with('paciente')->latest('fecha_cierre')->get();

            $grupos[$tipo] = [
                'activos'           => Trazador::activos()->where('tipo_trazador', $tipo)->with('paciente')->latest()->get(),
                'estadisticas'      => Trazador::estadisticas()->where('tipo_trazador', $tipo)->with('paciente')->latest('fecha_guardado_inicial')->get(),
                'pendientesDespues' => Trazador::pendientesDespues()->where('tipo_trazador', $tipo)->with('paciente')->orderBy('fecha_objetivo_despues')->get(),
                'cerrados'          => $cerr,
                'cumplimiento_prom' => $cerr->avg(fn($t) => $t->resultados['puntuacion_global_pct'] ?? null),
            ];
        }

        // ── KPIs globales ────────────────────────────────────────────────────
        $global = [
            'total_activos'    => Trazador::activos()->count(),
            'total_pendientes' => Trazador::pendientesDespues()->count(),
            'total_seguimiento'=> Trazador::estadisticas()->count(),
            'total_cerrados'   => Trazador::cerrados()->count(),
        ];

        // Promedio global de cumplimiento (cerrados con resultados)
        $cerradosTodos = Trazador::cerrados()->get();
        $global['cumplimiento_prom'] = round(
            $cerradosTodos->avg(fn($t) => $t->resultados['puntuacion_global_pct'] ?? null) ?? 0,
            1
        );

        // ── Tendencia mensual — últimos 6 meses (PHP grouping, SQLite safe) ──
        $tendencia = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes    = now()->subMonths($i);
            $count  = Trazador::cerrados()
                ->whereYear('fecha_cierre',  $mes->year)
                ->whereMonth('fecha_cierre', $mes->month)
                ->count();
            $cumProm = Trazador::cerrados()
                ->whereYear('fecha_cierre',  $mes->year)
                ->whereMonth('fecha_cierre', $mes->month)
                ->get()
                ->avg(fn($t) => $t->resultados['puntuacion_global_pct'] ?? null);
            $tendencia[] = [
                'label'     => $mes->translatedFormat('M Y'),
                'cerrados'  => $count,
                'cumplimiento' => $cumProm ? round($cumProm, 1) : null,
            ];
        }

        // ── Definición de indicadores por tipo (extensible) ──────────────────
        $indicadoresDef = [
            'sepsis' => [
                'codigo_sepsis' => [
                    'label' => 'Código Sepsis (S1–S7)',
                    'icon'  => 'bi-heart-pulse',
                    'color' => '#dc3545',
                    'items' => [
                        'S1' => 'Activación Código Sepsis',
                        'S2' => 'Lactato ≤ 60 min',
                        'S3' => 'Antibiótico ≤ 60 min',
                        'S4' => 'Hemocultivos tomados',
                        'S5' => 'Bundle 1h completo',
                        'S6' => 'Vasopresor ≤ 60 min',
                        'S7' => 'Control de foco',
                    ],
                ],
                'bundle_abcdef' => [
                    'label' => 'Bundle ABCDEF',
                    'icon'  => 'bi-list-check',
                    'color' => '#0d6efd',
                    'grupos' => [
                        'A' => ['label' => 'A — Analgesia',   'items' => ['A1'=>'Dolor c/turno','A2'=>'Dolor en meta','A3'=>'Analgesia prev.']],
                        'B' => ['label' => 'B — Respiratorio','items' => ['B1'=>'SAT','B2'=>'SBT','B3'=>'Extubación']],
                        'C' => ['label' => 'C — Sedación',    'items' => ['C1'=>'RASS meta','C2'=>'Sin BDZ','C3'=>'Propofol/Dex','C4'=>'RASS doc.']],
                        'D' => ['label' => 'D — Delirium',    'items' => ['D1'=>'CAM-ICU','D3'=>'Sin delirium','D4'=>'Interv. no farm.']],
                        'E' => ['label' => 'E — Ejercicio',   'items' => ['E1'=>'Fisioterapia','E3'=>'Doc. moviliz.']],
                        'F' => ['label' => 'F — Familia',     'items' => ['F1'=>'Educ. médico','F2'=>'Educ. fisio','F3'=>'Educ. aux.']],
                        'G' => ['label' => 'G — FAST-HUG',    'items' => ['G1'=>'FAST-HUG']],
                    ],
                ],
            ],
        ];

        // ── Cómputo de tasas de cumplimiento por indicador ────────────────────
        $dashIndicadores = [];
        foreach ($grupos as $tipo => $g) {
            if (!isset($indicadoresDef[$tipo])) continue;
            $cerrados = $g['cerrados'];
            $stats = [];

            // Recopila todos los códigos del tipo
            foreach ($indicadoresDef[$tipo] as $groupKey => $groupDef) {
                $cods = isset($groupDef['items'])
                    ? array_keys($groupDef['items'])
                    : collect($groupDef['grupos'] ?? [])->flatMap(fn($g) => array_keys($g['items']))->all();

                foreach ($cods as $cod) {
                    $vals      = $cerrados->map(fn($t) => $t->resultados['semaforo']['por_indicador'][$cod] ?? null)->filter();
                    $evaluados = $vals->filter(fn($v) => is_numeric($v['valor'] ?? null))->count();
                    $cumple    = $vals->filter(fn($v) => ($v['valor'] ?? null) === 100)->count();
                    $na        = $vals->filter(fn($v) => ($v['valor'] ?? null) === 'N/A')->count();
                    $rate      = $evaluados > 0 ? round($cumple / $evaluados * 100, 1) : null;
                    $stats[$cod] = [
                        'evaluados' => $evaluados,
                        'cumple'    => $cumple,
                        'na'        => $na,
                        'rate'      => $rate,
                        'color'     => $rate === null ? 'sin_dato'
                                    : ($rate >= 90 ? 'verde' : ($rate >= 70 ? 'amarillo' : 'rojo')),
                    ];
                }

                // Tasa agregada por letra (para bundle ABCDEF)
                if (isset($groupDef['grupos'])) {
                    foreach ($groupDef['grupos'] as $letra => $letraDef) {
                        $rates = collect(array_keys($letraDef['items']))
                            ->map(fn($c) => $stats[$c]['rate'] ?? null)
                            ->filter()
                            ->values();
                        $letraRate = $rates->isNotEmpty() ? round($rates->avg(), 1) : null;
                        $stats["_{$letra}"] = [
                            'rate'  => $letraRate,
                            'color' => $letraRate === null ? 'sin_dato'
                                     : ($letraRate >= 90 ? 'verde' : ($letraRate >= 70 ? 'amarillo' : 'rojo')),
                        ];
                    }
                }
            }
            $dashIndicadores[$tipo] = $stats;
        }

        return view('trazadores.index', compact(
            'grupos', 'tiposActivos', 'etiquetas', 'global', 'tendencia',
            'indicadoresDef', 'dashIndicadores'
        ));
    }

    // ─── Marcar paciente como trazador ────────────────────────────────────────

    public function marcar(Request $request, Paciente $paciente)
    {
        $tipo = $request->input('tipo_trazador', 'sepsis');

        // Evita duplicados: si ya existe uno activo del mismo tipo, redirige a él
        $existente = $paciente->trazadores()->where('tipo_trazador', $tipo)
            ->whereNotIn('estado', ['CERRADO'])->first();

        if ($existente) {
            return redirect()->route('trazadores.edit', $existente)
                ->with('warning', 'Este paciente ya tiene un trazador de Sepsis abierto.');
        }

        $datosPrellenados = $this->modelo->prellenarDesdePaciente($paciente);

        $trazador = Trazador::create([
            'paciente_id'  => $paciente->id,
            'tipo_trazador' => $tipo,
            'estado'        => 'TRAZADOR_INICIAL',
            'datos'         => $datosPrellenados,
        ]);

        return redirect()->route('trazadores.edit', $trazador)
            ->with('success', 'Paciente marcado como trazador. Complete los datos del formulario.');
    }

    // ─── Formulario principal (editar) ────────────────────────────────────────

    public function edit(Trazador $trazador)
    {
        $this->modelo->cargarTipo($trazador->tipo_trazador);
        $modelo    = $this->modelo->modelo();
        $catalogos = $this->modelo->catalogos();
        $paciente  = $trazador->paciente;
        $viewBase  = $this->viewBase($trazador->tipo_trazador);

        return view("{$viewBase}.form", compact('trazador', 'modelo', 'catalogos', 'paciente'));
    }

    // ─── Guardar parte inicial → SEGUIMIENTO_90D ─────────────────────────────

    public function store(Request $request, Trazador $trazador)
    {
        $datos = $request->input('datos', []);

        // Calcula indicadores antes de guardar
        $resultados = $this->indicadores->calcular($datos);

        $ahora = now();
        $trazador->update([
            'datos'                  => $datos,
            'resultados'             => $resultados,
            'estado'                 => 'SEGUIMIENTO_90D',
            'fecha_guardado_inicial' => $ahora,
            'fecha_objetivo_despues' => $ahora->copy()->addDays(90),
        ]);

        return redirect()->route('trazadores.show', $trazador)
            ->with('success', 'Trazador guardado. El paciente pasa a estadísticas. Se esperará la encuesta DESPUÉS a los 90 días.');
    }

    // ─── Vista de resultados / estadísticas ───────────────────────────────────

    public function show(Trazador $trazador)
    {
        $this->modelo->cargarTipo($trazador->tipo_trazador);
        $modelo    = $this->modelo->modelo();
        $catalogos = $this->modelo->catalogos();
        $paciente  = $trazador->paciente;
        $viewBase  = $this->viewBase($trazador->tipo_trazador);

        return view("{$viewBase}.show", compact('trazador', 'modelo', 'catalogos', 'paciente'));
    }

    // ─── Formulario Encuesta DESPUÉS ─────────────────────────────────────────

    public function editDespues(Trazador $trazador)
    {
        $this->modelo->cargarTipo($trazador->tipo_trazador);
        $modelo    = $this->modelo->modelo();
        $catalogos = $this->modelo->catalogos();
        $paciente  = $trazador->paciente;
        $viewBase  = $this->viewBase($trazador->tipo_trazador);

        return view("{$viewBase}.despues", compact('trazador', 'modelo', 'catalogos', 'paciente'));
    }

    // ─── Guardar Encuesta DESPUÉS → CERRADO ──────────────────────────────────

    public function storeDespues(Request $request, Trazador $trazador)
    {
        $datos = $trazador->datos ?? [];
        $datos['encuesta_despues'] = $request->input('datos.encuesta_despues', []);

        // Recalcula con la encuesta después incluida (genera comparativo)
        $resultados = $this->indicadores->calcular($datos);

        $trazador->update([
            'datos'       => $datos,
            'resultados'  => $resultados,
            'estado'      => 'CERRADO',
            'fecha_cierre' => now(),
        ]);

        return redirect()->route('trazadores.show', $trazador)
            ->with('success', 'Encuesta DESPUÉS guardada. Caso cerrado. Comparativo calculado.');
    }

    // ─── Editar un trazador ya guardado (cualquier estado) ───────────────────

    public function update(Request $request, Trazador $trazador)
    {
        $datos = $request->input('datos', []);
        $resultados = $this->indicadores->calcular($datos);

        $trazador->update([
            'datos'      => $datos,
            'resultados' => $resultados,
        ]);

        return redirect()->route('trazadores.show', $trazador)
            ->with('success', 'Trazador actualizado. Indicadores recalculados.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function viewBase(string $tipo): string
    {
        return match($tipo) {
            'sdra'      => 'trazadores.sdra',
            'post_paro' => 'trazadores.post-paro',
            default     => 'trazadores.sepsis',
        };
    }
}
