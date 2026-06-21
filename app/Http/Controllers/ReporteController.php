<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\UnidadUci;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    private const SUBUNIDADES_ORDEN = [
        'UCI Quirúrgica', 'UCI Cardiovascular', 'UCI Respiratoria',
        'UCI General', 'UCI Neurovascular', 'UCIN', 'UCI Torre C', 'UCI Torre B',
    ];

    public function index(Request $request)
    {
        $subunidadFiltro = $request->get('subunidad', 'todas');

        // Últimos snapshots por paciente activo
        $subquery = Snapshot::subqueryUltimoPorPaciente();

        $snapshotsQuery = Snapshot::joinSub($subquery, 'lt', fn($j) => $j->on('snapshots.id', '=', 'lt.snap_id'))
            ->join('pacientes', 'pacientes.id', '=', 'snapshots.paciente_id')
            ->where('pacientes.activo', true)
            ->select('snapshots.*', 'pacientes.ingreso_uci', 'pacientes.salida_hospitalizacion', 'pacientes.egreso_uci', 'pacientes.nombre', 'pacientes.documento');

        if ($subunidadFiltro !== 'todas') {
            $snapshotsQuery->where('snapshots.subunidad', $subunidadFiltro);
        }

        $snapshots = $snapshotsQuery->get();

        $capacidades = UnidadUci::with('indisponibilidades')->get()
            ->mapWithKeys(fn($u) => [$u->nombre => $u->capacidadDisponibleEn(today())]);
        $clasificar = fn($s) => $s->salida_hospitalizacion && !$s->egreso_uci ? 'traslado'
            : (str_contains(strtoupper($s->criterio_atencion ?? ''), 'INTERMEDIO') || $s->subunidad === 'UCIN' ? 'ucin' : 'uci');

        // Misma clasificación clínica usada en Ocupación por Subunidad del dashboard.
        $porSubunidad = collect(self::SUBUNIDADES_ORDEN)->mapWithKeys(function ($sub) use ($snapshots, $capacidades, $clasificar) {
            $pacsSub = $snapshots->where('subunidad', $sub);
            return [$sub => [
                'total'       => $pacsSub->count(),
                'capacidad'   => $capacidades[$sub] ?? 0,
                'uci'         => $pacsSub->filter(fn($s) => $clasificar($s) === 'uci')->count(),
                'ucin'        => $pacsSub->filter(fn($s) => $clasificar($s) === 'ucin')->count(),
                'traslado'    => $pacsSub->filter(fn($s) => $clasificar($s) === 'traslado')->count(),
                'con_vmi'     => $pacsSub->filter(fn($s) => $s->soporte_ventilatorio === 'VMI')->count(),
                'con_vasopresor' => $pacsSub->filter(fn($s) => $s->soporte_hemodinamico === 'Vasopresor')->count(),
                'pendiente_egreso' => $pacsSub->filter(fn($s) => $s->salida_hospitalizacion && !$s->egreso_uci)->count(),
            ]];
        });

        // Detalle de pacientes con espera hospitalización
        $pacientesEspera = Paciente::whereNotNull('salida_hospitalizacion')
            ->whereNull('egreso_uci')->where('activo', true)
            ->with('ultimoSnapshot')
            ->get()
            ->when($subunidadFiltro !== 'todas', fn($c) => $c->filter(
                fn($p) => ($p->ultimoSnapshot->subunidad ?? '') === $subunidadFiltro
            ))
            ->sortByDesc(fn($p) => $p->tiempoEsperaHoras());

        // Pacientes sin fecha ingreso UCI
        $sinIngreso = Paciente::whereNull('ingreso_uci')->where('activo', true)
            ->with('ultimoSnapshot')
            ->get()
            ->when($subunidadFiltro !== 'todas', fn($c) => $c->filter(
                fn($p) => ($p->ultimoSnapshot->subunidad ?? '') === $subunidadFiltro
            ));

        return view('reportes.index', compact(
            'porSubunidad', 'pacientesEspera', 'sinIngreso',
            'subunidadFiltro', 'snapshots'
        ));
    }
}
