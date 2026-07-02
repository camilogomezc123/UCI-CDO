<?php

namespace App\Http\Controllers;

use App\Models\BalanceHidrico;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BalanceHidricoController extends Controller
{
    public function index(Request $request)
    {
        $fecha  = $request->input('fecha') ? Carbon::parse($request->input('fecha')) : today();
        $pacienteId = $request->input('paciente_id');

        // Vista general: todos los pacientes activos con balance del día
        $pacientes = Paciente::where('activo', true)
            ->whereNotNull('ingreso_uci')
            ->with(['ultimoSnapshot'])
            ->orderBy('nombre')
            ->get();

        $balances = BalanceHidrico::whereDate('fecha', $fecha)
            ->get()->keyBy('paciente_id');

        // Balance acumulado por paciente (suma de todos los días en UCI)
        $acumulados = BalanceHidrico::whereIn('paciente_id', $pacientes->pluck('id'))
            ->selectRaw('paciente_id, SUM(vol_cristaloides+vol_coloides+vol_hemoderivados+vol_nutricion+vol_medicamentos+vol_otros_ingresos) - SUM(vol_diuresis+vol_drenajes+vol_perdidas_insensibles+vol_otros_egresos) as balance_acum')
            ->groupBy('paciente_id')
            ->pluck('balance_acum', 'paciente_id');

        // Paciente individual (historial 7 días)
        $historial = null;
        $pacienteSeleccionado = null;
        if ($pacienteId) {
            $pacienteSeleccionado = Paciente::find($pacienteId);
            $historial = BalanceHidrico::where('paciente_id', $pacienteId)
                ->orderByDesc('fecha')
                ->limit(14)
                ->get();
        }

        return view('balance-hidrico.index', compact(
            'pacientes', 'balances', 'acumulados', 'fecha',
            'historial', 'pacienteSeleccionado'
        ));
    }

    public function store(Request $request)
    {
        $pacienteId = $request->paciente_id;
        $fecha = $request->fecha ?? today()->toDateString();

        $data = [
            'paciente_id' => $pacienteId,
            'usuario_id'  => auth()->id(),
            'fecha'       => $fecha,
        ];

        foreach ([
            'vol_cristaloides','vol_coloides','vol_hemoderivados',
            'vol_nutricion','vol_medicamentos','vol_otros_ingresos',
            'vol_diuresis','vol_drenajes','vol_perdidas_insensibles','vol_otros_egresos',
        ] as $campo) {
            $data[$campo] = (int)($request->input($campo, 0));
        }

        $data['observaciones'] = $request->observaciones;

        BalanceHidrico::updateOrCreate(
            ['paciente_id' => $pacienteId, 'fecha' => $fecha],
            $data
        );

        return redirect()->route('balance-hidrico.index', ['fecha' => $fecha])
            ->with('success', 'Balance hídrico guardado.');
    }
}
