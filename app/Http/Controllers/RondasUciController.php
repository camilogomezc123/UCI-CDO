<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CamUci;
use App\Models\BundleVentilacion;
use App\Models\BalanceHidrico;
use App\Models\NutricionDiaria;
use App\Models\AntibioticosUci;
use App\Models\GoalOfCare;
use App\Models\Dispositivo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RondasUciController extends Controller
{
    public function index(Request $request)
    {
        $fecha = $request->input('fecha') ? Carbon::parse($request->input('fecha')) : today();

        $sub = Snapshot::subqueryUltimoPorPaciente();

        $pacientes = Paciente::where('activo', true)
            ->joinSub($sub, 'ls', fn($j) => $j->on('pacientes.id', '=', 'ls.paciente_id'))
            ->join('snapshots', 'snapshots.id', '=', 'ls.snap_id')
            ->select(
                'pacientes.*',
                'snapshots.ubicacion', 'snapshots.subunidad',
                'snapshots.soporte_ventilatorio', 'snapshots.soporte_hemodinamico',
                'snapshots.news', 'snapshots.sofa',
                'snapshots.rass', 'snapshots.eva', 'snapshots.bps',
                'snapshots.movilizacion', 'snapshots.diagnosticos', 'snapshots.metas_clinicas',
            )
            ->orderBy('snapshots.subunidad')
            ->orderBy('snapshots.ubicacion')
            ->get();

        $ids = $pacientes->pluck('id');

        $cams     = CamUci::whereDate('fecha', $fecha)->whereIn('paciente_id', $ids)->get()->keyBy('paciente_id');
        $bundles  = BundleVentilacion::whereDate('fecha', $fecha)->whereIn('paciente_id', $ids)->get()->keyBy('paciente_id');
        $balances = BalanceHidrico::whereDate('fecha', $fecha)->whereIn('paciente_id', $ids)->get()->keyBy('paciente_id');
        $nuts     = NutricionDiaria::whereDate('fecha', $fecha)->whereIn('paciente_id', $ids)->get()->keyBy('paciente_id');
        $atbs     = AntibioticosUci::where('activo', true)->whereIn('paciente_id', $ids)->get()->groupBy('paciente_id');
        $gocs     = GoalOfCare::whereIn('paciente_id', $ids)
                        ->orderByDesc('fecha_conversacion')->get()
                        ->keyBy('paciente_id');
        $disps    = Dispositivo::where('activo', true)->whereIn('paciente_id', $ids)->get()->groupBy('paciente_id');

        $pendientes = [
            'sin_cam'      => $pacientes->filter(fn($p) => !isset($cams[$p->id]))->count(),
            'sin_bundle'   => $pacientes->filter(fn($p) => !isset($bundles[$p->id]))->count(),
            'sin_balance'  => $pacientes->filter(fn($p) => !isset($balances[$p->id]))->count(),
            'sin_nutricion'=> $pacientes->filter(fn($p) => !isset($nuts[$p->id]))->count(),
            'sin_goc'      => $pacientes->filter(fn($p) => !isset($gocs[$p->id]))->count(),
            'delirium'     => collect($cams)->filter(fn($c) => $c->resultado === 'positivo')->count(),
        ];

        return view('rondas-uci.index', compact(
            'pacientes', 'fecha',
            'cams', 'bundles', 'balances', 'nuts', 'atbs', 'gocs', 'disps',
            'pendientes'
        ));
    }

    public function guardar(Request $request)
    {
        $fecha    = $request->fecha ?? today()->toDateString();
        $guardados = 0;

        DB::transaction(function () use ($request, $fecha, &$guardados) {

            // ── CAM-UCI ───────────────────────────────────────────────────────────
            foreach ($request->cam ?? [] as $pid => $d) {
                if (empty($d['resultado'])) continue;
                CamUci::updateOrCreate(
                    ['paciente_id' => $pid, 'fecha' => $fecha],
                    [
                        'usuario_id'       => auth()->id(),
                        'resultado'        => $d['resultado'],
                        'subtipo_delirium' => $d['subtipo_delirium'] ?? null,
                        'rass_momento'     => ($d['rass_momento'] ?? '') !== '' ? (int)$d['rass_momento'] : null,
                        'observacion'      => $d['observacion'] ?? null,
                    ]
                );
                $guardados++;
            }

            // ── Bundle ventilación (incluye todos los campos ABCDEF+S) ────────────
            $boolItems = array_keys(BundleVentilacion::items());
            foreach ($request->bundle ?? [] as $pid => $d) {
                $bd = [
                    'usuario_id'              => auth()->id(),
                    'sat_resultado'           => $d['sat_resultado']            ?? null,
                    'sbt_resultado'           => $d['sbt_resultado']            ?? null,
                    'nivel_movilizacion'      => ($d['nivel_movilizacion'] ?? '') !== '' ? (int)$d['nivel_movilizacion'] : null,
                    'motivo_no_movilizacion'  => $d['motivo_no_movilizacion']   ?? null,
                    'familia_reunion_clinica' => isset($d['familia_reunion_clinica']) && $d['familia_reunion_clinica'] === '1',
                    'rcsq_score'              => ($d['rcsq_score'] ?? '')         !== '' ? (int)$d['rcsq_score'] : null,
                    'interrupciones_nocturnas'=> ($d['interrupciones_nocturnas'] ?? '') !== '' ? (int)$d['interrupciones_nocturnas'] : null,
                    'observaciones'           => $d['observaciones']             ?? null,
                ];
                foreach ($boolItems as $item) {
                    $bd[$item] = isset($d[$item]) && $d[$item] === '1';
                }
                BundleVentilacion::updateOrCreate(
                    ['paciente_id' => $pid, 'fecha' => $fecha],
                    $bd
                );
                $guardados++;
            }

            // ── Pacientes: rass_objetivo + nutric_score ───────────────────────────
            foreach ($request->paciente ?? [] as $pid => $d) {
                $upd = [];
                if (($d['rass_objetivo'] ?? '') !== '') {
                    $upd['rass_objetivo'] = (int)$d['rass_objetivo'];
                }
                if (($d['nutric_score'] ?? '') !== '') {
                    $upd['nutric_score'] = (float)$d['nutric_score'];
                }
                if ($upd) {
                    Paciente::where('id', $pid)->update($upd);
                }
            }

            // ── Balance hídrico (todos los volúmenes) ─────────────────────────────
            $volCampos = [
                'vol_cristaloides', 'vol_coloides', 'vol_hemoderivados',
                'vol_nutricion', 'vol_medicamentos', 'vol_otros_ingresos',
                'vol_diuresis', 'vol_drenajes', 'vol_perdidas_insensibles', 'vol_otros_egresos',
            ];
            foreach ($request->balance ?? [] as $pid => $d) {
                $bd = ['paciente_id' => $pid, 'usuario_id' => auth()->id(), 'fecha' => $fecha];
                $total = 0;
                foreach ($volCampos as $c) {
                    $bd[$c] = (int)($d[$c] ?? 0);
                    $total += $bd[$c];
                }
                $bd['observaciones'] = $d['observaciones'] ?? null;
                // Solo guardar si hay algún volumen registrado
                if ($total > 0) {
                    BalanceHidrico::updateOrCreate(
                        ['paciente_id' => $pid, 'fecha' => $fecha],
                        $bd
                    );
                    $guardados++;
                }
            }

            // ── Nutrición diaria ──────────────────────────────────────────────────
            foreach ($request->nutricion ?? [] as $pid => $d) {
                if (empty($d['via'])) continue;
                NutricionDiaria::updateOrCreate(
                    ['paciente_id' => $pid, 'fecha' => $fecha],
                    [
                        'usuario_id'            => auth()->id(),
                        'via'                   => $d['via'],
                        'kcal_meta'             => ($d['kcal_meta'] ?? '') ?: null,
                        'kcal_aportadas'        => ($d['kcal_aportadas'] ?? '') ?: null,
                        'proteinas_g_meta'      => ($d['proteinas_g_meta'] ?? '') ?: null,
                        'proteinas_g_aportadas' => ($d['proteinas_g_aportadas'] ?? '') ?: null,
                        'motivo_suspension'     => $d['motivo_suspension'] ?? null,
                    ]
                );
                $guardados++;
            }

            // ── PCT control de antibióticos activos ───────────────────────────────
            foreach ($request->pct ?? [] as $atbId => $valor) {
                if ($valor === '' || $valor === null) continue;
                AntibioticosUci::where('id', $atbId)->update([
                    'pct_control_72h' => (float)$valor,
                ]);
                $guardados++;
            }

            // ── Goal of Care rápido (solo cuando no existía GoC) ──────────────────
            foreach ($request->goc ?? [] as $pid => $d) {
                if (empty($d['nivel_esfuerzo'])) continue;
                // Solo crear si no hay GoC vigente
                if (!GoalOfCare::where('paciente_id', $pid)->exists()) {
                    GoalOfCare::create([
                        'paciente_id'        => $pid,
                        'usuario_id'         => auth()->id(),
                        'fecha_conversacion' => $fecha,
                        'nivel_esfuerzo'     => $d['nivel_esfuerzo'],
                        'dnr'                => isset($d['dnr']) && $d['dnr'] === '1',
                        'quien_participo'    => ($d['quien_participo'] ?? '') ?: 'Ronda UCI',
                        'plan_cuidados'      => null,
                    ]);
                    $guardados++;
                }
            }
        });

        return redirect()->route('rondas-uci.index', ['fecha' => $fecha])
            ->with('success', "Ronda guardada: {$guardados} registros actualizados.");
    }
}
