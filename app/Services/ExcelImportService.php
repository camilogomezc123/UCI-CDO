<?php

namespace App\Services;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CargaArchivo;
use App\Models\CambioSnapshot;
use App\Models\EpisodioUci;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelImportService
{
    private const CRITERIOS_EXCLUIDOS = ['UCIN', 'PEDIATRIA'];

    private const SUBUNIDADES = [
        [1,  8,  'UCI Quirúrgica'],
        [9,  16, 'UCI Cardiovascular'],
        [22, 27, 'UCI Respiratoria'],
        [28, 38, 'UCI General'],
        [39, 46, 'UCI Neurovascular'],
        [49, 54, 'UCIN'],
        [55, 62, 'UCI Torre C'],
        [63, 82, 'UCI Torre B'],
    ];

    private const CAMPOS_SEGUIMIENTO = [
        'ubicacion', 'criterio_atencion', 'especialidad',
        'estado_nutricional', 'dieta',
        'soporte_hemodinamico', 'soporte_ventilatorio', 'movilizacion',
        'news', 'sofa', 'barthel', 'rass', 'bps', 'eva', 'must',
        'riesgos', 'observaciones', 'metas_clinicas',
    ];

    private ?string $errorLectura = null;
    public  ?string $barthelColDetectada = null;  // columna detectada de Barthel (para diagnóstico)

    public function procesar(string $rutaArchivo, int $usuarioId, string $nombreOriginal = 'datos.xlsx'): array
    {
        $resultado = ['nuevos' => 0, 'actualizados' => 0, 'omitidos' => 0, 'egresados' => 0, 'reingresos' => 0, 'errores' => [], 'barthel_col' => null, 'barthel_muestras' => []];

        $this->errorLectura       = null;
        $this->barthelColDetectada = null;
        $filas = $this->leerExcel($rutaArchivo);

        if (empty($filas)) {
            $resultado['errores'][] = $this->errorLectura ?? 'El archivo no contiene datos válidos.';
            return $resultado;
        }

        // Guardar info de diagnóstico de Barthel
        $resultado['barthel_col']      = $this->barthelColDetectada;
        $resultado['barthel_muestras'] = array_slice(
            array_map(fn($f) => $f['barthel'] ?? '', $filas), 0, 5
        );

        $fechaArchivo = $this->extraerFechaArchivo($nombreOriginal);
        $resultado['fecha_archivo'] = $fechaArchivo;

        DB::transaction(function () use ($filas, $usuarioId, $fechaArchivo, $nombreOriginal, &$resultado) {
            $carga = CargaArchivo::create([
                'nombre_archivo' => $nombreOriginal,
                'fecha_archivo'  => $fechaArchivo,
                'usuario_id'     => $usuarioId,
            ]);

            // Mapa de cama → documento del upload actual (para detectar desplazados)
            $camasEnUpload      = []; // ['U10' => '12345678']
            $documentosEnUpload = []; // ['12345678', '87654321', ...]

            foreach ($filas as $fila) {
                $criterio = trim($fila['criterio_atencion'] ?? '');

                if (in_array($criterio, self::CRITERIOS_EXCLUIDOS)) {
                    $resultado['omitidos']++;
                    continue;
                }

                $ubicacion = trim($fila['ubicacion'] ?? '');
                if (!preg_match('/^U(\d+)$/', $ubicacion)) {
                    $resultado['omitidos']++;
                    continue;
                }

                $documento = trim($fila['documento'] ?? '');
                if (empty($documento)) {
                    $resultado['omitidos']++;
                    continue;
                }

                $numeroCama = $this->extraerNumeroCama($ubicacion);
                $subunidad  = $this->resolverSubunidad($numeroCama);

                $datosPaciente = [
                    'nombre' => trim($fila['nombre'] ?? ''),
                    'edad'   => (int)($fila['edad'] ?? 0),
                    'sexo'   => strtoupper(trim($fila['sexo'] ?? '')),
                    'eapb'   => trim($fila['eapb'] ?? ''),
                ];

                $paciente = Paciente::where('documento', $documento)->first();
                $esNuevo  = false;

                if (!$paciente) {
                    $paciente = Paciente::create(array_merge($datosPaciente, [
                        'documento' => $documento,
                        'activo'    => true,
                    ]));
                    $esNuevo = true;
                    $resultado['nuevos']++;
                } else {
                    // Detectar reingreso: paciente previamente egresado vuelve a la UCI
                    if (!$paciente->activo && $paciente->egreso_uci) {
                        EpisodioUci::create([
                            'paciente_id'            => $paciente->id,
                            'numero_episodio'        => $paciente->numero_ingresos ?? 1,
                            'es_reingreso'           => ($paciente->numero_ingresos ?? 1) > 1,
                            'ingreso_uci'            => $paciente->ingreso_uci,
                            'salida_hospitalizacion' => $paciente->salida_hospitalizacion,
                            'egreso_uci'             => $paciente->egreso_uci,
                            'tipo_egreso'            => $paciente->tipo_egreso,
                        ]);
                        $paciente->update(array_merge($datosPaciente, [
                            'activo'                 => true,
                            'ingreso_uci'            => null,
                            'salida_hospitalizacion' => null,
                            'egreso_uci'             => null,
                            'tipo_egreso'            => null,
                            'numero_ingresos'        => ($paciente->numero_ingresos ?? 1) + 1,
                        ]));
                        $resultado['reingresos']++;
                    } else {
                        $paciente->update(array_merge($datosPaciente, ['activo' => true]));
                    }
                    $resultado['actualizados']++;
                }

                // Registrar la cama y documento de este paciente en el upload actual
                $camasEnUpload[$ubicacion]  = $documento;
                $documentosEnUpload[]       = $documento;

                $datosSnapshot = [
                    'paciente_id'                => $paciente->id,
                    'carga_id'                   => $carga->id,
                    'fecha_snapshot'             => $fechaArchivo,
                    'ubicacion'                  => $ubicacion,
                    'numero_cama'                => $numeroCama,
                    'subunidad'                  => $subunidad,
                    'cie10'                      => $this->limpiar($fila['cie10'] ?? ''),
                    'diagnostico_oncologico'     => $this->limpiar($fila['diagnostico_oncologico'] ?? ''),
                    'diagnosticos'               => $this->limpiar($fila['diagnosticos'] ?? ''),
                    'criterio_atencion'          => $criterio,
                    'especialidad'               => $this->limpiar($fila['especialidad'] ?? ''),
                    'estado_nutricional'         => $this->limpiar($fila['estado_nutricional'] ?? ''),
                    'dieta'                      => $this->limpiar($fila['dieta'] ?? ''),
                    'soporte_hemodinamico'       => $this->limpiar($fila['soporte_hemodinamico'] ?? ''),
                    'soporte_ventilatorio'       => $this->limpiar($fila['soporte_ventilatorio'] ?? ''),
                    'movilizacion'               => $this->limpiar($fila['movilizacion'] ?? ''),
                    'news'                       => $this->numero($fila['news'] ?? null),
                    'sofa'                       => $this->limpiar($fila['sofa'] ?? ''),
                    'barthel'                    => $this->numero($fila['barthel'] ?? null),
                    'de_movilidad'               => $this->limpiar($fila['de_movilidad'] ?? ''),
                    'rass'                       => $this->numero($fila['rass'] ?? null),
                    'bps'                        => $this->numero($fila['bps'] ?? null),
                    'eva'                        => $this->numero($fila['eva'] ?? null),
                    'must'                       => $this->numero($fila['must'] ?? null),
                    'riesgos'                    => $this->limpiar($fila['riesgos'] ?? ''),
                    'observaciones'              => $this->limpiar($fila['observaciones'] ?? ''),
                    'metas_clinicas'             => $this->limpiar($fila['metas_clinicas'] ?? ''),
                    'tiempo_hospitalizacion_texto' => $this->limpiar($fila['tiempo_hospitalizacion'] ?? ''),
                ];

                $snapshot = Snapshot::create($datosSnapshot);

                if (!$esNuevo) {
                    $snapshotAnterior = Snapshot::where('paciente_id', $paciente->id)
                        ->where('id', '!=', $snapshot->id)
                        ->orderByDesc('fecha_snapshot')
                        ->orderByDesc('id')
                        ->first();

                    if ($snapshotAnterior) {
                        $this->registrarCambios($snapshot, $snapshotAnterior);
                    }
                }
            }

            // ── Detectar pacientes desplazados ────────────────────────────────
            // Si una cama aparece en este upload con un paciente distinto al que
            // tenía antes, el anterior salió de la UCI → marcar como inactivo.
            // Su historial de snapshots se conserva intacto para los reportes.
            if (!empty($camasEnUpload)) {
                $documentosUnicos = array_unique($documentosEnUpload);
                $ubicacionesAfectadas = array_keys($camasEnUpload);

                // Pacientes activos que NO están en este upload
                // y cuyo último snapshot estaba en alguna de las camas afectadas
                Paciente::where('activo', true)
                    ->whereNotIn('documento', $documentosUnicos)
                    ->whereHas('snapshots', fn($q) => $q->whereIn('ubicacion', $ubicacionesAfectadas))
                    ->with('ultimoSnapshot')
                    ->get()
                    ->each(function (Paciente $p) use ($camasEnUpload, $fechaArchivo, &$resultado) {
                        $camaAnterior = $p->ultimoSnapshot?->ubicacion;
                        if (!$camaAnterior || !isset($camasEnUpload[$camaAnterior])) return;

                        // Solo desplazar si este archivo es igual o más reciente que el último snapshot del paciente
                        $fechaUltimoSnap = $p->ultimoSnapshot?->fecha_snapshot;
                        if ($fechaUltimoSnap && $fechaArchivo < (string)$fechaUltimoSnap) return;

                        // Su cama ahora tiene otro paciente → egreso automático
                        $actualizacion = ['activo' => false];
                        if (is_null($p->egreso_uci)) {
                            $actualizacion['egreso_uci'] = $fechaArchivo;
                        }
                        $p->update($actualizacion);
                        $resultado['egresados']++;
                    });
            }

            $carga->update([
                'nuevos'      => $resultado['nuevos'],
                'actualizados'=> $resultado['actualizados'],
                'omitidos'    => $resultado['omitidos'],
                'errores'     => !empty($resultado['errores']) ? implode("\n", $resultado['errores']) : null,
            ]);
        });

        return $resultado;
    }

    private function leerExcel(string $ruta): array
    {
        if (!file_exists($ruta) || filesize($ruta) === 0) {
            $this->errorLectura = 'El archivo temporal no se encontró o está vacío en el servidor.';
            return [];
        }

        try {
            // Detectar el tipo de archivo automáticamente (.xls, .xlsx, .ods, .csv, etc.)
            $reader = IOFactory::createReaderForFile($ruta);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($ruta);
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            $this->errorLectura = 'Formato de archivo no reconocido: ' . $e->getMessage();
            return [];
        } catch (\Exception $e) {
            $this->errorLectura = 'Error al abrir el archivo: ' . $e->getMessage();
            return [];
        }

        $sheet      = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();

        // Escanear hasta la columna AF por si el archivo tiene columnas adicionales
        $maxColIdx = Coordinate::columnIndexFromString('AF');

        if ($highestRow < 4) {
            $this->errorLectura = "El archivo tiene solo {$highestRow} fila(s). Se esperan mínimo 4 (título + fecha + cabeceras + datos).";
            return [];
        }

        // Leer cabeceras de fila 3 para detectar columnas desplazadas
        $colPorCampo = $this->detectarColumnas($sheet, $maxColIdx);

        // Guardar columna detectada para Barthel (diagnóstico)
        $this->barthelColDetectada = $colPorCampo['barthel'];

        $filas = [];

        for ($rowNum = 4; $rowNum <= $highestRow; $rowNum++) {
            $celdas = [];
            for ($colIdx = 1; $colIdx <= $maxColIdx; $colIdx++) {
                $colLetter         = Coordinate::stringFromColumnIndex($colIdx);
                $celdas[$colLetter] = trim((string)$sheet->getCell($colLetter . $rowNum)->getFormattedValue());
            }

            if (empty(array_filter($celdas))) continue;

            $filas[] = [
                'ubicacion'              => $celdas['B']  ?? '',
                'documento'              => $celdas['C']  ?? '',
                'nombre'                 => $celdas['D']  ?? '',
                'edad'                   => $celdas['E']  ?? 0,
                'sexo'                   => $celdas['F']  ?? '',
                'eapb'                   => $celdas['G']  ?? '',
                'cie10'                  => $celdas['H']  ?? '',
                'diagnostico_oncologico' => $celdas['I']  ?? '',
                'diagnosticos'           => $celdas['J']  ?? '',
                'criterio_atencion'      => $celdas['K']  ?? '',
                'especialidad'           => $celdas['L']  ?? '',
                'estado_nutricional'     => $celdas['M']  ?? '',
                'dieta'                  => $celdas['N']  ?? '',
                'soporte_hemodinamico'   => $celdas['O']  ?? '',
                'soporte_ventilatorio'   => $celdas['P']  ?? '',
                'movilizacion'           => $celdas['Q']  ?? '',
                'news'                   => $celdas[$colPorCampo['news']]    ?? '',
                'sofa'                   => $celdas[$colPorCampo['sofa']]    ?? '',
                'barthel'                => $celdas[$colPorCampo['barthel']] ?? '',
                'de_movilidad'           => $celdas[$colPorCampo['de_movilidad']] ?? '',
                'rass'                   => $celdas[$colPorCampo['rass']]    ?? '',
                'bps'                    => $celdas[$colPorCampo['bps']]     ?? '',
                'eva'                    => $celdas[$colPorCampo['eva']]     ?? '',
                'must'                   => $celdas[$colPorCampo['must']]    ?? '',
                'riesgos'                => $celdas['Z']  ?? '',
                'observaciones'          => $celdas['AA'] ?? '',
                'metas_clinicas'         => $celdas['AB'] ?? '',
                'tiempo_hospitalizacion' => $celdas['AC'] ?? '',
            ];
        }

        // Liberar memoria
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($filas)) {
            $this->errorLectura = 'El archivo no tiene filas de datos (solo cabeceras o está vacío).';
        }

        return $filas;
    }

    private function extraerFechaArchivo(string $nombreArchivo): string
    {
        // Extraer todos los grupos de dígitos del nombre del archivo
        // para detectar la fecha independientemente del separador o formato
        preg_match_all('/\d+/', $nombreArchivo, $todos);
        $numeros = $todos[0] ?? [];

        // Buscar YYYY-MM-DD (con guión, punto, espacio o guión bajo)
        // Sin lookbehind — validarFecha rechaza años fuera de 2020-2035
        if (preg_match('/(\d{4})[-_.\s](\d{2})[-_.\s](\d{2})/', $nombreArchivo, $m)) {
            $fecha = $this->validarFecha((int)$m[1], (int)$m[2], (int)$m[3]);
            if ($fecha) return $fecha;
        }

        // Buscar DD-MM-YYYY (formato colombiano)
        if (preg_match('/(\d{2})[-_.\s](\d{2})[-_.\s](\d{4})/', $nombreArchivo, $m)) {
            $fecha = $this->validarFecha((int)$m[3], (int)$m[2], (int)$m[1]);
            if ($fecha) return $fecha;
        }

        // Buscar grupos numéricos sueltos: año de 4 dígitos + mes 2 dígitos + día 2 dígitos
        // Filtra grupos que parezcan años válidos (2020-2030)
        $candidatos = array_filter($numeros, fn($n) => strlen($n) >= 4);
        foreach ($candidatos as $i => $bloque) {
            if (strlen($bloque) === 8) {
                // YYYYMMDD sin separador
                $y = (int)substr($bloque, 0, 4);
                $mo = (int)substr($bloque, 4, 2);
                $d = (int)substr($bloque, 6, 2);
                $fecha = $this->validarFecha($y, $mo, $d);
                if ($fecha) return $fecha;
            }
            if (strlen($bloque) === 4 && $bloque >= 2020 && $bloque <= 2035) {
                // Es un año; buscar mes y día en los grupos siguientes
                $mes = isset($numeros[$i + 1]) ? (int)$numeros[$i + 1] : 0;
                $dia = isset($numeros[$i + 2]) ? (int)$numeros[$i + 2] : 0;
                $fecha = $this->validarFecha((int)$bloque, $mes, $dia);
                if ($fecha) return $fecha;
            }
        }

        return now()->toDateString();
    }

    private function validarFecha(int $y, int $m, int $d): ?string
    {
        if ($y < 2020 || $y > 2035) return null;
        if ($m < 1 || $m > 12) return null;
        if ($d < 1 || $d > 31) return null;
        try {
            return Carbon::createFromDate($y, $m, $d)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Lee fila 3 del Excel y detecta columnas por nombre de cabecera.
     * Devuelve un mapa campo → letra de columna. Si no se encuentra la cabecera,
     * usa la posición por defecto (la del tablero original de la UCI).
     */
    private function detectarColumnas($sheet, int $maxColIdx): array
    {
        // Posiciones por defecto si no se detectan cabeceras
        $columnas = [
            'news'         => 'R',
            'sofa'         => 'S',
            'barthel'      => 'T',
            'de_movilidad' => 'U',
            'rass'         => 'V',
            'bps'          => 'W',
            'eva'          => 'X',
            'must'         => 'Y',
        ];

        // Patrones de cabecera para cada campo (en mayúsculas, se compara con contains)
        $patrones = [
            'news'         => ['NEWS'],
            'sofa'         => ['SOFA'],
            'barthel'      => ['BARTHEL', 'INDICE BARTHEL', 'ÍNDICE BARTHEL', 'ESCALA BARTHEL'],
            'de_movilidad' => ['DE MOVILIDAD', 'DEAMBULACION', 'DEAMBULACIÓN'],
            'rass'         => ['RASS'],
            'bps'          => ['BPS'],
            'eva'          => ['EVA', 'DOLOR EVA'],
            'must'         => ['MUST'],
        ];

        for ($colIdx = 1; $colIdx <= $maxColIdx; $colIdx++) {
            $letra   = Coordinate::stringFromColumnIndex($colIdx);
            $celda   = strtoupper(trim((string)$sheet->getCell($letra . '3')->getFormattedValue()));
            if ($celda === '') continue;

            foreach ($patrones as $campo => $variantes) {
                foreach ($variantes as $variante) {
                    if (str_contains($celda, $variante)) {
                        $columnas[$campo] = $letra;
                        break 2;
                    }
                }
            }
        }

        return $columnas;
    }

    private function extraerNumeroCama(string $ubicacion): ?int
    {
        if (preg_match('/^U(\d+)$/', $ubicacion, $m)) return (int)$m[1];
        return null;
    }

    private function resolverSubunidad(?int $numeroCama): ?string
    {
        if ($numeroCama === null) return null;
        foreach (self::SUBUNIDADES as [$ini, $fin, $nombre]) {
            if ($numeroCama >= $ini && $numeroCama <= $fin) return $nombre;
        }
        return 'Otra';
    }

    private function registrarCambios(Snapshot $nuevo, Snapshot $anterior): void
    {
        $cambios = [];
        foreach (self::CAMPOS_SEGUIMIENTO as $campo) {
            $valNuevo    = trim((string)($nuevo->$campo ?? ''));
            $valAnterior = trim((string)($anterior->$campo ?? ''));
            if ($valNuevo !== $valAnterior) {
                $cambios[] = [
                    'snapshot_id'   => $nuevo->id,
                    'campo'         => $campo,
                    'valor_anterior'=> $valAnterior ?: null,
                    'valor_nuevo'   => $valNuevo ?: null,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }
        }
        if (!empty($cambios)) CambioSnapshot::insert($cambios);
    }

    private function limpiar(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') return null;
        return trim($valor);
    }

    private function numero($valor): ?float
    {
        if ($valor === null || trim((string)$valor) === '') return null;
        $texto = trim((string)$valor);

        // Intento numérico directo (cubre enteros, decimales, negativos)
        $n = filter_var(str_replace(',', '.', $texto), FILTER_VALIDATE_FLOAT);
        if ($n !== false) return $n;

        // Barthel en formato texto — convierte categorías clínicas a puntaje representativo
        $upper = strtoupper($texto);
        return match(true) {
            str_contains($upper, 'INDEPENDIENT')                            => 100.0,
            str_contains($upper, 'LEVE')                                    => 70.0,
            str_contains($upper, 'MODERADO') || str_contains($upper, 'MODERADA') => 45.0,
            str_contains($upper, 'GRAVE') || str_contains($upper, 'SEVERO')  => 20.0,
            str_contains($upper, 'TOTAL') || str_contains($upper, 'COMPLET') => 5.0,
            default => null,
        };
    }
}
