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
        $etiquetas = ['sepsis' => 'Sepsis']; // registro de nombres amigables

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

        return view('trazadores.index', compact('grupos', 'tiposActivos', 'etiquetas', 'global', 'tendencia'));
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
        $modelo     = $this->modelo->modelo();
        $catalogos  = $this->modelo->catalogos();
        $paciente   = $trazador->paciente;

        return view('trazadores.sepsis.form', compact('trazador', 'modelo', 'catalogos', 'paciente'));
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
        $modelo    = $this->modelo->modelo();
        $catalogos = $this->modelo->catalogos();
        $paciente  = $trazador->paciente;

        return view('trazadores.sepsis.show', compact('trazador', 'modelo', 'catalogos', 'paciente'));
    }

    // ─── Formulario Encuesta DESPUÉS ─────────────────────────────────────────

    public function editDespues(Trazador $trazador)
    {
        // Solo disponible en PENDIENTE_DESPUES (o edición de CERRADO)
        $modelo    = $this->modelo->modelo();
        $catalogos = $this->modelo->catalogos();
        $paciente  = $trazador->paciente;

        return view('trazadores.sepsis.despues', compact('trazador', 'modelo', 'catalogos', 'paciente'));
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
}
