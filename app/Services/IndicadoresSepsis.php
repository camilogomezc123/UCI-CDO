<?php

namespace App\Services;

use App\Contracts\IndicadoresTrazadorInterface;
use Carbon\Carbon;

class IndicadoresSepsis implements IndicadoresTrazadorInterface
{
    // ─── Punto de entrada ─────────────────────────────────────────────────────

    public function calcular(array $datos): array
    {
        $f1   = $datos['fase1_activacion']  ?? [];
        $f2   = $datos['fase2_bundle_1h']   ?? [];
        $f3   = $datos['fase3_reeval']      ?? [];
        $metas = $datos['metas_manejo']     ?? [];
        $abcd = $datos['abcdef']            ?? [];
        $dpac = $datos['datos_paciente']    ?? [];
        $eAntes   = $datos['encuesta_antes']   ?? [];
        $eDespues = $datos['encuesta_despues'] ?? [];

        $sepsis   = $this->calcularSepsis($f1, $f2, $f3, $dpac);
        $abcdef   = $this->calcularAbcdef($abcd);
        $metasPct = $this->calcularMetasPct($metas);
        $semaforo = $this->calcularSemaforo($sepsis, $abcdef);
        $escalasAntes   = $this->calcularEscalas($eAntes);
        $escalasDespues = $this->calcularEscalas($eDespues);
        $comparativo    = $this->calcularComparativo($escalasAntes, $escalasDespues);

        return [
            'sepsis'          => $sepsis,
            'abcdef'          => $abcdef,
            'metas_pct'       => $metasPct,
            'semaforo'        => $semaforo,
            'adherencia_reanimacion_pct' => $semaforo['adherencia_reanimacion_pct'],
            'adherencia_abcdef_pct'      => $semaforo['adherencia_abcdef_pct'],
            'puntuacion_global_pct'      => $semaforo['puntuacion_global_pct'],
            'escalas_antes'   => $escalasAntes,
            'escalas_despues' => $escalasDespues,
            'comparativo'     => $comparativo,
        ];
    }

    // ─── Utilidades de fechas ─────────────────────────────────────────────────

    private function parseFecha(?string $valor): ?Carbon
    {
        if (empty($valor)) return null;
        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Minutos entre dos fechas (b − a). Null si alguna falta. */
    private function minutos(?string $a, ?string $b): ?int
    {
        $ta = $this->parseFecha($a);
        $tb = $this->parseFecha($b);
        if (!$ta || !$tb) return null;
        return (int) $ta->diffInMinutes($tb, false);
    }

    // ─── S1–S8 ────────────────────────────────────────────────────────────────

    private function calcularSepsis(array $f1, array $f2, array $f3, array $dpac): array
    {
        // Extraer valores clave
        $tc    = $f1['fase1_activacion.fecha_y_hora_de_activacion_tiempo_cero_B4'] ?? null;
        $tLac  = $f2['fase2_bundle_1h.lactato_serico_toma_B13'] ?? null;
        $tAtb  = $f2['fase2_bundle_1h.antibiotico_empirico_1_dosis_B19'] ?? null;
        $tVaso = $f2['fase2_bundle_1h.vasopresor_hora_de_inicio_B26'] ?? null;

        $hemocultivos = $f2['fase2_bundle_1h.hemocultivos_tomados_B17'] ?? null;
        $formulVaso   = $f2['fase2_bundle_1h.formulacion_vasopresor_pam_65_pad_50_B25'] ?? null;
        $liquidos     = $f2['fase2_bundle_1h.liquidos_endovenosos_indicados_B21'] ?? null;
        $volCrist     = (float)($f2['fase2_bundle_1h.volumen_de_cristaloides_ml_B22'] ?? 0);
        $focoIdent    = $f3['fase3_reeval.control_del_foco_identificado_B44'] ?? null;
        $focoReali    = $f3['fase3_reeval.control_del_foco_realizado_menor_a_6_h_B45'] ?? null;
        $desenlace    = $dpac['datos.desenlace_crear_lista_desplegable_muer_B28'] ?? null;

        // Minutos
        $lacMin  = $this->minutos($tc, $tLac);
        $atbMin  = $this->minutos($tc, $tAtb);
        $vasoMin = $this->minutos($tc, $tVaso);

        // S1 — Activación
        $s1 = !empty($tc) ? 100 : 0;

        // S2 — Lactato ≤ 60 min
        $s2 = null;
        if (!empty($tc) && !empty($tLac)) {
            $s2 = ($lacMin !== null && $lacMin <= 60) ? 100 : 0;
        }

        // S3 — Antibiótico ≤ 60 min
        $s3 = null;
        if (!empty($tc) && !empty($tAtb)) {
            $s3 = ($atbMin !== null && $atbMin <= 60) ? 100 : 0;
        }

        // S4 — Hemocultivos
        $s4 = ($hemocultivos === 'Sí') ? 100 : (($hemocultivos !== null) ? 0 : null);

        // S5 — Bundle 1 hora (todos los elementos presentes)
        $s5 = null;
        if (!empty($tc) && $lacMin !== null && $atbMin !== null && $hemocultivos !== null) {
            $cumple = true;
            if ($lacMin > 60) $cumple = false;
            if ($atbMin > 60) $cumple = false;
            if ($hemocultivos !== 'Sí') $cumple = false;
            if ($formulVaso === 'Sí' && ($vasoMin === null || $vasoMin > 60)) $cumple = false;
            if ($liquidos === 'Sí' && $volCrist <= 0) $cumple = false;
            $s5 = $cumple ? 100 : 0;
        }

        // S6 — Vasopresor oportuno
        $s6 = null;
        if ($formulVaso === 'No') {
            $s6 = 'N/A';
        } elseif ($formulVaso === 'Sí') {
            $s6 = ($vasoMin !== null && $vasoMin <= 60) ? 100 : 0;
        }

        // S7 — Control del foco
        $s7 = null;
        if ($focoIdent === 'No') {
            $s7 = 'N/A';
        } elseif ($focoIdent === 'Sí') {
            $s7 = ($focoReali === 'Sí') ? 100 : (($focoReali === 'No') ? 0 : null);
        }

        // S8 — Mortalidad (informativo)
        $s8 = $desenlace;

        return compact('s1', 's2', 's3', 's4', 's5', 's6', 's7', 's8',
            'lacMin', 'atbMin', 'vasoMin');
    }

    // ─── ABCDEF ───────────────────────────────────────────────────────────────

    private function calcularAbcdef(array $abcd): array
    {
        // Indicadores ratio (clave: código, valor: num/den → pct)
        $codigos = ['A1','A2','A3','B1','B2','B3','C1','C2','C3','C4',
                    'D1','D3','D4','E1','E3','F1','F2','F3','G1'];

        $ratios = [];
        foreach ($codigos as $cod) {
            $num = $abcd["ratio.{$cod}.num"] ?? null;
            $den = $abcd["ratio.{$cod}.den"] ?? null;
            if ($num !== null && $den !== null && (float)$den > 0) {
                $ratios[$cod] = round((float)$num / (float)$den * 100, 1);
            } else {
                $ratios[$cod] = null;
            }
        }

        // Informativos
        $d2Presencia = $abcd['delirium_presencia'] ?? null;
        $d2Subtipo   = $abcd['delirium_subtipo']   ?? null;
        $e2Nivel     = $abcd["ratio.E2.num"] ?? null;  // E2 es informativo_valor

        // Cumplimiento por elemento (A-E + F no tiene cumplimiento_elemento)
        $cumplimiento = [];
        foreach (['A','B','C','D','E'] as $el) {
            $cumplimiento[$el] = $abcd["cumplimiento.{$el}"] ?? null;
        }

        return compact('ratios', 'd2Presencia', 'd2Subtipo', 'e2Nivel', 'cumplimiento');
    }

    // ─── Metas de manejo (% de Sí sobre total respondidas) ───────────────────

    private function calcularMetasPct(array $metas): ?float
    {
        $ids = ['metas.meta_48','metas.meta_49','metas.meta_50','metas.meta_51',
                'metas.meta_52','metas.meta_53','metas.meta_54','metas.meta_55'];

        $si    = 0;
        $total = 0;
        foreach ($ids as $id) {
            $val = $metas[$id] ?? null;
            if ($val !== null && $val !== '') {
                $total++;
                if ($val === 'Sí') $si++;
            }
        }
        return $total > 0 ? round($si / $total * 100, 1) : null;
    }

    // ─── Semáforo ─────────────────────────────────────────────────────────────

    private function semáforoColor(mixed $valor, float $meta, float $piso): string
    {
        if ($valor === null || $valor === '') return 'sin_dato';
        if ($valor === 'N/A') return 'no_aplica';
        $v = (float) $valor;
        if ($v >= $meta) return 'verde';
        if ($v >= $piso) return 'amarillo';
        return 'rojo';
    }

    private function calcularSemaforo(array $sepsis, array $abcdef): array
    {
        $sepsisConfig = [
            'S1' => ['meta' => 85,   'piso' => 70,  'valor' => $sepsis['s1'] ?? null],
            'S2' => ['meta' => 90,   'piso' => 70,  'valor' => $sepsis['s2'] ?? null],
            'S3' => ['meta' => 90,   'piso' => 70,  'valor' => $sepsis['s3'] ?? null],
            'S4' => ['meta' => 85,   'piso' => 70,  'valor' => $sepsis['s4'] ?? null],
            'S5' => ['meta' => 75,   'piso' => 70,  'valor' => $sepsis['s5'] ?? null],
            'S6' => ['meta' => 90,   'piso' => 70,  'valor' => $sepsis['s6'] ?? null],
            'S7' => ['meta' => 82.5, 'piso' => 70,  'valor' => $sepsis['s7'] ?? null],
        ];

        $abcdefConfig = [
            'A1' => ['meta' => 90, 'piso' => 80],
            'A2' => ['meta' => 80, 'piso' => 65],
            'A3' => ['meta' => 85, 'piso' => 70],
            'B1' => ['meta' => 80, 'piso' => 60],
            'B2' => ['meta' => 85, 'piso' => 70],
            'B3' => ['meta' => 85, 'piso' => 80],
            'C1' => ['meta' => 70, 'piso' => 55],
            'C2' => ['meta' => 80, 'piso' => 60],
            'C3' => ['meta' => 75, 'piso' => 60],
            'C4' => ['meta' => 90, 'piso' => 75],
            'D1' => ['meta' => 90, 'piso' => 75],
            'D3' => ['meta' => 60, 'piso' => 45],
            'D4' => ['meta' => 90, 'piso' => 75],
            'E1' => ['meta' => 70, 'piso' => 50],
            'E3' => ['meta' => 90, 'piso' => 75],
            'F1' => ['meta' => 80, 'piso' => 60],
            'F2' => ['meta' => 90, 'piso' => 75],
            'F3' => ['meta' => 80, 'piso' => 60],
            'G1' => ['meta' => 90, 'piso' => 75],
        ];

        $porIndicador = [];
        $sumaReanim   = 0;
        $countReanim  = 0;
        $sumaAbcdef   = 0;
        $countAbcdef  = 0;
        $sumaGlobal   = 0;
        $countGlobal  = 0;

        // Sepsis S1–S7
        foreach ($sepsisConfig as $cod => $cfg) {
            $color = $this->semáforoColor($cfg['valor'], $cfg['meta'], $cfg['piso']);
            $porIndicador[$cod] = [
                'valor' => $cfg['valor'],
                'color' => $color,
                'meta'  => $cfg['meta'],
                'piso'  => $cfg['piso'],
            ];
            if ($color !== 'no_aplica') {
                $val = ($color === 'sin_dato') ? 0 : (float)$cfg['valor'];
                $sumaReanim  += $val;
                $countReanim++;
                $sumaGlobal  += $val;
                $countGlobal++;
            }
        }

        // ABCDEF
        $ratios = $abcdef['ratios'] ?? [];
        foreach ($abcdefConfig as $cod => $cfg) {
            $valor = $ratios[$cod] ?? null;
            $color = $this->semáforoColor($valor, $cfg['meta'], $cfg['piso']);
            $porIndicador[$cod] = [
                'valor' => $valor,
                'color' => $color,
                'meta'  => $cfg['meta'],
                'piso'  => $cfg['piso'],
            ];
            if ($color !== 'no_aplica') {
                $val = ($color === 'sin_dato') ? 0 : (float)$valor;
                $sumaAbcdef  += $val;
                $countAbcdef++;
                $sumaGlobal  += $val;
                $countGlobal++;
            }
        }

        $adherenciaReanimacion = $countReanim  > 0 ? round($sumaReanim / $countReanim, 1)  : null;
        $adherenciaAbcdef      = $countAbcdef  > 0 ? round($sumaAbcdef / $countAbcdef, 1)  : null;
        $puntuacionGlobal      = $countGlobal  > 0 ? round($sumaGlobal / $countGlobal, 1)  : null;

        return [
            'por_indicador'              => $porIndicador,
            'adherencia_reanimacion_pct' => $adherenciaReanimacion,
            'adherencia_abcdef_pct'      => $adherenciaAbcdef,
            'puntuacion_global_pct'      => $puntuacionGlobal,
        ];
    }

    // ─── Escalas de la encuesta ───────────────────────────────────────────────

    private function calcularEscalas(array $encuesta): array
    {
        if (empty($encuesta)) return [];

        $preguntas = $encuesta['preguntas'] ?? [];

        $q = function (string $id) use ($preguntas): ?int {
            $val = $preguntas[$id] ?? null;
            return ($val !== null && $val !== '') ? (int)$val : null;
        };

        // ── EQ-5D-5L ──────────────────────────────────────────────────────────
        // Dimensiones (code 1-5)
        $mov   = $q('Q1');
        $cuida = ($q('Q5') !== null && $q('Q6') !== null)
                    ? max($q('Q5'), $q('Q6'))
                    : ($q('Q5') ?? $q('Q6'));
        $act   = ($q('Q12') !== null && $q('Q13') !== null)
                    ? max($q('Q12'), $q('Q13'))
                    : ($q('Q12') ?? $q('Q13'));
        $dolor = $q('Q19');
        $animo = $q('Q20');

        $dimensionesEq = [$mov, $cuida, $act, $dolor, $animo];
        $perfil = implode('', array_map(fn($d) => $d ?? '-', $dimensionesEq));
        $todosTienenValor = !in_array(null, $dimensionesEq, true);
        $sumaEq  = $todosTienenValor ? array_sum($dimensionesEq) : null;
        $eqVas   = $q('Q21');

        $eq5d = [
            'movilidad'       => $mov,
            'cuidado_personal'=> $cuida,
            'actividades'     => $act,
            'dolor'           => $dolor,
            'ansiedad'        => $animo,
            'perfil'          => $perfil,
            'suma_niveles'    => $sumaEq,
            'eq_vas'          => $eqVas,
            'nota_lectura'    => 'Más alto = peor',
        ];

        // ── WHODAS 2.0 (12 ítems) ────────────────────────────────────────────
        $whodas_ids = ['Q2','Q12','Q14','Q16','Q20','Q15','Q1','Q6','Q5','Q17','Q18','Q13'];
        $whodas_vals = array_map(fn($id) => $q($id), $whodas_ids);
        $whodas_completo = !in_array(null, $whodas_vals, true);
        $sumaWhodas   = $whodas_completo ? array_sum($whodas_vals) : null;
        $indiceWhodas = $sumaWhodas !== null ? round(($sumaWhodas - 12) / 48 * 100, 1) : null;

        $whodas = [
            'items'       => array_combine($whodas_ids, $whodas_vals),
            'suma'        => $sumaWhodas,
            'indice_0_100'=> $indiceWhodas,
            'nota_lectura'=> 'Más alto = más discapacidad',
        ];

        // ── Barthel ───────────────────────────────────────────────────────────
        // Directos (el code ya es el puntaje)
        $bart_alim = $q('Q8');
        $bart_aseo = $q('Q7');
        $bart_retr = $q('Q9');
        $bart_depo = $q('Q10');
        $bart_micc = $q('Q11');
        $bart_tras = $q('Q3');
        $bart_esca = $q('Q4');

        // Derivados
        $q6 = $q('Q6');
        $q5 = $q('Q5');
        $q1 = $q('Q1');

        $bart_bano = ($q6 !== null) ? ($q6 <= 2 ? 5 : 0)
                        : null;
        $bart_vest = ($q5 !== null) ? ($q5 <= 2 ? 10 : ($q5 === 3 ? 5 : 0))
                        : null;
        $bart_deam = ($q1 !== null) ? ($q1 <= 2 ? 15 : ($q1 === 3 ? 10 : ($q1 === 4 ? 5 : 0)))
                        : null;

        $partes = [$bart_alim, $bart_aseo, $bart_retr, $bart_depo, $bart_micc,
                   $bart_tras, $bart_esca, $bart_bano, $bart_vest, $bart_deam];

        $todosBarthel = !in_array(null, $partes, true);
        $totalBarthel = $todosBarthel ? array_sum($partes) : null;

        $gradoBarthel = null;
        if ($totalBarthel !== null) {
            if ($totalBarthel === 100)           $gradoBarthel = 'Independiente';
            elseif ($totalBarthel >= 60)         $gradoBarthel = 'Dependencia leve';
            elseif ($totalBarthel >= 40)         $gradoBarthel = 'Dependencia moderada';
            elseif ($totalBarthel >= 20)         $gradoBarthel = 'Dependencia grave';
            else                                  $gradoBarthel = 'Dependencia total';
        }

        $barthel = [
            'alimentacion' => $bart_alim,
            'aseo'         => $bart_aseo,
            'retrete'      => $bart_retr,
            'deposicion'   => $bart_depo,
            'miccion'      => $bart_micc,
            'traslado'     => $bart_tras,
            'escaleras'    => $bart_esca,
            'bano'         => $bart_bano,
            'vestirse'     => $bart_vest,
            'deambulacion' => $bart_deam,
            'total'        => $totalBarthel,
            'grado'        => $gradoBarthel,
            'nota_lectura' => 'Más alto = más independiente',
        ];

        // ── CFS ───────────────────────────────────────────────────────────────
        $q22 = $q('Q22');
        $cfsLabels = [
            1 => 'Muy en forma', 2 => 'En forma', 3 => 'Se maneja bien',
            4 => 'Vulnerable', 5 => 'Levemente frágil', 6 => 'Moderadamente frágil',
            7 => 'Severamente frágil', 8 => 'Muy severamente frágil', 9 => 'Enfermo terminal',
        ];

        $cfs = [
            'valor'        => $q22,
            'categoria'    => ($q22 !== null && isset($cfsLabels[$q22])) ? $cfsLabels[$q22] : null,
            'nota_lectura' => 'Más alto = más frágil',
        ];

        return compact('eq5d', 'whodas', 'barthel', 'cfs');
    }

    // ─── Comparativo antes vs después ────────────────────────────────────────

    private function calcularComparativo(array $antes, array $despues): array
    {
        if (empty($antes) || empty($despues)) return [];

        $diff = fn($a, $d) => ($a !== null && $d !== null) ? round($d - $a, 1) : null;

        return [
            'barthel_total'    => $diff($antes['barthel']['total'] ?? null,    $despues['barthel']['total'] ?? null),
            'whodas_indice'    => $diff($antes['whodas']['indice_0_100'] ?? null, $despues['whodas']['indice_0_100'] ?? null),
            'eq5d_suma'        => $diff($antes['eq5d']['suma_niveles'] ?? null, $despues['eq5d']['suma_niveles'] ?? null),
            'eq5d_vas'         => $diff($antes['eq5d']['eq_vas'] ?? null,       $despues['eq5d']['eq_vas'] ?? null),
            'cfs_valor'        => $diff($antes['cfs']['valor'] ?? null,         $despues['cfs']['valor'] ?? null),
            'nota'             => 'Barthel: + mejor. WHODAS/EQ5D suma/CFS: - mejor (menos discapacidad).',
        ];
    }
}
