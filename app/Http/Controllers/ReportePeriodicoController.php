<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CargaArchivo;
use App\Models\CausaEstancia;
use App\Models\CamUci;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReportePeriodicoController extends Controller
{
    public function index(Request $request)
    {
        $tipo  = $request->get('tipo', 'semanal');
        $fecha = $request->get('fecha') ? Carbon::parse($request->get('fecha')) : now();

        [$inicio, $fin, $etiquetaPeriodo] = $this->resolverPeriodo($tipo, $fecha);

        $datos = $this->calcularPeriodo($inicio, $fin);

        // Desglose mes a mes para trimestral y anual
        $mesMes = in_array($tipo, ['trimestral', 'anual'])
            ? $this->calcularMesMes($inicio, $fin)
            : [];

        return view('reportes.periodicos', compact('datos', 'tipo', 'inicio', 'fin', 'etiquetaPeriodo', 'mesMes'));
    }

    public function datos(Request $request)
    {
        $tipo  = $request->get('tipo', 'semanal');
        $fecha = $request->get('fecha') ? Carbon::parse($request->get('fecha')) : now();

        [$inicio, $fin] = $this->resolverPeriodo($tipo, $fecha);

        return response()->json($this->calcularPeriodo($inicio, $fin));
    }

    public function descargar(Request $request)
    {
        $tipo  = $request->get('tipo', 'semanal');
        $fecha = $request->get('fecha') ? Carbon::parse($request->get('fecha')) : now();

        [$inicio, $fin, $etiquetaPeriodo] = $this->resolverPeriodo($tipo, $fecha);
        $datos  = $this->calcularPeriodo($inicio, $fin);
        $mesMes = in_array($tipo, ['trimestral', 'anual'])
            ? $this->calcularMesMes($inicio, $fin)
            : [];

        $spreadsheet = $this->generarExcel($datos, $mesMes, $etiquetaPeriodo, $tipo);

        $nombreArchivo = 'Reporte_UCI_' . str_replace(' ', '_', $etiquetaPeriodo) . '_' . now()->format('Ymd_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'uci_reporte_');
        $writer->save($tmpFile);

        return response()->download($tmpFile, $nombreArchivo, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    // ── Resolución de período ─────────────────────────────────────────────────

    private function resolverPeriodo(string $tipo, Carbon $fecha): array
    {
        switch ($tipo) {
            case 'mensual':
                $inicio = $fecha->copy()->startOfMonth();
                $fin    = $fecha->copy()->endOfMonth();
                $label  = 'Reporte Mensual ' . $inicio->translatedFormat('F Y');
                break;

            case 'trimestral':
                $trimestre = (int) ceil($fecha->month / 3);
                $inicio    = $fecha->copy()->month(($trimestre - 1) * 3 + 1)->startOfMonth();
                $fin       = $inicio->copy()->addMonths(3)->subSecond();
                $label     = "Q{$trimestre} " . $inicio->year;
                break;

            case 'anual':
                $inicio = $fecha->copy()->startOfYear();
                $fin    = $fecha->copy()->endOfYear();
                $label  = 'Reporte Anual ' . $inicio->year;
                break;

            default: // semanal
                $inicio = $fecha->copy()->startOfWeek(Carbon::MONDAY);
                $fin    = $fecha->copy()->endOfWeek(Carbon::SUNDAY);
                $label  = 'Semana ' . $inicio->weekOfYear . ' — ' . $inicio->year;
                break;
        }

        return [$inicio, $fin, $label];
    }

    // ── Desglose mes a mes (para trimestral y anual) ─────────────────────────

    private function calcularMesMes(Carbon $inicio, Carbon $fin): array
    {
        $meses  = [];
        $cursor = $inicio->copy()->startOfMonth();

        while ($cursor->lte($fin)) {
            $mesInicio = $cursor->copy()->startOfMonth();
            $mesFin    = $cursor->copy()->endOfMonth();
            $datos     = $this->calcularPeriodo($mesInicio, $mesFin);
            $meses[]   = [
                'mes'   => $cursor->translatedFormat('F Y'),
                'datos' => $datos,
            ];
            $cursor->addMonth();
        }

        return $meses;
    }

    // ── Cálculo del período ───────────────────────────────────────────────────

    private function calcularPeriodo(Carbon $inicio, Carbon $fin): array
    {
        $cargas = CargaArchivo::whereBetween('created_at', [$inicio->startOfDay(), $fin->copy()->endOfDay()])
            ->with('usuario')->get();

        $snapshotsPeriodo = Snapshot::whereBetween('fecha_snapshot', [$inicio, $fin])->get();

        $ocupacionDiaria = $snapshotsPeriodo
            ->groupBy(fn($s) => $s->fecha_snapshot->format('Y-m-d'))
            ->map(fn($g) => $g->unique('paciente_id')->count())
            ->sortKeys();

        $nuevosIds = Snapshot::whereBetween('fecha_snapshot', [$inicio, $fin])
            ->select('paciente_id', DB::raw('MIN(fecha_snapshot) as primera'))
            ->groupBy('paciente_id')
            ->havingRaw('MIN(fecha_snapshot) >= ?', [$inicio])
            ->pluck('paciente_id');
        $nuevosIngresos = $nuevosIds->count();

        $egresados      = Paciente::whereBetween('egreso_uci', [$inicio, $fin->copy()->endOfDay()])->get();
        $totalEgresados = $egresados->count();

        $avgEstanciaEgresados = $egresados
            ->filter(fn($p) => $p->ingreso_uci)
            ->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci));

        $salidaHosp      = Paciente::whereBetween('salida_hospitalizacion', [$inicio, $fin->copy()->endOfDay()])->get();
        $totalSalidaHosp = $salidaHosp->count();
        $avgEsperaEgreso = $salidaHosp
            ->filter(fn($p) => $p->egreso_uci)
            ->avg(fn($p) => Carbon::parse($p->salida_hospitalizacion)->diffInHours($p->egreso_uci));

        $avgOcupacion = $ocupacionDiaria->avg() ?: 0;

        $porSubunidad = $snapshotsPeriodo
            ->groupBy('subunidad')
            ->map(fn($g) => $g->unique('paciente_id')->count())
            ->sortDesc();

        $porCriterio = $snapshotsPeriodo
            ->unique('paciente_id')
            ->groupBy('criterio_atencion')
            ->map->count()
            ->sortDesc();

        $alertasNews = $snapshotsPeriodo
            ->filter(fn($s) => $s->news !== null && (int)$s->news >= 5)
            ->unique('paciente_id')->count();

        $alertasSofa = $snapshotsPeriodo
            ->filter(function ($s) {
                if (!$s->sofa) return false;
                preg_match('/(\d+)/', $s->sofa, $m);
                return isset($m[1]) && (int)$m[1] >= 10;
            })
            ->unique('paciente_id')->count();

        $conVmi = $snapshotsPeriodo
            ->filter(fn($s) => !empty($s->soporte_ventilatorio) &&
                (str_contains(strtolower($s->soporte_ventilatorio), 'vmi') ||
                 str_contains(strtolower($s->soporte_ventilatorio), 'invasiv')))
            ->unique('paciente_id')->count();

        $movilizacionTemprana = $snapshotsPeriodo
            ->filter(fn($s) => str_contains($s->movilizacion ?? '', '< 48'))
            ->unique('paciente_id')->count();

        $causas = CausaEstancia::all();
        $distribucionCausas = [
            'Pendiente cirugía'      => $causas->where('pendiente_cirugia', true)->count(),
            'Condición clínica'      => $causas->where('condicion_clinica', true)->count(),
            'Ventilación mecánica'   => $causas->where('ventilacion_mecanica', true)->count(),
            'Pendiente cama hosp.'   => $causas->where('pendiente_cama_hospitalizacion', true)->count(),
            'Trámite administrativo' => $causas->where('tramite_administrativo', true)->count(),
            'Homecare'               => $causas->where('homecare', true)->count(),
        ];

        // CAM-UCI
        $camRegistros = CamUci::whereBetween('fecha', [$inicio->toDateString(), $fin->toDateString()])->get();
        $camPositivos   = $camRegistros->where('resultado', 'positivo')->count();
        $camNegativos   = $camRegistros->where('resultado', 'negativo')->count();
        $camNoEval      = $camRegistros->where('resultado', 'no_evaluable')->count();
        $camTotal       = $camRegistros->count();
        $camPctDelirium = $camTotal > 0 ? round($camPositivos / $camTotal * 100, 1) : 0;
        $camPacientesConDelirium = $camRegistros->where('resultado', 'positivo')->unique('paciente_id')->count();
        $camPorDia = $camRegistros
            ->groupBy(fn($c) => $c->fecha->format('Y-m-d'))
            ->map(fn($g) => [
                'fecha'     => $g->first()->fecha->format('d/m/Y'),
                'positivos' => $g->where('resultado', 'positivo')->count(),
                'negativos' => $g->where('resultado', 'negativo')->count(),
                'no_eval'   => $g->where('resultado', 'no_evaluable')->count(),
                'total'     => $g->count(),
            ])
            ->sortKeys()
            ->values()
            ->toArray();
        $camDatos = [
            'total'            => $camTotal,
            'positivos'        => $camPositivos,
            'negativos'        => $camNegativos,
            'no_evaluables'    => $camNoEval,
            'pct_delirium'     => $camPctDelirium,
            'pac_con_delirium' => $camPacientesConDelirium,
            'por_dia'          => $camPorDia,
        ];

        $numerico = fn($val) => preg_match('/(-?\d+(?:\.\d+)?)/', (string)$val, $m) ? (float)$m[1] : null;
        $avgEscala = function (string $campo) use ($snapshotsPeriodo, $numerico): float {
            $valores = $snapshotsPeriodo
                ->filter(fn($s) => $s->$campo !== null && $s->$campo !== '')
                ->map(fn($s) => $numerico($s->$campo))
                ->filter(fn($v) => $v !== null);
            return $valores->isEmpty() ? 0 : round($valores->avg(), 1);
        };

        $promediosEscalas = [
            'NEWS'    => $avgEscala('news'),
            'SOFA'    => $avgEscala('sofa'),
            'RASS'    => $avgEscala('rass'),
            'BARTHEL' => $avgEscala('barthel'),
        ];

        $capacidadTotal = 75;
        $rotacion = $capacidadTotal > 0 ? round($nuevosIngresos / $capacidadTotal, 2) : 0;

        return [
            'periodo'              => ['inicio' => $inicio->format('d/m/Y'), 'fin' => $fin->format('d/m/Y')],
            'totalCargas'          => $cargas->count(),
            'nuevosIngresos'       => $nuevosIngresos,
            'totalEgresados'       => $totalEgresados,
            'totalSalidaHosp'      => $totalSalidaHosp,
            'avgEstanciaEgresados' => round($avgEstanciaEgresados ?? 0, 1),
            'avgEsperaEgreso'      => round($avgEsperaEgreso ?? 0, 1),
            'avgOcupacion'         => round($avgOcupacion, 1),
            'rotacionCamas'        => $rotacion,
            'alertasNews'          => $alertasNews,
            'alertasSofa'          => $alertasSofa,
            'conVmi'               => $conVmi,
            'movilizacionTemprana' => $movilizacionTemprana,
            'porSubunidad'         => $porSubunidad->toArray(),
            'porCriterio'          => $porCriterio->toArray(),
            'distribucionCausas'   => $distribucionCausas,
            'promediosEscalas'     => $promediosEscalas,
            'camUci'               => $camDatos,
            'ocupacionDiaria'      => $ocupacionDiaria->map(
                fn($v, $k) => ['fecha' => Carbon::parse($k)->format('d/m'), 'total' => $v]
            )->values()->toArray(),
        ];
    }

    // ── Generación del Excel ──────────────────────────────────────────────────

    private function generarExcel(array $datos, array $mesMes, string $etiquetaPeriodo, string $tipo): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('UCI Panel — Clínica de Occidente')
            ->setTitle($etiquetaPeriodo);

        $this->hojaResumen($spreadsheet->getActiveSheet(), $datos, $etiquetaPeriodo);

        if (!empty($mesMes)) {
            $hojaMes = $spreadsheet->createSheet();
            $hojaMes->setTitle('Mes a Mes');
            $this->hojaMesMes($hojaMes, $mesMes);
        }

        $hojaCam = $spreadsheet->createSheet();
        $hojaCam->setTitle('CAM-UCI Delirium');
        $this->hojaCamUci($hojaCam, $datos, $etiquetaPeriodo);

        return $spreadsheet;
    }

    private function hojaResumen($hoja, array $datos, string $titulo): void
    {
        $hoja->setTitle('Resumen');

        // Título
        $hoja->setCellValue('A1', 'UCI — Clínica de Occidente');
        $hoja->setCellValue('A2', $titulo);
        $hoja->setCellValue('A3', 'Del ' . $datos['periodo']['inicio'] . ' al ' . $datos['periodo']['fin']);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A2')->getFont()->setBold(true)->setSize(12);

        // KPIs
        $row = 5;
        $kpis = [
            ['Indicador', 'Valor'],
            ['Nuevos ingresos', $datos['nuevosIngresos']],
            ['Egresos UCI', $datos['totalEgresados']],
            ['Salidas a hospitalización', $datos['totalSalidaHosp']],
            ['Estancia promedio egresados (días)', $datos['avgEstanciaEgresados']],
            ['Espera promedio egreso (horas)', $datos['avgEsperaEgreso']],
            ['Ocupación promedio diaria', $datos['avgOcupacion']],
            ['Rotación de camas', $datos['rotacionCamas']],
            ['Pacientes NEWS ≥ 5', $datos['alertasNews']],
            ['Pacientes SOFA ≥ 10', $datos['alertasSofa']],
            ['Pacientes con VMI', $datos['conVmi']],
            ['Movilización temprana (< 48h)', $datos['movilizacionTemprana']],
        ];
        foreach ($kpis as $i => $kpi) {
            $hoja->setCellValue('A' . ($row + $i), $kpi[0]);
            $hoja->setCellValue('B' . ($row + $i), $kpi[1]);
        }
        $hoja->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);

        // Escalas clínicas
        $row += count($kpis) + 2;
        $hoja->setCellValue('A' . $row, 'Escalas Clínicas (promedio período)');
        $hoja->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($datos['promediosEscalas'] as $escala => $val) {
            $hoja->setCellValue('A' . $row, $escala);
            $hoja->setCellValue('B' . $row, $val);
            $row++;
        }

        // Por subunidad
        $row += 2;
        $hoja->setCellValue('A' . $row, 'Pacientes por Subunidad');
        $hoja->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($datos['porSubunidad'] as $sub => $n) {
            $hoja->setCellValue('A' . $row, $sub);
            $hoja->setCellValue('B' . $row, $n);
            $row++;
        }

        // Causas estancia
        $row += 2;
        $hoja->setCellValue('A' . $row, 'Causas de Estancia Prolongada');
        $hoja->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        foreach ($datos['distribucionCausas'] as $causa => $n) {
            $hoja->setCellValue('A' . $row, $causa);
            $hoja->setCellValue('B' . $row, $n);
            $row++;
        }

        // CAM-UCI
        $row += 2;
        $hoja->setCellValue('A' . $row, 'CAM-UCI — Evaluación de Delirium');
        $hoja->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $cam = $datos['camUci'];
        $filasCAM = [
            ['Total evaluaciones CAM-UCI',      $cam['total']],
            ['Positivo — Delirium presente',    $cam['positivos']],
            ['Negativo — Sin delirium',          $cam['negativos']],
            ['No evaluable (RASS ≤ -3)',         $cam['no_evaluables']],
            ['% Evaluaciones con delirium',      $cam['pct_delirium'] . '%'],
            ['Pacientes únicos con delirium',    $cam['pac_con_delirium']],
        ];
        foreach ($filasCAM as $fila) {
            $hoja->setCellValue('A' . $row, $fila[0]);
            $hoja->setCellValue('B' . $row, $fila[1]);
            $row++;
        }

        $hoja->getColumnDimension('A')->setWidth(40);
        $hoja->getColumnDimension('B')->setWidth(20);
    }

    private function hojaMesMes($hoja, array $mesMes): void
    {
        $encabezados = [
            'Mes', 'Nuevos Ingresos', 'Egresos UCI', 'Salidas Hosp.',
            'Estancia Prom. (d)', 'Ocup. Prom./Día', 'Rotación',
            'NEWS ≥ 5', 'SOFA ≥ 10', 'VMI', 'Moviliz. < 48h',
            'NEWS prom.', 'SOFA prom.', 'RASS prom.',
            'CAM+ (Delirium)', 'CAM- (Sin delirium)', 'CAM no eval.', '% Delirium',
        ];

        foreach ($encabezados as $col => $enc) {
            $letra = chr(65 + $col);
            $hoja->setCellValue($letra . '1', $enc);
        }
        $rango = 'A1:' . chr(64 + count($encabezados)) . '1';
        $hoja->getStyle($rango)->getFont()->setBold(true);
        $hoja->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6EFD');
        $hoja->getStyle($rango)->getFont()->getColor()->setARGB('FFFFFFFF');

        foreach ($mesMes as $i => $entrada) {
            $row = $i + 2;
            $d   = $entrada['datos'];
            $cam = $d['camUci'];
            $hoja->setCellValue('A' . $row, $entrada['mes']);
            $hoja->setCellValue('B' . $row, $d['nuevosIngresos']);
            $hoja->setCellValue('C' . $row, $d['totalEgresados']);
            $hoja->setCellValue('D' . $row, $d['totalSalidaHosp']);
            $hoja->setCellValue('E' . $row, $d['avgEstanciaEgresados']);
            $hoja->setCellValue('F' . $row, $d['avgOcupacion']);
            $hoja->setCellValue('G' . $row, $d['rotacionCamas']);
            $hoja->setCellValue('H' . $row, $d['alertasNews']);
            $hoja->setCellValue('I' . $row, $d['alertasSofa']);
            $hoja->setCellValue('J' . $row, $d['conVmi']);
            $hoja->setCellValue('K' . $row, $d['movilizacionTemprana']);
            $hoja->setCellValue('L' . $row, $d['promediosEscalas']['NEWS']);
            $hoja->setCellValue('M' . $row, $d['promediosEscalas']['SOFA']);
            $hoja->setCellValue('N' . $row, $d['promediosEscalas']['RASS']);
            $hoja->setCellValue('O' . $row, $cam['positivos']);
            $hoja->setCellValue('P' . $row, $cam['negativos']);
            $hoja->setCellValue('Q' . $row, $cam['no_evaluables']);
            $hoja->setCellValue('R' . $row, $cam['pct_delirium'] . '%');
        }

        foreach (range('A', 'R') as $letra) {
            $hoja->getColumnDimension($letra)->setAutoSize(true);
        }
    }

    private function hojaCamUci($hoja, array $datos, string $titulo): void
    {
        $hoja->setCellValue('A1', 'CAM-UCI — Evaluación Diaria de Delirium');
        $hoja->setCellValue('A2', $titulo);
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $hoja->getStyle('A2')->getFont()->setSize(11);

        // Resumen
        $cam = $datos['camUci'];
        $row = 4;
        $hoja->setCellValue('A' . $row, 'Resumen del período');
        $hoja->getStyle('A' . $row)->getFont()->setBold(true);
        $row++;
        $resumen = [
            ['Total evaluaciones',            $cam['total']],
            ['Positivo — Delirium presente',  $cam['positivos']],
            ['Negativo — Sin delirium',        $cam['negativos']],
            ['No evaluable (RASS ≤ -3)',       $cam['no_evaluables']],
            ['% días con delirium',            $cam['pct_delirium'] . '%'],
            ['Pacientes únicos con delirium',  $cam['pac_con_delirium']],
        ];
        foreach ($resumen as $fila) {
            $hoja->setCellValue('A' . $row, $fila[0]);
            $hoja->setCellValue('B' . $row, $fila[1]);
            $row++;
        }

        // Detalle por día
        if (!empty($cam['por_dia'])) {
            $row += 2;
            $hoja->setCellValue('A' . $row, 'Detalle por Fecha');
            $hoja->getStyle('A' . $row)->getFont()->setBold(true);
            $row++;

            $cabeceras = ['Fecha', 'Positivos', 'Negativos', 'No evaluables', 'Total'];
            foreach ($cabeceras as $col => $cab) {
                $hoja->setCellValue(chr(65 + $col) . $row, $cab);
            }
            $hoja->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
            $hoja->getStyle('A' . $row . ':E' . $row)
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF198754');
            $hoja->getStyle('A' . $row . ':E' . $row)
                ->getFont()->getColor()->setARGB('FFFFFFFF');
            $row++;

            foreach ($cam['por_dia'] as $dia) {
                $hoja->setCellValue('A' . $row, $dia['fecha']);
                $hoja->setCellValue('B' . $row, $dia['positivos']);
                $hoja->setCellValue('C' . $row, $dia['negativos']);
                $hoja->setCellValue('D' . $row, $dia['no_eval']);
                $hoja->setCellValue('E' . $row, $dia['total']);
                if ($dia['positivos'] > 0) {
                    $hoja->getStyle('B' . $row)->getFont()->getColor()->setARGB('FFDC3545');
                    $hoja->getStyle('B' . $row)->getFont()->setBold(true);
                }
                $row++;
            }
        }

        $hoja->getColumnDimension('A')->setWidth(18);
        foreach (range('B', 'E') as $letra) {
            $hoja->getColumnDimension($letra)->setWidth(16);
        }
    }
}
