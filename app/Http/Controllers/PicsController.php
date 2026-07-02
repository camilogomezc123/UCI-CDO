<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\PicsEvaluacion;
use App\Models\PicsRiesgo;
use App\Models\CamUci;
use App\Models\Snapshot;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PicsController extends Controller
{
    // ── Momentos válidos y ventanas de evaluación (días desde egreso) ─────────
    private const MOMENTOS = [
        'egreso' => ['desde' => 0,   'hasta' => 14,  'label' => 'Al egreso UCI'],
        '30d'    => ['desde' => 25,  'hasta' => 45,  'label' => '30 días'],
        '90d'    => ['desde' => 85,  'hasta' => 105, 'label' => '90 días'],
        '180d'   => ['desde' => 175, 'hasta' => 200, 'label' => '180 días'],
    ];

    // ── Ítems AMT-10 ──────────────────────────────────────────────────────────
    public const AMT_ITEMS = [
        'Sabe cuántos años tiene (±1 año)',
        'Sabe la hora aproximada (±1 hora)',
        'Recuerda la dirección dada al inicio ("Calle 10 # 5-20")',
        'Sabe en qué año estamos',
        'Sabe en qué institución está (nombre de la clínica/hospital)',
        'Reconoce al médico o enfermera por su rol',
        'Sabe su fecha de nacimiento (día y mes)',
        'Sabe en qué año ocurrió algo histórico importante (fin Segunda Guerra Mundial: 1945)',
        'Sabe quién es el Presidente de Colombia actualmente',
        'Cuenta hacia atrás desde 20 hasta 1 sin errores significativos',
    ];

    // ── Ítems HADS (14 ítems: 7 ansiedad + 7 depresión) ─────────────────────
    // Cada ítem: [pregunta, opciones en orden 0-3]
    public const HADS_ITEMS = [
        // Ansiedad (índices 0-6)
        ['Me siento tenso(a) o nervioso(a)', ['Nunca', 'A veces', 'Muchas veces', 'Casi siempre']],
        ['Siento una especie de temor como si algo malo fuera a suceder', ['No siento nada de eso', 'Sí, pero no me preocupa mucho', 'Sí, pero no muy intenso', 'Sí, y muy intenso']],
        ['Tengo la cabeza llena de preocupaciones', ['Solo ocasionalmente', 'A veces pero no muy a menudo', 'Muchas veces', 'Casi todo el día']],
        ['Puedo estar sentado tranquilamente y sentirme relajado(a)', ['Siempre', 'Generalmente', 'Pocas veces', 'Nunca']],
        ['Siento una especie de miedo como si tuviera "mariposas" en el estómago', ['Nunca', 'A veces', 'Con bastante frecuencia', 'Muy a menudo']],
        ['Me siento inquieto(a) como si tuviera que estar en movimiento', ['Nada en absoluto', 'No mucho', 'Bastante', 'Mucho']],
        ['Experimento de repente sensaciones de gran angustia o temor', ['Nunca', 'Pocas veces', 'Con bastante frecuencia', 'Muy a menudo']],
        // Depresión (índices 7-13)
        ['Sigo disfrutando de las cosas que antes me agradaban', ['Igual que antes', 'No tanto como antes', 'Solo un poco', 'Ya no disfruto de nada']],
        ['Soy capaz de reírme y ver el lado divertido de las cosas', ['Igual que antes', 'No tanto como antes', 'Pocas veces', 'Nunca']],
        ['Me siento alegre(a)', ['Casi siempre', 'A veces', 'No muy a menudo', 'Nunca']],
        ['Me siento lento(a) y torpe', ['Nunca', 'A veces', 'Muy a menudo', 'Casi siempre']],
        ['He perdido el interés por mi aspecto personal', ['Me cuido igual que siempre', 'Es posible que no me cuide tanto', 'No me preocupa tanto como debería', 'Totalmente']],
        ['Espero las cosas con ilusión', ['Igual que antes', 'Algo menos que antes', 'Mucho menos', 'Casi nunca']],
        ['Soy capaz de disfrutar un buen libro o programa de TV/radio', ['Muy a menudo', 'A veces', 'Pocas veces', 'Muy pocas veces']],
    ];

    // ── Ítems PC-PTSD-5 (Sí/No) ──────────────────────────────────────────────
    public const PCPTSD_ITEMS = [
        'Ha tenido pesadillas o pensamientos intrusivos sobre su experiencia en la UCI cuando no quería',
        'Ha intentado evitar pensar en lo que vivió en la UCI o ha evitado situaciones que se lo recuerdan',
        'Se ha sentido constantemente en alerta, vigilante o se asusta fácilmente',
        'Se ha sentido emocionalmente entumecido(a) o desconectado(a) de las demás personas',
        'Se ha sentido culpable o incapaz de dejar de culparse por lo que vivió en la UCI',
    ];

    // ── Ítems PTG-SF: Crecimiento Postraumático (0-5 cada uno) ───────────────
    public const PTG_ITEMS = [
        'He cambiado mis prioridades sobre lo que es importante en la vida',
        'Tengo una mayor apreciación por el valor de mi propia vida',
        'He desarrollado nuevos intereses o actividades',
        'Siento que puedo confiar más en mí mismo(a) para manejar las dificultades',
        'Tengo una mejor comprensión de las cuestiones espirituales o de vida',
        'Sé que puedo contar con las personas en momentos de crisis',
        'He establecido un nuevo camino o propósito para mi vida',
        'Tengo un mayor sentido de compasión hacia los demás',
        'Sé que soy capaz de manejar las dificultades mejor de lo que pensaba',
        'Hago mejor uso de mis energías y tiempo',
    ];

    // ── Ítems PICS-F: Cuidador familiar ──────────────────────────────────────
    public const PICSF_ITEMS = [
        ['Ha tenido pesadillas o recuerdos intrusivos sobre la estancia en UCI de su familiar', ['Nunca', 'Pocas veces', 'Frecuentemente', 'Casi siempre', 'Siempre']],
        ['Se ha sentido ansioso(a) o nervioso(a) con frecuencia', ['Nunca', 'Pocas veces', 'Frecuentemente', 'Casi siempre', 'Siempre']],
        ['Ha tenido dificultad para concentrarse en sus actividades diarias', ['Nunca', 'Pocas veces', 'Frecuentemente', 'Casi siempre', 'Siempre']],
        ['Ha tenido problemas para dormir relacionados con la situación de su familiar', ['Nunca', 'Pocas veces', 'Frecuentemente', 'Casi siempre', 'Siempre']],
        ['Ha sentido culpa relacionada con la enfermedad o la atención de su familiar', ['Nunca', 'Pocas veces', 'Frecuentemente', 'Casi siempre', 'Siempre']],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // CONTROLADOR PRINCIPAL
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        // Pacientes egresados de UCI en los últimos 180 días
        $egresados = Paciente::whereNotNull('egreso_uci')
            ->where('egreso_uci', '>=', now()->subDays(180))
            ->with(['picsEvaluaciones', 'picsRiesgo'])
            ->orderByDesc('egreso_uci')
            ->get();

        // Para cada paciente, calcular qué evaluaciones están pendientes
        $pacientes = $egresados->map(function ($p) {
            $diasDesdeEgreso = $p->egreso_uci->diffInDays(now());
            $evaluaciones    = $p->picsEvaluaciones->groupBy('momento');
            $pendientes      = [];
            $disponibles     = [];

            foreach (self::MOMENTOS as $mom => $ventana) {
                $tieneEval = isset($evaluaciones[$mom]);
                $enVentana = $diasDesdeEgreso >= $ventana['desde'];
                $vencida   = $diasDesdeEgreso > $ventana['hasta'];

                if ($enVentana && !$tieneEval) {
                    if ($vencida) {
                        $pendientes[] = ['momento' => $mom, 'label' => $ventana['label'], 'vencida' => true];
                    } else {
                        $disponibles[] = ['momento' => $mom, 'label' => $ventana['label']];
                    }
                }
            }

            return [
                'paciente'    => $p,
                'dias'        => $diasDesdeEgreso,
                'evaluaciones'=> $evaluaciones,
                'pendientes'  => $pendientes,
                'disponibles' => $disponibles,
                'riesgo'      => $p->picsRiesgo,
            ];
        });

        $stats = [
            'total'      => $pacientes->count(),
            'pendientes' => $pacientes->filter(fn($p) => count($p['pendientes']) > 0 || count($p['disponibles']) > 0)->count(),
            'alto_riesgo'=> $pacientes->filter(fn($p) => $p['riesgo']?->nivel_riesgo === 'alto')->count(),
            'evaluados'  => $pacientes->filter(fn($p) => $p['evaluaciones']->isNotEmpty())->count(),
        ];

        return view('pics.index', compact('pacientes', 'stats'));
    }

    // ── Calcular y guardar score de riesgo PICS ───────────────────────────────
    public function calcularRiesgo(Paciente $paciente)
    {
        $riesgo = $this->computarRiesgo($paciente);

        PicsRiesgo::updateOrCreate(
            ['paciente_id' => $paciente->id],
            $riesgo
        );

        return redirect()->route('pics.index')
            ->with('success', "Score de riesgo PICS calculado para {$paciente->nombre}: nivel {$riesgo['nivel_riesgo']} ({$riesgo['score_total']} puntos).");
    }

    // ── Formulario de evaluación ──────────────────────────────────────────────
    public function create(Paciente $paciente, string $momento)
    {
        abort_unless(array_key_exists($momento, self::MOMENTOS), 404);

        $existente = PicsEvaluacion::where('paciente_id', $paciente->id)
            ->where('momento', $momento)
            ->where('tipo', 'paciente')
            ->first();

        if ($existente) {
            return redirect()->route('pics.show', $existente)
                ->with('warning', 'Ya existe una evaluación para este momento. Puede verla aquí.');
        }

        $diasDesdeEgreso = $paciente->egreso_uci?->diffInDays(now()) ?? 0;
        $conPtg   = in_array($momento, ['90d', '180d']);
        $labelMom = self::MOMENTOS[$momento]['label'];

        return view('pics.form', compact(
            'paciente', 'momento', 'labelMom', 'diasDesdeEgreso', 'conPtg'
        ));
    }

    // ── Guardar evaluación ────────────────────────────────────────────────────
    public function store(Request $request, Paciente $paciente, string $momento)
    {
        abort_unless(array_key_exists($momento, self::MOMENTOS), 404);

        $tipo = $request->input('tipo', 'paciente');
        $datos = $request->input('datos', []);

        // ── Calcular scores ────────────────────────────────────────────────
        $amtResp    = array_map('boolval', $datos['amt'] ?? []);
        $amtScore   = array_sum($amtResp);

        $hadsResp   = array_map('intval', $datos['hads'] ?? []);
        $hadsAns    = array_sum(array_slice($hadsResp, 0, 7));
        $hadessDep  = array_sum(array_slice($hadsResp, 7, 7));

        $pcptsdResp  = array_map('boolval', $datos['pcptsd'] ?? []);
        $pcptsdScore = array_sum($pcptsdResp);

        $ptgResp  = array_map('intval', $datos['ptg'] ?? []);
        $ptgScore = array_sum($ptgResp);

        $picsfResp    = array_map('intval', $datos['picsf'] ?? []);
        $picsfDistres = array_sum($picsfResp);

        PicsEvaluacion::create([
            'paciente_id'       => $paciente->id,
            'usuario_id'        => auth()->id(),
            'momento'           => $momento,
            'tipo'              => $tipo,
            'fecha_evaluacion'  => now()->toDateString(),

            'disfagia'          => $datos['disfagia'] ?? null,

            'amt_respuestas'    => $amtResp ?: null,
            'amt_score'         => count($amtResp) === 10 ? $amtScore : null,

            'hads_respuestas'   => $hadsResp ?: null,
            'hads_ansiedad'     => count($hadsResp) >= 7 ? $hadsAns : null,
            'hads_depresion'    => count($hadsResp) >= 14 ? $hadessDep : null,

            'pcptsd_respuestas' => $pcptsdResp ?: null,
            'pcptsd_score'      => count($pcptsdResp) === 5 ? $pcptsdScore : null,

            'fatiga_score'      => isset($datos['fatiga']) ? (float)$datos['fatiga'] : null,
            'dolor_reposo'      => isset($datos['dolor_reposo']) ? (float)$datos['dolor_reposo'] : null,
            'dolor_movimiento'  => isset($datos['dolor_movimiento']) ? (float)$datos['dolor_movimiento'] : null,

            'ptg_respuestas'    => count($ptgResp) === 10 ? $ptgResp : null,
            'ptg_score'         => count($ptgResp) === 10 ? $ptgScore : null,

            'picsf_respuestas'  => $picsfResp ?: null,
            'picsf_distress'    => count($picsfResp) === 5 ? $picsfDistres : null,

            'observaciones'     => $datos['observaciones'] ?? null,
        ]);

        $eval = PicsEvaluacion::where('paciente_id', $paciente->id)
            ->where('momento', $momento)
            ->where('tipo', $tipo)
            ->latest()
            ->first();

        return redirect()->route('pics.show', $eval)
            ->with('success', 'Evaluación PICS guardada correctamente.');
    }

    // ── Ver resultado ─────────────────────────────────────────────────────────
    public function show(PicsEvaluacion $evaluacion)
    {
        $evaluacion->load('paciente', 'usuario');

        $anteriores = PicsEvaluacion::where('paciente_id', $evaluacion->paciente_id)
            ->where('tipo', $evaluacion->tipo)
            ->where('id', '!=', $evaluacion->id)
            ->orderBy('fecha_evaluacion')
            ->get();

        $riesgo   = $evaluacion->paciente->picsRiesgo;
        $conPtg   = in_array($evaluacion->momento, ['90d', '180d']);
        $esFamilia = $evaluacion->tipo === 'familia';

        return view('pics.show', compact('evaluacion', 'anteriores', 'riesgo', 'conPtg', 'esFamilia'));
    }

    // ── Formulario PICS-F (cuidador familiar) ─────────────────────────────────
    public function createFamilia(Paciente $paciente, string $momento)
    {
        abort_unless(array_key_exists($momento, self::MOMENTOS), 404);

        $existente = PicsEvaluacion::where('paciente_id', $paciente->id)
            ->where('momento', $momento)
            ->where('tipo', 'familia')
            ->first();

        if ($existente) {
            return redirect()->route('pics.show', $existente)
                ->with('warning', 'Ya existe una evaluación familiar para este momento.');
        }

        $labelMom = self::MOMENTOS[$momento]['label'];
        $conPtg   = false;
        $diasDesdeEgreso = $paciente->egreso_uci?->diffInDays(now()) ?? 0;

        return view('pics.form', compact('paciente', 'momento', 'labelMom', 'diasDesdeEgreso', 'conPtg'))
            ->with('tipoFamilia', true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CÁLCULO DE RIESGO PICS
    // ─────────────────────────────────────────────────────────────────────────

    private function computarRiesgo(Paciente $paciente): array
    {
        $score    = 0;
        $factores = [];

        // Días en UCI
        $diasUci = 0;
        if ($paciente->ingreso_uci && $paciente->egreso_uci) {
            $diasUci = (int) $paciente->ingreso_uci->diffInDays($paciente->egreso_uci);
        }
        if ($diasUci >= 14) {
            $score += 2;
            $factores[] = "Estancia ≥ 14 días ({$diasUci} días): +2";
        } elseif ($diasUci >= 7) {
            $score += 1;
            $factores[] = "Estancia 7-13 días ({$diasUci} días): +1";
        }

        // Días en ventilación mecánica (snapshots con soporte ventilatorio)
        $diasVm = Snapshot::where('paciente_id', $paciente->id)
            ->whereNotNull('soporte_ventilatorio')
            ->where('soporte_ventilatorio', '!=', '')
            ->where('soporte_ventilatorio', '!=', 'No')
            ->count();
        if ($diasVm >= 7) {
            $score += 3;
            $factores[] = "VM ≥ 7 días ({$diasVm} días): +3";
        } elseif ($diasVm >= 3) {
            $score += 2;
            $factores[] = "VM 3-6 días ({$diasVm} días): +2";
        } elseif ($diasVm >= 1) {
            $score += 1;
            $factores[] = "VM 1-2 días ({$diasVm} días): +1";
        }

        // Días con delirium positivo (CAM-UCI)
        $diasDelirium = CamUci::where('paciente_id', $paciente->id)
            ->where('resultado', 'positivo')
            ->count();
        if ($diasDelirium >= 4) {
            $score += 3;
            $factores[] = "Delirium ≥ 4 días ({$diasDelirium} días): +3";
        } elseif ($diasDelirium >= 2) {
            $score += 2;
            $factores[] = "Delirium 2-3 días ({$diasDelirium} días): +2";
        } elseif ($diasDelirium >= 1) {
            $score += 1;
            $factores[] = "Delirium 1 día: +1";
        }

        // Edad
        $edad = (int) ($paciente->edad ?? 0);
        if ($edad >= 65) {
            $score += 2;
            $factores[] = "Edad ≥ 65 años ({$edad} años): +2";
        }

        // Nivel de riesgo
        $nivel = match(true) {
            $score >= 7 => 'alto',
            $score >= 4 => 'medio',
            default     => 'bajo',
        };

        return [
            'fecha_calculo' => now()->toDateString(),
            'dias_uci'      => $diasUci,
            'dias_vm'       => $diasVm,
            'dias_delirium' => $diasDelirium,
            'edad'          => $edad,
            'score_total'   => $score,
            'nivel_riesgo'  => $nivel,
            'factores'      => $factores,
        ];
    }
}
