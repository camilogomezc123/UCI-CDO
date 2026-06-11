<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\NotaPaciente;
use App\Models\CausaEstancia;
use App\Models\CamUci;
use App\Models\BundleVentilacion;
use App\Models\TransfusionDiaria;
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

        // CAM-UCI
        $camUciRegistros = $paciente->camUci()->with('usuario')->limit(14)->get();
        $camUciHoy       = $camUciRegistros->firstWhere('fecha', today()->toDateString());

        // Bundle ventilador
        $bundleRegistros    = $paciente->bundleVentilacion()->with('usuario')->limit(14)->get();
        $bundleHoy          = $bundleRegistros->firstWhere('fecha', today()->toDateString());
        $cumplimientoBundle = $paciente->cumplimientoBundle();

        // Tendencia escalas (últimos 30 snapshots)
        $tendencia = $paciente->snapshots()
            ->orderBy('fecha_snapshot')
            ->limit(30)
            ->get(['fecha_snapshot','news','sofa','rass','eva','bps','barthel']);

        // Snapshot anterior para detectar dispositivos retirados
        $snapshotAnterior = null;
        if ($ultimoSnapshot) {
            $snapshotAnterior = $paciente->snapshots()
                ->where('fecha_snapshot', '<', $ultimoSnapshot->fecha_snapshot)
                ->first();
        }

        // Detección de dispositivos invasivos
        $dispositivosStatus = $this->detectarDispositivos(
            $ultimoSnapshot?->observaciones ?? '',
            $snapshotAnterior?->observaciones ?? '',
            $snapshotAnterior !== null
        );

        // Transfusiones
        $transfusionHoy        = TransfusionDiaria::where('paciente_id', $paciente->id)
                                    ->where('fecha', today())->first();
        $transfusionesRecientes = $paciente->transfusiones()->with('usuario')->limit(10)->get();

        return view('pacientes.show', compact(
            'paciente','ultimoSnapshot','historial',
            'notas','causaEstancia','diasVmi','diasVasopresor','diasInotropico',
            'camUciRegistros','camUciHoy','bundleRegistros','bundleHoy',
            'cumplimientoBundle','tendencia',
            'snapshotAnterior','dispositivosStatus',
            'transfusionHoy','transfusionesRecientes'
        ));
    }

    // ─── Dispositivos invasivos ──────────────────────────────────────────────────

    private function detectarDispositivos(string $obsHoy, string $obsAyer, bool $tieneAyer): array
    {
        $hoy  = strtolower($obsHoy);
        $ayer = strtolower($obsAyer);

        $definiciones = [
            'sonda_vesical'   => ['Sonda Vesical/Uretral',    'bi-droplet-fill', 'danger',
                ['sonda vesical','sonda uretral','catéter urinario','cateter urinario','svu','svc','foley']],
            'cateter_central' => ['Catéter Central (CVC)',    'bi-diagram-3',    'primary',
                ['catéter central','cateter central','cvc','línea central','linea central','acceso central','subclavia','yugular']],
            'linea_arterial'  => ['Línea Arterial',           'bi-activity',     'warning',
                ['línea arterial','linea arterial','catéter arterial','cateter arterial',' la ','l.a.','radial']],
            'sng'             => ['Sonda Nasogástrica (SNG)', 'bi-arrow-down',   'secondary',
                ['sonda nasogástrica','sonda nasogastrica','sng','sonda ng']],
        ];

        $result = [];
        foreach ($definiciones as $key => [$nombre, $icono, $color, $keywords]) {
            $enHoy  = collect($keywords)->contains(fn($k) => str_contains($hoy, $k));
            $enAyer = $tieneAyer && collect($keywords)->contains(fn($k) => str_contains($ayer, $k));

            if ($enHoy || $enAyer) {
                $result[$key] = [
                    'nombre'   => $nombre,
                    'icono'    => $icono,
                    'color'    => $color,
                    'activo'   => $enHoy,
                    'nuevo'    => $enHoy && !$enAyer && $tieneAyer,
                    'retirado' => !$enHoy && $enAyer,
                ];
            }
        }
        return $result;
    }

    // ─── CRUD básico ─────────────────────────────────────────────────────────────

    public function actualizarIngreso(Request $request, Paciente $paciente)
    {
        $request->validate(['ingreso_uci' => 'required|date']);
        $paciente->update(['ingreso_uci' => $request->ingreso_uci]);
        $accion = $paciente->wasChanged('ingreso_uci') ? 'actualizada' : 'registrada';
        return back()->with('success', "Fecha de ingreso a UCI {$accion} correctamente.");
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
            'tipo_egreso' => 'required|in:mejoria,alta_casa,traslado,fallecimiento',
        ]);
        $eraActivo = $paciente->activo;
        $paciente->update([
            'egreso_uci'  => $request->egreso_uci,
            'tipo_egreso' => $request->tipo_egreso,
            'activo'      => false,
        ]);
        $msg = $eraActivo ? 'Egreso de UCI registrado. Paciente marcado como inactivo.' : 'Egreso de UCI corregido correctamente.';
        return back()->with('success', $msg);
    }

    public function reactivarPaciente(Paciente $paciente)
    {
        $paciente->update([
            'egreso_uci'  => null,
            'tipo_egreso' => null,
            'activo'      => true,
        ]);
        return back()->with('success', 'Paciente reactivado. Egreso anulado correctamente.');
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

    public function guardarTransfusion(Request $request, Paciente $paciente)
    {
        $request->validate([
            'productos'        => 'required|string|max:200',
            'unidades_totales' => 'required|integer|min:1|max:50',
            'observaciones'    => 'nullable|string|max:500',
        ]);
        TransfusionDiaria::updateOrCreate(
            ['paciente_id' => $paciente->id, 'fecha' => today()],
            [
                'usuario_id'       => auth()->id(),
                'productos'        => $request->productos,
                'unidades_totales' => $request->unidades_totales,
                'observaciones'    => $request->observaciones,
            ]
        );
        return back()->with('success','Transfusión registrada.');
    }

    public function eliminarTransfusion(Paciente $paciente, TransfusionDiaria $transfusion)
    {
        if ($transfusion->paciente_id !== $paciente->id) {
            return back()->with('error', 'Registro no válido.');
        }
        $transfusion->delete();
        return back()->with('success', 'Registro de transfusión eliminado.');
    }
}
