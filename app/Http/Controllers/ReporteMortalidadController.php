<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReporteMortalidadController extends Controller
{
    private const GRUPOS_MORBILIDAD = [
        'Sepsis / Choque Séptico'  => ['sepsis','choque séptico','choque septico','shock séptico','shock septico','septicemia','a41','a40'],
        'Choque Cardiogénico'      => ['choque cardiogénico','choque cardiogenico','shock cardiogénico','shock cardiogenico','r57.0'],
        'Infarto / SCA'            => ['infarto','iamcest','iamsest','sca','síndrome coronario agudo','sindrome coronario agudo','i21','i22'],
        'Falla Cardíaca'           => ['falla cardíaca','falla cardiaca','insuficiencia cardíaca','insuficiencia cardiaca','icc','i50'],
        'TEP / Tromboembolismo'    => ['tep','tromboembolismo pulmonar','embolia pulmonar','i26'],
        'SDRA / Falla Resp. Aguda' => ['sdra','síndrome de dificultad respiratoria aguda','sdr agudo','insuficiencia respiratoria aguda','ira respiratoria','j96','j80'],
        'ACV / Stroke'             => ['acv','accidente cerebrovascular','stroke','ictus','hemorragia cerebral','hemorragia subaracnoidea','i61','i63','i64'],
        'Choque Hemorrágico'       => ['choque hipovolémico','choque hipovolemico','choque hemorrágico','choque hemorragico','r57.1','r57.8'],
        'Pancreatitis Grave'       => ['pancreatitis','pancreatis grave','k85'],
        'Politrauma'               => ['politrauma','traumatismo múltiple','traumatismo multiple'],
    ];

    public function index(Request $request)
    {
        $desde = $request->filled('desde')
            ? Carbon::parse($request->desde)->startOfDay()
            : now()->subDays(365);
        $hasta = $request->filled('hasta')
            ? Carbon::parse($request->hasta)->endOfDay()
            : now();

        $fallecidos = Paciente::where('tipo_egreso', 'fallecimiento')
            ->whereBetween('egreso_uci', [$desde, $hasta])
            ->with(['snapshots', 'camUci', 'bundleVentilacion'])
            ->orderByDesc('egreso_uci')
            ->get();

        $pacientesData = $fallecidos->map(fn($p) => $this->metricas($p));
        $agregado      = $this->calcularAgregado($pacientesData);
        $morbilidad    = $this->calcularMorbilidad($fallecidos, $pacientesData->count());

        return view('reportes.mortalidad', compact(
            'pacientesData', 'agregado', 'morbilidad', 'desde', 'hasta'
        ));
    }

    private function metricas(Paciente $p): array
    {
        $snaps   = $p->snapshots->sortBy('fecha_snapshot')->values();
        $primero = $snaps->first();
        $ultimo  = $snaps->last();

        $diasUci = ($p->ingreso_uci && $p->egreso_uci)
            ? (int)$p->ingreso_uci->diffInDays($p->egreso_uci)
            : $snaps->count();

        $toNum = fn($v) => ($v !== null && preg_match('/([-]?\d+(?:[.,]\d+)?)/', str_replace(',', '.', (string)$v), $m))
            ? (float)$m[1] : null;

        // ── NEWS ─────────────────────────────────────────────────────────
        $newsSnaps = $snaps->filter(fn($s) => $s->news !== null);
        $newsAdm   = $newsSnaps->first() ? (float)$newsSnaps->first()->news : null;
        $newsUlt   = $newsSnaps->last()  ? (float)$newsSnaps->last()->news  : null;
        $newsMax   = $newsSnaps->count() ? (float)$newsSnaps->max('news')   : null;
        $newsTend  = ($newsAdm !== null && $newsUlt !== null)
            ? ($newsUlt > $newsAdm ? '↑ Deterioro' : ($newsUlt < $newsAdm ? '↓ Mejoría' : '→ Estable'))
            : null;

        // ── SOFA ─────────────────────────────────────────────────────────
        $sofaSnaps = $snaps->filter(fn($s) => $toNum($s->sofa) !== null);
        $sofaAdm   = $toNum($sofaSnaps->first()?->sofa);
        $sofaUlt   = $toNum($sofaSnaps->last()?->sofa);
        $sofaMax   = $sofaSnaps->count() ? $sofaSnaps->max(fn($s) => $toNum($s->sofa)) : null;
        $sofaDelta = ($sofaAdm !== null && $sofaUlt !== null) ? round($sofaUlt - $sofaAdm, 1) : null;
        $sofaTend  = $sofaDelta === null ? null
            : ($sofaDelta > 0 ? "↑ +{$sofaDelta} (deterioro)" : ($sofaDelta < 0 ? "↓ {$sofaDelta} (mejoría)" : '→ Estable'));

        // ── Dolor ────────────────────────────────────────────────────────
        $evaMax       = $snaps->filter(fn($s) => $s->eva !== null)->max(fn($s) => (float)$s->eva);
        $bpsMax       = $snaps->filter(fn($s) => $s->bps !== null)->max(fn($s) => (float)$s->bps);
        $diasDolorEva = $snaps->filter(fn($s) => $s->eva !== null && (float)$s->eva > 4)->count();
        $diasDolorBps = $snaps->filter(fn($s) => $s->bps !== null && (float)$s->bps > 6)->count();

        // ── Sedación / RASS ──────────────────────────────────────────────
        $rassSnaps = $snaps->filter(fn($s) => $s->rass !== null);
        $rassProm  = $rassSnaps->count() ? round($rassSnaps->avg(fn($s) => (float)$s->rass), 1) : null;
        $rassMin   = $rassSnaps->count() ? (float)$rassSnaps->min('rass') : null;

        // ── Nutrición ────────────────────────────────────────────────────
        $mustUlt      = $snaps->filter(fn($s) => $s->must !== null)->last()?->must;
        $nutricionUlt = $snaps->filter(fn($s) => !empty($s->estado_nutricional))->last()?->estado_nutricional;

        // ── Barthel ──────────────────────────────────────────────────────
        $barthelSnaps = $snaps->filter(fn($s) => $s->barthel !== null);
        $barthelAdm   = $barthelSnaps->first()?->barthel;
        $barthelUlt   = $barthelSnaps->last()?->barthel;

        // ── IMS / Movilización ───────────────────────────────────────────
        $imsData = $snaps->map(fn($s) => [
            'fecha' => $s->fecha_snapshot,
            'val'   => $toNum($s->de_movilidad),
        ])->filter(fn($x) => $x['val'] !== null)->values();

        $imsPico      = $imsData->count() ? (int)$imsData->max('val') : null;
        $diasHastaMov = null;
        if ($p->ingreso_uci && $imsData->isNotEmpty()) {
            $primera = $imsData->first(fn($x) => $x['val'] > 0);
            if ($primera) {
                $diasHastaMov = (int)$p->ingreso_uci->diffInDays($primera['fecha']);
            }
        }
        $movTexto = strtolower($snaps->filter(fn($s) => !empty($s->movilizacion))->last()?->movilizacion ?? '');
        $tipoMov  = (str_contains($movTexto,'precoz') || str_contains($movTexto,'< 48') || str_contains($movTexto,'temprana'))
            ? 'Temprana'
            : ((str_contains($movTexto,'tardía') || str_contains($movTexto,'> 48') || str_contains($movTexto,'tardia'))
                ? 'Tardía' : null);

        // ── Soportes ─────────────────────────────────────────────────────
        $esVmi  = fn($s) => $s->soporte_ventilatorio  && preg_match('/vmi|invasiv|mecanic/i', $s->soporte_ventilatorio);
        $esVaso = fn($s) => $s->soporte_hemodinamico && preg_match('/vasopresor|norepinefrina|vasopresina|adrenalina/i', $s->soporte_hemodinamico);
        $esIno  = fn($s) => $s->soporte_hemodinamico && preg_match('/inotr|dobutamina|milrinona|levosimendan/i', $s->soporte_hemodinamico);

        $diasVmi     = $snaps->filter($esVmi)->count();
        $diasVaso    = $snaps->filter($esVaso)->count();
        $diasIno     = $snaps->filter($esIno)->count();
        $diasVmiVaso = $snaps->filter(fn($s) => $esVmi($s) && $esVaso($s))->count();
        $pctVmiDias  = $diasUci > 0 ? round($diasVmi / $diasUci * 100) : null;

        // ── CAM-UCI / Delirium ───────────────────────────────────────────
        $cam    = $p->camUci;
        $camPos = $cam->where('resultado', 'positivo')->count();
        $camTot = $cam->count();
        $pctDeli = $camTot > 0 ? round($camPos / $camTot * 100) : null;

        // ── Bundle ABCDEF ────────────────────────────────────────────────
        $bundles    = $p->bundleVentilacion;
        $bundleDias = $bundles->count();
        $bundlePct  = $bundleDias > 0 ? round($bundles->avg(fn($b) => $b->cumplimiento())) : null;

        // ── CIE-10 y diagnósticos ────────────────────────────────────────
        $cie10s = $snaps->pluck('cie10')->unique()->filter()->values();
        $diags  = $snaps->pluck('diagnosticos')->unique()->filter()->values();

        // ── Grupo morbilidad ─────────────────────────────────────────────
        $txt    = strtolower($cie10s->merge($diags)->implode(' '));
        $grupos = [];
        foreach (self::GRUPOS_MORBILIDAD as $nombre => $kws) {
            foreach ($kws as $kw) {
                if (str_contains($txt, $kw)) { $grupos[] = $nombre; break; }
            }
        }

        return [
            'p'            => $p,
            'diasUci'      => $diasUci,
            // NEWS
            'newsAdm'      => $newsAdm,
            'newsUlt'      => $newsUlt,
            'newsMax'      => $newsMax,
            'newsTend'     => $newsTend,
            // SOFA
            'sofaAdm'      => $sofaAdm,
            'sofaUlt'      => $sofaUlt,
            'sofaMax'      => $sofaMax,
            'sofaDelta'    => $sofaDelta,
            'sofaTend'     => $sofaTend,
            // Dolor / sedación
            'evaMax'       => $evaMax,
            'bpsMax'       => $bpsMax,
            'diasDolorEva' => $diasDolorEva,
            'diasDolorBps' => $diasDolorBps,
            'rassProm'     => $rassProm,
            'rassMin'      => $rassMin,
            // Nutrición
            'mustUlt'      => $mustUlt,
            'nutricionUlt' => $nutricionUlt,
            // Funcionalidad
            'barthelAdm'   => $barthelAdm,
            'barthelUlt'   => $barthelUlt,
            'imsPico'      => $imsPico,
            'diasHastaMov' => $diasHastaMov,
            'tipoMov'      => $tipoMov,
            // Soportes
            'diasVmi'      => $diasVmi,
            'diasVaso'     => $diasVaso,
            'diasIno'      => $diasIno,
            'diasVmiVaso'  => $diasVmiVaso,
            'pctVmiDias'   => $pctVmiDias,
            // Delirium
            'camPos'       => $camPos,
            'camTot'       => $camTot,
            'pctDeli'      => $pctDeli,
            // Bundle
            'bundleDias'   => $bundleDias,
            'bundlePct'    => $bundlePct,
            // Diagnósticos
            'cie10s'       => $cie10s,
            'diags'        => $diags,
            'grupos'       => $grupos,
        ];
    }

    private function calcularAgregado(\Illuminate\Support\Collection $d): array
    {
        $n = $d->count();
        if ($n === 0) return ['total' => 0];

        $avg = fn(string $k) => ($v = $d->filter(fn($x) => $x[$k] !== null)->avg($k)) !== null ? round((float)$v, 1) : null;
        $pct = fn(callable $fn) => round($d->filter($fn)->count() / $n * 100);

        return [
            'total'           => $n,
            'dias_uci_avg'    => $avg('diasUci'),
            'news_max_avg'    => $avg('newsMax'),
            'news_adm_avg'    => $avg('newsAdm'),
            'sofa_max_avg'    => $avg('sofaMax'),
            'sofa_adm_avg'    => $avg('sofaAdm'),
            'pct_vmi'         => $pct(fn($x) => $x['diasVmi'] > 0),
            'pct_vaso'        => $pct(fn($x) => $x['diasVaso'] > 0),
            'pct_ino'         => $pct(fn($x) => $x['diasIno'] > 0),
            'pct_delirium'    => $pct(fn($x) => ($x['camPos'] ?? 0) > 0),
            'pct_bundle_bajo' => $pct(fn($x) => $x['bundlePct'] !== null && $x['bundlePct'] < 50),
            'dias_vmi_avg'    => $avg('diasVmi'),
            'dias_vaso_avg'   => $avg('diasVaso'),
            'ims_pico_avg'    => $avg('imsPico'),
            'pct_mov_temp'    => $pct(fn($x) => $x['tipoMov'] === 'Temprana'),
            'pct_dolor'       => $pct(fn($x) => ($x['diasDolorEva'] ?? 0) > 0 || ($x['diasDolorBps'] ?? 0) > 0),
        ];
    }

    private function calcularMorbilidad(\Illuminate\Support\Collection $fallecidos, int $total): array
    {
        // Collect CIE-10 codes actually present in patients' snapshots
        $codigoMap = []; // cie10 => ['count' => n, 'pacientes' => [...], 'estancias' => [...]]

        foreach ($fallecidos as $p) {
            $estancia = ($p->ingreso_uci && $p->egreso_uci)
                ? (int)$p->ingreso_uci->diffInDays($p->egreso_uci)
                : $p->snapshots->count();

            // Get unique non-empty CIE-10 values for this patient
            $codigos = $p->snapshots->pluck('cie10')
                ->filter(fn($v) => !empty(trim((string)$v)))
                ->map(fn($v) => trim((string)$v))
                ->unique()
                ->values();

            foreach ($codigos as $cie) {
                if (!isset($codigoMap[$cie])) {
                    $codigoMap[$cie] = ['count' => 0, 'ids' => [], 'estancias' => []];
                }
                // Count each patient only once per code
                if (!in_array($p->id, $codigoMap[$cie]['ids'])) {
                    $codigoMap[$cie]['count']++;
                    $codigoMap[$cie]['ids'][]      = $p->id;
                    $codigoMap[$cie]['estancias'][] = $estancia;
                }
            }
        }

        // Build result sorted by count desc
        $result = [];
        foreach ($codigoMap as $cie => $data) {
            $result[$cie] = [
                'count'        => $data['count'],
                'pct'          => $total > 0 ? round($data['count'] / $total * 100) : 0,
                'estancia_avg' => count($data['estancias']) > 0
                    ? round(array_sum($data['estancias']) / count($data['estancias']), 1)
                    : 0,
                'ids'          => $data['ids'],
            ];
        }

        uasort($result, fn($a, $b) => $b['count'] <=> $a['count']);
        return $result;
    }
}
