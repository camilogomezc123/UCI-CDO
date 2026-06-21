<?php

namespace App\Http\Controllers;

use App\Services\ExcelImportService;
use App\Models\CargaArchivo;
use App\Models\Paciente;
use App\Models\Snapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CargaController extends Controller
{
    private const SUBUNIDADES_ESPERADAS = [
        'UCI Quirúrgica', 'UCI Cardiovascular', 'UCI Respiratoria', 'UCI General',
        'UCI Neurovascular', 'UCI Torre C', 'UCI Torre B',
    ];

    public function index()
    {
        $ultimaCarga = CargaArchivo::with('usuario')->latest()->first();
        return view('carga.index', compact('ultimaCarga'));
    }

    public function store(Request $request, ExcelImportService $importService)
    {
        $request->validate([
            'archivos'   => 'required|array|min:1|max:10',
            'archivos.*' => 'file|extensions:xlsx,xls|max:20480',
        ], [
            'archivos.required'   => 'Debe seleccionar al menos un archivo.',
            'archivos.max'        => 'Puede subir máximo 10 archivos a la vez.',
            'archivos.*.extensions' => 'Solo se aceptan archivos Excel (.xlsx, .xls).',
            'archivos.*.max'      => 'Cada archivo no debe superar 20 MB.',
        ]);

        $exitos = [];
        $errores = [];
        $infoBarthel = [];
        $fechasCargadas = [];

        foreach ($request->file('archivos') as $archivo) {
            $nombreOriginal = $archivo->getClientOriginalName();
            // Usar la ruta temporal que PHP ya gestionó — no requiere copiar a storage
            $rutaAbsoluta   = $archivo->getRealPath();

            $resultado = $importService->procesar($rutaAbsoluta, auth()->id(), $nombreOriginal);
            if (!empty($resultado['fecha_archivo'])) {
                $fechasCargadas[] = $resultado['fecha_archivo'];
            }

            $egresados  = $resultado['egresados']  ?? 0;
            $reingresos = $resultado['reingresos'] ?? 0;
            $resumen = "'{$nombreOriginal}': {$resultado['nuevos']} nuevos, {$resultado['actualizados']} actualizados, {$resultado['omitidos']} omitidos"
                . ($egresados  > 0 ? ", {$egresados} egresados automáticamente" : '')
                . ($reingresos > 0 ? ", {$reingresos} reingreso(s) a UCI detectado(s)" : '');

            if (!empty($resultado['errores'])) {
                $errores[] = $resumen . ' — ' . implode(', ', $resultado['errores']);
            } else {
                $exitos[] = $resumen;
            }

            // Diagnóstico de Barthel: capturar columna detectada y primeras muestras
            if (!empty($resultado['barthel_col'])) {
                $muestras = array_filter($resultado['barthel_muestras'] ?? [], fn($v) => $v !== '');
                $infoBarthel[] = [
                    'archivo'  => $nombreOriginal,
                    'columna'  => $resultado['barthel_col'],
                    'muestras' => array_values($muestras),
                ];
            }
        }

        if (!empty($infoBarthel)) {
            session(['barthel_debug' => $infoBarthel]);
        }

        if (!empty($errores) && empty($exitos)) {
            return redirect()->route('carga.historial')
                ->with('error', implode(' | ', $errores));
        }

        $flash = implode(' | ', $exitos);
        $faltantesPorFecha = collect($fechasCargadas)->unique()->map(function ($fecha) {
            $presentes = Snapshot::whereDate('fecha_snapshot', $fecha)
                ->pluck('subunidad')->filter()->unique()->all();
            return [
                'fecha' => $fecha,
                'faltantes' => array_values(array_diff(self::SUBUNIDADES_ESPERADAS, $presentes)),
            ];
        })->filter(fn($cobertura) => !empty($cobertura['faltantes']));

        if ($faltantesPorFecha->isNotEmpty()) {
            $detalle = $faltantesPorFecha->map(fn($cobertura) => $cobertura['fecha'] . ': faltan ' . implode(', ', $cobertura['faltantes']))->implode(' | ');
            return redirect()->route('carga.historial')
                ->with('warning', trim($flash . ' — Atención: cobertura incompleta. ' . $detalle, ' —'));
        }
        if (!empty($errores)) {
            $flash .= ' — Con errores: ' . implode(' | ', $errores);
            return redirect()->route('carga.historial')->with('warning', $flash);
        }

        return redirect()->route('carga.historial')->with('success', $flash);
    }

    public function historial()
    {
        $cargas = CargaArchivo::with('usuario')->latest()->paginate(20);
        return view('carga.historial', compact('cargas'));
    }

    public function destroy(CargaArchivo $carga)
    {
        $nombre      = $carga->nombre_archivo;
        $fechaArchivo = $carga->fecha_archivo;

        DB::transaction(function () use ($carga, $fechaArchivo) {
            // Pacientes cuyo snapshot viene de esta carga
            $pacienteIds = $carga->snapshots()->pluck('paciente_id')->unique();

            // Eliminar snapshots de esta carga
            $carga->snapshots()->delete();

            // Pacientes que quedaron sin ningún snapshot → eliminar el registro
            Paciente::whereIn('id', $pacienteIds)
                ->whereDoesntHave('snapshots')
                ->delete();

            // Restaurar pacientes que fueron desplazados automáticamente por esta carga
            // (egreso_uci = fecha del archivo + tipo_egreso nulo = fue automático, no manual)
            Paciente::where('activo', false)
                ->whereDate('egreso_uci', $fechaArchivo)
                ->whereNull('tipo_egreso')
                ->update(['activo' => true, 'egreso_uci' => null]);

            $carga->delete();
        });

        return redirect()->route('carga.historial')
            ->with('success', "Carga «{$nombre}» eliminada. Los snapshots importados fueron revertidos.");
    }
}
