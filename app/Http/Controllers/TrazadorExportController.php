<?php

namespace App\Http\Controllers;

use App\Models\Trazador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class TrazadorExportController extends Controller
{
    // ── Paleta corporativa ────────────────────────────────────────────────────
    private const H_BG    = 'FF1a3a5c';  // azul oscuro encabezados
    private const H_FG    = 'FFFFFFFF';
    private const SH_BG   = 'FF2d6a9f';  // azul medio sub-encabezados
    private const ALT     = 'FFE8F4FD';  // fila alternada
    private const VERDE   = 'FFC6EFCE';
    private const AMARILLO= 'FFFFEB9C';
    private const ROJO    = 'FFFFC7CE';
    private const NA_BG   = 'FFE9ECEF';
    private const DELTA_P = 'FFD1E7DD';  // delta positivo
    private const DELTA_N = 'FFF8D7DA';  // delta negativo

    // ── Indicadores del módulo Sepsis ─────────────────────────────────────────
    private const INDICADORES_SEPSIS = [
        'S1' => 'Activación Código Sepsis',
        'S2' => 'Lactato ≤ 60 min',
        'S3' => 'Antibiótico ≤ 60 min',
        'S4' => 'Hemocultivos tomados',
        'S5' => 'Bundle de 1h completo',
        'S6' => 'Vasopresor ≤ 60 min',
        'S7' => 'Control de foco',
    ];

    private const INDICADORES_ABCDEF = [
        'A1' => 'Dolor evaluado c/turno',
        'A2' => 'Dolor en meta',
        'A3' => 'Analgesia preventiva',
        'B1' => 'SAT realizado',
        'B2' => 'SBT realizado',
        'B3' => 'Extubación exitosa',
        'C1' => 'RASS en meta',
        'C2' => 'Sin benzodiacepinas',
        'C3' => 'Propofol/Dexmedetomidina',
        'C4' => 'RASS documentado',
        'D1' => 'CAM-ICU realizado',
        'D3' => 'Sin delirium',
        'D4' => 'Intervención no farmacológica',
        'E1' => 'Fisioterapia realizada',
        'E3' => 'Movilización documentada',
        'F1' => 'Educación médico',
        'F2' => 'Educación fisio',
        'F3' => 'Educación auxiliar',
        'G1' => 'FAST-HUG evaluado',
    ];

    // ── Entrada pública ───────────────────────────────────────────────────────

    public function descargar(Request $request)
    {
        $request->validate([
            'periodo'       => 'required|in:mensual,trimestral,anual',
            'fecha'         => ['required', 'string', 'regex:/^\d{4}-\d{2}/'],
            'tipo_trazador' => 'nullable|string',
        ]);

        [$inicio, $fin, $label] = $this->resolverPeriodo(
            $request->periodo,
            Carbon::parse($request->fecha . '-01')
        );

        $query = Trazador::with('paciente')
            ->where('estado', 'CERRADO')
            ->whereBetween('fecha_cierre', [$inicio->startOfDay(), $fin->endOfDay()]);

        if ($request->filled('tipo_trazador')) {
            $query->where('tipo_trazador', $request->tipo_trazador);
        }

        $trazadores = $query->orderBy('fecha_cierre')->get();

        $ss = new Spreadsheet();
        $ss->getProperties()
           ->setCreator('UCI Panel — Clínica de Occidente')
           ->setTitle('Consolidado Trazadores ' . $label)
           ->setDescription('Reporte consolidado de pacientes trazadores UCI');

        $sepsisGroup = $trazadores->where('tipo_trazador', 'sepsis');

        $this->hojaResumenEjecutivo($ss, $trazadores, $sepsisGroup, $label, $inicio, $fin);
        $this->hojaPacientes($ss, $trazadores, $label);
        $this->hojaIndicadoresSepsis($ss, $sepsisGroup, $label);
        $this->hojaCalidadDeVida($ss, $sepsisGroup, $label);
        $this->hojaIncumplimientos($ss, $trazadores, $label);

        $ss->setActiveSheetIndex(0);

        $writer   = new Xlsx($ss);
        $filename = 'trazadores_' . $request->periodo . '_' . $inicio->format('Y-m') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ── Resolución de período ─────────────────────────────────────────────────

    private function resolverPeriodo(string $periodo, Carbon $fecha): array
    {
        return match ($periodo) {
            'mensual'    => [
                $fecha->copy()->startOfMonth(),
                $fecha->copy()->endOfMonth(),
                'Mensual: ' . $fecha->translatedFormat('F Y'),
            ],
            'trimestral' => [
                $fecha->copy()->firstOfQuarter(),
                $fecha->copy()->lastOfQuarter(),
                'Trimestral: Q' . $fecha->quarter . ' ' . $fecha->year,
            ],
            'anual'      => [
                $fecha->copy()->startOfYear(),
                $fecha->copy()->endOfYear(),
                'Anual: ' . $fecha->year,
            ],
        };
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HOJA 1 — Resumen Ejecutivo
    // ═══════════════════════════════════════════════════════════════════════════

    private function hojaResumenEjecutivo(
        Spreadsheet $ss, $todos, $sepsis, string $label, Carbon $inicio, Carbon $fin
    ): void {
        $ws = $ss->getActiveSheet()->setTitle('Resumen Ejecutivo');

        // Encabezado
        $ws->mergeCells('A1:J1');
        $ws->setCellValue('A1', 'PLATAFORMA UCI TRAZADORES — RESUMEN EJECUTIVO');
        $this->estiloTitulo($ws, 'A1:J1', 14);

        $ws->mergeCells('A2:J2');
        $ws->setCellValue('A2',
            strtoupper($label) . '   |   Período: ' . $inicio->format('d/m/Y') .
            ' – ' . $fin->format('d/m/Y') . '   |   Generado: ' . now()->format('d/m/Y H:i')
        );
        $ws->getStyle('A2')->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF888888']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension(2)->setRowHeight(14);

        // ── KPIs globales ─────────────────────────────────────────────────────
        $row = 4;
        $ws->mergeCells("A{$row}:J{$row}");
        $ws->setCellValue("A{$row}", '▶  INDICADORES CLAVE DEL PERÍODO');
        $this->estiloSubHeader($ws, "A{$row}:J{$row}");
        $row++;

        $total  = $todos->count();
        $reProm = $total > 0 ? round($todos->avg(fn($t) => $t->resultados['adherencia_reanimacion_pct'] ?? null), 1) : null;
        $abProm = $total > 0 ? round($todos->avg(fn($t) => $t->resultados['adherencia_abcdef_pct']      ?? null), 1) : null;
        $glProm = $total > 0 ? round($todos->avg(fn($t) => $t->resultados['puntuacion_global_pct']       ?? null), 1) : null;

        $kpis = [
            ['Total pacientes cerrados en el período', $total, null],
            ['Patologías incluidas', $todos->pluck('tipo_trazador')->unique()->values()->implode(', '), null],
            ['Adherencia Reanimación (prom. período)', $reProm !== null ? $reProm.'%' : '—', $reProm],
            ['Adherencia Bundle ABCDEF (prom. período)', $abProm !== null ? $abProm.'%' : '—', $abProm],
            ['Puntuación Global (prom. período)', $glProm !== null ? $glProm.'%' : '—', $glProm],
        ];

        foreach ($kpis as [$nombre, $valor, $pct]) {
            $ws->setCellValue("A{$row}", $nombre);
            $ws->setCellValue("C{$row}", $valor);
            if ($pct !== null) {
                $color = $pct >= 90 ? self::VERDE : ($pct >= 70 ? self::AMARILLO : self::ROJO);
                $ws->getStyle("C{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($color);
                $sem = $pct >= 90 ? '● VERDE' : ($pct >= 70 ? '● AMARILLO' : '● ROJO');
                $ws->setCellValue("D{$row}", $sem);
                $ws->getStyle("D{$row}")->getFont()->setBold(true);
            }
            $ws->getStyle("A{$row}")->getFont()->setSize(9);
            $row++;
        }

        // ── Tabla por indicador Sepsis ────────────────────────────────────────
        $row += 1;
        $ws->mergeCells("A{$row}:J{$row}");
        $ws->setCellValue("A{$row}", '▶  CUMPLIMIENTO POR INDICADOR — SEPSIS');
        $this->estiloSubHeader($ws, "A{$row}:J{$row}");
        $row++;

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $col => $letra) {
            $headers = ['Código', 'Indicador', 'Evaluados', 'Cumplen (100%)', 'N/A', 'No cumplen', 'Cumplimiento %', 'Meta'];
            $ws->setCellValue(Coordinate::stringFromColumnIndex($col + 1) . $row, $headers[$col]);
        }
        $ws->setCellValue("H{$row}", 'Semáforo');
        $this->estiloEncabezado($ws, "A{$row}:H{$row}");
        $row++;

        foreach (self::INDICADORES_SEPSIS as $cod => $nombre) {
            $vals  = $sepsis->map(fn($t) => $t->resultados['semaforo']['por_indicador'][$cod] ?? null)->filter();
            $total = $vals->count();
            $na    = $vals->filter(fn($v) => ($v['valor'] ?? null) === 'N/A')->count();
            $evaluados = $total - $na;
            $cumplen   = $vals->filter(fn($v) => ($v['valor'] ?? null) === 100)->count();
            $noCumplen = $vals->filter(fn($v) => is_numeric($v['valor'] ?? null) && ($v['valor'] ?? null) === 0)->count();
            $pct = $evaluados > 0 ? round($cumplen / $evaluados * 100, 1) : null;

            $ws->setCellValue("A{$row}", $cod);
            $ws->setCellValue("B{$row}", $nombre);
            $ws->setCellValue("C{$row}", $evaluados ?: '—');
            $ws->setCellValue("D{$row}", $cumplen);
            $ws->setCellValue("E{$row}", $na ?: '—');
            $ws->setCellValue("F{$row}", $noCumplen);
            $ws->setCellValue("G{$row}", $pct !== null ? $pct.'%' : '—');
            $ws->setCellValue("H{$row}", '≥ 90%');

            if ($pct !== null) {
                $bg = $pct >= 90 ? self::VERDE : ($pct >= 70 ? self::AMARILLO : self::ROJO);
                $ws->getStyle("G{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            }
            $row++;
        }

        // Anchos
        $anchos = [1=>28, 2=>32, 3=>11, 4=>14, 5=>8, 6=>12, 7=>14, 8=>10, 9=>14, 10=>14];
        foreach ($anchos as $col => $w) {
            $ws->getColumnDimensionByColumn($col)->setWidth($w);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HOJA 2 — Pacientes (uno por fila)
    // ═══════════════════════════════════════════════════════════════════════════

    private function hojaPacientes(Spreadsheet $ss, $trazadores, string $label): void
    {
        $ws = $ss->createSheet()->setTitle('Pacientes');

        $ws->mergeCells('A1:P1');
        $ws->setCellValue('A1', 'PACIENTES CERRADOS — ' . strtoupper($label));
        $this->estiloTitulo($ws, 'A1:P1');

        $headers = [
            '#', 'Paciente', 'Documento', 'Tipo trazador', 'Fecha cierre',
            'Diagnóstico', 'Desenlace',
            'Reanimación %', 'ABCDEF %', 'Global %', 'Banda',
            'Barthel antes', 'Barthel después', 'Barthel Δ',
            'WHODAS antes', 'WHODAS después',
        ];
        $row = 2;
        $col = 1;
        foreach ($headers as $h) {
            $ws->setCellValueByColumnAndRow($col++, $row, $h);
        }
        $this->estiloEncabezado($ws, "A{$row}:P{$row}");
        $row = 3;

        foreach ($trazadores as $i => $t) {
            $r    = $t->resultados ?? [];
            $ea   = $r['escalas_antes']   ?? [];
            $ed   = $r['escalas_despues'] ?? [];
            $comp = $r['comparativo']     ?? [];
            $datos= $t->datos ?? [];

            $re = $r['adherencia_reanimacion_pct'] ?? null;
            $ab = $r['adherencia_abcdef_pct']      ?? null;
            $gl = $r['puntuacion_global_pct']       ?? null;

            $fila = [
                $i + 1,
                $t->paciente->nombre ?? '—',
                $t->paciente->documento ?? '—',
                strtoupper($t->tipo_trazador),
                $t->fecha_cierre?->format('d/m/Y') ?? '—',
                $datos['diagnostico_ingreso'] ?? ($datos['diagnostico'] ?? '—'),
                $datos['desenlace'] ?? '—',
                $re !== null ? $re / 100 : null,
                $ab !== null ? $ab / 100 : null,
                $gl !== null ? $gl / 100 : null,
                $this->bandaLabel($t->getBandaGlobal()),
                $ea['barthel']['total'] ?? null,
                $ed['barthel']['total'] ?? null,
                $comp['barthel_total']  ?? null,
                $ea['whodas']['indice_0_100'] ?? null,
                $ed['whodas']['indice_0_100'] ?? null,
            ];

            $col = 1;
            foreach ($fila as $val) {
                $ws->setCellValueByColumnAndRow($col++, $row, $val ?? '');
            }

            // Formato porcentaje en columnas H, I, J (cols 8,9,10)
            foreach ([8, 9, 10] as $c) {
                if ($fila[$c - 1] !== null) {
                    $ws->getStyleByColumnAndRow($c, $row)->getNumberFormat()->setFormatCode('0.0%');
                }
            }

            // Color banda (col 11)
            if ($gl !== null) {
                $bg = $gl >= 90 ? self::VERDE : ($gl >= 70 ? self::AMARILLO : self::ROJO);
                $ws->getStyleByColumnAndRow(10, $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
                $ws->getStyleByColumnAndRow(11, $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            }

            // Color delta Barthel (col 14)
            $delta = $comp['barthel_total'] ?? null;
            if ($delta !== null) {
                $bg = $delta > 0 ? self::DELTA_P : ($delta < 0 ? self::DELTA_N : self::NA_BG);
                $ws->getStyleByColumnAndRow(14, $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
            }

            // Filas alternadas
            if ($i % 2 === 0) {
                $ws->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT);
            }

            $row++;
        }

        // Anchos de columna
        $anchos = [1=>3,2=>28,3=>14,4=>12,5=>13,6=>30,7=>16,8=>14,9=>12,10=>12,11=>12,12=>13,13=>14,14=>11,15=>13,16=>14];
        foreach ($anchos as $c => $w) {
            $ws->getColumnDimensionByColumn($c)->setWidth($w);
        }

        // Bordes
        if ($row > 3) {
            $ws->getStyle("A2:P" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HOJA 3 — Semáforo por indicador Sepsis
    // ═══════════════════════════════════════════════════════════════════════════

    private function hojaIndicadoresSepsis(Spreadsheet $ss, $sepsis, string $label): void
    {
        $ws = $ss->createSheet()->setTitle('Indicadores Sepsis');

        $todos = self::INDICADORES_SEPSIS + self::INDICADORES_ABCDEF;
        $ncols = count($todos) + 2;

        $ws->mergeCells("A1:" . Coordinate::stringFromColumnIndex($ncols) . "1");
        $ws->setCellValue('A1', 'DETALLE DE INDICADORES SEPSIS — ' . strtoupper($label));
        $this->estiloTitulo($ws, "A1:" . Coordinate::stringFromColumnIndex($ncols) . "1");

        // Encabezados de indicadores
        $row = 3;
        $ws->setCellValue("A{$row}", 'Paciente');
        $ws->setCellValue("B{$row}", 'Cierre');
        $col = 3;
        foreach ($todos as $cod => $nombre) {
            $ws->setCellValueByColumnAndRow($col, $row, "{$cod}\n" . $nombre);
            $ws->getStyleByColumnAndRow($col, $row)->getAlignment()->setWrapText(true);
            $col++;
        }
        $this->estiloEncabezado($ws, "A{$row}:" . Coordinate::stringFromColumnIndex($col - 1) . $row);
        $ws->getRowDimension($row)->setRowHeight(48);

        $row = 4;
        foreach ($sepsis as $i => $t) {
            $sem = $t->resultados['semaforo']['por_indicador'] ?? [];

            $ws->setCellValue("A{$row}", $t->paciente->nombre ?? '—');
            $ws->setCellValue("B{$row}", $t->fecha_cierre?->format('d/m/Y') ?? '—');

            $col = 3;
            foreach (array_keys($todos) as $cod) {
                $ind   = $sem[$cod] ?? [];
                $valor = $ind['valor'] ?? null;
                $color = $ind['color'] ?? 'sin_dato';

                if ($valor === 'N/A') {
                    $ws->setCellValueByColumnAndRow($col, $row, 'N/A');
                    $bg = self::NA_BG;
                } elseif ($valor === null) {
                    $ws->setCellValueByColumnAndRow($col, $row, '—');
                    $bg = self::NA_BG;
                } else {
                    $ws->setCellValueByColumnAndRow($col, $row, $valor / 100);
                    $ws->getStyleByColumnAndRow($col, $row)->getNumberFormat()->setFormatCode('0%');
                    $bg = match($color) {
                        'verde'    => self::VERDE,
                        'amarillo' => self::AMARILLO,
                        'rojo'     => self::ROJO,
                        default    => self::NA_BG,
                    };
                }

                $ws->getStyleByColumnAndRow($col, $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bg);
                $ws->getStyleByColumnAndRow($col, $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            if ($i % 2 === 0) {
                $ws->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT);
            }
            $row++;
        }

        // Anchos
        $ws->getColumnDimension('A')->setWidth(28);
        $ws->getColumnDimension('B')->setWidth(12);
        for ($c = 3; $c < $col; $c++) {
            $ws->getColumnDimensionByColumn($c)->setWidth(9);
        }

        if ($row > 4) {
            $ws->getStyle("A3:" . Coordinate::stringFromColumnIndex($col - 1) . ($row - 1))
               ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HOJA 4 — Calidad de Vida (escalas antes/después)
    // ═══════════════════════════════════════════════════════════════════════════

    private function hojaCalidadDeVida(Spreadsheet $ss, $sepsis, string $label): void
    {
        $ws = $ss->createSheet()->setTitle('Calidad de Vida');

        $ws->mergeCells('A1:R1');
        $ws->setCellValue('A1', 'CALIDAD DE VIDA — COMPARATIVO ANTES / DESPUÉS — ' . strtoupper($label));
        $this->estiloTitulo($ws, 'A1:R1');

        // Grupo de encabezados
        $ws->mergeCells('C2:E2'); $ws->setCellValue('C2', 'BARTHEL (0-100)');
        $ws->mergeCells('F2:H2'); $ws->setCellValue('F2', 'WHODAS 2.0 (Índice 0-100)');
        $ws->mergeCells('I2:K2'); $ws->setCellValue('I2', 'EQ-5D-5L');
        $ws->mergeCells('L2:M2'); $ws->setCellValue('L2', 'CFS (1-9)');
        $ws->mergeCells('N2:P2'); $ws->setCellValue('N2', 'EQ-VAS (0-100)');

        $groupStyle = [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => self::H_FG]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::SH_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        foreach (['C2:E2','F2:H2','I2:K2','L2:M2','N2:P2'] as $rng) {
            $ws->getStyle($rng)->applyFromArray($groupStyle);
        }

        $row = 3;
        $headers = [
            'A' => '#', 'B' => 'Paciente',
            'C' => 'Antes', 'D' => 'Después', 'E' => 'Δ',
            'F' => 'Antes', 'G' => 'Después', 'H' => 'Δ',
            'I' => 'Perfil A', 'J' => 'Perfil D', 'K' => 'Δ suma',
            'L' => 'CFS A', 'M' => 'CFS D',
            'N' => 'VAS A', 'O' => 'VAS D', 'P' => 'Δ VAS',
        ];
        foreach ($headers as $col => $titulo) {
            $ws->setCellValue("{$col}{$row}", $titulo);
        }
        $this->estiloEncabezado($ws, "A{$row}:P{$row}");
        $row = 4;

        foreach ($sepsis as $i => $t) {
            $ea   = $t->resultados['escalas_antes']   ?? [];
            $ed   = $t->resultados['escalas_despues'] ?? [];
            $comp = $t->resultados['comparativo']     ?? [];

            $barA  = $ea['barthel']['total'] ?? null;
            $barD  = $ed['barthel']['total'] ?? null;
            $barDt = $comp['barthel_total']  ?? null;

            $whoA  = $ea['whodas']['indice_0_100'] ?? null;
            $whoD  = $ed['whodas']['indice_0_100'] ?? null;
            $whoDt = $comp['whodas_indice']        ?? null;

            $eqPA  = $ea['eq5d']['perfil'] ?? '—';
            $eqPD  = $ed['eq5d']['perfil'] ?? '—';
            $eqSA  = $ea['eq5d']['suma_niveles'] ?? null;
            $eqSD  = $ed['eq5d']['suma_niveles'] ?? null;
            $eqDt  = ($eqSA !== null && $eqSD !== null) ? ($eqSD - $eqSA) : null;

            $cfsA  = $ea['cfs']['codigo'] ?? '—';
            $cfsD  = $ed['cfs']['codigo'] ?? '—';

            $vasA  = $ea['eq5d']['eq_vas'] ?? null;
            $vasD  = $ed['eq5d']['eq_vas'] ?? null;
            $vasDt = $comp['eq_vas'] ?? null;

            $ws->setCellValue("A{$row}", $i + 1);
            $ws->setCellValue("B{$row}", $t->paciente->nombre ?? '—');
            $ws->setCellValue("C{$row}", $barA);
            $ws->setCellValue("D{$row}", $barD);
            $ws->setCellValue("E{$row}", $barDt);
            $ws->setCellValue("F{$row}", $whoA);
            $ws->setCellValue("G{$row}", $whoD);
            $ws->setCellValue("H{$row}", $whoDt);
            $ws->setCellValue("I{$row}", $eqPA);
            $ws->setCellValue("J{$row}", $eqPD);
            $ws->setCellValue("K{$row}", $eqDt);
            $ws->setCellValue("L{$row}", $cfsA);
            $ws->setCellValue("M{$row}", $cfsD);
            $ws->setCellValue("N{$row}", $vasA);
            $ws->setCellValue("O{$row}", $vasD);
            $ws->setCellValue("P{$row}", $vasDt);

            // Color deltas: verde = mejoría (Barthel+, WHODAS-, EQ suma+, VAS+)
            $this->colorDelta($ws, "E{$row}", $barDt, true);    // Barthel: + es mejor
            $this->colorDelta($ws, "H{$row}", $whoDt, false);   // WHODAS: - es mejor
            $this->colorDelta($ws, "K{$row}", $eqDt,  false);   // EQ suma: - es mejor
            $this->colorDelta($ws, "P{$row}", $vasDt, true);    // VAS: + es mejor

            if ($i % 2 === 0) {
                $ws->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ALT);
            }
            $row++;
        }

        $anchos = ['A'=>4,'B'=>28,'C'=>9,'D'=>9,'E'=>8,'F'=>9,'G'=>9,'H'=>8,'I'=>9,'J'=>9,'K'=>8,'L'=>7,'M'=>7,'N'=>8,'O'=>8,'P'=>8];
        foreach ($anchos as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        if ($row > 4) {
            $ws->getStyle("A3:P" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // HOJA 5 — Incumplimientos (indicadores en rojo)
    // ═══════════════════════════════════════════════════════════════════════════

    private function hojaIncumplimientos(Spreadsheet $ss, $trazadores, string $label): void
    {
        $ws = $ss->createSheet()->setTitle('Incumplimientos');

        $ws->mergeCells('A1:F1');
        $ws->setCellValue('A1', 'INDICADORES EN ROJO — ' . strtoupper($label));
        $this->estiloTitulo($ws, 'A1:F1');

        $headers = ['Paciente', 'Tipo', 'Cierre', 'Código', 'Indicador', 'Adherencia Global'];
        $row = 2;
        $col = 1;
        foreach ($headers as $h) {
            $ws->setCellValueByColumnAndRow($col++, $row, $h);
        }
        $this->estiloEncabezado($ws, "A{$row}:F{$row}");
        $row = 3;

        $todosIndicadores = self::INDICADORES_SEPSIS + self::INDICADORES_ABCDEF;

        foreach ($trazadores as $t) {
            $sem = $t->resultados['semaforo']['por_indicador'] ?? [];
            $gl  = $t->resultados['puntuacion_global_pct'] ?? null;
            $nombre = $t->paciente->nombre ?? '—';
            $cierre = $t->fecha_cierre?->format('d/m/Y') ?? '—';

            foreach ($sem as $cod => $ind) {
                if (($ind['color'] ?? '') !== 'rojo') continue;

                $ws->setCellValue("A{$row}", $nombre);
                $ws->setCellValue("B{$row}", strtoupper($t->tipo_trazador));
                $ws->setCellValue("C{$row}", $cierre);
                $ws->setCellValue("D{$row}", $cod);
                $ws->setCellValue("E{$row}", $todosIndicadores[$cod] ?? $cod);
                $ws->setCellValue("F{$row}", $gl !== null ? $gl / 100 : null);
                if ($gl !== null) {
                    $ws->getStyle("F{$row}")->getNumberFormat()->setFormatCode('0.0%');
                }
                $ws->getStyle("A{$row}:F{$row}")->getFill()
                   ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ROJO);
                $row++;
            }
        }

        if ($row === 3) {
            $ws->mergeCells('A3:F3');
            $ws->setCellValue('A3', 'Sin incumplimientos en el período.');
            $ws->getStyle('A3')->applyFromArray([
                'font'      => ['italic' => true, 'color' => ['argb' => 'FF198754']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::VERDE]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        $anchos = ['A' => 28, 'B' => 12, 'C' => 13, 'D' => 8, 'E' => 40, 'F' => 16];
        foreach ($anchos as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }

        if ($row > 3) {
            $ws->getStyle("A2:F" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
    }

    // ── Helpers de estilo ─────────────────────────────────────────────────────

    private function estiloTitulo($ws, string $rango, int $size = 12): void
    {
        $ws->getStyle($rango)->applyFromArray([
            'font'      => ['bold' => true, 'size' => $size, 'color' => ['argb' => self::H_FG]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::H_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension(1)->setRowHeight(24);
    }

    private function estiloEncabezado($ws, string $rango): void
    {
        $ws->getStyle($rango)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => self::H_FG]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::SH_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    private function estiloSubHeader($ws, string $rango): void
    {
        $ws->getStyle($rango)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::H_FG]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF3d5a80']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    private function colorDelta($ws, string $cell, $val, bool $positiveIsBetter): void
    {
        if ($val === null) return;
        $mejor = $positiveIsBetter ? $val > 0 : $val < 0;
        $peor  = $positiveIsBetter ? $val < 0 : $val > 0;
        if ($mejor) {
            $ws->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::DELTA_P);
            $ws->getStyle($cell)->getFont()->setBold(true);
        } elseif ($peor) {
            $ws->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::DELTA_N);
            $ws->getStyle($cell)->getFont()->setBold(true);
        }
    }

    private function bandaLabel(?string $banda): string
    {
        return match ($banda) {
            'verde'    => '● Verde',
            'amarillo' => '● Amarillo',
            'rojo'     => '● Rojo',
            default    => '—',
        };
    }
}
