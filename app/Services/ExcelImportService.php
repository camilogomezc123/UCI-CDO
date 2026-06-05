<?php

namespace App\Services;

use App\Models\Paciente;
use App\Models\Snapshot;
use App\Models\CargaArchivo;
use App\Models\CambioSnapshot;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExcelImportService
{
    // Criterios excluidos (neonatos y pediátricos)
    private const CRITERIOS_EXCLUIDOS = ['UCIN', 'PEDIATRIA'];

    // Mapeo de subunidades por rango de número de cama
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

    // Campos clínicos que se comparan para detectar cambios
    private const CAMPOS_SEGUIMIENTO = [
        'ubicacion', 'criterio_atencion', 'especialidad',
        'estado_nutricional', 'dieta',
        'soporte_hemodinamico', 'soporte_ventilatorio', 'movilizacion',
        'news', 'sofa', 'barthel', 'rass', 'bps', 'eva', 'must',
        'riesgos', 'observaciones', 'metas_clinicas',
    ];

    public function procesar(string $rutaArchivo, int $usuarioId): array
    {
        $resultado = ['nuevos' => 0, 'actualizados' => 0, 'omitidos' => 0, 'errores' => []];

        $filas = $this->leerExcel($rutaArchivo);
        if (empty($filas)) {
            $resultado['errores'][] = 'No se pudo leer el archivo o está vacío.';
            return $resultado;
        }

        $fechaArchivo = $this->extraerFechaArchivo($filas);

        DB::transaction(function () use ($filas, $usuarioId, $fechaArchivo, &$resultado) {
            $carga = CargaArchivo::create([
                'nombre_archivo' => basename($this->rutaActual ?? 'datos.xlsx'),
                'fecha_archivo' => $fechaArchivo,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($filas as $fila) {
                $criterio = trim($fila['criterio_atencion'] ?? '');

                // Excluir neonatos y pediátricos
                if (in_array($criterio, self::CRITERIOS_EXCLUIDOS)) {
                    $resultado['omitidos']++;
                    continue;
                }

                // Excluir ubicaciones no-UCI
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
                $subunidad = $this->resolverSubunidad($numeroCama);

                $datosPaciente = [
                    'nombre' => trim($fila['nombre'] ?? ''),
                    'edad' => (int)($fila['edad'] ?? 0),
                    'sexo' => strtoupper(trim($fila['sexo'] ?? '')),
                    'eapb' => trim($fila['eapb'] ?? ''),
                ];

                $paciente = Paciente::where('documento', $documento)->first();
                $esNuevo = false;

                if (!$paciente) {
                    $paciente = Paciente::create(array_merge($datosPaciente, [
                        'documento' => $documento,
                        'activo' => true,
                    ]));
                    $esNuevo = true;
                    $resultado['nuevos']++;
                } else {
                    $paciente->update(array_merge($datosPaciente, ['activo' => true]));
                    $resultado['actualizados']++;
                }

                $datosSnapshot = [
                    'paciente_id' => $paciente->id,
                    'carga_id' => $carga->id,
                    'fecha_snapshot' => $fechaArchivo,
                    'ubicacion' => $ubicacion,
                    'numero_cama' => $numeroCama,
                    'subunidad' => $subunidad,
                    'cie10' => $this->limpiar($fila['cie10'] ?? ''),
                    'diagnostico_oncologico' => $this->limpiar($fila['diagnostico_oncologico'] ?? ''),
                    'diagnosticos' => $this->limpiar($fila['diagnosticos'] ?? ''),
                    'criterio_atencion' => $criterio,
                    'especialidad' => $this->limpiar($fila['especialidad'] ?? ''),
                    'estado_nutricional' => $this->limpiar($fila['estado_nutricional'] ?? ''),
                    'dieta' => $this->limpiar($fila['dieta'] ?? ''),
                    'soporte_hemodinamico' => $this->limpiar($fila['soporte_hemodinamico'] ?? ''),
                    'soporte_ventilatorio' => $this->limpiar($fila['soporte_ventilatorio'] ?? ''),
                    'movilizacion' => $this->limpiar($fila['movilizacion'] ?? ''),
                    'news' => $this->numero($fila['news'] ?? null),
                    'sofa' => $this->limpiar($fila['sofa'] ?? ''),
                    'barthel' => $this->numero($fila['barthel'] ?? null),
                    'de_movilidad' => $this->limpiar($fila['de_movilidad'] ?? ''),
                    'rass' => $this->numero($fila['rass'] ?? null),
                    'bps' => $this->numero($fila['bps'] ?? null),
                    'eva' => $this->numero($fila['eva'] ?? null),
                    'must' => $this->numero($fila['must'] ?? null),
                    'riesgos' => $this->limpiar($fila['riesgos'] ?? ''),
                    'observaciones' => $this->limpiar($fila['observaciones'] ?? ''),
                    'metas_clinicas' => $this->limpiar($fila['metas_clinicas'] ?? ''),
                    'tiempo_hospitalizacion_texto' => $this->limpiar($fila['tiempo_hospitalizacion'] ?? ''),
                ];

                $snapshot = Snapshot::create($datosSnapshot);

                // Detectar cambios respecto al snapshot anterior
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

            // Actualizar contadores en la carga
            $carga->update([
                'nuevos' => $resultado['nuevos'],
                'actualizados' => $resultado['actualizados'],
                'omitidos' => $resultado['omitidos'],
                'errores' => !empty($resultado['errores']) ? implode("\n", $resultado['errores']) : null,
            ]);
        });

        return $resultado;
    }

    private function leerExcel(string $ruta): array
    {
        $this->rutaActual = $ruta;
        $filas = [];

        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) return [];

        // Leer strings compartidos
        $strings = [];
        $ssIndex = $zip->locateName('xl/sharedStrings.xml');
        if ($ssIndex !== false) {
            $ssXml = simplexml_load_string($zip->getFromIndex($ssIndex));
            if ($ssXml) {
                foreach ($ssXml->si as $si) {
                    // Manejar nodos con <r> (rich text) y <t> (plain text)
                    $texto = '';
                    if (isset($si->t)) {
                        $texto = (string)$si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            if (isset($r->t)) $texto .= (string)$r->t;
                        }
                    }
                    $strings[] = $texto;
                }
            }
        }

        // Leer la hoja 1
        $shIndex = $zip->locateName('xl/worksheets/sheet1.xml');
        if ($shIndex === false) { $zip->close(); return []; }

        $shXml = simplexml_load_string($zip->getFromIndex($shIndex));
        $zip->close();

        if (!$shXml) return [];

        // Las filas del Excel: fila 1=título, 2=fecha, 3=cabeceras, 4+=datos
        $filaNum = 0;
        foreach ($shXml->sheetData->row as $row) {
            $filaNum++;
            if ($filaNum < 4) continue; // saltar título, fecha generación, cabeceras

            $celdas = [];
            foreach ($row->c as $c) {
                $col = preg_replace('/[0-9]/', '', (string)$c['r']);
                $val = (string)$c->v;
                $tipo = (string)$c['t'];
                if ($tipo === 's' && $val !== '') {
                    $val = $strings[(int)$val] ?? '';
                }
                $celdas[$col] = $val;
            }

            if (empty(array_filter($celdas))) continue;

            $filas[] = [
                'ubicacion'              => $celdas['B'] ?? '',
                'documento'              => $celdas['C'] ?? '',
                'nombre'                 => $celdas['D'] ?? '',
                'edad'                   => $celdas['E'] ?? 0,
                'sexo'                   => $celdas['F'] ?? '',
                'eapb'                   => $celdas['G'] ?? '',
                'cie10'                  => $celdas['H'] ?? '',
                'diagnostico_oncologico' => $celdas['I'] ?? '',
                'diagnosticos'           => $celdas['J'] ?? '',
                'criterio_atencion'      => $celdas['K'] ?? '',
                'especialidad'           => $celdas['L'] ?? '',
                'estado_nutricional'     => $celdas['M'] ?? '',
                'dieta'                  => $celdas['N'] ?? '',
                'soporte_hemodinamico'   => $celdas['O'] ?? '',
                'soporte_ventilatorio'   => $celdas['P'] ?? '',
                'movilizacion'           => $celdas['Q'] ?? '',
                'news'                   => $celdas['R'] ?? '',
                'sofa'                   => $celdas['S'] ?? '',
                'barthel'                => $celdas['T'] ?? '',
                'de_movilidad'           => $celdas['U'] ?? '',
                'rass'                   => $celdas['V'] ?? '',
                'bps'                    => $celdas['W'] ?? '',
                'eva'                    => $celdas['X'] ?? '',
                'must'                   => $celdas['Y'] ?? '',
                'riesgos'                => $celdas['Z'] ?? '',
                'observaciones'          => $celdas['AA'] ?? '',
                'metas_clinicas'         => $celdas['AB'] ?? '',
                'tiempo_hospitalizacion' => $celdas['AC'] ?? '',
            ];
        }

        return $filas;
    }

    private function extraerFechaArchivo(array $filas): string
    {
        // La fecha se puede inferir del nombre o usar hoy
        return now()->toDateString();
    }

    private function extraerNumeroCama(string $ubicacion): ?int
    {
        if (preg_match('/^U(\d+)$/', $ubicacion, $m)) {
            return (int)$m[1];
        }
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
            $valNuevo = trim((string)($nuevo->$campo ?? ''));
            $valAnterior = trim((string)($anterior->$campo ?? ''));
            if ($valNuevo !== $valAnterior) {
                $cambios[] = [
                    'snapshot_id' => $nuevo->id,
                    'campo' => $campo,
                    'valor_anterior' => $valAnterior ?: null,
                    'valor_nuevo' => $valNuevo ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if (!empty($cambios)) {
            CambioSnapshot::insert($cambios);
        }
    }

    private function limpiar(?string $valor): ?string
    {
        if ($valor === null || trim($valor) === '') return null;
        return trim($valor);
    }

    private function numero($valor): ?float
    {
        if ($valor === null || trim((string)$valor) === '') return null;
        $n = filter_var($valor, FILTER_VALIDATE_FLOAT);
        return $n !== false ? $n : null;
    }

    private ?string $rutaActual = null;
}
