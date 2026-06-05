<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    private const SUBUNIDADES_ORDEN = [
        'UCI Quirúrgica', 'UCI Cardiovascular', 'UCI Respiratoria',
        'UCI General', 'UCI Neurovascular', 'UCIN', 'UCI Torre C', 'UCI Torre B',
    ];

    private const CAPACIDADES = [
        'UCI Quirúrgica'     => 8,
        'UCI Cardiovascular' => 8,
        'UCI Respiratoria'   => 6,
        'UCI General'        => 11,
        'UCI Neurovascular'  => 8,
        'UCIN'               => 6,
        'UCI Torre C'        => 8,
        'UCI Torre B'        => 20,
    ];

    public function index(Request $request)
    {
        $subunidadFiltro = $request->get('subunidad', 'todas');

        // Últimos snapshots por paciente activo
        $subquery = Snapshot::select('paciente_id', DB::raw('MAX(id) as max_id'))->groupBy('paciente_id');

        $snapshotsQuery = Snapshot::joinSub($subquery, 'lt', fn($j) => $j->on('snapshots.id', '=', 'lt.max_id'))
            ->join('pacientes', 'pacientes.id', '=', 'snapshots.paciente_id')
            ->where('pacientes.activo', true)
            ->select('snapshots.*', 'pacientes.ingreso_uci', 'pacientes.salida_hospitalizacion', 'pacientes.egreso_uci', 'pacientes.nombre', 'pacientes.documento');

        if ($subunidadFiltro !== 'todas') {
            $snapshotsQuery->where('snapshots.subunidad', $subunidadFiltro);
        }

        $snapshots = $snapshotsQuery->get();

        // Por subunidad
        $porSubunidad = collect(self::SUBUNIDADES_ORDEN)->mapWithKeys(function ($sub) use ($snapshots) {
            $pacsSub = $snapshots->where('subunidad', $sub);
            return [$sub => [
                'total'       => $pacsSub->count(),
                'capacidad'   => self::CAPACIDADES[$sub] ?? 0,
                'intensivo'   => $pacsSub->filter(fn($s) => str_contains($s->criterio_atencion ?? '', 'INTENSIVO'))->count(),
                'intermedio'  => $pacsSub->filter(fn($s) => str_contains($s->criterio_atencion ?? '', 'INTERMEDIO'))->count(),
                'otros'       => $pacsSub->filter(fn($s) => str_contains($s->criterio_atencion ?? '', 'OTROS'))->count(),
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
