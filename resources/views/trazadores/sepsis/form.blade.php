@extends('layouts.trazador')
@section('title', 'Trazador Sepsis — ' . ($paciente->nombre ?? 'Paciente'))
@section('page-title', 'Trazador Sepsis &mdash; ' . ($paciente->nombre ?? 'Paciente'))
@section('trazador-estado')
    @php
        $estadoColor = match($trazador->estado ?? 'TRAZADOR_INICIAL') {
            'TRAZADOR_INICIAL'  => 'warning',
            'SEGUIMIENTO_90D'   => 'info',
            'PENDIENTE_DESPUES' => 'orange',
            'CERRADO'           => 'success',
            default             => 'secondary',
        };
    @endphp
    <span class="tz-badge-estado bg-{{ $estadoColor === 'orange' ? 'warning' : $estadoColor }} text-{{ in_array($estadoColor,['warning','info']) ? 'dark' : 'white' }}">
        {{ $trazador->estado ?? 'TRAZADOR_INICIAL' }}
    </span>
@endsection

@push('styles')
<style>
    .campo-amarillo { background: #fffde7 !important; border-color: #f0cc00 !important; }
    .campo-gris     { background: #f0f0f0 !important; color: #666; }
    .seccion-titulo { background: #e8f0fe; border-left: 4px solid #0d6efd;
                      padding: .5rem 1rem; border-radius: 4px; font-weight: 700; margin-bottom: 1rem; }
    .minutos-badge  { font-size: .7rem; background: #0d6efd20; color: #0d6efd;
                      padding: 2px 8px; border-radius: 8px; margin-left: 6px; }
    .label-req { font-size: .8rem; font-weight: 600; color: #444; }
</style>
@endpush

@section('content')
<form method="POST"
      action="{{ $trazador->estado === 'SEGUIMIENTO_90D' || $trazador->estado === 'CERRADO'
                 ? route('trazadores.update', $trazador)
                 : route('trazadores.store', $trazador) }}">
    @csrf
    @if($trazador->estado === 'SEGUIMIENTO_90D' || $trazador->estado === 'CERRADO')
        @method('PATCH')
    @endif

    {{-- Estado actual --}}
    <div class="alert alert-info d-flex align-items-center gap-2 mb-3" style="font-size:.85rem;">
        <i class="bi bi-info-circle-fill"></i>
        Estado: <strong>{{ $trazador->estado }}</strong>
        @if($trazador->fecha_objetivo_despues)
            — Encuesta DESPUÉS objetivo: <strong>{{ $trazador->fecha_objetivo_despues->format('d/m/Y') }}</strong>
        @endif
        <span class="ms-2 text-muted">Los campos amarillos son editables. Los grises son calculados (solo lectura).</span>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════╗ --}}
    {{-- ║  1 · Datos del paciente                                 ║ --}}
    {{-- ╚══════════════════════════════════════════════════════════╝ --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="seccion-titulo">1 · Datos del Paciente</div>
            <div class="row g-2">
            @foreach($modelo['secciones'][0]['campos'] as $campo)
                @php
                    $id    = $campo['id'];
                    $val   = $trazador->datos['datos_paciente'][$id] ?? '';
                    $tipo  = $campo['tipo'];
                    $label = $campo['etiqueta'];
                    $catId = $campo['catalogo'] ?? null;
                    $cat   = $catId ? ($modelo['catalogos'][$catId] ?? []) : [];
                    $inputType = $tipo === 'datetime' ? 'datetime-local'
                               : ($tipo === 'date'   ? 'date'
                               : ($tipo === 'number' ? 'number' : 'text'));
                @endphp
                <div class="col-md-6 col-lg-4">
                    <label class="label-req">{{ $label }}</label>
                    @if($tipo === 'select')
                        <select name="datos[datos_paciente][{{ $id }}]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">— Sin dato —</option>
                            @foreach($cat as $opcion)
                                @php
                                    $optVal = is_array($opcion) ? (string)$opcion['code'] : $opcion;
                                    $optLabel = is_array($opcion) ? $opcion['label'] : $opcion;
                                @endphp
                                <option value="{{ $optVal }}" {{ (string)$val === $optVal ? 'selected' : '' }}>
                                    {{ $optLabel }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="{{ $inputType }}"
                               name="datos[datos_paciente][{{ $id }}]"
                               value="{{ $tipo === 'datetime' ? ($val ? date('Y-m-d\TH:i', strtotime($val)) : '') : $val }}"
                               class="form-control form-control-sm campo-amarillo"
                               {{ $tipo === 'number' ? 'step=any' : '' }}>
                    @endif
                </div>
            @endforeach

            {{-- Calculados: estancia UCI y días VM --}}
            <div class="col-md-6 col-lg-4">
                <label class="label-req text-muted">Estancia en UCI (días) <small>calculado</small></label>
                <input type="text" class="form-control form-control-sm campo-gris" readonly
                       id="estanciaUciDias" value="">
            </div>
            <div class="col-md-6 col-lg-4">
                <label class="label-req text-muted">Días de VM <small>calculado</small></label>
                <input type="text" class="form-control form-control-sm campo-gris" readonly
                       id="diasVM" value="">
            </div>
            </div>
        </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════╗ --}}
    {{-- ║  2 · Evaluación Integrada (Fases I–III + Metas)        ║ --}}
    {{-- ╚══════════════════════════════════════════════════════════╝ --}}
    @php
        $fases = [
            ['key' => 'fase1_activacion', 'idx' => 1, 'titulo' => 'Fase I · Reconocimiento y Activación'],
            ['key' => 'fase2_bundle_1h',  'idx' => 2, 'titulo' => 'Fase II · Bundle de la Primera Hora'],
            ['key' => 'fase3_reeval',     'idx' => 3, 'titulo' => 'Fase III · Reevaluación 3–6 h y Fenotipo'],
        ];
    @endphp

    @foreach($fases as $fase)
    @php $seccion = collect($modelo['secciones'])->firstWhere('id', $fase['key']); @endphp
    <div class="card mb-3">
        <div class="card-body">
            <div class="seccion-titulo">{{ $fase['titulo'] }}</div>
            <div class="row g-2">
            @foreach($seccion['campos'] as $campo)
                @php
                    $id    = $campo['id'];
                    $val   = $trazador->datos[$fase['key']][$id] ?? '';
                    $tipo  = $campo['tipo'];
                    $label = $campo['etiqueta'];
                    $catId = $campo['catalogo'] ?? null;
                    $cat   = $catId ? ($modelo['catalogos'][$catId] ?? []) : [];
                    $inputType = $tipo === 'datetime' ? 'datetime-local'
                               : ($tipo === 'number' ? 'number' : 'text');
                    $esFecha   = $tipo === 'datetime';
                @endphp
                <div class="col-md-6 col-lg-4">
                    <label class="label-req">{{ $label }}
                        @if($esFecha && $fase['key'] !== 'fase1_activacion')
                            <span class="minutos-badge" id="min_{{ str_replace(['.', ' '], '_', $id) }}"></span>
                        @endif
                    </label>
                    @if($tipo === 'select')
                        <select name="datos[{{ $fase['key'] }}][{{ $id }}]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">— Sin dato —</option>
                            @foreach($cat as $opcion)
                                @php
                                    $optVal   = is_array($opcion) ? (string)$opcion['code'] : $opcion;
                                    $optLabel = is_array($opcion) ? $opcion['label'] : $opcion;
                                @endphp
                                <option value="{{ $optVal }}" {{ (string)$val === $optVal ? 'selected' : '' }}>
                                    {{ $optLabel }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="{{ $inputType }}"
                               name="datos[{{ $fase['key'] }}][{{ $id }}]"
                               value="{{ $esFecha ? ($val ? date('Y-m-d\TH:i', strtotime($val)) : '') : $val }}"
                               class="form-control form-control-sm campo-amarillo"
                               id="input_{{ str_replace(['.', ' '], '_', $id) }}"
                               {{ $tipo === 'number' ? 'step=any' : '' }}>
                    @endif
                </div>
            @endforeach
            </div>
        </div>
    </div>
    @endforeach

    {{-- ── Metas de manejo ──────────────────────────────────────────────── --}}
    @php $metasSec = collect($modelo['secciones'])->firstWhere('id', 'metas_manejo'); @endphp
    <div class="card mb-3">
        <div class="card-body">
            <div class="seccion-titulo">Metas de Manejo <small class="fw-normal text-muted">(informativo — 8 metas clínicas)</small></div>
            <div class="row g-2">
            @foreach($metasSec['metas'] as $meta)
                @php
                    $id  = $meta['id'];
                    $val = $trazador->datos['metas_manejo'][$id] ?? '';
                    $cat = $modelo['catalogos']['SI_NO_NE'];
                @endphp
                <div class="col-md-6 col-lg-4">
                    <label class="label-req">{{ $meta['etiqueta'] }}</label>
                    <select name="datos[metas_manejo][{{ $id }}]"
                            class="form-select form-select-sm campo-amarillo meta-item">
                        <option value="">— Sin dato —</option>
                        @foreach($cat as $opcion)
                            <option value="{{ $opcion }}" {{ $val === $opcion ? 'selected' : '' }}>{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            </div>
            <div class="mt-2">
                <label class="label-req text-muted">% Metas cumplidas <small>calculado</small></label>
                <input type="text" class="form-control form-control-sm campo-gris" readonly id="pctMetas" value="—">
            </div>
        </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════╗ --}}
    {{-- ║  3 · Bundle ABCDEF                                     ║ --}}
    {{-- ╚══════════════════════════════════════════════════════════╝ --}}
    @php
        $abcSec = collect($modelo['secciones'])->firstWhere('id', 'abcdef');
        $indicadores = $abcSec['indicadores'];
        $camposEl    = $abcSec['campos_elemento'];
        $abcDatos    = $trazador->datos['abcdef'] ?? [];
        $elementos   = ['A','B','C','D','E'];
        $elementoActual = '';
    @endphp
    <div class="card mb-3">
        <div class="card-body">
            <div class="seccion-titulo">Bundle ABCDEF (Fase IV)</div>
            <p class="text-muted" style="font-size:.82rem;">
                Indicadores ratio: ingrese <strong>numerador</strong> (turnos/días cumplidos)
                y <strong>denominador</strong> (turnos/días evaluados). El % se calcula automáticamente.
            </p>

            <div class="table-responsive">
            <table class="table table-sm table-bordered" style="font-size:.8rem;">
                <thead class="table-light">
                    <tr>
                        <th style="width:40%">Indicador</th>
                        <th>Cód.</th>
                        <th>Num.</th>
                        <th>Den.</th>
                        <th>%</th>
                        <th>Evidencia / Oport. mejora</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($indicadores as $ind)
                    @php
                        $cod   = $ind['codigo'];
                        $letra = substr($cod, 0, 1);
                        // Fila de separación de elemento
                        $printSep = $letra !== $elementoActual;
                        $elementoActual = $letra;

                        $num = $abcDatos["ratio.{$cod}.num"] ?? '';
                        $den = $abcDatos["ratio.{$cod}.den"] ?? '';
                        $evidencia = $abcDatos["evidencia.{$cod}"] ?? '';
                        $oport     = $abcDatos["oportunidad.{$cod}"] ?? '';
                        $esInfo    = $ind['tipo'] === 'informativo_valor';
                    @endphp

                    @if($printSep)
                    <tr class="table-primary">
                        <td colspan="6" class="fw-bold">
                            Elemento {{ $letra }}
                            @php
                                $cumEl = $abcDatos["cumplimiento.{$letra}"] ?? '';
                                // El cumplimiento_elemento se pone después de cada bloque
                            @endphp
                        </td>
                    </tr>
                    @endif

                    <tr>
                        <td>{{ $ind['indicador'] }}</td>
                        <td class="text-center fw-bold">{{ $cod }}</td>
                        @if($esInfo)
                            <td colspan="2">
                                <input type="number" step="any"
                                       name="datos[abcdef][ratio.{{ $cod }}.num]"
                                       value="{{ $abcDatos["ratio.{$cod}.num"] ?? '' }}"
                                       class="form-control form-control-sm campo-amarillo"
                                       placeholder="Valor informativo">
                            </td>
                            <td class="text-center text-muted">info</td>
                        @else
                            <td>
                                <input type="number" min="0"
                                       name="datos[abcdef][ratio.{{ $cod }}.num]"
                                       value="{{ $num }}"
                                       class="form-control form-control-sm campo-amarillo abcdef-num"
                                       data-cod="{{ $cod }}">
                            </td>
                            <td>
                                <input type="number" min="0"
                                       name="datos[abcdef][ratio.{{ $cod }}.den]"
                                       value="{{ $den }}"
                                       class="form-control form-control-sm campo-amarillo abcdef-den"
                                       data-cod="{{ $cod }}">
                            </td>
                            <td>
                                <input type="text" readonly
                                       id="pct_{{ $cod }}"
                                       class="form-control form-control-sm campo-gris"
                                       value="{{ $num !== '' && $den > 0 ? round($num/$den*100,1).'%' : '—' }}">
                            </td>
                        @endif
                        <td>
                            <input type="text"
                                   name="datos[abcdef][evidencia.{{ $cod }}]"
                                   value="{{ $evidencia }}"
                                   placeholder="Evidencia"
                                   class="form-control form-control-sm campo-amarillo mb-1">
                            <input type="text"
                                   name="datos[abcdef][oportunidad.{{ $cod }}]"
                                   value="{{ $oport }}"
                                   placeholder="Oportunidad mejora"
                                   class="form-control form-control-sm campo-amarillo">
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            </div>

            {{-- Campos especiales: D2 delirium y cumplimiento por elemento --}}
            <div class="row g-2 mt-2">
                @foreach($camposEl as $campo)
                @php
                    $catId = $campo['catalogo'];
                    $cat   = $modelo['catalogos'][$catId] ?? [];
                @endphp
                @if($campo['tipo'] === 'cumplimiento_elemento')
                    <div class="col-md-4 col-lg-3">
                        <label class="label-req">Cumplimiento Elemento {{ $campo['elemento'] }}</label>
                        <select name="datos[abcdef][cumplimiento.{{ $campo['elemento'] }}]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">— Sin dato —</option>
                            @foreach($cat as $op)
                                <option value="{{ $op }}" {{ ($abcDatos["cumplimiento.{$campo['elemento']}"] ?? '') === $op ? 'selected' : '' }}>
                                    {{ $op }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @elseif($campo['tipo'] === 'delirium_presencia')
                    <div class="col-md-4 col-lg-3">
                        <label class="label-req">D2 — Presencia de delirium (CAM+)</label>
                        <select name="datos[abcdef][delirium_presencia]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">— Sin dato —</option>
                            @foreach($cat as $op)
                                <option value="{{ $op }}" {{ ($abcDatos['delirium_presencia'] ?? '') === $op ? 'selected' : '' }}>
                                    {{ $op }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @elseif($campo['tipo'] === 'delirium_subtipo')
                    <div class="col-md-4 col-lg-3">
                        <label class="label-req">D2 — Subtipo de delirium</label>
                        <select name="datos[abcdef][delirium_subtipo]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">— Sin dato —</option>
                            @foreach($cat as $op)
                                <option value="{{ $op }}" {{ ($abcDatos['delirium_subtipo'] ?? '') === $op ? 'selected' : '' }}>
                                    {{ $op }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @endforeach
            </div>
        </div>
    </div>

    {{-- ╔══════════════════════════════════════════════════════════╗ --}}
    {{-- ║  4 · Encuesta ANTES                                    ║ --}}
    {{-- ╚══════════════════════════════════════════════════════════╝ --}}
    @php
        $encSec     = collect($modelo['secciones'])->firstWhere('id', 'encuesta');
        $pregAntes  = $trazador->datos['encuesta_antes']['preguntas'] ?? [];
        $datEncAntes = $trazador->datos['encuesta_antes']['datos_encuestado'] ?? [];
    @endphp
    <div class="card mb-3">
        <div class="card-body">
            <div class="seccion-titulo">4 · Encuesta de Funcionalidad — ANTES (basal)</div>

            {{-- Datos del encuestado --}}
            <div class="row g-2 mb-3">
            @foreach($encSec['datos_encuestado'] as $campo)
                @php
                    $id    = $campo['id'];
                    $val   = $datEncAntes[$id] ?? '';
                    $catId = $campo['catalogo'] ?? null;
                    $cat   = $catId ? ($modelo['catalogos'][$catId] ?? []) : [];
                @endphp
                <div class="col-md-6 col-lg-3">
                    <label class="label-req">{{ $campo['etiqueta'] }}</label>
                    @if($catId)
                        <select name="datos[encuesta_antes][datos_encuestado][{{ $id }}]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">—</option>
                            @foreach($cat as $op)
                                <option value="{{ $op }}" {{ $val === $op ? 'selected' : '' }}>{{ $op }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="datos[encuesta_antes][datos_encuestado][{{ $id }}]"
                               value="{{ $val }}"
                               class="form-control form-control-sm campo-amarillo">
                    @endif
                </div>
            @endforeach
            </div>

            {{-- Preguntas --}}
            <div class="row g-3">
            @foreach($encSec['preguntas'] as $preg)
                @php
                    $qid   = $preg['id'];
                    $val   = $pregAntes[$qid] ?? '';
                    $catId = $preg['catalogo'] ?? null;
                    $cat   = $catId ? ($modelo['catalogos'][$catId] ?? []) : [];
                    $tipo  = $preg['tipo'];
                @endphp
                <div class="col-md-6">
                    <label class="label-req">
                        <span class="badge bg-secondary me-1">{{ $qid }}</span>
                        {{ $preg['texto_leer'] }}
                    </label>
                    @if($tipo === 'select' && $catId)
                        <select name="datos[encuesta_antes][preguntas][{{ $qid }}]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">— Sin dato —</option>
                            @foreach($cat as $opcion)
                                @php
                                    $optVal   = is_array($opcion) ? (string)$opcion['code'] : $opcion;
                                    $optLabel = is_array($opcion) ? $opcion['label'] : $opcion;
                                @endphp
                                <option value="{{ $optVal }}" {{ (string)$val === $optVal ? 'selected' : '' }}>
                                    {{ $optLabel }}
                                </option>
                            @endforeach
                        </select>
                    @else
                        <input type="number" step="1" min="{{ $preg['rango'][0] ?? 0 }}" max="{{ $preg['rango'][1] ?? 100 }}"
                               name="datos[encuesta_antes][preguntas][{{ $qid }}]"
                               value="{{ $val }}"
                               class="form-control form-control-sm campo-amarillo"
                               placeholder="0–100">
                    @endif
                </div>
            @endforeach
            </div>
        </div>
    </div>

    {{-- Guardar --}}
    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-save me-2"></i>
            @if($trazador->estado === 'TRAZADOR_INICIAL')
                Guardar y pasar a Estadísticas (inicia conteo 90 días)
            @else
                Guardar cambios y recalcular
            @endif
        </button>
        <a href="{{ route('trazadores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        @if($trazador->estado !== 'TRAZADOR_INICIAL')
            <a href="{{ route('trazadores.show', $trazador) }}" class="btn btn-outline-primary">
                <i class="bi bi-eye me-1"></i>Ver resultados
            </a>
        @endif
    </div>
</form>
@endsection

@push('scripts')
<script>
// ── Minutos desde tiempo cero ──────────────────────────────────────────────
function calcularMinutos() {
    const tc = document.getElementById(
        'input_fase1_activacion_fecha_y_hora_de_activacion_tiempo_cero_B4'
    );
    if (!tc || !tc.value) return;
    const tzero = new Date(tc.value);

    document.querySelectorAll('[id^="input_fase2_bundle_1h_"], [id^="input_fase3_reeval_"]').forEach(inp => {
        if (inp.type !== 'datetime-local' || !inp.value) return;
        const t = new Date(inp.value);
        const minutos = Math.round((t - tzero) / 60000);
        const badgeId = 'min_' + inp.id.replace('input_', '');
        const badge   = document.getElementById(badgeId);
        if (badge) badge.textContent = (minutos >= 0 ? '+' : '') + minutos + ' min';
    });
}

document.addEventListener('DOMContentLoaded', function() {
    calcularMinutos();

    // Recalcular al cambiar cualquier datetime
    document.querySelectorAll('[type=datetime-local]').forEach(inp => {
        inp.addEventListener('change', calcularMinutos);
    });

    // ── Calculados: estancia UCI ──────────────────────────────────────────
    function calcFechas() {
        const ingresoUCI = document.querySelector(
            '[name="datos[datos_paciente][datos.fecha_y_hora_de_ingreso_a_uci_B17]"]');
        const egresoUCI  = document.querySelector(
            '[name="datos[datos_paciente][datos.fecha_y_hora_de_egreso_de_uci_B18]"]');
        const inicioVM   = document.querySelector(
            '[name="datos[datos_paciente][datos.fecha_y_hora_de_inicio_de_vm_B23]"]');
        const finVM      = document.querySelector(
            '[name="datos[datos_paciente][datos.fecha_y_hora_de_fin_de_vm_extubacion_B24]"]');
        const elEst = document.getElementById('estanciaUciDias');
        const elVM  = document.getElementById('diasVM');

        if (elEst && ingresoUCI?.value && egresoUCI?.value) {
            const d = (new Date(egresoUCI.value) - new Date(ingresoUCI.value)) / 86400000;
            elEst.value = d >= 0 ? d.toFixed(1) + ' días' : '—';
        }
        if (elVM && inicioVM?.value && finVM?.value) {
            const d = (new Date(finVM.value) - new Date(inicioVM.value)) / 86400000;
            elVM.value = d >= 0 ? d.toFixed(1) + ' días' : '—';
        }
    }
    calcFechas();
    document.querySelectorAll('[type=datetime-local]').forEach(i => i.addEventListener('change', calcFechas));

    // ── ABCDEF ratio → % en tiempo real ──────────────────────────────────
    function calcRatioAbcdef(cod) {
        const num = document.querySelector(`.abcdef-num[data-cod="${cod}"]`);
        const den = document.querySelector(`.abcdef-den[data-cod="${cod}"]`);
        const pct = document.getElementById(`pct_${cod}`);
        if (!num || !den || !pct) return;
        const n = parseFloat(num.value), d = parseFloat(den.value);
        pct.value = (!isNaN(n) && !isNaN(d) && d > 0) ? (n/d*100).toFixed(1) + '%' : '—';
    }

    document.querySelectorAll('.abcdef-num, .abcdef-den').forEach(inp => {
        calcRatioAbcdef(inp.dataset.cod);
        inp.addEventListener('input', () => calcRatioAbcdef(inp.dataset.cod));
    });

    // ── % metas ──────────────────────────────────────────────────────────
    function calcMetas() {
        const selects = document.querySelectorAll('.meta-item');
        let si = 0, total = 0;
        selects.forEach(s => {
            if (s.value) { total++; if (s.value === 'Sí') si++; }
        });
        const el = document.getElementById('pctMetas');
        if (el) el.value = total > 0 ? (si/total*100).toFixed(1) + '%' : '—';
    }
    calcMetas();
    document.querySelectorAll('.meta-item').forEach(s => s.addEventListener('change', calcMetas));
});
</script>
@endpush
