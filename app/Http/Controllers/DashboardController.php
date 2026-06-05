<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CargaArchivo;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // KPIs generales
        $totalActivos = Paciente::where('activo', true)->count();
        $pendientesEgreso = Paciente::whereNotNull('salida_hospitalizacion')
            ->whereNull('egreso_uci')->where('activo', true)->count();

        // Fecha de la última carga
        $ultimaCarga = CargaArchivo::latest()->first();

        // Últimos snapshots de pacientes activos (uno por paciente)
        $subquery = Snapshot::select('paciente_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('paciente_id');

        $snapshotsActuales = Snapshot::joinSub($subquery, 'latest', fn($j) => $j->on('snapshots.id', '=', 'latest.max_id'))
            ->join('pacientes', 'pacientes.id', '=', 'snapshots.paciente_id')
            ->where('pacientes.activo', true)
            ->select('snapshots.*')
            ->get();

        // Conteos por criterio
        $porCriterio = $snapshotsActuales->groupBy('criterio_atencion')->map->count();

        // Conteos por subunidad
        $porSubunidad = $snapshotsActuales->groupBy('subunidad')->map->count()->sortKeys();

        // Conteos soporte ventilatorio
        $porVentilatorio = $snapshotsActuales
            ->filter(fn($s) => !empty($s->soporte_ventilatorio))
            ->groupBy('soporte_ventilatorio')->map->count();

        // Conteos soporte hemodinámico
        $porHemodinamico = $snapshotsActuales
            ->filter(fn($s) => !empty($s->soporte_hemodinamico))
            ->groupBy('soporte_hemodinamico')->map->count();

        // Pacientes con criterio hospitalización pendientes de egreso (más de 4h)
        $pacientesEsperaLarga = Paciente::whereNotNull('salida_hospitalizacion')
            ->whereNull('egreso_uci')
            ->where('activo', true)
            ->with('ultimoSnapshot')
            ->get()
            ->filter(fn($p) => $p->tiempoEsperaHoras() > 4)
            ->sortByDesc(fn($p) => $p->tiempoEsperaHoras());

        // Ocupación por subunidad (capacidad vs activos)
        $capacidades = [
            'UCI Quirúrgica'     => 8,
            'UCI Cardiovascular' => 8,
            'UCI Respiratoria'   => 6,
            'UCI General'        => 11,
            'UCI Neurovascular'  => 8,
            'UCIN'               => 6,
            'UCI Torre C'        => 8,
            'UCI Torre B'        => 20,
        ];

        // Promedios de escalas clínicas (solo valores no nulos)
        $promedios = [
            'NEWS'    => $snapshotsActuales->whereNotNull('news')->avg('news'),
            'SOFA'    => null,
            'BARTHEL' => $snapshotsActuales->whereNotNull('barthel')->avg('barthel'),
            'RASS'    => $snapshotsActuales->whereNotNull('rass')->avg('rass'),
            'EVA'     => $snapshotsActuales->whereNotNull('eva')->avg('eva'),
        ];

        return view('dashboard.index', compact(
            'totalActivos', 'pendientesEgreso', 'ultimaCarga',
            'porCriterio', 'porSubunidad', 'porVentilatorio', 'porHemodinamico',
            'pacientesEsperaLarga', 'capacidades', 'promedios'
        ));
    }
}
