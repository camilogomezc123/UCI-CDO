<?php

namespace App\Http\Controllers;

use App\Models\GoalOfCare;
use App\Models\Paciente;
use Illuminate\Http\Request;

class GoalOfCareController extends Controller
{
    public function index()
    {
        $pacientes = Paciente::where('activo', true)
            ->whereNotNull('ingreso_uci')
            ->with(['ultimoSnapshot','goalOfCare'])
            ->orderBy('nombre')
            ->get();

        $sinGoc = $pacientes->filter(fn($p) => !$p->goalOfCare);
        $conGoc = $pacientes->filter(fn($p) => $p->goalOfCare);

        // Últimas conversaciones (histórico)
        $historico = GoalOfCare::with('paciente','usuario')
            ->orderByDesc('fecha_conversacion')
            ->limit(30)
            ->get();

        $niveles = GoalOfCare::niveles();

        return view('goals-of-care.index', compact('pacientes','sinGoc','conGoc','historico','niveles'));
    }

    public function store(Request $request)
    {
        GoalOfCare::create([
            'paciente_id'          => $request->paciente_id,
            'usuario_id'           => auth()->id(),
            'fecha_conversacion'   => $request->fecha_conversacion ?? today()->toDateString(),
            'nivel_esfuerzo'       => $request->nivel_esfuerzo,
            'dnr'                  => (bool)$request->dnr,
            'tiempo_limitado_hasta'=> $request->tiempo_limitado_hasta ?: null,
            'quien_participo'      => $request->quien_participo,
            'plan_cuidados'        => $request->plan_cuidados,
            'observaciones'        => $request->observaciones,
        ]);

        return redirect()->route('goals-of-care.index')
            ->with('success', 'Conversación Goals of Care registrada.');
    }

    public function update(GoalOfCare $goalOfCare, Request $request)
    {
        $goalOfCare->update([
            'fecha_conversacion'   => $request->fecha_conversacion,
            'nivel_esfuerzo'       => $request->nivel_esfuerzo,
            'dnr'                  => (bool)$request->dnr,
            'tiempo_limitado_hasta'=> $request->tiempo_limitado_hasta ?: null,
            'quien_participo'      => $request->quien_participo,
            'plan_cuidados'        => $request->plan_cuidados,
            'observaciones'        => $request->observaciones,
        ]);

        return redirect()->route('goals-of-care.index')
            ->with('success', 'Goals of Care actualizado.');
    }
}
