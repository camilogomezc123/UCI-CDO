<?php

namespace App\Http\Controllers;

use App\Services\ExcelImportService;
use App\Models\CargaArchivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CargaController extends Controller
{
    public function index()
    {
        $ultimaCarga = CargaArchivo::with('usuario')->latest()->first();
        return view('carga.index', compact('ultimaCarga'));
    }

    public function store(Request $request, ExcelImportService $importService)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ], [
            'archivo.required' => 'Debe seleccionar un archivo.',
            'archivo.mimes' => 'Solo se aceptan archivos Excel (.xlsx, .xls).',
            'archivo.max' => 'El archivo no debe superar 10 MB.',
        ]);

        $archivo = $request->file('archivo');
        $nombreOriginal = $archivo->getClientOriginalName();
        $rutaTemporal = $archivo->storeAs('cargas_temp', 'import_' . time() . '.xlsx');
        $rutaAbsoluta = storage_path('app/' . $rutaTemporal);

        $resultado = $importService->procesar($rutaAbsoluta, auth()->id());

        // Eliminar archivo temporal
        Storage::delete($rutaTemporal);

        $resumen = "Archivo '{$nombreOriginal}' procesado: {$resultado['nuevos']} nuevos, {$resultado['actualizados']} actualizados, {$resultado['omitidos']} omitidos.";

        if (!empty($resultado['errores'])) {
            return redirect()->route('carga.historial')
                ->with('error', $resumen . ' Errores: ' . implode(' | ', $resultado['errores']));
        }

        return redirect()->route('carga.historial')->with('success', $resumen);
    }

    public function historial()
    {
        $cargas = CargaArchivo::with('usuario')->latest()->paginate(20);
        return view('carga.historial', compact('cargas'));
    }
}
