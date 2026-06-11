<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use Illuminate\Http\Request;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReporteDescargasController extends Controller
{
    // ── Colores corporativos ─────────────────────────────────────────────
    private const H_BG   = 'FF1a3a5c';   // encabezado fila
    private const H_FG   = 'FFFFFFFF';
    private const H2_BG  = 'FF2d6a9f';
    private const ALT_BG = 'FFF0F4FF';

    public function index()
    {
        return view('reportes.descargas');
    }

    public function descargar(Request $request)
    {
        $request->validate([
            'tipo_reporte' => 'required|in:epidemiologia,mortalidad,subunidad',
            'periodo'      => 'required|in:mensual,trimestral,semestral,anual',
            'fecha'        => 'required|date',
        ]);

        [$inicio, $fin, $label] = $this->resolverPeriodo(
            $request->periodo,
            Carbon::parse($request->fecha)
        );

        switch ($request->tipo_reporte) {
            case 'epidemiologia':
                $spreadsheet = $this->excelEpidemiologia($inicio, $fin, $label, $request->periodo);
                $nombre = "Perfil_Epidemiologico_{$label}";
                break;
            case 'mortalidad':
                $spreadsheet = $this->excelMortalidad($inicio, $fin, $label);
                $nombre = "Informe_Mortalidad_{$label}";
                break;
            default:
                $spreadsheet = $this->excelSubunidad($inicio, $fin, $label, $request->periodo);
                $nombre = "Reporte_Subunidad_{$label}";
                break;
        }

        $nombre = str_replace([' ', '/'], '_', $nombre) . '_' . now()->format('Ymd') . '.xlsx';
        $writer  = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'uci_desc_');
        $writer->save($tmpFile);

        return response()->download($tmpFile, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PERÍODO
    // ═══════════════════════════════════════════════════════════════════════

    private function resolverPeriodo(string $tipo, Carbon $fecha): array
    {
        switch ($tipo) {
            case 'mensual':
                $ini   = $fecha->copy()->startOfMonth();
                $fin   = $fecha->copy()->endOfMonth();
                $label = $ini->translatedFormat('F Y');
                break;
            case 'trimestral':
                $q     = (int)ceil($fecha->month / 3);
                $ini   = $fecha->copy()->month(($q - 1) * 3 + 1)->startOfMonth();
                $fin   = $ini->copy()->addMonths(3)->subSecond();
                $label = "Q{$q} {$ini->year}";
                break;
            case 'semestral':
                $s     = $fecha->month <= 6 ? 1 : 2;
                $ini   = $fecha->copy()->month($s === 1 ? 1 : 7)->startOfMonth();
                $fin   = $ini->copy()->addMonths(6)->subSecond();
                $label = "S{$s} {$ini->year}";
                break;
            default: // anual
                $ini   = $fecha->copy()->startOfYear();
                $fin   = $fecha->copy()->endOfYear();
                $label = (string)$ini->year;
                break;
        }
        return [$ini, $fin, $label];
    }

    private function mesesEnPeriodo(Carbon $ini, Carbon $fin): array
    {
        $meses = [];
        $cur   = $ini->copy()->startOfMonth();
        while ($cur->lte($fin)) {
            $meses[] = [$cur->copy()->startOfMonth(), $cur->copy()->endOfMonth(), $cur->translatedFormat('F Y')];
            $cur->addMonth();
        }
        return $meses;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EXCEL: PERFIL EPIDEMIOLÓGICO
    // ═══════════════════════════════════════════════════════════════════════

    private function excelEpidemiologia(Carbon $ini, Carbon $fin, string $label, string $periodo): Spreadsheet
    {
        $ss = $this->nuevoSpreadsheet("Perfil Epidemiológico — {$label}");

        $snaps    = Snapshot::whereBetween('fecha_snapshot', [$ini, $fin])->get();
        $egresados = Paciente::whereBetween('egreso_uci', [$ini, $fin->copy()->endOfDay()])->get();
        $fallec   = $egresados->where('tipo_egreso', 'fallecimiento')->count();
        $total    = $egresados->count();

        // ── Hoja 1: Resumen ──────────────────────────────────────────────
        $h1 = $ss->getActiveSheet()->setTitle('Resumen');
        $this->titulo($h1, "Perfil Epidemiológico UCI — {$label}", "Del {$ini->format('d/m/Y')} al {$fin->format('d/m/Y')}");

        $numExt = fn($v) => preg_match('/(-?\d+(?:[.,]\d+)?)/', str_replace(',', '.', (string)$v), $m) ? (float)$m[1] : null;

        $kpis = [
            ['Pacientes únicos en el período', $snaps->unique('paciente_id')->count()],
            ['Nuevos ingresos', $this->nuevosIngresos($snaps, $ini)],
            ['Egresos UCI totales', $total],
            ['Fallecimientos', $fallec],
            ['Mortalidad bruta (%)', $total > 0 ? round($fallec / $total * 100, 1) : 0],
            ['Estancia media egresados (días)', round($egresados->filter(fn($p) => $p->ingreso_uci)->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)) ?? 0, 1)],
            ['Promedio NEWS', $this->promEscala($snaps, 'news', $numExt)],
            ['Promedio SOFA', $this->promEscala($snaps, 'sofa', $numExt)],
            ['Promedio RASS', $this->promEscala($snaps, 'rass', $numExt)],
            ['Promedio Barthel', $this->promEscala($snaps, 'barthel', $numExt)],
            ['Pacientes con VMI', $snaps->filter(fn($s) => preg_match('/vmi|invasiv/i', $s->soporte_ventilatorio ?? ''))->unique('paciente_id')->count()],
        ];
        $this->tabla($h1, $kpis, ['Indicador', 'Valor'], 4);

        // Distribución por sexo
        $pacs    = Paciente::whereIn('id', $snaps->pluck('paciente_id')->unique())->get();
        $sexos   = [['Sexo', 'Pacientes'], ['Femenino', $pacs->where('sexo', 'F')->count()], ['Masculino', $pacs->where('sexo', 'M')->count()]];
        $this->tabla($h1, array_slice($sexos, 1), ['Sexo', 'Pacientes'], 4 + count($kpis) + 3, ['Sexo', 'Pacientes']);

        // Grupos de edad
        $grupos = [['< 18' => 0], ['18-40' => 0], ['41-60' => 0], ['61-75' => 0], ['> 75' => 0]];
        $gruposEdad = ['< 18' => 0, '18-40' => 0, '41-60' => 0, '61-75' => 0, '> 75' => 0];
        foreach ($pacs as $p) {
            if (!$p->edad) continue;
            if ($p->edad < 18)       $gruposEdad['< 18']++;
            elseif ($p->edad <= 40)  $gruposEdad['18-40']++;
            elseif ($p->edad <= 60)  $gruposEdad['41-60']++;
            elseif ($p->edad <= 75)  $gruposEdad['61-75']++;
            else                     $gruposEdad['> 75']++;
        }
        $edadRows = array_map(fn($k, $v) => [$k, $v], array_keys($gruposEdad), $gruposEdad);
        $fila = 4 + count($kpis) + 3 + 3 + count($sexos);
        $this->tabla($h1, $edadRows, ['Grupo Edad', 'Pacientes'], $fila);
        $h1->getColumnDimension('A')->setWidth(38);
        $h1->getColumnDimension('B')->setWidth(18);

        // ── Hoja 2: Top CIE-10 ──────────────────────────────────────────
        $h2 = $ss->createSheet()->setTitle('CIE-10');
        $this->titulo($h2, 'Top Códigos CIE-10', "Período: {$label}");
        $cie10map = [];
        foreach ($snaps->pluck('cie10')->filter() as $raw) {
            foreach ($this->parsearCie10((string)$raw) as $item) {
                $cie10map[$item['code']] = ['desc' => $item['desc'], 'n' => ($cie10map[$item['code']]['n'] ?? 0) + 1];
            }
        }
        arsort($cie10map);
        $cie10rows = array_map(fn($c, $d) => [$c, $d['desc'], $d['n']], array_keys($cie10map), $cie10map);
        $this->tabla($h2, array_slice($cie10rows, 0, 30), ['Código', 'Descripción', 'Snapshots'], 4);
        $h2->getColumnDimension('A')->setWidth(12);
        $h2->getColumnDimension('B')->setWidth(55);
        $h2->getColumnDimension('C')->setWidth(14);

        // ── Hoja 3: Mes a mes (si aplica) ───────────────────────────────
        if (in_array($periodo, ['trimestral', 'semestral', 'anual'])) {
            $h3 = $ss->createSheet()->setTitle('Mes a Mes');
            $this->titulo($h3, 'Desglose Mes a Mes', $label);
            $enc3 = ['Mes', 'Pac. únicos', 'Nuevos', 'Egresos', 'Fallec.', 'Mort. %', 'Estancia prom.', 'NEWS prom.', 'SOFA prom.'];
            $rows3 = [];
            foreach ($this->mesesEnPeriodo($ini, $fin) as [$mi, $mf, $ml]) {
                $sm  = Snapshot::whereBetween('fecha_snapshot', [$mi, $mf])->get();
                $em  = Paciente::whereBetween('egreso_uci', [$mi, $mf->copy()->endOfDay()])->get();
                $fm  = $em->where('tipo_egreso', 'fallecimiento')->count();
                $rows3[] = [
                    $ml,
                    $sm->unique('paciente_id')->count(),
                    $this->nuevosIngresos($sm, $mi),
                    $em->count(),
                    $fm,
                    $em->count() > 0 ? round($fm / $em->count() * 100, 1) : 0,
                    round($em->filter(fn($p) => $p->ingreso_uci)->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)) ?? 0, 1),
                    $this->promEscala($sm, 'news', $numExt),
                    $this->promEscala($sm, 'sofa', $numExt),
                ];
            }
            $this->tabla($h3, $rows3, $enc3, 4);
            foreach (range('A', 'I') as $l) $h3->getColumnDimension($l)->setAutoSize(true);
        }

        return $ss;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EXCEL: INFORME DE MORTALIDAD
    // ═══════════════════════════════════════════════════════════════════════

    private function excelMortalidad(Carbon $ini, Carbon $fin, string $label): Spreadsheet
    {
        $ss = $this->nuevoSpreadsheet("Informe de Mortalidad — {$label}");

        $fallecidos = Paciente::where('tipo_egreso', 'fallecimiento')
            ->whereBetween('egreso_uci', [$ini, $fin->copy()->endOfDay()])
            ->with(['snapshots', 'camUci', 'bundleVentilacion'])
            ->orderByDesc('egreso_uci')
            ->get();

        $numExt = fn($v) => preg_match('/(-?\d+(?:[.,]\d+)?)/', str_replace(',', '.', (string)$v), $m) ? (float)$m[1] : null;

        // ── Hoja 1: Resumen agregado ─────────────────────────────────────
        $h1   = $ss->getActiveSheet()->setTitle('Resumen');
        $n    = $fallecidos->count();
        $this->titulo($h1, "Informe de Mortalidad UCI — {$label}", "Fallecidos del {$ini->format('d/m/Y')} al {$fin->format('d/m/Y')} · Total: {$n}");

        $esVmi  = fn($s) => preg_match('/vmi|invasiv|mecanic/i', $s->soporte_ventilatorio ?? '');
        $esVaso = fn($s) => preg_match('/vasopresor|norepinefrina|vasopresina|adrenalina/i', $s->soporte_hemodinamico ?? '');
        $esIno  = fn($s) => preg_match('/inotr|dobutamina|milrinona|levosimendan/i', $s->soporte_hemodinamico ?? '');

        $conVmi  = $fallecidos->filter(fn($p) => $p->snapshots->filter($esVmi)->count() > 0)->count();
        $conVaso = $fallecidos->filter(fn($p) => $p->snapshots->filter($esVaso)->count() > 0)->count();
        $conDeli = $fallecidos->filter(fn($p) => $p->camUci->where('resultado', 'positivo')->count() > 0)->count();

        $kpis = [
            ['Total fallecidos en el período', $n],
            ['Con VMI (%)', $n > 0 ? round($conVmi / $n * 100, 1) : 0],
            ['Con vasopresor (%)', $n > 0 ? round($conVaso / $n * 100, 1) : 0],
            ['Con delirium CAM+ (%)', $n > 0 ? round($conDeli / $n * 100, 1) : 0],
            ['Estancia media (días)', round($fallecidos->filter(fn($p) => $p->ingreso_uci)->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)) ?? 0, 1)],
        ];
        $this->tabla($h1, $kpis, ['Indicador', 'Valor'], 4);
        $h1->getColumnDimension('A')->setWidth(38);
        $h1->getColumnDimension('B')->setWidth(16);

        // ── Hoja 2: Distribución CIE-10 ─────────────────────────────────
        $h2 = $ss->createSheet()->setTitle('CIE-10');
        $this->titulo($h2, 'Distribución por CIE-10', $label);
        $cie10map = [];
        foreach ($fallecidos as $p) {
            $estancia = ($p->ingreso_uci && $p->egreso_uci) ? (int)$p->ingreso_uci->diffInDays($p->egreso_uci) : $p->snapshots->count();
            $vistos = [];
            foreach ($p->snapshots->pluck('cie10')->filter() as $raw) {
                foreach ($this->parsearCie10((string)$raw) as $item) {
                    $c = $item['code'];
                    if (isset($vistos[$c])) continue;
                    $vistos[$c] = true;
                    if (!isset($cie10map[$c])) $cie10map[$c] = ['desc' => $item['desc'], 'n' => 0, 'est' => []];
                    $cie10map[$c]['n']++;
                    $cie10map[$c]['est'][] = $estancia;
                }
            }
        }
        uasort($cie10map, fn($a, $b) => $b['n'] <=> $a['n']);
        $cie10rows = array_map(fn($c, $d) => [
            $c, $d['desc'],
            $d['n'],
            $n > 0 ? round($d['n'] / $n * 100, 1) : 0,
            count($d['est']) > 0 ? round(array_sum($d['est']) / count($d['est']), 1) : 0,
        ], array_keys($cie10map), $cie10map);
        $this->tabla($h2, $cie10rows, ['Código', 'Descripción', 'Pacientes', '% del total', 'Estancia prom. (d)'], 4);
        $h2->getColumnDimension('A')->setWidth(12);
        $h2->getColumnDimension('B')->setWidth(55);
        foreach (['C','D','E'] as $l) $h2->getColumnDimension($l)->setWidth(16);

        // ── Hoja 3: Detalle por paciente ─────────────────────────────────
        $h3 = $ss->createSheet()->setTitle('Detalle Pacientes');
        $this->titulo($h3, 'Detalle Clínico por Paciente Fallecido', $label);
        $enc3 = [
            'Nombre', 'Documento', 'Edad', 'Sexo', 'Fecha Egreso',
            'Días UCI', 'NEWS ingreso', 'NEWS máx', 'SOFA ingreso', 'SOFA máx', 'Delta SOFA',
            'EVA máx', 'BPS máx', 'Días dolor EVA', 'Días dolor BPS',
            'RASS prom', 'RASS mín',
            'Barthel ingreso', 'Barthel último', 'IMS pico', 'Días hasta 1ª mov.', 'Tipo mov.',
            'Días VMI', 'Días Vasopresor', 'Días Inotrópico', 'VMI+Vaso simultáneo', '% VMI/estancia',
            'Días CAM+', 'Total CAM eval.', '% Delirium', 'Cumplimiento Bundle %',
            'CIE-10 principales',
        ];
        $rows3 = [];
        foreach ($fallecidos as $p) {
            $snaps = $p->snapshots->sortBy('fecha_snapshot')->values();
            $prim  = $snaps->first();
            $ult   = $snaps->last();
            $diasUci = ($p->ingreso_uci && $p->egreso_uci) ? (int)$p->ingreso_uci->diffInDays($p->egreso_uci) : $snaps->count();

            $newsSnaps = $snaps->filter(fn($s) => $s->news !== null);
            $newsAdm   = $newsSnaps->first() ? (float)$newsSnaps->first()->news : null;
            $newsMax   = $newsSnaps->count() ? (float)$newsSnaps->max('news') : null;

            $sofaNum   = fn($s) => $numExt($s->sofa);
            $sofaSnaps = $snaps->filter(fn($s) => $sofaNum($s) !== null);
            $sofaAdm   = $sofaNum($sofaSnaps->first() ?? new \stdClass());
            $sofaMax   = $sofaSnaps->count() ? $sofaSnaps->max(fn($s) => $sofaNum($s)) : null;
            $sofaUlt   = $sofaNum($sofaSnaps->last() ?? new \stdClass());
            $sofaDelta = ($sofaAdm !== null && $sofaUlt !== null) ? round($sofaUlt - $sofaAdm, 1) : null;

            $evaMax   = $snaps->filter(fn($s) => $s->eva !== null)->max(fn($s) => (float)$s->eva);
            $bpsMax   = $snaps->filter(fn($s) => $s->bps !== null)->max(fn($s) => (float)$s->bps);
            $dEva     = $snaps->filter(fn($s) => $s->eva !== null && (float)$s->eva > 4)->count();
            $dBps     = $snaps->filter(fn($s) => $s->bps !== null && (float)$s->bps > 6)->count();

            $rassSnaps = $snaps->filter(fn($s) => $s->rass !== null);
            $rassProm  = $rassSnaps->count() ? round($rassSnaps->avg(fn($s) => (float)$s->rass), 1) : null;
            $rassMin   = $rassSnaps->count() ? (float)$rassSnaps->min('rass') : null;

            $barthelSnaps = $snaps->filter(fn($s) => $s->barthel !== null);
            $barthelAdm   = $barthelSnaps->first()?->barthel;
            $barthelUlt   = $barthelSnaps->last()?->barthel;

            $imsData  = $snaps->map(fn($s) => $numExt($s->de_movilidad))->filter();
            $imsPico  = $imsData->count() ? (int)$imsData->max() : null;
            $dMov     = null;
            if ($p->ingreso_uci) {
                $priMov = $snaps->first(fn($s) => $numExt($s->de_movilidad) > 0);
                if ($priMov) $dMov = (int)$p->ingreso_uci->diffInDays($priMov->fecha_snapshot);
            }
            $movTxt = strtolower($snaps->filter(fn($s) => !empty($s->movilizacion))->last()?->movilizacion ?? '');
            $tipoMov = str_contains($movTxt, 'precoz') || str_contains($movTxt, 'temprana') || str_contains($movTxt, '< 48') ? 'Temprana'
                : (str_contains($movTxt, 'tardía') || str_contains($movTxt, 'tardia') || str_contains($movTxt, '> 48') ? 'Tardía' : '');

            $diasVmi     = $snaps->filter(fn($s) => preg_match('/vmi|invasiv|mecanic/i', $s->soporte_ventilatorio ?? ''))->count();
            $diasVaso    = $snaps->filter(fn($s) => preg_match('/vasopresor|norepinefrina|vasopresina|adrenalina/i', $s->soporte_hemodinamico ?? ''))->count();
            $diasIno     = $snaps->filter(fn($s) => preg_match('/inotr|dobutamina|milrinona|levosimendan/i', $s->soporte_hemodinamico ?? ''))->count();
            $diasVmiVaso = $snaps->filter(fn($s) =>
                preg_match('/vmi|invasiv|mecanic/i', $s->soporte_ventilatorio ?? '') &&
                preg_match('/vasopresor|norepinefrina|vasopresina|adrenalina/i', $s->soporte_hemodinamico ?? '')
            )->count();
            $pctVmi = $diasUci > 0 ? round($diasVmi / $diasUci * 100) : null;

            $camPos = $p->camUci->where('resultado', 'positivo')->count();
            $camTot = $p->camUci->count();
            $pctDeli = $camTot > 0 ? round($camPos / $camTot * 100) : null;

            $bundles   = $p->bundleVentilacion;
            $bundlePct = $bundles->count() > 0 ? round($bundles->avg(fn($b) => $b->cumplimiento())) : null;

            $cie10s = $snaps->pluck('cie10')->filter()
                ->flatMap(fn($raw) => $this->parsearCie10((string)$raw))
                ->unique('code')->pluck('code')->take(5)->implode(', ');

            $rows3[] = [
                $p->nombre, $p->documento, $p->edad, $p->sexo,
                $p->egreso_uci?->format('d/m/Y'),
                $diasUci, $newsAdm, $newsMax, $sofaAdm, $sofaMax, $sofaDelta,
                $evaMax, $bpsMax, $dEva, $dBps,
                $rassProm, $rassMin,
                $barthelAdm, $barthelUlt, $imsPico, $dMov, $tipoMov,
                $diasVmi, $diasVaso, $diasIno, $diasVmiVaso, $pctVmi,
                $camPos, $camTot, $pctDeli, $bundlePct,
                $cie10s,
            ];
        }
        $this->tabla($h3, $rows3, $enc3, 4);
        foreach (range('A', chr(64 + count($enc3))) as $l) $h3->getColumnDimension($l)->setAutoSize(true);

        return $ss;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EXCEL: REPORTE POR SUBUNIDAD
    // ═══════════════════════════════════════════════════════════════════════

    private function excelSubunidad(Carbon $ini, Carbon $fin, string $label, string $periodo): Spreadsheet
    {
        $ss = $this->nuevoSpreadsheet("Reporte por Subunidad — {$label}");

        $snaps = Snapshot::whereBetween('fecha_snapshot', [$ini, $fin])->get();
        $numExt = fn($v) => preg_match('/(-?\d+(?:[.,]\d+)?)/', str_replace(',', '.', (string)$v), $m) ? (float)$m[1] : null;

        // ── Hoja 1: Resumen por subunidad ───────────────────────────────
        $h1 = $ss->getActiveSheet()->setTitle('Resumen');
        $this->titulo($h1, "Reporte por Subunidad — {$label}", "Del {$ini->format('d/m/Y')} al {$fin->format('d/m/Y')}");

        $enc1 = ['Subunidad', 'Pac. únicos', 'Con VMI', 'Con vasopresor', 'Fallec.', 'Estancia prom. (d)', 'NEWS prom.', 'SOFA prom.', 'RASS prom.'];
        $rows1 = [];
        $subunidades = $snaps->groupBy('subunidad');
        foreach ($subunidades as $sub => $sSnaps) {
            $ids   = $sSnaps->pluck('paciente_id')->unique();
            $pacs  = Paciente::whereIn('id', $ids)->get();
            $fallec = $pacs->where('tipo_egreso', 'fallecimiento')->count();
            $rows1[] = [
                $sub ?: '(sin subunidad)',
                $ids->count(),
                $sSnaps->filter(fn($s) => preg_match('/vmi|invasiv/i', $s->soporte_ventilatorio ?? ''))->unique('paciente_id')->count(),
                $sSnaps->filter(fn($s) => preg_match('/vasopresor|norepinefrina/i', $s->soporte_hemodinamico ?? ''))->unique('paciente_id')->count(),
                $fallec,
                round($pacs->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)) ?? 0, 1),
                $this->promEscala($sSnaps, 'news', $numExt),
                $this->promEscala($sSnaps, 'sofa', $numExt),
                $this->promEscala($sSnaps, 'rass', $numExt),
            ];
        }
        $this->tabla($h1, $rows1, $enc1, 4);
        foreach (range('A', 'I') as $l) $h1->getColumnDimension($l)->setAutoSize(true);

        // ── Hoja 2: Mes a mes por subunidad ─────────────────────────────
        if (in_array($periodo, ['trimestral', 'semestral', 'anual'])) {
            $h2 = $ss->createSheet()->setTitle('Mes a Mes');
            $this->titulo($h2, 'Mes a Mes por Subunidad', $label);
            $subs = $snaps->pluck('subunidad')->unique()->filter()->sort()->values();
            $enc2 = array_merge(['Mes'], $subs->toArray());
            $rows2 = [];
            foreach ($this->mesesEnPeriodo($ini, $fin) as [$mi, $mf, $ml]) {
                $sm  = Snapshot::whereBetween('fecha_snapshot', [$mi, $mf])->get();
                $row = [$ml];
                foreach ($subs as $sub) {
                    $row[] = $sm->where('subunidad', $sub)->unique('paciente_id')->count();
                }
                $rows2[] = $row;
            }
            $this->tabla($h2, $rows2, $enc2, 4);
            foreach (range('A', chr(64 + count($enc2))) as $l) $h2->getColumnDimension($l)->setAutoSize(true);
        }

        // ── Hoja 3: Detalle pacientes ────────────────────────────────────
        $h3  = $ss->createSheet()->setTitle('Detalle Pacientes');
        $this->titulo($h3, 'Detalle Pacientes por Subunidad', $label);
        $enc3 = ['Nombre', 'Documento', 'Edad', 'Sexo', 'Subunidad', 'Cama', 'Ingreso UCI', 'Egreso UCI', 'Días UCI', 'Tipo Egreso', 'NEWS máx.', 'SOFA máx.', 'VMI', 'Vasopresor', 'CIE-10'];
        $ids  = $snaps->pluck('paciente_id')->unique();
        $pacs = Paciente::whereIn('id', $ids)->get()->keyBy('id');
        $rows3 = [];
        foreach ($snaps->groupBy('paciente_id') as $pid => $pSnaps) {
            $p = $pacs[$pid] ?? null;
            if (!$p) continue;
            $ult  = $pSnaps->sortByDesc('fecha_snapshot')->first();
            $newsMax = $pSnaps->filter(fn($s) => $s->news !== null)->max('news');
            $sofaMax = $pSnaps->filter(fn($s) => $numExt($s->sofa) !== null)->max(fn($s) => $numExt($s->sofa));
            $cie10s  = $pSnaps->pluck('cie10')->filter()->flatMap(fn($r) => $this->parsearCie10((string)$r))->unique('code')->pluck('code')->take(4)->implode(', ');
            $rows3[] = [
                $p->nombre, $p->documento, $p->edad, $p->sexo,
                $ult->subunidad, $ult->ubicacion,
                $p->ingreso_uci?->format('d/m/Y'), $p->egreso_uci?->format('d/m/Y'),
                ($p->ingreso_uci && $p->egreso_uci) ? (int)$p->ingreso_uci->diffInDays($p->egreso_uci) : null,
                $p->tipo_egreso,
                $newsMax, $sofaMax,
                $pSnaps->filter(fn($s) => preg_match('/vmi|invasiv/i', $s->soporte_ventilatorio ?? ''))->count() > 0 ? 'Sí' : 'No',
                $pSnaps->filter(fn($s) => preg_match('/vasopresor|norepinefrina/i', $s->soporte_hemodinamico ?? ''))->count() > 0 ? 'Sí' : 'No',
                $cie10s,
            ];
        }
        usort($rows3, fn($a, $b) => strcmp($a[4] ?? '', $b[4] ?? ''));
        $this->tabla($h3, $rows3, $enc3, 4);
        foreach (range('A', 'O') as $l) $h3->getColumnDimension($l)->setAutoSize(true);

        return $ss;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function nuevoSpreadsheet(string $titulo): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getProperties()->setCreator('UCI Panel — Clínica de Occidente')->setTitle($titulo);
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);
        return $ss;
    }

    private function titulo($hoja, string $t1, string $t2): void
    {
        $hoja->setCellValue('A1', $t1);
        $hoja->setCellValue('A2', $t2);
        $hoja->setCellValue('A3', 'Generado: ' . now()->format('d/m/Y H:i'));
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $hoja->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
        $hoja->getStyle('A3')->getFont()->setSize(9)->setItalic(true);
        $hoja->getStyle('A3')->getFont()->getColor()->setARGB('FF888888');
    }

    private function tabla($hoja, array $filas, array $encabezados, int $startRow): void
    {
        $colCount = count($encabezados);
        $endCol   = chr(64 + $colCount);

        // Encabezado
        foreach ($encabezados as $i => $enc) {
            $hoja->setCellValue(chr(65 + $i) . $startRow, $enc);
        }
        $hoja->getStyle("A{$startRow}:{$endCol}{$startRow}")
            ->getFont()->setBold(true)->getColor()->setARGB(self::H_FG);
        $hoja->getStyle("A{$startRow}:{$endCol}{$startRow}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB(self::H_BG);
        $hoja->getStyle("A{$startRow}:{$endCol}{$startRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Datos
        foreach ($filas as $ri => $fila) {
            $row = $startRow + 1 + $ri;
            foreach ((array)$fila as $ci => $val) {
                $hoja->setCellValue(chr(65 + $ci) . $row, $val ?? '');
            }
            if ($ri % 2 === 1) {
                $hoja->getStyle("A{$row}:{$endCol}{$row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(self::ALT_BG);
            }
        }

        // Borde exterior
        if (!empty($filas)) {
            $lastRow = $startRow + count($filas);
            $hoja->getStyle("A{$startRow}:{$endCol}{$lastRow}")
                ->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()->setARGB('FFCCCCCC');
        }
    }

    private function parsearCie10(string $raw): array
    {
        $parts = preg_split('/\s+(?=[A-Z]\d{2,4}[A-Z0-9]?-)/', trim($raw));
        $codes = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([A-Z]\d{2,4}[A-Z0-9]?)-(.+)$/s', $part, $m)) {
                $codes[] = ['code' => $m[1], 'desc' => mb_convert_case(trim($m[2]), MB_CASE_TITLE, 'UTF-8')];
            }
        }
        return $codes;
    }

    private function promEscala($snaps, string $campo, callable $numExt): float
    {
        $vals = $snaps->filter(fn($s) => $s->$campo !== null && $s->$campo !== '')
            ->map(fn($s) => $numExt($s->$campo))->filter();
        return $vals->isEmpty() ? 0 : round((float)$vals->avg(), 1);
    }

    private function nuevosIngresos($snaps, Carbon $ini): int
    {
        return $snaps->groupBy('paciente_id')
            ->filter(fn($g) => $g->min('fecha_snapshot') >= $ini)
            ->count();
    }
}
