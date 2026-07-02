<?php

namespace App\Http\Controllers;

use App\Models\NutricionDiaria;
use App\Models\AntibioticosUci;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NutricionController extends Controller
{
    public function index(Request $request)
    {
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha')) : today();

        $pacientes = Paciente::where('activo', true)
            ->whereNotNull('ingreso_uci')
            ->with(['ultimoSnapshot'])
            ->orderBy('nombre')
            ->get();

        $nutricion = NutricionDiaria::whereDate('fecha', $fecha)
            ->get()->keyBy('paciente_id');

        // ATBs activos por paciente
        $atbs = AntibioticosUci::where('activo', true)
            ->whereIn('paciente_id', $pacientes->pluck('id'))
            ->get()->groupBy('paciente_id');

        // Tendencia semanal (últimos 7 días, % meta calórica promedio)
        $tendencia = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = today()->subDays($i);
            $registros = NutricionDiaria::whereDate('fecha', $d)
                ->whereNotNull('kcal_meta')
                ->whereNotNull('kcal_aportadas')
                ->get();
            $tendencia[] = [
                'fecha'   => $d->format('d/m'),
                'pct_avg' => $registros->count() > 0
                    ? round($registros->avg(fn($r) => $r->kcal_aportadas / $r->kcal_meta * 100))
                    : null,
                'n'       => $registros->count(),
            ];
        }

        return view('nutricion.index', compact('pacientes','nutricion','atbs','tendencia','fecha'));
    }

    public function storeNutricion(Request $request)
    {
        $fecha = $request->fecha ?? today()->toDateString();
        NutricionDiaria::updateOrCreate(
            ['paciente_id' => $request->paciente_id, 'fecha' => $fecha],
            [
                'usuario_id'             => auth()->id(),
                'via'                    => $request->via,
                'kcal_meta'              => $request->kcal_meta ?: null,
                'kcal_aportadas'         => $request->kcal_aportadas ?: null,
                'proteinas_g_meta'       => $request->proteinas_g_meta ?: null,
                'proteinas_g_aportadas'  => $request->proteinas_g_aportadas ?: null,
                'inicio_ne_hoy'          => (bool)$request->inicio_ne_hoy,
                'motivo_suspension'      => $request->motivo_suspension,
                'observaciones'          => $request->observaciones,
            ]
        );

        return redirect()->route('nutricion.index', ['fecha' => $fecha])
            ->with('success', 'Nutrición registrada.');
    }

    public function storeAtb(Request $request)
    {
        AntibioticosUci::create([
            'paciente_id'       => $request->paciente_id,
            'usuario_id'        => auth()->id(),
            'antibiotico'       => $request->antibiotico,
            'fecha_inicio'      => $request->fecha_inicio,
            'via'               => $request->via ?? 'iv',
            'dosis'             => $request->dosis,
            'indicacion'        => $request->indicacion,
            'foco'              => $request->foco,
            'activo'            => true,
            'cultivo_disponible'=> (bool)$request->cultivo_disponible,
            'resultado_cultivo' => $request->resultado_cultivo,
            'pct_inicio'        => $request->pct_inicio ?: null,
            'observaciones'     => $request->observaciones,
        ]);

        return redirect()->route('nutricion.index')
            ->with('success', 'Antibiótico registrado.');
    }

    public function suspenderAtb(AntibioticosUci $atb, Request $request)
    {
        $atb->update([
            'activo'             => false,
            'fecha_fin'          => $request->fecha_fin ?? today()->toDateString(),
            'de_escalado'        => (bool)$request->de_escalado,
            'fecha_deescalacion' => $request->de_escalado ? ($request->fecha_fin ?? today()->toDateString()) : null,
            'pct_control_72h'    => $request->pct_control_72h ?: null,
        ]);

        return redirect()->route('nutricion.index')
            ->with('success', 'Antibiótico suspendido / actualizado.');
    }
}
