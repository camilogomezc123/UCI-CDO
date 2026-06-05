<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PacienteController extends Controller
{
    public function index(Request $request)
    {
        $subunidades = [
            'UCI Quirúrgica', 'UCI Cardiovascular', 'UCI Respiratoria',
            'UCI General', 'UCI Neurovascular', 'UCIN', 'UCI Torre C', 'UCI Torre B',
        ];
        $criterios = [
            'ESTANCIA EN UNIDAD CUIDADO INTENSIVO',
            'ESTANCIA EN UNIDAD CUIDADO INTERMEDIO',
            'OTROS CRITERIOS(Hosp, Alta)',
        ];

        // Últimos snapshots por paciente activo
        $subquery = Snapshot::select('paciente_id', DB::raw('MAX(id) as max_id'))
            ->groupBy('paciente_id');

        $query = Paciente::where('activo', true)
            ->joinSub(
                Snapshot::select('paciente_id', DB::raw('MAX(id) as snap_id'), 'subunidad', 'ubicacion', 'criterio_atencion', 'soporte_hemodinamico', 'soporte_ventilatorio', 'fecha_snapshot')
                    ->groupBy('paciente_id', 'subunidad', 'ubicacion', 'criterio_atencion', 'soporte_hemodinamico', 'soporte_ventilatorio', 'fecha_snapshot'),
                'snap',
                'pacientes.id', '=', 'snap.paciente_id'
            )
            ->select('pacientes.*', 'snap.subunidad', 'snap.ubicacion', 'snap.criterio_atencion', 'snap.soporte_hemodinamico', 'snap.soporte_ventilatorio', 'snap.fecha_snapshot');

        // Filtros
        if ($request->filled('subunidad')) {
            $query->where('snap.subunidad', $request->subunidad);
        }
        if ($request->filled('criterio')) {
            $query->where('snap.criterio_atencion', $request->criterio);
        }
        if ($request->filled('busqueda')) {
            $b = '%' . $request->busqueda . '%';
            $query->where(fn($q) => $q->where('pacientes.nombre', 'like', $b)
                ->orWhere('pacientes.documento', 'like', $b));
        }
        if ($request->get('filtro') === 'pendiente_egreso') {
            $query->whereNotNull('pacientes.salida_hospitalizacion')
                ->whereNull('pacientes.egreso_uci');
        }
        if ($request->get('filtro') === 'sin_ingreso') {
            $query->whereNull('pacientes.ingreso_uci');
        }

        $pacientes = $query->orderBy('snap.subunidad')->orderBy('snap.ubicacion')->paginate(30)->withQueryString();

        return view('pacientes.index', compact('pacientes', 'subunidades', 'criterios'));
    }

    public function show(Paciente $paciente)
    {
        $ultimoSnapshot = $paciente->snapshots()->with('cambios')->first();
        $historial = $paciente->snapshots()->with(['carga.usuario', 'cambios'])->get();
        return view('pacientes.show', compact('paciente', 'ultimoSnapshot', 'historial'));
    }

    public function actualizarIngreso(Request $request, Paciente $paciente)
    {
        $request->validate([
            'ingreso_uci' => 'required|date',
        ], ['ingreso_uci.required' => 'La fecha de ingreso es obligatoria.']);

        if ($paciente->ingreso_uci) {
            return back()->with('error', 'El ingreso a UCI ya fue registrado y no puede modificarse.');
        }

        $paciente->update(['ingreso_uci' => $request->ingreso_uci]);
        return back()->with('success', 'Fecha de ingreso a UCI registrada correctamente.');
    }

    public function actualizarSalidaHospitalizacion(Request $request, Paciente $paciente)
    {
        $request->validate([
            'salida_hospitalizacion' => 'required|date',
        ]);

        $paciente->update(['salida_hospitalizacion' => $request->salida_hospitalizacion]);
        return back()->with('success', 'Fecha de salida para hospitalización registrada.');
    }

    public function actualizarEgresoUci(Request $request, Paciente $paciente)
    {
        $request->validate([
            'egreso_uci' => 'required|date',
        ]);

        $paciente->update([
            'egreso_uci' => $request->egreso_uci,
            'activo' => false,
        ]);
        return back()->with('success', 'Egreso de UCI registrado. Paciente marcado como inactivo.');
    }
}
