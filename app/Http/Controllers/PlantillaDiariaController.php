<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CamUci;
use App\Models\BundleVentilacion;
use App\Models\CausaEstancia;
use App\Models\TransfusionDiaria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlantillaDiariaController extends Controller
{
    public function index()
    {
        $sub = Snapshot::subqueryUltimoPorPaciente();

        // Pacientes activos con datos de su último snapshot
        $activos = Paciente::where('pacientes.activo', true)
            ->joinSub($sub, 'ls', fn($j) => $j->on('pacientes.id', '=', 'ls.paciente_id'))
            ->join('snapshots', 'snapshots.id', '=', 'ls.snap_id')
            ->select(
                'pacientes.*',
                'snapshots.ubicacion',
                'snapshots.subunidad',
                'snapshots.soporte_ventilatorio',
                'snapshots.soporte_hemodinamico',
                'snapshots.news',
                'snapshots.sofa',
            )
            ->orderBy('snapshots.subunidad')
            ->orderBy('snapshots.ubicacion')
            ->get();

        // IDs con CAM-UCI ya registrado hoy
        $conCamHoy = CamUci::whereDate('fecha', today())->pluck('paciente_id')->flip();
        // IDs con Bundle ya registrado hoy
        $conBundleHoy = BundleVentilacion::whereDate('fecha', today())->pluck('paciente_id')->flip();
        // IDs con causas de estancia registradas
        $conCausas = CausaEstancia::pluck('paciente_id')->flip();
        // IDs con transfusión registrada hoy
        $conTransfusionHoy = TransfusionDiaria::whereDate('fecha', today())->pluck('paciente_id')->flip();

        // Agrupar pendientes
        $sinIngreso     = $activos->whereNull('ingreso_uci');
        $sinCamUci      = $activos->filter(fn($p) => !isset($conCamHoy[$p->id]));
        $sinBundle      = $activos->filter(fn($p) =>
            !isset($conBundleHoy[$p->id]) && $this->esVmi($p->soporte_ventilatorio)
        );
        $pendientesEgreso = $activos->whereNotNull('salida_hospitalizacion')->whereNull('egreso_uci');
        $sinCausas      = $activos->filter(fn($p) => !isset($conCausas[$p->id]));

        $bundleItems    = BundleVentilacion::items();
        $tiposHemoder   = TransfusionDiaria::tiposDisponibles();

        return view('plantilla-diaria', compact(
            'activos', 'sinIngreso', 'sinCamUci', 'sinBundle',
            'pendientesEgreso', 'sinCausas', 'bundleItems',
            'conTransfusionHoy', 'tiposHemoder'
        ));
    }

    public function guardar(Request $request)
    {
        $guardados = 0;

        DB::transaction(function () use ($request, &$guardados) {

            // ── 1. Fechas de ingreso UCI ─────────────────────────────────────
            foreach ($request->ingreso ?? [] as $pacienteId => $fecha) {
                if (!empty($fecha)) {
                    $updated = Paciente::where('id', $pacienteId)
                        ->whereNull('ingreso_uci')
                        ->update(['ingreso_uci' => $fecha]);
                    $guardados += $updated;
                }
            }

            // ── 2. CAM-UCI ───────────────────────────────────────────────────
            foreach ($request->cam ?? [] as $pacienteId => $datos) {
                if (empty($datos['resultado'])) continue;
                CamUci::updateOrCreate(
                    ['paciente_id' => $pacienteId, 'fecha' => today()],
                    [
                        'usuario_id'   => auth()->id(),
                        'resultado'    => $datos['resultado'],
                        'rass_momento' => isset($datos['rass_momento']) && $datos['rass_momento'] !== ''
                            ? (int)$datos['rass_momento'] : null,
                        'observacion'  => $datos['observacion'] ?? null,
                    ]
                );
                $guardados++;
            }

            // ── 3. Bundle de ventilación ─────────────────────────────────────
            $bundleItems = array_keys(BundleVentilacion::items());
            foreach ($request->bundle ?? [] as $pacienteId => $datos) {
                $bundleData = [
                    'usuario_id'   => auth()->id(),
                    'observaciones'=> $datos['observaciones'] ?? null,
                ];
                foreach ($bundleItems as $item) {
                    $bundleData[$item] = isset($datos[$item]) && $datos[$item] === '1';
                }
                BundleVentilacion::updateOrCreate(
                    ['paciente_id' => $pacienteId, 'fecha' => today()],
                    $bundleData
                );
                $guardados++;
            }

            // ── 4. Egresos UCI ───────────────────────────────────────────────
            $tiposValidos = ['mejoria', 'alta_casa', 'traslado', 'fallecimiento'];
            foreach ($request->egreso ?? [] as $pacienteId => $datos) {
                if (empty($datos['fecha']) || empty($datos['tipo'])) continue;
                if (!in_array($datos['tipo'], $tiposValidos)) continue;
                Paciente::where('id', $pacienteId)->update([
                    'egreso_uci'  => $datos['fecha'],
                    'tipo_egreso' => $datos['tipo'],
                    'activo'      => false,
                ]);
                $guardados++;
            }

            // ── 5. Transfusiones ─────────────────────────────────────────────
            foreach ($request->transfusion ?? [] as $pacienteId => $datos) {
                if (empty($datos['productos'])) continue;
                TransfusionDiaria::updateOrCreate(
                    ['paciente_id' => $pacienteId, 'fecha' => today()],
                    [
                        'usuario_id'       => auth()->id(),
                        'productos'        => $datos['productos'],
                        'unidades_totales' => max(1, (int)($datos['unidades'] ?? 1)),
                        'observaciones'    => $datos['observaciones'] ?? null,
                    ]
                );
                $guardados++;
            }

            // ── 6. Causas de estancia prolongada ─────────────────────────────
            foreach ($request->causas ?? [] as $pacienteId => $datos) {
                $tieneAlguna = collect([
                    'pendiente_cirugia', 'condicion_clinica', 'ventilacion_mecanica',
                    'pendiente_cama_hospitalizacion', 'tramite_administrativo', 'homecare',
                ])->some(fn($k) => !empty($datos[$k]));

                if (!$tieneAlguna) continue;

                CausaEstancia::updateOrCreate(
                    ['paciente_id' => $pacienteId],
                    [
                        'usuario_id'                     => auth()->id(),
                        'pendiente_cirugia'              => !empty($datos['pendiente_cirugia']),
                        'condicion_clinica'              => !empty($datos['condicion_clinica']),
                        'ventilacion_mecanica'           => !empty($datos['ventilacion_mecanica']),
                        'pendiente_cama_hospitalizacion' => !empty($datos['pendiente_cama_hospitalizacion']),
                        'tramite_administrativo'         => !empty($datos['tramite_administrativo']),
                        'homecare'                       => !empty($datos['homecare']),
                        'observaciones'                  => $datos['observaciones'] ?? null,
                    ]
                );
                $guardados++;
            }
        });

        return redirect()->route('plantilla-diaria')
            ->with('success', "Se guardaron {$guardados} registro(s) correctamente.");
    }

    private function esVmi(?string $soporte): bool
    {
        if (!$soporte) return false;
        $s = strtolower($soporte);
        return str_contains($s, 'vmi') || str_contains($s, 'invasiv') || str_contains($s, 'mecanic');
    }
}
