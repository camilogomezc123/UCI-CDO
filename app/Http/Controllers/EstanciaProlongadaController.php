<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CausaEstancia;

class EstanciaProlongadaController extends Controller
{
    public function index()
    {
        $etiquetas = CausaEstancia::etiquetas();

        // Pacientes activos con ingreso UCI hace más de 5 días
        $pacientesQuery = Paciente::where('activo', true)
            ->whereNotNull('ingreso_uci')
            ->where('ingreso_uci', '<=', now()->subDays(5));

        $pacientes = $pacientesQuery->with(['ultimoSnapshot', 'causaEstancia'])
            ->get()
            ->sortByDesc(fn($p) => $p->diasEnUci());

        // Distribución de causas para el gráfico
        // Solo las causas de pacientes actualmente en estancia prolongada.
        $causas = CausaEstancia::whereIn('paciente_id', $pacientes->pluck('id'))->get();
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
            ->with('ultimoSnapshot')
            ->get()
            ->map(fn($p) => [
                'subunidad' => $p->ultimoSnapshot->subunidad ?? 'Desconocida',
                'dias'      => $p->diasEnUci(),
            ])
            ->groupBy('subunidad')
            ->map(fn($g) => round($g->avg('dias'), 1));

        return view('pacientes.estancia-prolongada', compact(
            'pacientes', 'distribucionCausas', 'promedioPorSubunidad', 'etiquetas'
        ));
    }
}
