<?php

namespace App\Http\Controllers;

use App\Models\EpisodioUci;
use App\Models\Paciente;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ReingresosController extends Controller
{
    public function index(Request $request)
    {
        $desde = $request->get('desde')
            ? Carbon::parse($request->get('desde'))->startOfDay()
            : now()->subDays(90)->startOfDay();
        $hasta = $request->get('hasta')
            ? Carbon::parse($request->get('hasta'))->endOfDay()
            : now()->endOfDay();

        $tablaLista = Schema::hasTable('episodios_uci')
                   && Schema::hasColumn('pacientes', 'numero_ingresos');

        if (!$tablaLista) {
            return view('reingresos.index', [
                'necesitaMigracion' => true,
                'desde' => $desde, 'hasta' => $hasta,
                'episodiosEnPeriodo' => collect(), 'reingresos' => collect(),
                'historialCompleto'  => collect(), 'activosReingreso' => collect(),
                'totalPacientes' => 0, 'conReingreso' => 0,
                'totalEpisodios' => 0, 'pctReingreso'  => 0,
                'pacientesActivos' => 0,
            ]);
        }

        // Episodios archivados en el período (= reingresos detectados)
        $episodiosEnPeriodo = EpisodioUci::with('paciente')
            ->whereBetween('created_at', [$desde, $hasta])
            ->orderByDesc('created_at')
            ->get();

        // Pacientes con múltiples episodios (alguna vez reingresaron)
        $reingresos = EpisodioUci::with('paciente')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('paciente_id');

        // Estadísticas globales
        $totalPacientes    = Paciente::count();
        $conReingreso      = Paciente::where('numero_ingresos', '>', 1)->count();
        $totalEpisodios    = EpisodioUci::count();
        $pctReingreso      = $totalPacientes > 0 ? round($conReingreso / $totalPacientes * 100, 1) : 0;
        $pacientesActivos  = Paciente::where('activo', true)->count();

        // Pacientes actualmente activos que son reingreso
        $activosReingreso  = Paciente::where('activo', true)
            ->where('numero_ingresos', '>', 1)
            ->with('ultimoSnapshot')
            ->get();

        // Historial completo por paciente con episodios
        $historialCompleto = Paciente::where('numero_ingresos', '>', 1)
            ->with(['episodios', 'ultimoSnapshot'])
            ->orderByDesc('numero_ingresos')
            ->get();

        return view('reingresos.index', compact(
            'episodiosEnPeriodo', 'reingresos', 'historialCompleto',
            'totalPacientes', 'conReingreso', 'totalEpisodios', 'pctReingreso',
            'activosReingreso', 'pacientesActivos',
            'desde', 'hasta'
        ));
    }

    public function descargar(Request $request)
    {
        $desde = $request->get('desde')
            ? Carbon::parse($request->get('desde'))->startOfDay()
            : now()->subDays(90)->startOfDay();
        $hasta = $request->get('hasta')
            ? Carbon::parse($request->get('hasta'))->endOfDay()
            : now()->endOfDay();

        $tipoEgresoNombres = [
            'mejoria' => 'Mejoría', 'alta_casa' => 'Alta a casa',
            'traslado' => 'Traslado', 'fallecimiento' => 'Fallecimiento',
        ];

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()->setTitle('Reingresos UCI');

        // ── Hoja 1: Reingresos en el período ─────────────────────────────────
        $h1 = $spreadsheet->getActiveSheet()->setTitle('Reingresos Período');
        $h1->setCellValue('A1', 'Reingresos UCI — Clínica de Occidente');
        $h1->setCellValue('A2', 'Período: ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y'));
        $h1->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $cab1 = ['Paciente', 'Documento', 'N° Episodio', 'Es Reingreso', 'Ingreso Previo', 'Salida Hosp. Previa', 'Egreso Previo', 'Tipo Egreso Previo', 'Reingreso Detectado'];
        foreach ($cab1 as $col => $c) {
            $h1->setCellValue(chr(65 + $col) . '4', $c);
        }
        $h1->getStyle('A4:I4')->getFont()->setBold(true);
        $h1->getStyle('A4:I4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF6F42C1');
        $h1->getStyle('A4:I4')->getFont()->getColor()->setARGB('FFFFFFFF');

        $episodios = EpisodioUci::with('paciente')
            ->whereBetween('created_at', [$desde, $hasta])
            ->orderByDesc('created_at')
            ->get();

        $row = 5;
        foreach ($episodios as $ep) {
            $p = $ep->paciente;
            $h1->setCellValue('A' . $row, $p->nombre);
            $h1->setCellValue('B' . $row, $p->documento);
            $h1->setCellValue('C' . $row, $ep->numero_episodio);
            $h1->setCellValue('D' . $row, $ep->es_reingreso ? 'Sí' : 'No');
            $h1->setCellValue('E' . $row, $ep->ingreso_uci?->format('d/m/Y H:i') ?? '');
            $h1->setCellValue('F' . $row, $ep->salida_hospitalizacion?->format('d/m/Y H:i') ?? '');
            $h1->setCellValue('G' . $row, $ep->egreso_uci?->format('d/m/Y H:i') ?? '');
            $h1->setCellValue('H' . $row, $tipoEgresoNombres[$ep->tipo_egreso] ?? ($ep->tipo_egreso ?? ''));
            $h1->setCellValue('I' . $row, $ep->created_at->format('d/m/Y H:i'));
            $row++;
        }
        foreach (range('A', 'I') as $l) { $h1->getColumnDimension($l)->setAutoSize(true); }

        // ── Hoja 2: Historial completo ────────────────────────────────────────
        $h2 = $spreadsheet->createSheet()->setTitle('Historial Completo');
        $h2->setCellValue('A1', 'Historial completo de episodios por paciente');
        $h2->getStyle('A1')->getFont()->setBold(true)->setSize(12);

        $cab2 = ['Paciente', 'Documento', 'N° Total Episodios', 'N° Episodio', 'Es Reingreso', 'Ingreso UCI', 'Salida Hosp.', 'Egreso UCI', 'Tipo Egreso', 'Estado Actual'];
        foreach ($cab2 as $col => $c) {
            $h2->setCellValue(chr(65 + $col) . '3', $c);
        }
        $h2->getStyle('A3:J3')->getFont()->setBold(true);
        $h2->getStyle('A3:J3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6EFD');
        $h2->getStyle('A3:J3')->getFont()->getColor()->setARGB('FFFFFFFF');

        $pacientes = Paciente::where('numero_ingresos', '>', 1)
            ->with('episodios')
            ->orderByDesc('numero_ingresos')
            ->get();

        $row = 4;
        foreach ($pacientes as $p) {
            foreach ($p->episodios as $ep) {
                $h2->setCellValue('A' . $row, $p->nombre);
                $h2->setCellValue('B' . $row, $p->documento);
                $h2->setCellValue('C' . $row, $p->numero_ingresos);
                $h2->setCellValue('D' . $row, $ep->numero_episodio);
                $h2->setCellValue('E' . $row, $ep->es_reingreso ? 'Sí' : 'No');
                $h2->setCellValue('F' . $row, $ep->ingreso_uci?->format('d/m/Y H:i') ?? '');
                $h2->setCellValue('G' . $row, $ep->salida_hospitalizacion?->format('d/m/Y H:i') ?? '');
                $h2->setCellValue('H' . $row, $ep->egreso_uci?->format('d/m/Y H:i') ?? '');
                $h2->setCellValue('I' . $row, $tipoEgresoNombres[$ep->tipo_egreso] ?? ($ep->tipo_egreso ?? ''));
                $h2->setCellValue('J' . $row, $p->activo ? 'Activo en UCI' : 'Egresado');
                $row++;
            }
            // Episodio actual (en pacientes)
            $h2->setCellValue('A' . $row, $p->nombre);
            $h2->setCellValue('B' . $row, $p->documento);
            $h2->setCellValue('C' . $row, $p->numero_ingresos);
            $h2->setCellValue('D' . $row, $p->numero_ingresos . ' (actual)');
            $h2->setCellValue('E' . $row, 'Sí');
            $h2->setCellValue('F' . $row, $p->ingreso_uci?->format('d/m/Y H:i') ?? 'Por registrar');
            $h2->setCellValue('G' . $row, $p->salida_hospitalizacion?->format('d/m/Y H:i') ?? '');
            $h2->setCellValue('H' . $row, $p->egreso_uci?->format('d/m/Y H:i') ?? 'En curso');
            $h2->setCellValue('I' . $row, $tipoEgresoNombres[$p->tipo_egreso] ?? ($p->tipo_egreso ?? ''));
            $h2->setCellValue('J' . $row, $p->activo ? 'Activo en UCI' : 'Egresado');
            $h2->getStyle('A' . $row . ':J' . $row)->getFont()->setBold(true);
            $row++;
        }
        foreach (range('A', 'J') as $l) { $h2->getColumnDimension($l)->setAutoSize(true); }

        $writer  = new Xlsx($spreadsheet);
        $tmpFile = tempnam(sys_get_temp_dir(), 'reingresos_');
        $writer->save($tmpFile);

        $nombre = 'Reingresos_UCI_' . now()->format('Ymd_His') . '.xlsx';
        return response()->download($tmpFile, $nombre, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
