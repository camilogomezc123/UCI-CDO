@extends('layouts.app')
@section('title', 'Indicadores de Calidad UCI')
@section('page-title', 'Indicadores de Calidad UCI')

@push('styles')
<style>
    /* ── Tarjeta de indicador ──────────────────────────────────────────────── */
    .ind-card {
        border-radius: 10px; border: none;
        border-left: 5px solid #dee2e6;
        box-shadow: 0 2px 8px rgba(0,0,0,.06);
        transition: box-shadow .15s;
    }
    .ind-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.12); }
    .ind-card.verde    { border-left-color: #198754; }
    .ind-card.amarillo { border-left-color: #d97706; }
    .ind-card.rojo     { border-left-color: #dc3545; }
    .ind-card.sin_dato { border-left-color: #adb5bd; }
    .ind-card.informativo { border-left-color: #0d6efd; }

    .ind-valor { font-size: 2rem; font-weight: 800; line-height: 1; }
    .ind-nombre { font-size: .82rem; font-weight: 700; color: #333; margin-bottom: .1rem; }
    .ind-desc  { font-size: .72rem; color: #777; }
    .ind-meta  { font-size: .7rem; margin-top: .3rem; }
    .ind-fuente { font-size: .65rem; color: #aaa; }

    .sem-dot-lg {
        display: inline-block; width: 10px; height: 10px;
        border-radius: 50%; margin-right: 3px; vertical-align: middle;
    }
    .bg-sem-verde    { background: #198754; }
    .bg-sem-amarillo { background: #d97706; }
    .bg-sem-rojo     { background: #dc3545; }
    .bg-sem-sin_dato { background: #adb5bd; }
    .bg-sem-informativo { background: #0d6efd; }

    .color-verde    { color: #198754 !important; }
    .color-amarillo { color: #d97706 !important; }
    .color-rojo     { color: #dc3545 !important; }
    .color-sin_dato { color: #adb5bd !important; }
    .color-informativo { color: #0d6efd !important; }

    /* ── Resumen semáforo global ───────────────────────────────────────────── */
    .sem-resumen-ring {
        width: 80px; height: 80px; border-radius: 50%;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.5rem;
    }
    .sem-resumen-ring small { font-size: .58rem; font-weight: 500; }

    /* ── Tabla de indicadores ─────────────────────────────────────────────── */
    .ind-table th { font-size: .75rem; background: #f8f9fa; }
    .ind-table td { font-size: .82rem; vertical-align: middle; }
    .ind-cat-header td {
        background: #1a3a5c; color: #fff;
        font-weight: 700; font-size: .78rem;
        padding: .4rem .75rem;
    }

    /* ── Filtros de período ────────────────────────────────────────────────── */
    .periodo-btn { font-size: .78rem; }
    .periodo-btn.active { font-weight: 700; }
</style>
@endpush

@section('content')

{{-- ── Selector de período ──────────────────────────────────────────────────── --}}
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <span style="font-size:.8rem; font-weight:600; color:#555;">
                <i class="bi bi-calendar3 me-1"></i>Período de análisis:
            </span>
            @foreach([7=>'7 días', 30=>'30 días', 60=>'60 días', 90=>'90 días', 180=>'6 meses'] as $d => $lbl)
            <a href="{{ route('indicadores.calidad', ['dias' => $d]) }}"
               class="btn btn-sm periodo-btn {{ $dias == $d ? 'btn-primary active' : 'btn-outline-secondary' }}">
                {{ $lbl }}
            </a>
            @endforeach
            <span class="text-muted ms-2" style="font-size:.75rem;">
                {{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}
                · Indicadores en tiempo real actualizados al {{ now()->format('d/m/Y H:i') }}
            </span>
        </div>
    </div>
</div>

{{-- ── Resumen global del semáforo ─────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="sem-resumen-ring" style="border: 5px solid #198754; color:#198754;">
                    {{ $resumen['verde'] }}
                    <small>Verde</small>
                </div>
                <div>
                    <div style="font-size:.75rem; color:#666;">Indicadores en meta</div>
                    <div style="font-size:.8rem; color:#aaa;">{{ $resumen['pct_verde'] }}% del total evaluado</div>
                    <div class="mt-1" style="font-size:.72rem;">
                        <span class="badge bg-warning text-dark me-1">{{ $resumen['amarillo'] }} alerta</span>
                        <span class="badge bg-danger">{{ $resumen['rojo'] }} crítico</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mini KPI cards de los indicadores más críticos --}}
    @php
        $criticos = collect($resultados)->where('semaforo', 'rojo')->values();
        $alertas  = collect($resultados)->where('semaforo', 'amarillo')->values();
    @endphp
    <div class="col-lg-9">
        <div class="card h-100">
            <div class="card-body py-2">
                <div class="row g-2">
                    @foreach(array_slice($resultados, 0, 4) as $cod => $ind)
                    @php
                        $vcol = match($ind['semaforo']) {
                            'verde'=>'#198754','amarillo'=>'#d97706','rojo'=>'#dc3545','informativo'=>'#0d6efd',default=>'#adb5bd'
                        };
                    @endphp
                    <div class="col-6 col-md-3">
                        <div class="text-center p-2 rounded" style="border:2px solid {{ $vcol }}22; background:{{ $vcol }}08;">
                            <div style="font-size:1.4rem; font-weight:800; color:{{ $vcol }}">
                                {{ $ind['valor'] !== null ? $ind['valor'].$ind['unidad'] : '—' }}
                            </div>
                            <div style="font-size:.68rem; color:#555; font-weight:600;">{{ $ind['nombre'] }}</div>
                            <div style="font-size:.62rem; color:#aaa;">Meta: {{ $ind['meta'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Gráficos de tendencia ────────────────────────────────────────────────── --}}
<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-graph-down me-2 text-danger"></i>Mortalidad y egresos — 6 meses</div>
            <div class="card-body" style="padding:.5rem 1rem;"><canvas id="chartMortalidad" height="100"></canvas></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar2-range me-2 text-primary"></i>Estancia media UCI — 6 meses</div>
            <div class="card-body" style="padding:.5rem 1rem;"><canvas id="chartEstancia" height="100"></canvas></div>
        </div>
    </div>
</div>

{{-- ── Tarjetas de indicadores por categoría ───────────────────────────────── --}}
@php
    $categorias = collect($resultados)->groupBy('categoria');
    $catIconos = [
        'Seguridad'          => 'bi-shield-check text-danger',
        'Eficiencia'         => 'bi-speedometer2 text-primary',
        'Neurología'         => 'bi-brain text-warning',
        'Dolor y Sedación'   => 'bi-thermometer-half text-orange',
        'Soporte'            => 'bi-lungs text-info',
        'Alertas clínicas'   => 'bi-exclamation-triangle text-warning',
        'Calidad trazadores' => 'bi-clipboard2-pulse text-success',
    ];
@endphp

@foreach($categorias as $catNombre => $inds)
<div class="mb-3">
    <div class="d-flex align-items-center gap-2 mb-2">
        <i class="bi {{ $catIconos[$catNombre] ?? 'bi-circle' }} fs-5"></i>
        <h6 class="mb-0 fw-bold" style="color:#1a3a5c;">{{ $catNombre }}</h6>
        <div class="flex-grow-1 border-bottom" style="border-color:#e5e9f0 !important;"></div>
        @php
            $rojos = $inds->where('semaforo','rojo')->count();
            $amarillos = $inds->where('semaforo','amarillo')->count();
        @endphp
        @if($rojos > 0)<span class="badge bg-danger ms-1">{{ $rojos }} crítico{{ $rojos>1?'s':'' }}</span>@endif
        @if($amarillos > 0)<span class="badge bg-warning text-dark ms-1">{{ $amarillos }} alerta{{ $amarillos>1?'s':'' }}</span>@endif
    </div>
    <div class="row g-2">
        @foreach($inds as $cod => $ind)
        @php
            $vcol = match($ind['semaforo']) {
                'verde'=>'#198754','amarillo'=>'#d97706','rojo'=>'#dc3545','informativo'=>'#0d6efd',default=>'#adb5bd'
            };
            $aux = $ind['aux'] ?? [];
            $tieneDesglose = !empty($aux['tasas']);
        @endphp
        <div class="{{ $tieneDesglose ? 'col-12' : 'col-md-4 col-lg-3' }}">
            <div class="card ind-card {{ $ind['semaforo'] }} h-100">
                <div class="card-body py-2 px-3">
                    @if($tieneDesglose)
                    {{-- Layout expandido con desglose S1-S7 --}}
                    <div class="row align-items-center">
                        <div class="col-md-3 border-end pe-3">
                            <div class="d-flex align-items-start justify-content-between mb-1">
                                <i class="bi {{ $ind['icono'] }}" style="color:{{ $vcol }}; font-size:1.1rem;"></i>
                                <span class="badge" style="background:{{ $vcol }}22; color:{{ $vcol }}; font-size:.6rem;">{{ $cod }}</span>
                            </div>
                            <div class="ind-nombre">{{ $ind['nombre'] }}</div>
                            <div class="ind-valor" style="color:{{ $vcol }}">
                                {{ $ind['valor'] !== null ? $ind['valor'].$ind['unidad'] : '—' }}
                            </div>
                            @if(isset($aux['n']) && $aux['n'] !== null)
                            <div style="font-size:.68rem; color:#888; margin-top:.1rem;">
                                {{ $aux['n'] }} / {{ $aux['d'] ?? '?' }} casos
                            </div>
                            @endif
                            <div class="ind-meta mt-1">
                                <span class="sem-dot-lg bg-sem-{{ $ind['semaforo'] }}"></span>
                                <span style="color:{{ $vcol }}; font-size:.7rem; font-weight:600;">
                                    {{ match($ind['semaforo']) {
                                        'verde'=>'En meta','amarillo'=>'Alerta','rojo'=>'Crítico',
                                        'informativo'=>'Informativo',default=>'Sin datos'
                                    } }}
                                </span>
                                · Meta: <span style="font-size:.68rem; color:#666;">{{ $ind['meta'] }}</span>
                            </div>
                            <div class="ind-fuente mt-1"><i class="bi bi-journal-text me-1"></i>{{ $ind['fuente'] }}</div>
                        </div>
                        <div class="col-md-9 ps-3">
                            @php
                            $sLabels = [
                                'S1' => 'S1 · Activación tiempo cero',
                                'S2' => 'S2 · Lactato ≤ 60 min',
                                'S3' => 'S3 · Antibiótico ≤ 60 min',
                                'S4' => 'S4 · Hemocultivos tomados',
                                'S5' => 'S5 · Bundle hora-1 completo',
                                'S6' => 'S6 · Vasopresor ≤ 60 min',
                                'S7' => 'S7 · Control de foco < 6h',
                            ];
                            @endphp
                            <div style="font-size:.72rem; font-weight:700; color:#444; margin-bottom:.4rem;">
                                Desglose por criterio — tasa de cumplimiento (casos evaluables)
                            </div>
                            @foreach($aux['tasas'] as $s => $tasa)
                            @php
                                $pct  = $tasa['pct'] ?? null;
                                $scol = $pct === null ? '#adb5bd' : ($pct >= 80 ? '#198754' : ($pct >= 60 ? '#d97706' : '#dc3545'));
                                $esS5 = $s === 'S5';
                            @endphp
                            <div class="d-flex align-items-center gap-2 mb-1" style="{{ $esS5 ? 'background:#f8f9fa; border-radius:4px; padding:2px 4px;' : '' }}">
                                <span style="font-size:.7rem; min-width:175px; color:{{ $esS5 ? '#333' : '#555' }}; font-weight:{{ $esS5 ? '700' : '400' }};">
                                    {{ $sLabels[$s] ?? $s }}
                                    @if($esS5)<i class="bi bi-star-fill ms-1" style="color:#d97706; font-size:.6rem;"></i>@endif
                                </span>
                                @if($pct !== null)
                                <div class="flex-grow-1" style="height:7px; background:#e9ecef; border-radius:4px; overflow:hidden;">
                                    <div style="height:100%; width:{{ $pct }}%; background:{{ $scol }}; border-radius:4px; transition:width .4s;"></div>
                                </div>
                                <span style="font-size:.72rem; font-weight:700; color:{{ $scol }}; min-width:38px; text-align:right;">{{ $pct }}%</span>
                                <span style="font-size:.65rem; color:#aaa; min-width:55px;">{{ $tasa['cumple'] }}/{{ $tasa['total'] }}</span>
                                @else
                                <span style="font-size:.68rem; color:#aaa; font-style:italic;">Sin datos o N/A</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @else
                    {{-- Layout normal --}}
                    <div class="d-flex align-items-start justify-content-between mb-1">
                        <i class="bi {{ $ind['icono'] }}" style="color:{{ $vcol }}; font-size:1.1rem;"></i>
                        <span class="badge" style="background:{{ $vcol }}22; color:{{ $vcol }}; font-size:.6rem;">{{ $cod }}</span>
                    </div>
                    <div class="ind-nombre">{{ $ind['nombre'] }}</div>
                    <div class="ind-valor" style="color:{{ $vcol }}">
                        {{ $ind['valor'] !== null ? $ind['valor'].$ind['unidad'] : '—' }}
                    </div>
                    @if(!empty($aux['n']) || (isset($aux['n']) && $aux['n'] === 0))
                    <div style="font-size:.68rem; color:#888; margin-top:.1rem;">
                        {{ $aux['n'] }} / {{ $aux['d'] ?? '?' }}
                        {{ $ind['periodo'] ? 'en el período' : 'hoy' }}
                    </div>
                    @endif
                    <div class="ind-meta">
                        <span class="sem-dot-lg bg-sem-{{ $ind['semaforo'] }}"></span>
                        <span style="color:{{ $vcol }}; font-size:.7rem; font-weight:600;">
                            {{ match($ind['semaforo']) {
                                'verde'=>'En meta','amarillo'=>'Alerta','rojo'=>'Crítico',
                                'informativo'=>'Informativo',default=>'Sin datos'
                            } }}
                        </span>
                        · Meta: <span style="font-size:.68rem; color:#666;">{{ $ind['meta'] }}</span>
                    </div>
                    <div class="ind-fuente mt-1"><i class="bi bi-journal-text me-1"></i>{{ $ind['fuente'] }}</div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endforeach

{{-- ── Tabla consolidada con benchmarks ────────────────────────────────────── --}}
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2 text-primary"></i>Tabla consolidada de indicadores — benchmarks internacionales</span>
        <span class="text-muted" style="font-size:.75rem;">Fuentes: SEMICYUC 2017 · IHI · SCCM/ESICM · JCI · RCP 2017</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 ind-table">
                <thead>
                    <tr>
                        <th style="width:70px">Código</th>
                        <th>Indicador</th>
                        <th style="width:110px">Valor actual</th>
                        <th style="width:100px">Meta</th>
                        <th style="width:90px">Estado</th>
                        <th>Descripción</th>
                        <th style="width:160px">Fuente</th>
                    </tr>
                </thead>
                <tbody>
                @php $catActual = ''; @endphp
                @foreach($resultados as $cod => $ind)
                @if($ind['categoria'] !== $catActual)
                @php $catActual = $ind['categoria']; @endphp
                <tr><td colspan="7" class="ind-cat-header">
                    <i class="bi {{ $catIconos[$catActual] ?? 'bi-circle' }} me-2"></i>{{ $catActual }}
                </td></tr>
                @endif
                @php
                    $vcol = match($ind['semaforo']) {
                        'verde'=>'#198754','amarillo'=>'#d97706','rojo'=>'#dc3545','informativo'=>'#0d6efd',default=>'#adb5bd'
                    };
                    $rowBg = match($ind['semaforo']) {
                        'rojo'=>'rgba(220,53,69,.04)','amarillo'=>'rgba(217,119,6,.04)',default=>''
                    };
                @endphp
                <tr style="background:{{ $rowBg }}">
                    <td>
                        <span class="badge" style="background:{{ $vcol }}22; color:{{ $vcol }}; font-size:.68rem;">{{ $cod }}</span>
                    </td>
                    <td style="font-weight:600; font-size:.82rem;">
                        <i class="bi {{ $ind['icono'] }} me-1" style="color:{{ $vcol }}"></i>
                        {{ $ind['nombre'] }}
                    </td>
                    <td style="font-weight:800; font-size:.95rem; color:{{ $vcol }}">
                        {{ $ind['valor'] !== null ? $ind['valor'].' '.$ind['unidad'] : '—' }}
                    </td>
                    <td style="font-size:.78rem; color:#555;">{{ $ind['meta'] }}</td>
                    <td>
                        <span class="sem-dot-lg bg-sem-{{ $ind['semaforo'] }}"></span>
                        <span style="font-size:.72rem; color:{{ $vcol }}; font-weight:600;">
                            {{ match($ind['semaforo']) {
                                'verde'=>'Verde','amarillo'=>'Amarillo','rojo'=>'Rojo',
                                'informativo'=>'Info',default=>'S/D'
                            } }}
                        </span>
                    </td>
                    <td style="font-size:.75rem; color:#666;">{{ $ind['descripcion'] }}</td>
                    <td style="font-size:.68rem; color:#999;">{{ $ind['fuente'] }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── Alertas y recomendaciones ────────────────────────────────────────────── --}}
@php
    $indsCriticos = collect($resultados)->where('semaforo', 'rojo')->values();
@endphp
@if($indsCriticos->isNotEmpty())
<div class="card mt-3 border-danger">
    <div class="card-header bg-danger text-white">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        Indicadores críticos — Requieren intervención
    </div>
    <div class="card-body py-2">
        @foreach($indsCriticos as $ind)
        <div class="d-flex align-items-start gap-2 py-2 border-bottom">
            <i class="bi {{ $ind['icono'] }} text-danger mt-1"></i>
            <div>
                <div style="font-size:.82rem; font-weight:700;">{{ $ind['nombre'] }}
                    <span class="badge bg-danger ms-1">{{ $ind['valor'] }}{{ $ind['unidad'] }}</span>
                    <span class="text-muted fw-normal" style="font-size:.72rem;">· Meta: {{ $ind['meta'] }}</span>
                </div>
                <div style="font-size:.73rem; color:#666;">{{ $ind['descripcion'] }} · Fuente: {{ $ind['fuente'] }}</div>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
const tLabels = @json(collect($tendencia)->pluck('label'));

// Gráfico mortalidad
new Chart(document.getElementById('chartMortalidad'), {
    type: 'bar',
    data: {
        labels: tLabels,
        datasets: [
            {
                label: 'Egresados',
                data: @json(collect($tendencia)->pluck('egresados')),
                backgroundColor: 'rgba(13,110,253,.2)', borderColor: '#0d6efd',
                borderWidth: 2, borderRadius: 5, yAxisID: 'y',
            },
            {
                label: 'Mortalidad %',
                data: @json(collect($tendencia)->pluck('mortalidad')),
                type: 'line', borderColor: '#dc3545',
                backgroundColor: 'transparent', pointBackgroundColor: '#dc3545',
                tension: .3, yAxisID: 'y2',
            },
        ],
    },
    options: {
        responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } },
        scales: {
            y:  { beginAtZero: true, ticks: { font:{size:10} }, title:{display:true,text:'Egresados',font:{size:10}} },
            y2: { beginAtZero: true, max: 40, position: 'right', grid:{drawOnChartArea:false},
                  ticks:{font:{size:10}, callback: v=>v+'%'},
                  title:{display:true,text:'Mortalidad %',font:{size:10}} },
        },
    },
});

// Gráfico estancia media
new Chart(document.getElementById('chartEstancia'), {
    type: 'line',
    data: {
        labels: tLabels,
        datasets: [
            {
                label: 'Estancia media (días)',
                data: @json(collect($tendencia)->pluck('estancia_media')),
                borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.1)',
                fill: true, tension: .3, pointBackgroundColor: '#0d6efd',
            },
            {
                label: 'Benchmark (7 días)',
                data: Array(@json(count($tendencia))).fill(7),
                borderColor: '#dc3545', borderDash: [5,5],
                backgroundColor: 'transparent', pointRadius: 0,
            },
        ],
    },
    options: {
        responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } },
        scales: {
            y: { beginAtZero: true, ticks: { font:{size:10}, callback: v=>v+'d' },
                 title:{display:true,text:'Días',font:{size:10}} },
            x: { ticks: { font:{size:10} } },
        },
    },
});
</script>
@endpush
