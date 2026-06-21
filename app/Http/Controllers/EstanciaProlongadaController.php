<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CausaEstancia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EstanciaProlongadaController extends Controller
{
    public function index(Request $request)
    {
        $etiquetas = CausaEstancia::etiquetas();
        $causaFiltro = $request->get('causa', 'todas');
        if ($causaFiltro !== 'todas' && $causaFiltro !== 'sin_causa' && !array_key_exists($causaFiltro, $etiquetas)) {
            $causaFiltro = 'todas';
        }

        // Pacientes activos con ingreso UCI hace más de 5 días
        $pacientesQuery = Paciente::where('activo', true)
            ->whereNotNull('ingreso_uci')
            ->where('ingreso_uci', '<=', now()->subDays(5));

        if ($causaFiltro === 'sin_causa') {
            $pacientesQuery->doesntHave('causaEstancia');
        } elseif ($causaFiltro !== 'todas') {
            $pacientesQuery->whereHas('causaEstancia', fn($q) => $q->where($causaFiltro, true));
        }

        $pacientes = $pacientesQuery->with(['ultimoSnapshot', 'causaEstancia'])
            ->get()
            ->sortByDesc(fn($p) => $p->diasEnUci());

        // Distribución de causas para el gráfico
        $causas = CausaEstancia::all();
        $distribucionCausas = [
            'Pendiente cirugía'          => $causas->where('pendiente_cirugia', true)->count(),
            'Condición clínica'          => $causas->where('condicion_clinica', true)->count(),
            'Ventilación mecánica'       => $causas->where('ventilacion_mecanica', true)->count(),
            'Pendiente cama hosp.'       => $causas->where('pendiente_cama_hospitalizacion', true)->count(),
            'Trámite administrativo'     => $causas->where('tramite_administrativo', true)->count(),
            'Homecare'                   => $causas->where('homecare', true)->count(),
        ];

        // Promedios días por subunidad (solo pacientes egresados con ingreso/egreso registrado)
        $promedioPorSubunidad = Paciente::where('activo', false)
            ->whereNotNull('ingreso_uci')
            ->whereNotNull('egreso_uci')
            ->get()
            ->map(fn($p) => [
                'subunidad' => $p->ultimoSnapshot->subunidad ?? 'Desconocida',
                'dias'      => $p->diasEnUci(),
            ])
            ->groupBy('subunidad')
            ->map(fn($g) => round($g->avg('dias'), 1));

        return view('pacientes.estancia-prolongada', compact(
            'pacientes', 'distribucionCausas', 'promedioPorSubunidad', 'etiquetas', 'causaFiltro'
        ));
    }
}
