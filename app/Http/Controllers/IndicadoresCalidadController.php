<?php

namespace App\Http\Controllers;

use App\Models\AntibioticosUci;
use App\Models\BalanceHidrico;
use App\Models\BundleVentilacion;
use App\Models\CamUci;
use App\Models\Dispositivo;
use App\Models\GoalOfCare;
use App\Models\NutricionDiaria;
use App\Models\Paciente;
use App\Models\PicsEvaluacion;
use App\Models\PicsRiesgo;
use App\Models\Snapshot;
use App\Models\Trazador;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndicadoresCalidadController extends Controller
{
    // ── Definición completa de indicadores ───────────────────────────────────
    public const DEFINICIONES = [

        // ─── CAT 1: SEGURIDAD / MORTALIDAD ───────────────────────────────────
        'IND-01' => [
            'categoria'  => 'Seguridad',
            'nombre'     => 'Mortalidad bruta UCI',
            'descripcion'=> 'Fallecidos en UCI / total egresados en el período',
            'unidad'     => '%',
            'fuente'     => 'SEMICYUC 2017 · IHI',
            'meta'       => '< 15%',
            'verde'      => [null, 10],
            'amarillo'   => [10, 20],
            'rojo'       => [20, null],
            'icono'      => 'bi-heart-pulse',
            'periodo'    => true,
        ],
        'IND-02' => [
            'categoria'  => 'Seguridad',
            'nombre'     => 'Tasa de reingreso UCI',
            'descripcion'=> 'Reingresos a UCI / total ingresos en el período',
            'unidad'     => '%',
            'fuente'     => 'SEMICYUC 2017',
            'meta'       => '< 5%',
            'verde'      => [null, 3],
            'amarillo'   => [3, 6],
            'rojo'       => [6, null],
            'icono'      => 'bi-arrow-repeat',
            'periodo'    => true,
        ],
        'IND-03' => [
            'categoria'  => 'Seguridad',
            'nombre'     => 'Alta a domicilio (desenlace favorable)',
            'descripcion'=> 'Alta a casa / total egresados en el período',
            'unidad'     => '%',
            'fuente'     => 'JCI / IHI',
            'meta'       => '> 40%',
            'verde'      => [50, null],
            'amarillo'   => [30, 50],
            'rojo'       => [null, 30],
            'icono'      => 'bi-house-check',
            'periodo'    => true,
            'invertir'   => true,
        ],

        // ─── CAT 2: EFICIENCIA / FLUJO ────────────────────────────────────────
        'IND-04' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => 'Estancia media UCI',
            'descripcion'=> 'Promedio de días entre ingreso y egreso UCI en el período',
            'unidad'     => 'días',
            'fuente'     => 'SEMICYUC 2017',
            'meta'       => '< 7 días',
            'verde'      => [null, 5],
            'amarillo'   => [5, 9],
            'rojo'       => [9, null],
            'icono'      => 'bi-calendar2-range',
            'periodo'    => true,
        ],
        'IND-05' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => 'Estancias prolongadas (> 7 días)',
            'descripcion'=> 'Porcentaje de ingresos con estancia > 7 días',
            'unidad'     => '%',
            'fuente'     => 'ESICM / SEMICYUC',
            'meta'       => '< 20%',
            'verde'      => [null, 15],
            'amarillo'   => [15, 25],
            'rojo'       => [25, null],
            'icono'      => 'bi-calendar-x',
            'periodo'    => true,
        ],
        'IND-06' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => 'Tiempo espera egreso (actual)',
            'descripcion'=> 'Promedio de horas de espera entre indicación y egreso físico UCI',
            'unidad'     => 'horas',
            'fuente'     => 'JCI / Flujo hospitalario',
            'meta'       => '< 4 h',
            'verde'      => [null, 3],
            'amarillo'   => [3, 8],
            'rojo'       => [8, null],
            'icono'      => 'bi-hourglass-split',
            'periodo'    => false,
        ],
        'IND-07' => [
            'categoria'  => 'Eficiencia',
            'nombre'     => '% Pacientes pendientes de egreso',
            'descripcion'=> 'Pacientes con indicación de salida pero aún en UCI',
            'unidad'     => '%',
            'fuente'     => 'Indicador local UCI',
            'meta'       => '< 10%',
            'verde'      => [null, 8],
            'amarillo'   => [8, 18],
            'rojo'       => [18, null],
            'icono'      => 'bi-person-walking',
            'periodo'    => false,
        ],

        // ─── CAT 3: NEUROLOGÍA / DELIRIUM ─────────────────────────────────────
        'IND-08' => [
            'categoria'  => 'Neurología',
            'nombre'     => 'Tasa de delirium (CAM-ICU hoy)',
            'descripcion'=> 'Pacientes CAM-UCI positivo / total evaluados hoy',
            'unidad'     => '%',
            'fuente'     => 'SCCM ABCDEF Bundle · ICDSC',
            'meta'       => '< 40%',
            'verde'      => [null, 30],
            'amarillo'   => [30, 50],
            'rojo'       => [50, null],
            'icono'      => 'bi-brain',
            'periodo'    => false,
        ],
        'IND-09' => [
            'categoria'  => 'Neurología',
            'nombre'     => 'Cobertura evaluación CAM-UCI',
            'descripcion'=> 'Pacientes activos con CAM-UCI registrado hoy',
            'unidad'     => '%',
            'fuente'     => 'SCCM · SEMICYUC',
            'meta'       => '> 90%',
            'verde'      => [90, null],
            'amarillo'   => [70, 90],
            'rojo'       => [null, 70],
            'icono'      => 'bi-clipboard2-check',
            'periodo'    => false,
            'invertir'   => true,
        ],

        // ─── CAT 4: DOLOR Y SEDACIÓN ──────────────────────────────────────────
        'IND-10' => [
            'categoria'  => 'Dolor y Sedación',
            'nombre'     => 'Dolor no controlado (EVA > 4 / BPS > 6)',
            'descripcion'=> 'Pacientes con dolor fuera de meta / total con evaluación',
            'unidad'     => '%',
            'fuente'     => 'SCCM PAD Guidelines 2018',
            'meta'       => '< 15%',
            'verde'      => [null, 10],
            'amarillo'   => [10, 20],
            'rojo'       => [20, null],
            'icono'      => 'bi-thermometer-high',
            'periodo'    => false,
        ],
        'IND-11' => [
            'categoria'  => 'Dolor y Sedación',
            'nombre'     => 'Sobresedación (RASS < -3)',
            'descripcion'=> 'Pacientes con sedación excesiva fuera de meta terapéutica',
            'unidad'     => '%',
            'fuente'     => 'SCCM PAD Guidelines · Bundle C',
            'meta'       => '< 20%',
            'verde'      => [null, 15],
            'amarillo'   => [15, 25],
            'rojo'       => [25, null],
            'icono'      => 'bi-moon-stars',
            'periodo'    => false,
        ],

        // ─── CAT 5: SOPORTE AVANZADO (informativos) ───────────────────────────
        'IND-12' => [
            'categoria'  => 'Soporte',
            'nombre'     => 'Pacientes en ventilación mecánica',
            'descripcion'=> 'Porcentaje de pacientes activos con soporte ventilatorio',
            'unidad'     => '%',
            'fuente'     => 'Estadística UCI',
            'meta'       => 'Informativo',
            'verde'      => null,
            'amarillo'   => null,
            'rojo'       => null,
            'icono'      => 'bi-lungs',
            'periodo'    => false,
        ],
        'IND-13' => [
            'categoria'  => 'Soporte',
            'nombre'     => 'Pacientes con soporte hemodinámico',
            'descripcion'=> 'Porcentaje de pacientes activos con vasoactivos',
            'unidad'     => '%',
            'fuente'     => 'Estadística UCI',
            'meta'       => 'Informativo',
            'verde'      => null,
            'amarillo'   => null,
            'rojo'       => null,
            'icono'      => 'bi-droplet-half',
            'periodo'    => false,
        ],

        // ─── CAT 6: ALERTAS CLÍNICAS ──────────────────────────────────────────
        'IND-14' => [
            'categoria'  => 'Alertas clínicas',
            'nombre'     => 'Pacientes con NEWS ≥ 5',
            'descripcion'=> 'Pacientes con puntuación NEWS de alerta (≥5) en último registro',
            'unidad'     => '%',
            'fuente'     => 'Royal College of Physicians 2017',
            'meta'       => '< 20%',
            'verde'      => [null, 15],
            'amarillo'   => [15, 30],
            'rojo'       => [30, null],
            'icono'      => 'bi-exclamation-triangle',
            'periodo'    => false,
        ],
        'IND-15' => [
            'categoria'  => 'Alertas clínicas',
            'nombre'     => 'Pacientes con SOFA ≥ 10',
            'descripcion'=> 'Pacientes con falla orgánica múltiple severa (SOFA ≥ 10)',
            'unidad'     => '%',
            'fuente'     => 'ESICM · Singer et al. 2016',
            'meta'       => '< 15%',
            'verde'      => [null, 10],
            'amarillo'   => [10, 20],
            'rojo'       => [20, null],
            'icono'      => 'bi-activity',
            'periodo'    => false,
        ],

        // ─── CAT 7: CALIDAD DE CUIDADO — TRAZADORES ───────────────────────────
        'IND-16' => [
            'categoria'  => 'Calidad trazadores',
            'nombre'     => 'Bundle hora-1 Sepsis completo (S5)',
            'descripcion'=> '% de casos donde todos los criterios del bundle de reanimación en la primera hora se cumplieron (S5) sobre los casos evaluables',
            'unidad'     => '%',
            'fuente'     => 'SSC 2021 · SEMICYUC',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-heart-pulse',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-17' => [
            'categoria'  => 'Calidad trazadores',
            'nombre'     => 'Adherencia Bundle ABCDEF (trazadores)',
            'descripcion'=> 'Cumplimiento promedio del bundle ABCDEF en trazadores Sepsis cerrados',
            'unidad'     => '%',
            'fuente'     => 'SCCM ICU Liberation Bundle',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-list-check',
            'periodo'    => false,
            'invertir'   => true,
        ],

        // ─── CAT 8: UCI LIBERATION — BUNDLE ABCDEF DIARIO ────────────────────
        'IND-18' => [
            'categoria'  => 'UCI Liberation',
            'nombre'     => 'Bundle ABCDEF completo (hoy)',
            'descripcion'=> '% pacientes activos con todos los componentes aplicables del bundle en meta',
            'unidad'     => '%',
            'fuente'     => 'SCCM ICU Liberation · Marra et al. 2017',
            'meta'       => '≥ 60%',
            'verde'      => [60, null],
            'amarillo'   => [40, 60],
            'rojo'       => [null, 40],
            'icono'      => 'bi-shield-check',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-19' => [
            'categoria'  => 'UCI Liberation',
            'nombre'     => 'Componente A: Dolor controlado',
            'descripcion'=> '% pacientes con EVA ≤ 3 o BPS ≤ 5 en el registro actual',
            'unidad'     => '%',
            'fuente'     => 'SCCM PAD Guidelines 2018',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-emoji-smile',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-20' => [
            'categoria'  => 'UCI Liberation',
            'nombre'     => 'Componente B: SAT + SBT exitoso',
            'descripcion'=> '% pacientes en VMI con vacación de sedación y prueba de respiración espontánea exitosa',
            'unidad'     => '%',
            'fuente'     => 'IHI Bundle Ventilador · Kress et al.',
            'meta'       => '≥ 70%',
            'verde'      => [70, null],
            'amarillo'   => [50, 70],
            'rojo'       => [null, 50],
            'icono'      => 'bi-lungs-fill',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-21' => [
            'categoria'  => 'UCI Liberation',
            'nombre'     => 'Componente C: RASS en objetivo',
            'descripcion'=> '% pacientes con RASS real dentro de ±1 del objetivo terapéutico',
            'unidad'     => '%',
            'fuente'     => 'SCCM PAD Guidelines 2018',
            'meta'       => '≥ 75%',
            'verde'      => [75, null],
            'amarillo'   => [50, 75],
            'rojo'       => [null, 50],
            'icono'      => 'bi-capsule',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-22' => [
            'categoria'  => 'UCI Liberation',
            'nombre'     => 'Componente E: Movilización activa',
            'descripcion'=> '% pacientes con nivel de movilización ≥ 1 (activa en cama o superior)',
            'unidad'     => '%',
            'fuente'     => 'ABCDEF Bundle · E · Schweickert et al.',
            'meta'       => '≥ 60%',
            'verde'      => [60, null],
            'amarillo'   => [40, 60],
            'rojo'       => [null, 40],
            'icono'      => 'bi-person-walking',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-23' => [
            'categoria'  => 'UCI Liberation',
            'nombre'     => 'Componente F: Familia involucrada',
            'descripcion'=> '% pacientes con contacto familiar activo o reunión clínica registrada hoy',
            'unidad'     => '%',
            'fuente'     => 'ABCDEF Bundle · F · Davidson et al.',
            'meta'       => '≥ 70%',
            'verde'      => [70, null],
            'amarillo'   => [50, 70],
            'rojo'       => [null, 50],
            'icono'      => 'bi-people-fill',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-24' => [
            'categoria'  => 'UCI Liberation',
            'nombre'     => 'Calidad del sueño — RCSQ promedio',
            'descripcion'=> 'Puntuación promedio Richards-Campbell Sleep Questionnaire (0-100) en el período',
            'unidad'     => 'pts',
            'fuente'     => 'Richards-Campbell Sleep Questionnaire · SCCM',
            'meta'       => '≥ 50 pts',
            'verde'      => [50, null],
            'amarillo'   => [25, 50],
            'rojo'       => [null, 25],
            'icono'      => 'bi-moon-fill',
            'periodo'    => true,
            'invertir'   => true,
        ],

        // ─── CAT 9: PICS — RIESGO Y VIGILANCIA ───────────────────────────────
        'IND-25' => [
            'categoria'  => 'PICS — Riesgo',
            'nombre'     => '% Pacientes con riesgo PICS alto',
            'descripcion'=> 'Egresados en 180 días con score de riesgo PICS calculado en nivel alto',
            'unidad'     => '%',
            'fuente'     => 'Pandharipande et al. · Davidson et al. 2012',
            'meta'       => 'Informativo',
            'verde'      => null,
            'amarillo'   => null,
            'rojo'       => null,
            'icono'      => 'bi-exclamation-diamond-fill',
            'periodo'    => false,
        ],
        'IND-26' => [
            'categoria'  => 'PICS — Riesgo',
            'nombre'     => 'Cobertura cálculo riesgo PICS',
            'descripcion'=> '% egresados de los últimos 180 días con score PICS calculado',
            'unidad'     => '%',
            'fuente'     => 'Protocolo local PICS-UCI',
            'meta'       => '≥ 90%',
            'verde'      => [90, null],
            'amarillo'   => [70, 90],
            'rojo'       => [null, 70],
            'icono'      => 'bi-clipboard2-pulse',
            'periodo'    => false,
            'invertir'   => true,
        ],

        // ─── CAT 10: PICS — COBERTURA DE SEGUIMIENTO ─────────────────────────
        'IND-27' => [
            'categoria'  => 'PICS — Seguimiento',
            'nombre'     => 'Cobertura PICS al egreso UCI (0–14 días)',
            'descripcion'=> '% egresados en 180 días con evaluación PICS completada al egreso de UCI',
            'unidad'     => '%',
            'fuente'     => 'SCCM PICS Task Force · ICU Liberation',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-person-check',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-28' => [
            'categoria'  => 'PICS — Seguimiento',
            'nombre'     => 'Cobertura PICS a 30 días',
            'descripcion'=> '% de pacientes elegibles (egreso ≥ 25 días) con evaluación PICS de 30 días completada',
            'unidad'     => '%',
            'fuente'     => 'SCCM PICS Task Force',
            'meta'       => '≥ 70%',
            'verde'      => [70, null],
            'amarillo'   => [50, 70],
            'rojo'       => [null, 50],
            'icono'      => 'bi-calendar2-check',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-29' => [
            'categoria'  => 'PICS — Seguimiento',
            'nombre'     => 'Cobertura PICS a 90 días',
            'descripcion'=> '% de pacientes elegibles (egreso ≥ 85 días) con evaluación PICS de 90 días completada',
            'unidad'     => '%',
            'fuente'     => 'SCCM PICS Task Force',
            'meta'       => '≥ 60%',
            'verde'      => [60, null],
            'amarillo'   => [40, 60],
            'rojo'       => [null, 40],
            'icono'      => 'bi-calendar2-week',
            'periodo'    => false,
            'invertir'   => true,
        ],

        // ─── CAT 11: PICS — DESENLACES MEDIDOS ───────────────────────────────
        'IND-30' => [
            'categoria'  => 'PICS — Desenlaces',
            'nombre'     => 'Deterioro cognitivo al egreso UCI (AMT-10 < 8)',
            'descripcion'=> '% evaluaciones al egreso con deterioro cognitivo (AMT-10 < 8/10)',
            'unidad'     => '%',
            'fuente'     => 'Abbreviated Mental Test 10 · Hodkinson 1972',
            'meta'       => 'Informativo',
            'verde'      => null,
            'amarillo'   => null,
            'rojo'       => null,
            'icono'      => 'bi-brain',
            'periodo'    => false,
        ],
        'IND-31' => [
            'categoria'  => 'PICS — Desenlaces',
            'nombre'     => 'Ansiedad o depresión post-UCI a 30 días (HADS ≥ 8)',
            'descripcion'=> '% evaluaciones de 30 días con ansiedad (HADS-A ≥ 8) o depresión (HADS-D ≥ 8)',
            'unidad'     => '%',
            'fuente'     => 'Hospital Anxiety and Depression Scale · Zigmond 1983',
            'meta'       => '< 30%',
            'verde'      => [null, 20],
            'amarillo'   => [20, 35],
            'rojo'       => [35, null],
            'icono'      => 'bi-emoji-frown-fill',
            'periodo'    => false,
        ],
        'IND-32' => [
            'categoria'  => 'PICS — Desenlaces',
            'nombre'     => 'Screening PTSD positivo a 90 días (PC-PTSD-5 ≥ 3)',
            'descripcion'=> '% evaluaciones de 90 días con cribado de PTSD positivo (PC-PTSD-5 ≥ 3/5)',
            'unidad'     => '%',
            'fuente'     => 'Primary Care PTSD Screen for DSM-5 · Prins et al. 2016',
            'meta'       => '< 20%',
            'verde'      => [null, 15],
            'amarillo'   => [15, 30],
            'rojo'       => [30, null],
            'icono'      => 'bi-shield-exclamation',
            'periodo'    => false,
        ],
        'IND-33' => [
            'categoria'  => 'PICS — Desenlaces',
            'nombre'     => 'Distress familiar severo (PICS-F ≥ 12/20)',
            'descripcion'=> '% evaluaciones familiares con distress psicológico severo (PICS-F ≥ 12)',
            'unidad'     => '%',
            'fuente'     => 'PICS-F Tool · Davidson et al. · ICU Liberation',
            'meta'       => 'Informativo',
            'verde'      => null,
            'amarillo'   => null,
            'rojo'       => null,
            'icono'      => 'bi-people',
            'periodo'    => false,
        ],

        // ─── CAT 12: INFECCIONES ASOCIADAS A DISPOSITIVOS (IAAS) ─────────────
        'IND-34' => [
            'categoria'  => 'IAAS',
            'nombre'     => 'Tasa CLABSI (bacteriemia por CVC)',
            'descripcion'=> 'Infecciones de torrente sanguíneo asociadas a catéter por 1 000 días-CVC',
            'unidad'     => '/1000 d',
            'fuente'     => 'CDC NHSN · JCI',
            'meta'       => '< 1.2 /1000 días-CVC',
            'verde'      => [null, 1.2],
            'amarillo'   => [1.2, 2.5],
            'rojo'       => [2.5, null],
            'icono'      => 'bi-droplet-fill',
            'periodo'    => true,
        ],
        'IND-35' => [
            'categoria'  => 'IAAS',
            'nombre'     => 'Tasa CAUTI (ITU por sonda vesical)',
            'descripcion'=> 'Infecciones urinarias asociadas a sonda vesical por 1 000 días-SV',
            'unidad'     => '/1000 d',
            'fuente'     => 'CDC NHSN · JCI',
            'meta'       => '< 1.9 /1000 días-SV',
            'verde'      => [null, 1.9],
            'amarillo'   => [1.9, 3.5],
            'rojo'       => [3.5, null],
            'icono'      => 'bi-thermometer',
            'periodo'    => true,
        ],
        'IND-36' => [
            'categoria'  => 'IAAS',
            'nombre'     => 'Tasa VAP (neumonía por ventilador)',
            'descripcion'=> 'Neumonías asociadas a ventilación mecánica por 1 000 días-VM',
            'unidad'     => '/1000 d',
            'fuente'     => 'IHI · SEMICYUC · CDC NHSN',
            'meta'       => '< 2.5 /1000 días-VM',
            'verde'      => [null, 2.5],
            'amarillo'   => [2.5, 5.0],
            'rojo'       => [5.0, null],
            'icono'      => 'bi-lungs',
            'periodo'    => true,
        ],

        // ─── CAT 13: NUTRICIÓN UCI ────────────────────────────────────────────
        'IND-37' => [
            'categoria'  => 'Nutrición',
            'nombre'     => 'Cobertura calórica promedio',
            'descripcion'=> 'Promedio de (kcal aportadas / kcal meta × 100) en registros del período con meta definida',
            'unidad'     => '%',
            'fuente'     => 'ESPEN Critical Care Guidelines 2023',
            'meta'       => '70–100%',
            'verde'      => [70, null],
            'amarillo'   => [50, 70],
            'rojo'       => [null, 50],
            'icono'      => 'bi-cup-hot-fill',
            'periodo'    => true,
            'invertir'   => true,
        ],
        'IND-38' => [
            'categoria'  => 'Nutrición',
            'nombre'     => 'Nutrición enteral precoz (< 48 h de ingreso)',
            'descripcion'=> '% inicios de NE registrados en el período que ocurrieron en las primeras 48 h del ingreso UCI',
            'unidad'     => '%',
            'fuente'     => 'ESPEN 2023 · ASPEN/SCCM 2022',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-clock-history',
            'periodo'    => true,
            'invertir'   => true,
        ],

        // ─── CAT 14: BALANCE HÍDRICO ──────────────────────────────────────────
        'IND-39' => [
            'categoria'  => 'Balance hídrico',
            'nombre'     => '% Pacientes con balance positivo severo (> +1 000 mL/día)',
            'descripcion'=> 'Pacientes con balance hídrico diario > +1 000 mL registrado hoy',
            'unidad'     => '%',
            'fuente'     => 'FACTT Trial · Wiedemann et al. NEJM 2006',
            'meta'       => '< 30%',
            'verde'      => [null, 20],
            'amarillo'   => [20, 35],
            'rojo'       => [35, null],
            'icono'      => 'bi-droplet-half',
            'periodo'    => false,
        ],

        // ─── CAT 15: ATB STEWARDSHIP ──────────────────────────────────────────
        'IND-40' => [
            'categoria'  => 'ATB Stewardship',
            'nombre'     => 'Tasa de de-escalada antibiótica',
            'descripcion'=> '% ATB con cultivo disponible que fueron de-escalados en el período',
            'unidad'     => '%',
            'fuente'     => 'IDSA Stewardship Guidelines · SSC 2021',
            'meta'       => '≥ 60%',
            'verde'      => [60, null],
            'amarillo'   => [40, 60],
            'rojo'       => [null, 40],
            'icono'      => 'bi-arrow-down-circle-fill',
            'periodo'    => true,
            'invertir'   => true,
        ],
        'IND-41' => [
            'categoria'  => 'ATB Stewardship',
            'nombre'     => '% ATB con cultivo previo tomado',
            'descripcion'=> '% de antibióticos iniciados en el período con cultivo microbiológico tomado',
            'unidad'     => '%',
            'fuente'     => 'IDSA Stewardship Guidelines',
            'meta'       => '≥ 90%',
            'verde'      => [90, null],
            'amarillo'   => [70, 90],
            'rojo'       => [null, 70],
            'icono'      => 'bi-eyedropper',
            'periodo'    => true,
            'invertir'   => true,
        ],

        // ─── CAT 16: GOALS OF CARE / HUMANIZACIÓN ────────────────────────────
        'IND-42' => [
            'categoria'  => 'Goals of Care',
            'nombre'     => 'Cobertura GoC en pacientes activos',
            'descripcion'=> '% pacientes activos con al menos una conversación de objetivos de cuidado documentada',
            'unidad'     => '%',
            'fuente'     => 'JCI · SEMICYUC · Humanización UCI',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-chat-heart-fill',
            'periodo'    => false,
            'invertir'   => true,
        ],
        'IND-43' => [
            'categoria'  => 'Goals of Care',
            'nombre'     => '% VMI > 72 h con reunión familiar documentada',
            'descripcion'=> '% pacientes con VMI ≥ 3 días que tienen reunión familiar registrada en los últimos 3 días',
            'unidad'     => '%',
            'fuente'     => 'ABCDEF Bundle · F · JCI Standard PFE',
            'meta'       => '≥ 80%',
            'verde'      => [80, null],
            'amarillo'   => [60, 80],
            'rojo'       => [null, 60],
            'icono'      => 'bi-house-heart-fill',
            'periodo'    => false,
            'invertir'   => true,
        ],
    ];

    // ── Controlador principal ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $dias  = (int) $request->input('dias', 30);
        $dias  = in_array($dias, [7, 30, 60, 90, 180]) ? $dias : 30;
        $desde = now()->subDays($dias)->startOfDay();
        $hasta = now()->endOfDay();

        $resultados = $this->calcularTodos($desde, $hasta);
        $tendencia  = $this->tendencia6meses();
        $resumen    = $this->resumenSemaforo($resultados);

        return view('indicadores.index', compact(
            'resultados', 'tendencia', 'resumen', 'dias', 'desde', 'hasta'
        ));
    }

    // ── Cálculo de todos los indicadores ─────────────────────────────────────

    private function calcularTodos(Carbon $desde, Carbon $hasta): array
    {
        // ── Base: egresados e ingresos del período ────────────────────────────
        $egresados   = Paciente::whereNotNull('egreso_uci')->whereBetween('egreso_uci', [$desde, $hasta])->get();
        $totalEgr    = $egresados->count();
        $conEstancia = $egresados->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci);

        $ingresosPer   = Paciente::whereBetween('ingreso_uci', [$desde, $hasta])->count();
        $reingresosPer = Paciente::where('numero_ingresos', '>', 1)->whereBetween('ingreso_uci', [$desde, $hasta])->count();

        // ── Activos con snapshot ───────────────────────────────────────────────
        $activos      = Paciente::where('activo', true)->with('ultimoSnapshot')->get();
        $totalActivos = $activos->count();
        $activoIds    = $activos->pluck('id');

        // ── CAM-UCI hoy ────────────────────────────────────────────────────────
        $camHoy       = CamUci::whereDate('fecha', today())->get();
        $camEvaluados = $camHoy->whereIn('resultado', ['positivo', 'negativo'])->count();
        $camPos       = $camHoy->where('resultado', 'positivo')->count();

        // ── Scores clínicos del último snapshot ───────────────────────────────
        $conNews  = $activos->filter(fn($p) => $p->ultimoSnapshot?->news !== null);
        $conSofa  = $activos->filter(fn($p) => $p->ultimoSnapshot?->sofa !== null);
        $conRass  = $activos->filter(fn($p) => $p->ultimoSnapshot?->rass !== null);
        $conDolor = $activos->filter(fn($p) =>
            $p->ultimoSnapshot?->eva !== null || $p->ultimoSnapshot?->bps !== null
        );

        $toNum = fn($v) => is_numeric($v) ? (float)$v
            : (preg_match('/([-]?\d+(?:[.,]\d+)?)/', str_replace(',', '.', (string)$v), $m) ? (float)$m[1] : null);

        $pendientesEgr = $activos->filter(fn($p) => $p->salida_hospitalizacion && !$p->egreso_uci)->count();

        $esperaHoras = $activos
            ->filter(fn($p) => $p->salida_hospitalizacion && !$p->egreso_uci && $p->activo)
            ->map(fn($p) => $p->salida_hospitalizacion->diffInMinutes(now()) / 60)
            ->values();

        // ── Trazadores Sepsis cerrados ────────────────────────────────────────
        $sepsisTotal = Trazador::cerrados()->where('tipo_trazador', 'sepsis')->get();

        // ── Valores ──────────────────────────────────────────────────────────
        $vals = [];

        // IND-01: Mortalidad bruta
        $fall = $egresados->where('tipo_egreso', 'fallecimiento')->count();
        $vals['IND-01'] = $totalEgr > 0 ? round($fall / $totalEgr * 100, 1) : null;

        // IND-02: Reingreso
        $vals['IND-02'] = $ingresosPer > 0 ? round($reingresosPer / $ingresosPer * 100, 1) : null;

        // IND-03: Alta a domicilio
        $altaCasa = $egresados->where('tipo_egreso', 'alta_casa')->count();
        $vals['IND-03'] = $totalEgr > 0 ? round($altaCasa / $totalEgr * 100, 1) : null;

        // IND-04: Estancia media
        $vals['IND-04'] = $conEstancia->isNotEmpty()
            ? round($conEstancia->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)), 1)
            : null;

        // IND-05: Estancias prolongadas
        $prolongadas    = $conEstancia->filter(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci) > 7)->count();
        $vals['IND-05'] = $conEstancia->isNotEmpty()
            ? round($prolongadas / $conEstancia->count() * 100, 1)
            : null;

        // IND-06: Tiempo espera egreso
        $vals['IND-06'] = $esperaHoras->isNotEmpty() ? round($esperaHoras->avg(), 1) : null;

        // IND-07: % Pendientes egreso
        $vals['IND-07'] = $totalActivos > 0 ? round($pendientesEgr / $totalActivos * 100, 1) : null;

        // IND-08: Tasa delirium
        $vals['IND-08'] = $camEvaluados > 0 ? round($camPos / $camEvaluados * 100, 1) : null;

        // IND-09: Cobertura CAM-UCI
        $vals['IND-09'] = $totalActivos > 0 ? round($camHoy->count() / $totalActivos * 100, 1) : null;

        // IND-10: Dolor no controlado
        $dolorFueraMeta = $conDolor->filter(
            fn($p) => ($p->ultimoSnapshot?->eva !== null && (float)$p->ultimoSnapshot->eva > 4)
                   || ($p->ultimoSnapshot?->bps !== null && (float)$p->ultimoSnapshot->bps > 6)
        )->count();
        $vals['IND-10'] = $conDolor->isNotEmpty() ? round($dolorFueraMeta / $conDolor->count() * 100, 1) : null;

        // IND-11: Sobresedación RASS < -3
        $sobreSed       = $conRass->filter(fn($p) => ($p->ultimoSnapshot?->rass ?? 0) < -3)->count();
        $vals['IND-11'] = $conRass->isNotEmpty() ? round($sobreSed / $conRass->count() * 100, 1) : null;

        // IND-12: % VMI
        $vent           = $activos->filter(fn($p) => !empty($p->ultimoSnapshot?->soporte_ventilatorio))->count();
        $vals['IND-12'] = $totalActivos > 0 ? round($vent / $totalActivos * 100, 1) : null;

        // IND-13: % Soporte hemodinámico
        $hemo           = $activos->filter(fn($p) => !empty($p->ultimoSnapshot?->soporte_hemodinamico))->count();
        $vals['IND-13'] = $totalActivos > 0 ? round($hemo / $totalActivos * 100, 1) : null;

        // IND-14: NEWS ≥ 5
        $newsAlerta     = $conNews->filter(fn($p) => (float)($p->ultimoSnapshot?->news ?? 0) >= 5)->count();
        $vals['IND-14'] = $conNews->isNotEmpty() ? round($newsAlerta / $conNews->count() * 100, 1) : null;

        // IND-15: SOFA ≥ 10
        $sofaAlerta     = $conSofa->filter(function ($p) use ($toNum) {
            $v = $toNum($p->ultimoSnapshot?->sofa);
            return $v !== null && $v >= 10;
        })->count();
        $vals['IND-15'] = $conSofa->isNotEmpty() ? round($sofaAlerta / $conSofa->count() * 100, 1) : null;

        // Tasas S1–S7 sepsis
        $sRates = [];
        foreach (['S1','S2','S3','S4','S5','S6','S7'] as $s) {
            $ev = $sepsisTotal->filter(function ($t) use ($s) {
                $v = $t->resultados['semaforo']['por_indicador'][$s]['valor'] ?? 'x';
                return $v !== null && $v !== 'N/A';
            });
            $cu = $ev->filter(fn($t) =>
                ($t->resultados['semaforo']['por_indicador'][$s]['valor'] ?? null) === 100
            )->count();
            $sRates[$s] = $ev->count() > 0
                ? ['pct' => round($cu / $ev->count() * 100, 1), 'cumple' => $cu, 'total' => $ev->count()]
                : null;
        }

        $vals['IND-16'] = $sRates['S5']['pct'] ?? null;
        $abProm         = $sepsisTotal->avg(fn($t) => $t->resultados['adherencia_abcdef_pct'] ?? null);
        $vals['IND-17'] = $abProm !== null ? round($abProm, 1) : null;

        // ─── BLOQUE: UCI Liberation — ABCDEF diario ──────────────────────────
        $bundlesHoy = BundleVentilacion::whereDate('fecha', today())->get()->keyBy('paciente_id');
        $camHoyById = $camHoy->keyBy('paciente_id');

        $abcdefRows = $activos->map(function ($p) use ($bundlesHoy, $camHoyById) {
            $bundle  = $bundlesHoy[$p->id] ?? null;
            $cam     = $camHoyById[$p->id] ?? null;
            $snap    = $p->ultimoSnapshot;
            $eva     = (float)($snap?->eva ?? 0);
            $bps     = (float)($snap?->bps ?? 0);
            $rass    = $snap?->rass !== null ? (int)$snap->rass : null;
            $rassObj = $p->rass_objetivo !== null ? (int)$p->rass_objetivo : null;
            return [
                'A' => $this->evalA($eva, $bps),
                'B' => $this->evalB($bundle),
                'C' => $this->evalC($rass, $rassObj),
                'D' => $this->evalD($cam),
                'E' => $this->evalE($bundle, $snap),
                'F' => $this->evalF($bundle),
            ];
        });

        // IND-18: Bundle completo
        $conAplicables18 = $abcdefRows->filter(
            fn($r) => collect($r)->filter(fn($i) => $i['ok'] !== null)->isNotEmpty()
        )->count();
        $conCompleto18   = $abcdefRows->filter(function ($r) {
            $items = collect($r)->filter(fn($i) => $i['ok'] !== null);
            return $items->isNotEmpty() && $items->every(fn($i) => $i['ok'] === true);
        })->count();
        $vals['IND-18']  = $conAplicables18 > 0 ? round($conCompleto18 / $conAplicables18 * 100, 1) : null;

        // IND-19 a IND-23: Componentes A, B, C, E, F
        $compMap    = ['A' => 'IND-19', 'B' => 'IND-20', 'C' => 'IND-21', 'E' => 'IND-22', 'F' => 'IND-23'];
        $compCounts = [];
        foreach ($compMap as $comp => $ind) {
            $evalComp        = $abcdefRows->filter(fn($r) => $r[$comp]['ok'] !== null);
            $cumplComp       = $evalComp->filter(fn($r) => $r[$comp]['ok'] === true)->count();
            $totalComp       = $evalComp->count();
            $vals[$ind]      = $totalComp > 0 ? round($cumplComp / $totalComp * 100, 1) : null;
            $compCounts[$comp] = ['cumpl' => $cumplComp, 'total' => $totalComp];
        }

        // IND-24: RCSQ promedio
        $rcsqBase   = BundleVentilacion::whereBetween('fecha', [$desde, $hasta])->whereNotNull('rcsq_score');
        $rcsqAvg    = $rcsqBase->avg('rcsq_score');
        $rcsqN      = $rcsqBase->count();
        $vals['IND-24'] = $rcsqAvg !== null ? round((float)$rcsqAvg, 1) : null;

        // ─── BLOQUE: PICS ─────────────────────────────────────────────────────
        $egresados180 = Paciente::whereNotNull('egreso_uci')
            ->where('egreso_uci', '>=', now()->subDays(180))
            ->get();
        $total180 = $egresados180->count();
        $ids180   = $egresados180->pluck('id');

        $picsEvals = PicsEvaluacion::whereIn('paciente_id', $ids180)->where('tipo', 'paciente')->get();
        $picsFam   = PicsEvaluacion::whereIn('paciente_id', $ids180)->where('tipo', 'familia')->get();

        // IND-25: % riesgo alto
        $altosRiesgo    = PicsRiesgo::whereIn('paciente_id', $ids180)->where('nivel_riesgo', 'alto')->count();
        $vals['IND-25'] = $total180 > 0 ? round($altosRiesgo / $total180 * 100, 1) : null;

        // IND-26: Cobertura cálculo riesgo
        $conRiesgoCalc  = PicsRiesgo::whereIn('paciente_id', $ids180)->count();
        $vals['IND-26'] = $total180 > 0 ? round($conRiesgoCalc / $total180 * 100, 1) : null;

        // IND-27: Cobertura al egreso (todos elegibles)
        $conEgreso      = $picsEvals->where('momento', 'egreso')->pluck('paciente_id')->unique()->count();
        $vals['IND-27'] = $total180 > 0 ? round($conEgreso / $total180 * 100, 1) : null;

        // IND-28: Cobertura 30d
        $eleg30         = $egresados180->filter(fn($p) => $p->egreso_uci->diffInDays(now()) >= 25)->count();
        $con30          = $picsEvals->where('momento', '30d')->pluck('paciente_id')->unique()->count();
        $vals['IND-28'] = $eleg30 > 0 ? round($con30 / $eleg30 * 100, 1) : null;

        // IND-29: Cobertura 90d
        $eleg90         = $egresados180->filter(fn($p) => $p->egreso_uci->diffInDays(now()) >= 85)->count();
        $con90          = $picsEvals->where('momento', '90d')->pluck('paciente_id')->unique()->count();
        $vals['IND-29'] = $eleg90 > 0 ? round($con90 / $eleg90 * 100, 1) : null;

        // IND-30: Deterioro cognitivo (AMT-10 < 8)
        $egresoConAmt    = $picsEvals->where('momento', 'egreso')->filter(fn($e) => $e->amt_score !== null);
        $detCog          = $egresoConAmt->filter(fn($e) => $e->amt_score < 8)->count();
        $egresoConAmtN   = $egresoConAmt->count();
        $vals['IND-30']  = $egresoConAmtN > 0 ? round($detCog / $egresoConAmtN * 100, 1) : null;

        // IND-31: Ansiedad/depresión 30d (HADS ≥ 8)
        $evals30   = $picsEvals->where('momento', '30d')->filter(
            fn($e) => $e->hads_ansiedad !== null || $e->hads_depresion !== null
        );
        $ansDepPos = $evals30->filter(fn($e) => ($e->hads_ansiedad ?? 0) >= 8 || ($e->hads_depresion ?? 0) >= 8)->count();
        $evals30N  = $evals30->count();
        $vals['IND-31'] = $evals30N > 0 ? round($ansDepPos / $evals30N * 100, 1) : null;

        // IND-32: PTSD 90d (PC-PTSD-5 ≥ 3)
        $evals90  = $picsEvals->where('momento', '90d')->filter(fn($e) => $e->pcptsd_score !== null);
        $ptsdPos  = $evals90->filter(fn($e) => $e->pcptsd_score >= 3)->count();
        $evals90N = $evals90->count();
        $vals['IND-32'] = $evals90N > 0 ? round($ptsdPos / $evals90N * 100, 1) : null;

        // IND-33: Distress familiar (PICS-F ≥ 12)
        $famConScore  = $picsFam->filter(fn($e) => $e->picsf_distress !== null);
        $famDistres   = $famConScore->filter(fn($e) => $e->picsf_distress >= 12)->count();
        $famConScoreN = $famConScore->count();
        $vals['IND-33'] = $famConScoreN > 0 ? round($famDistres / $famConScoreN * 100, 1) : null;

        // ─── BLOQUE: IAAS ─────────────────────────────────────────────────────
        $diasCvc = $this->sumaDiasDisp('cvc');
        $diasSv  = $this->sumaDiasDisp('sonda_vesical');
        $diasVmD = $this->sumaDiasDisp('vm');

        $clabsiN        = Dispositivo::where('tipo', 'cvc')->where('tipo_iaas', 'CLABSI')->count();
        $cautiN         = Dispositivo::where('tipo', 'sonda_vesical')->where('tipo_iaas', 'CAUTI')->count();
        $vapN           = Dispositivo::where('tipo', 'vm')->where('tipo_iaas', 'VAP')->count();
        $vals['IND-34'] = $diasCvc > 0 ? round($clabsiN / $diasCvc * 1000, 2) : 0;
        $vals['IND-35'] = $diasSv  > 0 ? round($cautiN  / $diasSv  * 1000, 2) : 0;
        $vals['IND-36'] = $diasVmD > 0 ? round($vapN    / $diasVmD * 1000, 2) : 0;

        // ─── BLOQUE: Nutrición ────────────────────────────────────────────────
        $nutrisPeriodo = NutricionDiaria::whereBetween('fecha', [$desde, $hasta])->get();

        $nutConMeta     = $nutrisPeriodo->filter(
            fn($n) => ($n->kcal_meta ?? 0) > 0 && ($n->kcal_aportadas ?? 0) > 0
        );
        $avgKcal        = $nutConMeta->avg(fn($n) => $n->kcal_aportadas / $n->kcal_meta * 100);
        $nutConMetaN    = $nutConMeta->count();
        $vals['IND-37'] = $avgKcal !== null ? round($avgKcal, 1) : null;

        $neIniciosAll = NutricionDiaria::whereBetween('fecha', [$desde, $hasta])
            ->where('inicio_ne_hoy', true)
            ->with('paciente:id,ingreso_uci')
            ->get();
        $totalNeI     = $neIniciosAll->count();
        $nePreco      = $neIniciosAll->filter(function ($n) {
            $ing = $n->paciente?->ingreso_uci;
            if (!$ing) return false;
            $dias = (int) Carbon::parse($ing)->diffInDays($n->fecha);
            return $dias >= 0 && $dias <= 2;
        })->count();
        $vals['IND-38'] = $totalNeI > 0 ? round($nePreco / $totalNeI * 100, 1) : null;

        // ─── BLOQUE: Balance hídrico ──────────────────────────────────────────
        $balancesHoy    = BalanceHidrico::whereDate('fecha', today())->get();
        $totalBal       = $balancesHoy->count();
        $balPos         = $balancesHoy->filter(fn($b) => $b->balance() > 1000)->count();
        $vals['IND-39'] = $totalBal > 0 ? round($balPos / $totalBal * 100, 1) : null;

        // ─── BLOQUE: ATB Stewardship ──────────────────────────────────────────
        $atbPeriodo   = AntibioticosUci::whereBetween('fecha_inicio', [$desde, $hasta])->get();
        $totalAtb     = $atbPeriodo->count();
        $conCultivo   = $atbPeriodo->where('cultivo_disponible', true)->count();
        $atbConCultiv = $atbPeriodo->where('cultivo_disponible', true);
        $totalAtbCC   = $atbConCultiv->count();
        $deescalados  = $atbConCultiv->where('de_escalado', true)->count();
        $vals['IND-40'] = $totalAtbCC > 0 ? round($deescalados / $totalAtbCC * 100, 1) : null;
        $vals['IND-41'] = $totalAtb    > 0 ? round($conCultivo  / $totalAtb    * 100, 1) : null;

        // ─── BLOQUE: Goals of Care ────────────────────────────────────────────
        $gocActivoIds  = GoalOfCare::whereIn('paciente_id', $activoIds)->pluck('paciente_id')->unique();
        $activosConGoc = $gocActivoIds->count();
        $vals['IND-42'] = $totalActivos > 0 ? round($activosConGoc / $totalActivos * 100, 1) : null;

        $bundleCountsPP = BundleVentilacion::whereIn('paciente_id', $activoIds)
            ->select('paciente_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('paciente_id')
            ->pluck('cnt', 'paciente_id');
        $vmiLargoIds    = $bundleCountsPP->filter(fn($cnt) => $cnt >= 3)->keys();
        $totalVmi3      = $vmiLargoIds->count();
        $conReunionN    = $totalVmi3 > 0
            ? BundleVentilacion::whereIn('paciente_id', $vmiLargoIds)
                ->where('fecha', '>=', today()->subDays(3)->toDateString())
                ->where('familia_reunion_clinica', true)
                ->pluck('paciente_id')->unique()->count()
            : 0;
        $vals['IND-43'] = $totalVmi3 > 0 ? round($conReunionN / $totalVmi3 * 100, 1) : null;

        // ── Aux precalculado para tarjetas ────────────────────────────────────
        $auxEx = [
            'IND-18' => ['n' => $conCompleto18,             'd' => $conAplicables18],
            'IND-19' => ['n' => $compCounts['A']['cumpl'],  'd' => $compCounts['A']['total']],
            'IND-20' => ['n' => $compCounts['B']['cumpl'],  'd' => $compCounts['B']['total']],
            'IND-21' => ['n' => $compCounts['C']['cumpl'],  'd' => $compCounts['C']['total']],
            'IND-22' => ['n' => $compCounts['E']['cumpl'],  'd' => $compCounts['E']['total']],
            'IND-23' => ['n' => $compCounts['F']['cumpl'],  'd' => $compCounts['F']['total']],
            'IND-24' => ['n' => $rcsqN,                     'd' => null],
            'IND-25' => ['n' => $altosRiesgo,               'd' => $total180],
            'IND-26' => ['n' => $conRiesgoCalc,             'd' => $total180],
            'IND-27' => ['n' => $conEgreso,                 'd' => $total180],
            'IND-28' => ['n' => $con30,                     'd' => $eleg30],
            'IND-29' => ['n' => $con90,                     'd' => $eleg90],
            'IND-30' => ['n' => $detCog,                    'd' => $egresoConAmtN],
            'IND-31' => ['n' => $ansDepPos,                 'd' => $evals30N],
            'IND-32' => ['n' => $ptsdPos,                   'd' => $evals90N],
            'IND-33' => ['n' => $famDistres,                'd' => $famConScoreN],
            'IND-34' => ['n' => $clabsiN, 'd' => $diasCvc, 'label' => 'eventos / días-CVC'],
            'IND-35' => ['n' => $cautiN,  'd' => $diasSv,  'label' => 'eventos / días-SV'],
            'IND-36' => ['n' => $vapN,    'd' => $diasVmD, 'label' => 'eventos / días-VM'],
            'IND-37' => ['n' => $nutConMetaN,               'd' => null],
            'IND-38' => ['n' => $nePreco,                   'd' => $totalNeI],
            'IND-39' => ['n' => $balPos,                    'd' => $totalBal],
            'IND-40' => ['n' => $deescalados,               'd' => $totalAtbCC],
            'IND-41' => ['n' => $conCultivo,                'd' => $totalAtb],
            'IND-42' => ['n' => $activosConGoc,             'd' => $totalActivos],
            'IND-43' => ['n' => $conReunionN,               'd' => $totalVmi3],
        ];

        // ── Construir resultado con semáforo ──────────────────────────────────
        $resultado = [];
        foreach (self::DEFINICIONES as $cod => $def) {
            $valor = $vals[$cod] ?? null;
            $resultado[$cod] = array_merge($def, [
                'codigo'   => $cod,
                'valor'    => $valor,
                'semaforo' => $this->semaforo($valor, $def),
                'aux'      => $this->aux($cod, $activos, $egresados, $camHoy, $sepsisTotal, $ingresosPer, $reingresosPer, $sRates, $auxEx),
            ]);
        }

        return $resultado;
    }

    // ── Datos auxiliares contextuales por indicador ───────────────────────────

    private function aux(
        string $cod,
        $activos,
        $egresados,
        $camHoy,
        $sepsisTotal,
        int $ingresosPer,
        int $reingresosPer,
        array $sRates = [],
        array $auxEx  = []
    ): array {
        if (isset($auxEx[$cod])) {
            return $auxEx[$cod];
        }

        $totalActivos = $activos->count();
        $totalEgr     = $egresados->count();

        return match($cod) {
            'IND-01' => ['n' => $egresados->where('tipo_egreso', 'fallecimiento')->count(), 'd' => $totalEgr],
            'IND-02' => ['n' => $reingresosPer, 'd' => $ingresosPer],
            'IND-03' => ['n' => $egresados->where('tipo_egreso', 'alta_casa')->count(), 'd' => $totalEgr],
            'IND-05' => ['n' => $egresados->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci && $p->ingreso_uci->diffInDays($p->egreso_uci) > 7)->count(),
                         'd' => $egresados->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)->count()],
            'IND-07' => ['n' => $activos->filter(fn($p) => $p->salida_hospitalizacion && !$p->egreso_uci)->count(), 'd' => $totalActivos],
            'IND-08' => ['n' => $camHoy->where('resultado','positivo')->count(),
                         'd' => $camHoy->whereIn('resultado',['positivo','negativo'])->count()],
            'IND-09' => ['n' => $camHoy->count(), 'd' => $totalActivos],
            'IND-16' => ['n' => $sRates['S5']['cumple'] ?? null, 'd' => $sRates['S5']['total'] ?? null, 'tasas' => $sRates],
            'IND-17' => ['n' => $sepsisTotal->count(), 'd' => null],
            default   => [],
        };
    }

    // ── Clasificación semáforo ────────────────────────────────────────────────

    private function semaforo(?float $valor, array $def): string
    {
        if ($valor === null) return 'sin_dato';
        if ($def['verde'] === null && $def['amarillo'] === null && $def['rojo'] === null) return 'informativo';

        foreach (['verde', 'amarillo', 'rojo'] as $nivel) {
            [$min, $max] = $def[$nivel];
            $enRango = ($min === null || $valor >= $min) && ($max === null || $valor < $max);
            if ($enRango) return $nivel;
        }
        return 'sin_dato';
    }

    // ── Resumen global del semáforo ───────────────────────────────────────────

    private function resumenSemaforo(array $resultados): array
    {
        $counts = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0, 'sin_dato' => 0, 'informativo' => 0];
        foreach ($resultados as $r) {
            $counts[$r['semaforo']] = ($counts[$r['semaforo']] ?? 0) + 1;
        }
        $evaluados = $counts['verde'] + $counts['amarillo'] + $counts['rojo'];
        $counts['pct_verde'] = $evaluados > 0 ? round($counts['verde'] / $evaluados * 100) : 0;
        return $counts;
    }

    // ── Tendencia mensual últimos 6 meses ─────────────────────────────────────

    private function tendencia6meses(): array
    {
        $meses = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes    = now()->subMonths($i);
            $inicio = $mes->copy()->startOfMonth();
            $fin    = $mes->copy()->endOfMonth();

            $egr      = Paciente::whereNotNull('egreso_uci')->whereBetween('egreso_uci', [$inicio, $fin])->get();
            $totalEgr = $egr->count();

            $meses[] = [
                'label'          => $mes->translatedFormat('M Y'),
                'egresados'      => $totalEgr,
                'fallecidos'     => $egr->where('tipo_egreso', 'fallecimiento')->count(),
                'mortalidad'     => $totalEgr > 0
                    ? round($egr->where('tipo_egreso','fallecimiento')->count() / $totalEgr * 100, 1)
                    : null,
                'estancia_media' => $egr->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)->isNotEmpty()
                    ? round($egr->filter(fn($p) => $p->ingreso_uci && $p->egreso_uci)
                        ->avg(fn($p) => $p->ingreso_uci->diffInDays($p->egreso_uci)), 1)
                    : null,
                'reingresos' => Paciente::where('numero_ingresos', '>', 1)
                    ->whereBetween('ingreso_uci', [$inicio, $fin])->count(),
            ];
        }
        return $meses;
    }

    // ── Evaluadores Bundle ABCDEF ─────────────────────────────────────────────

    private function evalA(float $eva, float $bps): array
    {
        if ($eva === 0.0 && $bps === 0.0) return ['ok' => null, 'valor' => '—', 'label' => 'Sin dato'];
        $sinDolor = ($eva > 0 && $eva <= 3) || ($bps > 0 && $bps <= 5);
        $label    = [];
        if ($eva > 0) $label[] = "EVA {$eva}";
        if ($bps > 0) $label[] = "BPS {$bps}";
        return ['ok' => $sinDolor, 'valor' => implode(' / ', $label), 'label' => $sinDolor ? 'Sin dolor' : 'Dolor'];
    }

    private function evalB(?BundleVentilacion $b): array
    {
        if (!$b) return ['ok' => null, 'valor' => '—', 'label' => 'Sin registro'];
        $sat    = $b->vacacion_sedacion;
        $sbt    = $b->sbt;
        $satRes = $b->sat_resultado;
        $sbtRes = $b->sbt_resultado;
        if (!$sat && !$sbt) return ['ok' => null, 'valor' => 'No aplica', 'label' => 'Sin VMI'];
        $ok     = ($sat && $satRes !== 'fallido') && ($sbt && $sbtRes !== 'fallido');
        $partes = [];
        if ($sat) $partes[] = 'SAT ' . ($satRes ?? '✓');
        if ($sbt) $partes[] = 'SBT ' . ($sbtRes ?? '✓');
        return ['ok' => $ok, 'valor' => implode(' + ', $partes), 'label' => $ok ? 'Exitoso' : 'Fallido/incompleto'];
    }

    private function evalC(?int $rassReal, ?int $rassObj): array
    {
        if ($rassReal === null) return ['ok' => null, 'valor' => '—', 'label' => 'Sin RASS'];
        if ($rassObj  === null) return ['ok' => null, 'valor' => "RASS {$rassReal}", 'label' => 'Sin objetivo'];
        $ok = $rassReal >= $rassObj && $rassReal <= ($rassObj + 1);
        return ['ok' => $ok, 'valor' => "RASS {$rassReal} (obj {$rassObj})", 'label' => $ok ? 'En objetivo' : 'Fuera objetivo'];
    }

    private function evalD(?CamUci $cam): array
    {
        if (!$cam) return ['ok' => null, 'valor' => '—', 'label' => 'Sin CAM-UCI'];
        $ok    = $cam->resultado === 'negativo';
        $label = match($cam->resultado) {
            'positivo'     => 'Delirium ' . ($cam->subtipo_delirium ?? ''),
            'negativo'     => 'Sin delirium',
            'no_evaluable' => 'No evaluable',
            default        => $cam->resultado,
        };
        return ['ok' => $ok, 'valor' => $label, 'label' => $label];
    }

    private function evalE(?BundleVentilacion $b, $snap): array
    {
        $nivel = $b?->nivel_movilizacion;
        if ($nivel === null) {
            $mov = strtolower($snap?->movilizacion ?? '');
            if (!$mov) return ['ok' => null, 'valor' => '—', 'label' => 'Sin dato'];
            $ok = str_contains($mov, 'activ') || str_contains($mov, 'ambu') || str_contains($mov, 'sent');
            return ['ok' => $ok, 'valor' => $snap->movilizacion, 'label' => $ok ? 'Activa' : 'Pasiva/ninguna'];
        }
        $labels = ['Pasiva en cama', 'Activa en cama', 'Sedestación', 'Bipedestación', 'Deambulación'];
        $ok     = $nivel >= 1;
        return ['ok' => $ok, 'valor' => "Nivel {$nivel}: " . ($labels[$nivel] ?? ''), 'label' => $labels[$nivel] ?? "Nivel {$nivel}"];
    }

    private function evalF(?BundleVentilacion $b): array
    {
        if (!$b) return ['ok' => null, 'valor' => '—', 'label' => 'Sin registro'];
        $reunion  = $b->familia_reunion_clinica ?? false;
        $contacto = $b->familia_involucrada     ?? false;
        if (!$contacto && !$reunion) return ['ok' => false, 'valor' => 'Sin contacto', 'label' => 'Sin familia'];
        $ok    = $contacto || $reunion;
        $label = $reunion ? 'Reunión clínica' : 'Contacto activo';
        return ['ok' => $ok, 'valor' => $label, 'label' => $label];
    }

    // ── Suma de días-dispositivo compatible con PostgreSQL y MySQL ────────────

    private function sumaDiasDisp(string $tipo): int
    {
        $sql = DB::getDriverName() === 'pgsql'
            ? "SUM(COALESCE(fecha_retiro, CURRENT_DATE) - fecha_inicio + 1)"
            : "SUM(DATEDIFF(COALESCE(fecha_retiro, CURDATE()), fecha_inicio) + 1)";

        return (int) (Dispositivo::where('tipo', $tipo)->selectRaw("{$sql} as dias")->value('dias') ?? 0);
    }
}
