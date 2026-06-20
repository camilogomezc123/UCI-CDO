@extends('layouts.app')
@section('title', 'Encuesta DESPUÉS — ' . ($paciente->nombre ?? ''))
@section('page-title', 'Encuesta DESPUÉS (90 días): ' . ($paciente->nombre ?? 'Paciente'))

@push('styles')
<style>
    .campo-amarillo { background: #fffde7 !important; border-color: #f0cc00 !important; }
    .label-req { font-size: .8rem; font-weight: 600; color: #444; }
    .seccion-titulo { background: #fff3cd; border-left: 4px solid #fd7e14;
                      padding: .5rem 1rem; border-radius: 4px; font-weight: 700; margin-bottom: 1rem; }
</style>
@endpush

@section('content')
<div class="alert alert-warning d-flex gap-2 mb-3" style="font-size:.85rem;">
    <i class="bi bi-clock-history fs-5"></i>
    <div>
        <strong>Encuesta de seguimiento a 90 días.</strong>
        Solo diligencia esta sección. Al guardar, el caso quedará <strong>CERRADO</strong>
        y se calculará el comparativo antes/después.
        Fecha objetivo: <strong>{{ $trazador->fecha_objetivo_despues?->format('d/m/Y') ?? '—' }}</strong>
    </div>
</div>

<form method="POST" action="{{ route('trazadores.despues.store', $trazador) }}">
    @csrf

    @php
        $encSec  = collect($modelo['secciones'])->firstWhere('id', 'encuesta');
        $datDespues = $trazador->datos['encuesta_despues']['datos_encuestado'] ?? [];
        $pregDespues = $trazador->datos['encuesta_despues']['preguntas'] ?? [];
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <div class="seccion-titulo">Datos del encuestado — DESPUÉS</div>
            <div class="row g-2 mb-3">
            @foreach($encSec['datos_encuestado'] as $campo)
                @php
                    $id    = $campo['id'];
                    $val   = $datDespues[$id] ?? '';
                    $catId = $campo['catalogo'] ?? null;
                    $cat   = $catId ? ($modelo['catalogos'][$catId] ?? []) : [];
                @endphp
                <div class="col-md-6 col-lg-3">
                    <label class="label-req">{{ $campo['etiqueta'] }}</label>
                    @if($catId)
                        <select name="datos[encuesta_despues][datos_encuestado][{{ $id }}]"
                                class="form-select form-select-sm campo-amarillo">
                            <option value="">—</option>
                            @foreach($cat as $op)
                                <option value="{{ $op }}" {{ $val === $op ? 'selected' : '' }}>{{ $op }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text"
                               name="datos[encuesta_despues][datos_encuestado][{{ $id }}]"
                               value="{{ $val }}"
                               class="form-control form-control-sm campo-amarillo">
                    @endif
                </div>
            @endforeach
            </div>

            {{-- Preguntas Q1–Q22 --}}
            <div class="seccion-titulo">Preguntas de funcionalidad — DESPUÉS</div>
            <div class="row g-3">
            @foreach($encSec['preguntas'] as $preg)
                @php
                    $qid   = $preg['id'];
                    $val   = $pregDespues[$qid] ?? '';
                    $catId = $preg['catalogo'] ?? null;
                    $cat   = $catId ? ($modelo['catalogos'][$catId] ?? []) : [];
                    $tipo  = $preg['tipo'];
                @endphp
                <div class="col-md-6">
                    <label class="label-req">
                        <span class="badge bg-warning text-dark me-1">{{ $qid }}</span>
                        {{ $preg['texto_leer'] }}
                    </label>
                    @if($tipo === 'select' && $catId)
                        <select name="datos[encuesta_despues][preguntas][{{ $qid }}]"
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
                               name="datos[encuesta_despues][preguntas][{{ $qid }}]"
                               value="{{ $val }}"
                               class="form-control form-control-sm campo-amarillo"
                               placeholder="0–100">
                    @endif
                </div>
            @endforeach
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-warning px-4">
            <i class="bi bi-lock me-2"></i>Guardar encuesta DESPUÉS y cerrar caso
        </button>
        <a href="{{ route('trazadores.show', $trazador) }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
