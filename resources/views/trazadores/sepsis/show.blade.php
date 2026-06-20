@extends('layouts.trazador')
@section('title', 'Resultados Trazador — ' . ($paciente->nombre ?? ''))
@section('page-title', 'Resultados Trazador Sepsis &mdash; ' . ($paciente->nombre ?? 'Paciente'))
@section('trazador-estado')
    <span class="tz-badge-estado bg-{{ $trazador->estado === 'CERRADO' ? 'success' : 'info' }} text-{{ $trazador->estado === 'CERRADO' ? 'white' : 'dark' }}">
        {{ $trazador->estado }}
    </span>
@endsection
@section('trazador-acciones')
    <a href="{{ route('trazadores.edit', $trazador) }}" class="tz-btn-volver">
        <i class="bi bi-pencil"></i> Editar
    </a>
@endsection

@push('styles')
<style>
    .sem-verde   { background:#d1e7dd; color:#0f5132; border:1px solid #a3cfbb; }
    .sem-amarillo{ background:#fff3cd; color:#664d03; border:1px solid #ffe69c; }
    .sem-rojo    { background:#f8d7da; color:#842029; border:1px solid #f1aeb5; }
    .sem-sin_dato{ background:#e9ecef; color:#6c757d; border:1px solid #dee2e6; }
    .sem-no_aplica{ background:#f8f9fa; color:#adb5bd; border:1px solid #dee2e6; font-style:italic; }
    .ind-card { border-radius:8px; padding:.6rem .9rem; margin-bottom:.4rem; font-size:.82rem; }
    .ind-cod  { font-weight:700; font-size:.9rem; min-width:2.5rem; }
    .escala-card { border-radius:10px; border:1px solid #dee2e6; padding:1rem; }
    .delta-pos { color:#198754; font-weight:700; }
    .delta-neg { color:#dc3545; font-weight:700; }
</style>
@endpush

@section('content')
@php $r = $trazador->resultados ?? []; @endphp

{{-- Navegación rápida --}}
<div class="d-flex gap-2 mb-3 flex-wrap">
    <a href="{{ route('trazadores.edit', $trazador) }}" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-pencil me-1"></i>Editar / corregir
    </a>
    @if($trazador->estado === 'PENDIENTE_DESPUES')
    <a href="{{ route('trazadores.despues.edit', $trazador) }}" class="btn btn-sm btn-warning">
        <i class="bi bi-clipboard2-check me-1"></i>Encuesta DESPUÉS
    </a>
    @endif
    <a href="{{ route('trazadores.index') }}" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Bandejas
    </a>
</div>

{{-- ── Puntuación global ──────────────────────────────────────────────── --}}
@php
    $gl    = $r['puntuacion_global_pct'] ?? null;
    $re    = $r['adherencia_reanimacion_pct'] ?? null;
    $ab    = $r['adherencia_abcdef_pct'] ?? null;
    $meta  = $r['metas_pct'] ?? null;
    $banda = $trazador->getBandaGlobal();
    $bandaColor = match($banda) { 'verde'=>'success','amarillo'=>'warning','rojo'=>'danger', default=>'secondary' };
    $bandaLabel = match($banda) { 'verde'=>'Verde (>90%)','amarillo'=>'Amarillo (70-89%)','rojo'=>'Rojo (<70%)', default=>'Sin datos' };
@endphp
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="kpi-card bg-{{ $bandaColor }}">
            <i class="bi bi-trophy kpi-icon"></i>
            <div class="kpi-number">{{ $gl !== null ? $gl.'%' : '—' }}</div>
            <div class="kpi-label">Puntuación global</div>
            <div class="kpi-sub">{{ $bandaLabel }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-heart-pulse kpi-icon"></i>
            <div class="kpi-number">{{ $re !== null ? $re.'%' : '—' }}</div>
            <div class="kpi-label">Adherencia reanimación</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6610f2,#520dc2)">
            <i class="bi bi-grid-3x3 kpi-icon"></i>
            <div class="kpi-number">{{ $ab !== null ? $ab.'%' : '—' }}</div>
            <div class="kpi-label">Adherencia ABCDEF</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#198754,#146c43)">
            <i class="bi bi-check2-all kpi-icon"></i>
            <div class="kpi-number">{{ $meta !== null ? $meta.'%' : '—' }}</div>
            <div class="kpi-label">Metas de manejo</div>
        </div>
    </div>
</div>

{{-- ── Semáforo Sepsis S1–S7 ─────────────────────────────────────────── --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-heart-pulse me-2"></i>Semáforo — Código Sepsis</div>
    <div class="card-body">
    @php
        $semConf = [
            'S1'=>['nombre'=>'Activación del Código Sepsis','meta'=>85,'piso'=>70],
            'S2'=>['nombre'=>'Lactato ≤60 min','meta'=>90,'piso'=>70],
            'S3'=>['nombre'=>'Antibiótico ≤60 min','meta'=>90,'piso'=>70],
            'S4'=>['nombre'=>'Hemocultivos previos','meta'=>85,'piso'=>70],
            'S5'=>['nombre'=>'Bundle de 1 hora completo','meta'=>75,'piso'=>70],
            'S6'=>['nombre'=>'Vasopresor oportuno','meta'=>90,'piso'=>70],
            'S7'=>['nombre'=>'Control del foco','meta'=>82.5,'piso'=>70],
        ];
        $semPorInd = $r['semaforo']['por_indicador'] ?? [];
    @endphp
    <div class="row g-2">
    @foreach($semConf as $cod => $cfg)
        @php
            $ind   = $semPorInd[$cod] ?? [];
            $color = $ind['color'] ?? 'sin_dato';
            $valor = $ind['valor'] ?? null;
            $label = $valor === 'N/A' ? 'No aplica' : ($valor === null ? '— sin dato' : $valor.'%');
        @endphp
        <div class="col-md-4 col-lg-3">
            <div class="ind-card sem-{{ $color }}">
                <div class="d-flex align-items-center gap-2">
                    <span class="ind-cod">{{ $cod }}</span>
                    <div>
                        <div style="font-size:.78rem;">{{ $cfg['nombre'] }}</div>
                        <strong style="font-size:1rem;">{{ $label }}</strong>
                        <div style="font-size:.72rem; opacity:.7;">Meta {{ $cfg['meta'] }}% · Piso {{ $cfg['piso'] }}%</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
    </div>

    {{-- S8 Mortalidad --}}
    @php $s8 = $r['sepsis']['s8'] ?? null; @endphp
    @if($s8)
    <div class="mt-2">
        <span class="badge bg-secondary">S8 Mortalidad (informativo):</span>
        <strong>{{ $s8 }}</strong>
    </div>
    @endif
    </div>
</div>

{{-- ── Semáforo ABCDEF ───────────────────────────────────────────────── --}}
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-grid-3x3 me-2"></i>Semáforo — Bundle ABCDEF</div>
    <div class="card-body">
    @php
        $abcConf = [
            'A1'=>['nombre'=>'Evaluación dolor c/turno','meta'=>90,'piso'=>80],
            'A2'=>['nombre'=>'Dolor en meta terapéutica','meta'=>80,'piso'=>65],
            'A3'=>['nombre'=>'Analgesia previa procedimientos','meta'=>85,'piso'=>70],
            'B1'=>['nombre'=>'SAT en elegibles','meta'=>80,'piso'=>60],
            'B2'=>['nombre'=>'SBT tras SAT exitoso','meta'=>85,'piso'=>70],
            'B3'=>['nombre'=>'Extubación exitosa','meta'=>85,'piso'=>80],
            'C1'=>['nombre'=>'RASS en meta','meta'=>70,'piso'=>55],
            'C2'=>['nombre'=>'Días sin benzodiacepinas','meta'=>80,'piso'=>60],
            'C3'=>['nombre'=>'Propofol/dex sedante principal','meta'=>75,'piso'=>60],
            'C4'=>['nombre'=>'RASS documentado c/4h','meta'=>90,'piso'=>75],
            'D1'=>['nombre'=>'CAM-ICU c/turno','meta'=>90,'piso'=>75],
            'D3'=>['nombre'=>'Días libres de delirium','meta'=>60,'piso'=>45],
            'D4'=>['nombre'=>'Intervenciones no farm. si CAM+','meta'=>90,'piso'=>75],
            'E1'=>['nombre'=>'Fisioterapia activa diaria','meta'=>70,'piso'=>50],
            'E3'=>['nombre'=>'Documentación movilización','meta'=>90,'piso'=>75],
            'F1'=>['nombre'=>'Educación Médico','meta'=>80,'piso'=>60],
            'F2'=>['nombre'=>'Educación Fisioterapia','meta'=>90,'piso'=>75],
            'F3'=>['nombre'=>'Educación Auxiliar','meta'=>80,'piso'=>60],
            'G1'=>['nombre'=>'FAST-HUG realizado','meta'=>90,'piso'=>75],
        ];
        $abcdfDatos = $r['abcdef'] ?? [];
    @endphp
    <div class="row g-2">
    @foreach($abcConf as $cod => $cfg)
        @php
            $ind   = $semPorInd[$cod] ?? [];
            $color = $ind['color'] ?? 'sin_dato';
            $valor = $ind['valor'] ?? null;
            $label = $valor === null ? '— sin dato' : $valor.'%';
        @endphp
        <div class="col-md-4 col-lg-3">
            <div class="ind-card sem-{{ $color }}">
                <div class="d-flex align-items-center gap-2">
                    <span class="ind-cod">{{ $cod }}</span>
                    <div>
                        <div style="font-size:.75rem;">{{ $cfg['nombre'] }}</div>
                        <strong>{{ $label }}</strong>
                        <div style="font-size:.72rem; opacity:.7;">Meta {{ $cfg['meta'] }}%</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
    </div>

    {{-- D2 y E2 informativos --}}
    @php
        $d2p = $abcdfDatos['d2Presencia'] ?? null;
        $d2s = $abcdfDatos['d2Subtipo']   ?? null;
        $e2  = $abcdfDatos['e2Nivel']      ?? null;
    @endphp
    <div class="mt-2 d-flex gap-3 flex-wrap" style="font-size:.82rem;">
        <span><strong>D2 Delirium:</strong> {{ $d2p ?? '—' }} @if($d2s) ({{ $d2s }}) @endif</span>
        <span><strong>E2 Movilización máx.:</strong> {{ $e2 ?? '—' }}</span>
    </div>
    </div>
</div>

{{-- ── Escalas de la encuesta ────────────────────────────────────────── --}}
@php
    $ea = $r['escalas_antes']   ?? [];
    $ed = $r['escalas_despues'] ?? [];
    $comp = $r['comparativo']   ?? [];
@endphp
@if(!empty($ea) || !empty($ed))
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-clipboard2-data me-2"></i>Escalas de Funcionalidad</div>
    <div class="card-body">
        <div class="row g-3">

            {{-- BARTHEL --}}
            <div class="col-md-4">
                <div class="escala-card">
                    <h6 class="fw-bold">Barthel <small class="text-muted fw-normal">(+ alto = más independiente)</small></h6>
                    <table class="table table-sm mb-0" style="font-size:.8rem;">
                        <thead><tr><th></th><th>Antes</th><th>Después</th></tr></thead>
                        <tbody>
                        <tr><td>Total</td>
                            <td><strong>{{ $ea['barthel']['total'] ?? '—' }}</strong></td>
                            <td><strong>{{ $ed['barthel']['total'] ?? '—' }}</strong></td>
                        </tr>
                        <tr><td>Grado</td>
                            <td style="font-size:.75rem;">{{ $ea['barthel']['grado'] ?? '—' }}</td>
                            <td style="font-size:.75rem;">{{ $ed['barthel']['grado'] ?? '—' }}</td>
                        </tr>
                        @if(isset($comp['barthel_total']))
                        <tr class="table-light">
                            <td>Δ</td>
                            <td colspan="2">
                                <span class="{{ $comp['barthel_total'] >= 0 ? 'delta-pos' : 'delta-neg' }}">
                                    {{ $comp['barthel_total'] >= 0 ? '+' : '' }}{{ $comp['barthel_total'] }}
                                </span>
                                <small class="text-muted ms-1">{{ $comp['barthel_total'] > 0 ? 'mejoró' : ($comp['barthel_total'] < 0 ? 'empeoró' : 'igual') }}</small>
                            </td>
                        </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- WHODAS --}}
            <div class="col-md-4">
                <div class="escala-card">
                    <h6 class="fw-bold">WHODAS 2.0 <small class="text-muted fw-normal">(+ alto = más discapacidad)</small></h6>
                    <table class="table table-sm mb-0" style="font-size:.8rem;">
                        <thead><tr><th></th><th>Antes</th><th>Después</th></tr></thead>
                        <tbody>
                        <tr><td>Suma</td>
                            <td>{{ $ea['whodas']['suma'] ?? '—' }}</td>
                            <td>{{ $ed['whodas']['suma'] ?? '—' }}</td>
                        </tr>
                        <tr><td>Índice 0–100</td>
                            <td><strong>{{ $ea['whodas']['indice_0_100'] ?? '—' }}</strong></td>
                            <td><strong>{{ $ed['whodas']['indice_0_100'] ?? '—' }}</strong></td>
                        </tr>
                        @if(isset($comp['whodas_indice']))
                        <tr class="table-light">
                            <td>Δ índice</td>
                            <td colspan="2">
                                <span class="{{ $comp['whodas_indice'] <= 0 ? 'delta-pos' : 'delta-neg' }}">
                                    {{ $comp['whodas_indice'] >= 0 ? '+' : '' }}{{ $comp['whodas_indice'] }}
                                </span>
                                <small class="text-muted ms-1">{{ $comp['whodas_indice'] < 0 ? 'mejoró' : ($comp['whodas_indice'] > 0 ? 'empeoró' : 'igual') }}</small>
                            </td>
                        </tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- EQ-5D + CFS --}}
            <div class="col-md-4">
                <div class="escala-card mb-2">
                    <h6 class="fw-bold">EQ-5D-5L <small class="text-muted fw-normal">(+ alto = peor)</small></h6>
                    <table class="table table-sm mb-0" style="font-size:.8rem;">
                        <thead><tr><th></th><th>Antes</th><th>Después</th></tr></thead>
                        <tbody>
                        <tr><td>Perfil</td>
                            <td><code>{{ $ea['eq5d']['perfil'] ?? '—' }}</code></td>
                            <td><code>{{ $ed['eq5d']['perfil'] ?? '—' }}</code></td>
                        </tr>
                        <tr><td>Suma</td>
                            <td>{{ $ea['eq5d']['suma_niveles'] ?? '—' }}</td>
                            <td>{{ $ed['eq5d']['suma_niveles'] ?? '—' }}</td>
                        </tr>
                        <tr><td>EQ-VAS</td>
                            <td>{{ $ea['eq5d']['eq_vas'] ?? '—' }}</td>
                            <td>{{ $ed['eq5d']['eq_vas'] ?? '—' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="escala-card">
                    <h6 class="fw-bold">CFS <small class="text-muted fw-normal">(+ alto = más frágil)</small></h6>
                    <table class="table table-sm mb-0" style="font-size:.8rem;">
                        <thead><tr><th></th><th>Antes</th><th>Después</th></tr></thead>
                        <tbody>
                        <tr><td>Valor</td>
                            <td>{{ $ea['cfs']['valor'] ?? '—' }}</td>
                            <td>{{ $ed['cfs']['valor'] ?? '—' }}</td>
                        </tr>
                        <tr><td>Categoría</td>
                            <td style="font-size:.75rem;">{{ $ea['cfs']['categoria'] ?? '—' }}</td>
                            <td style="font-size:.75rem;">{{ $ed['cfs']['categoria'] ?? '—' }}</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
@endif

@endsection
