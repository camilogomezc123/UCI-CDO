<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\NotaPaciente;
use App\Models\CausaEstancia;
use App\Models\CamUci;
use App\Models\BundleVentilacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PacienteController extends Controller
{
    public function index(Request $request)
    {
        $subunidades = ['UCI Quirúrgica','UCI Cardiovascular','UCI Respiratoria','UCI General','UCI Neurovascular','UCIN','UCI Torre C','UCI Torre B'];
        $criterios   = ['ESTANCIA EN UNIDAD CUIDADO INTENSIVO','ESTANCIA EN UNIDAD CUIDADO INTERMEDIO','OTROS CRITERIOS(Hosp, Alta)'];

        $sub = Snapshot::subqueryUltimoPorPaciente();

        $snaps = Snapshot::joinSub($sub, 's', fn($j) => $j->on('snapshots.id','=','s.snap_id'))
            ->select('snapshots.paciente_id','snapshots.subunidad','snapshots.ubicacion',
                     'snapshots.criterio_atencion','snapshots.soporte_hemodinamico',
                     'snapshots.soporte_ventilatorio','snapshots.fecha_snapshot');

        $query = Paciente::where('activo', true)
            ->joinSub($snaps, 'snap', fn($j) => $j->on('pacientes.id','=','snap.paciente_id'))
            ->select('pacientes.*','snap.subunidad','snap.ubicacion','snap.criterio_atencion',
                     'snap.soporte_hemodinamico','snap.soporte_ventilatorio','snap.fecha_snapshot');

        if ($request->filled('subunidad')) $query->where('snap.subunidad', $request->subunidad);
        if ($request->filled('criterio'))  $query->where('snap.criterio_atencion', $request->criterio);
        if ($request->filled('busqueda')) {
            $b = '%'.$request->busqueda.'%';
            $query->where(fn($q) => $q->where('pacientes.nombre','like',$b)
                ->orWhere('pacientes.documento','like',$b)
                ->orWhere('snap.criterio_atencion','like',$b));
        }
        if ($request->get('filtro') === 'pendiente_egreso') {
            $query->whereNotNull('pacientes.salida_hospitalizacion')->whereNull('pacientes.egreso_uci');
        }
        if ($request->get('filtro') === 'sin_ingreso') {
            $query->whereNull('pacientes.ingreso_uci');
        }
        if ($request->filled('cie10')) {
            $query->whereHas('ultimoSnapshot', fn($q) => $q->where('cie10','like','%'.$request->cie10.'%'));
        }

        $pacientes = $query->orderBy('snap.subunidad')->orderBy('snap.ubicacion')->paginate(30)->withQueryString();

        return view('pacientes.index', compact('pacientes','subunidades','criterios'));
    }

    public function show(Paciente $paciente)
    {
        $ultimoSnapshot = $paciente->snapshots()->with('cambios')->first();
        $historial      = $paciente->snapshots()->with(['carga.usuario','cambios'])->get();
        $notas          = $paciente->notas()->with('usuario')->get();
        $causaEstancia  = $paciente->causaEstancia;
        $diasVmi        = $paciente->diasVmi();
        $diasVasopresor = $paciente->diasVasopresor();
        $diasInotropico = $paciente->diasInotropico();

        // CAM-UCI: últimos 14 registros + registro de hoy si existe
        $camUciRegistros  = $paciente->camUci()->with('usuario')->limit(14)->get();
        $camUciHoy        = $camUciRegistros->firstWhere('fecha', today()->toDateString());

        // Bundle ventilador: últimos 14 días + hoy
        $bundleRegistros  = $paciente->bundleVentilacion()->with('usuario')->limit(14)->get();
        $bundleHoy        = $bundleRegistros->firstWhere('fecha', today()->toDateString());
        $cumplimientoBundle = $paciente->cumplimientoBundle();

        // Tendencia escalas (últimos 30 snapshots)
        $tendencia = $paciente->snapshots()
            ->orderBy('fecha_snapshot')
            ->limit(30)
            ->get(['fecha_snapshot','news','sofa','rass','eva','barthel']);

        return view('pacientes.show', compact(
            'paciente','ultimoSnapshot','historial',
            'notas','causaEstancia','diasVmi','diasVasopresor','diasInotropico',
            'camUciRegistros','camUciHoy','bundleRegistros','bundleHoy',
            'cumplimientoBundle','tendencia'
        ));
    }

    public function actualizarIngreso(Request $request, Paciente $paciente)
    {
        $request->validate(['ingreso_uci' => 'required|date']);
        if ($paciente->ingreso_uci) return back()->with('error','El ingreso a UCI ya fue registrado.');
        $paciente->update(['ingreso_uci' => $request->ingreso_uci]);
        return back()->with('success','Fecha de ingreso a UCI registrada correctamente.');
    }

    public function actualizarSalidaHospitalizacion(Request $request, Paciente $paciente)
    {
        $request->validate(['salida_hospitalizacion' => 'required|date']);
        $paciente->update(['salida_hospitalizacion' => $request->salida_hospitalizacion]);
        return back()->with('success','Fecha de salida para hospitalización registrada.');
    }

    public function actualizarEgresoUci(Request $request, Paciente $paciente)
    {
        $request->validate([
            'egreso_uci'  => 'required|date',
            'tipo_egreso' => 'required|in:mejoria,traslado,fallecimiento',
        ]);
        $paciente->update([
            'egreso_uci'  => $request->egreso_uci,
            'tipo_egreso' => $request->tipo_egreso,
            'activo'      => false,
        ]);
        return back()->with('success','Egreso de UCI registrado. Paciente marcado como inactivo.');
    }

    public function guardarNota(Request $request, Paciente $paciente)
    {
        $request->validate(['nota' => 'required|string|max:2000']);
        NotaPaciente::create([
            'paciente_id' => $paciente->id,
            'usuario_id'  => auth()->id(),
            'fecha'       => today(),
            'nota'        => $request->nota,
        ]);
        return back()->with('success','Nota registrada.');
    }

    public function guardarCausa(Request $request, Paciente $paciente)
    {
        $data = [
            'usuario_id'                     => auth()->id(),
            'pendiente_cirugia'              => $request->boolean('pendiente_cirugia'),
            'condicion_clinica'              => $request->boolean('condicion_clinica'),
            'ventilacion_mecanica'           => $request->boolean('ventilacion_mecanica'),
            'pendiente_cama_hospitalizacion' => $request->boolean('pendiente_cama_hospitalizacion'),
            'tramite_administrativo'         => $request->boolean('tramite_administrativo'),
            'homecare'                       => $request->boolean('homecare'),
            'observaciones'                  => $request->observaciones,
        ];

        CausaEstancia::updateOrCreate(['paciente_id' => $paciente->id], $data);
        return back()->with('success','Causas de estancia actualizadas.');
    }

    public function guardarCamUci(Request $request, Paciente $paciente)
    {
        $request->validate([
            'resultado'    => 'required|in:positivo,negativo,no_evaluable',
            'rass_momento' => 'nullable|integer|between:-5,4',
            'observacion'  => 'nullable|string|max:500',
        ]);

        CamUci::updateOrCreate(
            ['paciente_id' => $paciente->id, 'fecha' => today()],
            [
                'usuario_id'   => auth()->id(),
                'resultado'    => $request->resultado,
                'rass_momento' => $request->rass_momento,
                'observacion'  => $request->observacion,
            ]
        );
        return back()->with('success','CAM-UCI registrado correctamente.');
    }

    public function guardarBundle(Request $request, Paciente $paciente)
    {
        $items = array_keys(BundleVentilacion::items());
        $data  = ['usuario_id' => auth()->id(), 'observaciones' => $request->observaciones];
        foreach ($items as $item) {
            $data[$item] = $request->boolean($item);
        }

        BundleVentilacion::updateOrCreate(
            ['paciente_id' => $paciente->id, 'fecha' => today()],
            $data
        );
        return back()->with('success','Bundle ventilador registrado.');
    }
}
