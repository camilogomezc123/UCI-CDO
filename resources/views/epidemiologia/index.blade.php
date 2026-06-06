@extends('layouts.app')
@section('title', 'Perfil Epidemiológico')
@section('page-title', 'Perfil Epidemiológico UCI')

@section('content')

{{-- KPIs rápidos --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-people kpi-icon"></i>
            <div class="kpi-number">{{ $estadisticos['totalPacientes'] }}</div>
            <div class="kpi-label">Total pacientes</div>
            <div class="kpi-sub">Histórico completo</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="kpi-card" style="background:linear-gradient(135deg,#198754,#157347)">
            <i class="bi bi-person-check kpi-icon"></i>
            <div class="kpi-number">{{ $estadisticos['totalActivos'] }}</div>
            <div class="kpi-label">Activos en UCI</div>
            <div class="kpi-sub">Actualmente</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
            <i class="bi bi-calendar3 kpi-icon"></i>
            <div class="kpi-number">{{ $estadisticos['edadPromedio'] }}</div>
            <div class="kpi-label">Edad promedio</div>
            <div class="kpi-sub">Mediana: {{ $estadisticos['edadMediana'] }} años</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-clock-history kpi-icon"></i>
            <div class="kpi-number">{{ $estadisticos['estanciaPromedio'] }}</div>
            <div class="kpi-label">Días estancia prom.</div>
            <div class="kpi-sub">Todos los pacientes</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="kpi-card" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
            <i class="bi bi-heart-pulse kpi-icon"></i>
            <div class="kpi-number">{{ $mortalidadBruta }}%</div>
            <div class="kpi-label">Mortalidad bruta</div>
            <div class="kpi-sub">{{ $fallecidos }} fallecido(s) / {{ $totalEgresados }} egresados</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0dcaf0,#0aa2c0)">
            <i class="bi bi-activity kpi-icon"></i>
            <div class="kpi-number">{{ $mortalidadEsperada !== null ? $mortalidadEsperada.'%' : '—' }}</div>
            <div class="kpi-label">Mortalidad esperada</div>
            <div class="kpi-sub">Predicha por SOFA</div>
        </div>
    </div>
</div>

{{-- Fila 1: Edad + Sexo + Estancias --}}
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-primary"></i>Distribución por Grupos de Edad</div>
            <div class="card-body"><canvas id="chartEdad" style="max-height:220px;"></canvas></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-gender-ambiguous me-2 text-primary"></i>Distribución por Sexo</div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="chartSexo" style="max-height:180px;"></canvas>
                <div class="mt-3 w-100">
                    @foreach($porSexo as $sexo => $n)
                    @if($n > 0)
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.82rem;">{{ $sexo }}</span>
                        <strong>{{ $n }}</strong>
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-hourglass me-2 text-primary"></i>Distribución por Días de Estancia</div>
            <div class="card-body"><canvas id="chartEstancia" style="max-height:220px;"></canvas></div>
        </div>
    </div>
</div>

{{-- Fila 2: Mortalidad --}}
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-clipboard2-heart text-danger"></i>
                <span>Tipo de Egreso</span>
            </div>
            <div class="card-body">
                @if($totalEgresados == 0)
                    <div class="text-center text-muted py-4" style="font-size:0.875rem;">
                        <i class="bi bi-clipboard2 fs-3 d-block mb-2 opacity-25"></i>
                        Sin egresos registrados con tipo.
                    </div>
                @else
                <canvas id="chartEgreso" style="max-height:180px;"></canvas>
                <div class="mt-3">
                    <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:#d1e7dd;">
                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <div>
                            <div class="fw-bold">{{ $mejoria }}</div>
                            <div style="font-size:0.78rem;">Mejoría / Alta</div>
                        </div>
                        <span class="ms-auto fw-bold text-success">{{ $totalEgresados > 0 ? round($mejoria/$totalEgresados*100,1) : 0 }}%</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded" style="background:#cff4fc;">
                        <i class="bi bi-arrow-right-circle-fill text-info fs-5"></i>
                        <div>
                            <div class="fw-bold">{{ $traslados }}</div>
                            <div style="font-size:0.78rem;">Traslado</div>
                        </div>
                        <span class="ms-auto fw-bold text-info">{{ $totalEgresados > 0 ? round($traslados/$totalEgresados*100,1) : 0 }}%</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f8d7da;">
                        <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                        <div>
                            <div class="fw-bold">{{ $fallecidos }}</div>
                            <div style="font-size:0.78rem;">Fallecimiento</div>
                        </div>
                        <span class="ms-auto fw-bold text-danger">{{ $mortalidadBruta }}%</span>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-graph-up-arrow text-danger"></i>
                <span>Mortalidad: Observada vs Esperada (SOFA)</span>
            </div>
            <div class="card-body">
                @if($mortalidadEsperada === null)
                <div class="text-center text-muted py-4" style="font-size:0.875rem;">
                    <i class="bi bi-activity fs-3 d-block mb-2 opacity-25"></i>
                    Sin datos SOFA para calcular mortalidad esperada.
                </div>
                @else
                <div class="row g-3 text-center mb-3">
                    <div class="col-6">
                        <div class="p-3 rounded" style="background:#f8d7da;">
                            <div class="fw-bold" style="font-size:2rem;color:#dc3545;">{{ $mortalidadBruta }}%</div>
                            <div style="font-size:0.78rem;color:#842029;">Observada</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded" style="background:#cff4fc;">
                            <div class="fw-bold" style="font-size:2rem;color:#0dcaf0;">{{ $mortalidadEsperada }}%</div>
                            <div style="font-size:0.78rem;color:#055160;">Esperada (SOFA)</div>
                        </div>
                    </div>
                </div>
                @php
                    $diferencia = round($mortalidadBruta - $mortalidadEsperada, 1);
                    $smr = $mortalidadEsperada > 0 ? round($mortalidadBruta / $mortalidadEsperada, 2) : null;
                @endphp
                <div class="p-3 rounded text-center" style="background:#f8f9fa;">
                    <div class="fw-bold fs-4 {{ $diferencia > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $diferencia > 0 ? '+' : '' }}{{ $diferencia }}%
                    </div>
                    <div style="font-size:0.78rem;color:#6c757d;">Diferencia observada − esperada</div>
                    @if($smr !== null)
                    <div class="mt-2">
                        <span class="badge bg-{{ $smr > 1.1 ? 'danger' : ($smr < 0.9 ? 'success' : 'warning text-dark') }} fs-6">
                            SMR = {{ $smr }}
                        </span>
                        <div style="font-size:0.72rem;color:#6c757d;margin-top:4px;">
                            Razón Estandarizada de Mortalidad
                            @if($smr > 1.1) <span class="text-danger">· Por encima de lo esperado</span>
                            @elseif($smr < 0.9) <span class="text-success">· Por debajo de lo esperado</span>
                            @else <span class="text-warning">· Dentro del rango esperado</span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-event me-2 text-primary"></i>Tasa de Mortalidad Mensual</div>
            <div class="card-body">
                @if($mortalidadMensual->isEmpty())
                <div class="text-center text-muted py-4" style="font-size:0.875rem;">Sin egresos registrados en los últimos 6 meses.</div>
                @else
                <canvas id="chartMortalidadMensual" style="max-height:200px;"></canvas>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Fila 3: Criticidad por subunidad --}}
<div class="card mb-3">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-building text-danger"></i>
        <span>Criticidad por Subunidad UCI</span>
        <span class="badge bg-secondary ms-2" style="font-size:0.7rem;">Basado en NEWS y SOFA actuales</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Subunidad</th>
                        <th class="text-center">Pacientes</th>
                        <th class="text-center">NEWS prom.</th>
                        <th class="text-center">NEWS ≥ 5</th>
                        <th class="text-center">SOFA prom.</th>
                        <th class="text-center">SOFA ≥ 10</th>
                        <th class="text-center">Mort. esp. SOFA</th>
                        <th>Índice criticidad</th>
                        <th class="text-center">Nivel</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($criticidadPorSub as $sub => $d)
                    @php
                        $nivel = $d['indiceCriticidad'] >= 40 ? ['CRÍTICO','danger'] : ($d['indiceCriticidad'] >= 20 ? ['ALTO','warning text-dark'] : ['MODERADO','success']);
                        $mortSub = $d['mortalidadEsperadaSub'] ?? null;
                        $mortColor = $mortSub === null ? 'secondary' : ($mortSub >= 40 ? 'danger' : ($mortSub >= 15 ? 'warning text-dark' : 'success'));
                    @endphp
                    <tr>
                        <td class="fw-semibold" style="font-size:0.875rem;">{{ $sub }}</td>
                        <td class="text-center">{{ $d['total'] }}</td>
                        <td class="text-center">
                            <span class="{{ ($d['avgNews'] ?? 0) >= 5 ? 'text-danger fw-bold' : '' }}">
                                {{ $d['avgNews'] ?? '—' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $d['pctNews5'] >= 30 ? 'bg-danger' : 'bg-secondary' }}">
                                {{ $d['pctNews5'] }}%
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="{{ ($d['avgSofa'] ?? 0) >= 10 ? 'text-danger fw-bold' : '' }}">
                                {{ $d['avgSofa'] ?? '—' }}
                            </span>
                        </td>
                        <td class="text-center">
                            <span class="badge {{ $d['pctSofa10'] >= 30 ? 'bg-danger' : 'bg-secondary' }}">
                                {{ $d['pctSofa10'] }}%
                            </span>
                        </td>
                        <td class="text-center">
                            @if($mortSub !== null)
                                <span class="badge bg-{{ $mortColor }}">{{ $mortSub }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td style="min-width:160px;">
                            <div class="progress" style="height:18px;border-radius:6px;">
                                <div class="progress-bar bg-{{ $nivel[1] == 'warning text-dark' ? 'warning' : explode(' ',$nivel[1])[0] }}"
                                     style="width:{{ min($d['indiceCriticidad']*2,100) }}%;">
                                    <span style="font-size:0.72rem;">{{ $d['indiceCriticidad'] }}/50</span>
                                </div>
                            </div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $nivel[1] }}">{{ $nivel[0] }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-3">Sin datos disponibles.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($criticidadPorSub->isNotEmpty())
        <div class="p-3" style="background:#f8f9fa;border-top:1px solid #f0f0f0;">
            <canvas id="chartCriticidad" style="max-height:200px;"></canvas>
        </div>
        @endif
    </div>
</div>

{{-- Fila 4: CIE-10 + Especialidades + EAPB --}}
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-journal-medical me-2 text-primary"></i>Top 10 Diagnósticos CIE-10</div>
            <div class="card-body">
                @forelse($cie10Raw as $cod => $n)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-primary me-2" style="font-size:0.8rem;min-width:50px;">{{ $cod }}</span>
                    <div class="progress flex-fill mx-2" style="height:14px;border-radius:4px;">
                        <div class="progress-bar" style="width:{{ $cie10Raw->max() > 0 ? round($n/$cie10Raw->max()*100) : 0 }}%;"></div>
                    </div>
                    <span class="fw-bold" style="font-size:0.82rem;min-width:25px;text-align:right;">{{ $n }}</span>
                </div>
                @empty
                <div class="text-center text-muted py-3" style="font-size:0.875rem;">Sin datos CIE-10 registrados.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-badge me-2 text-primary"></i>Top Especialidades</div>
            <div class="card-body">
                @forelse($topEspecialidades as $esp => $n)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-truncate me-2" style="font-size:0.8rem;max-width:180px;" title="{{ $esp }}">{{ $esp }}</span>
                    <div class="progress flex-fill mx-2" style="height:14px;border-radius:4px;">
                        <div class="progress-bar bg-success" style="width:{{ $topEspecialidades->max() > 0 ? round($n/$topEspecialidades->max()*100) : 0 }}%;"></div>
                    </div>
                    <span class="fw-bold" style="font-size:0.82rem;min-width:25px;text-align:right;">{{ $n }}</span>
                </div>
                @empty
                <div class="text-center text-muted py-3" style="font-size:0.875rem;">Sin datos de especialidad.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-building-check me-2 text-primary"></i>Top EAPB / Aseguradora</div>
            <div class="card-body">
                @forelse($topEapb as $eapb => $n)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-truncate me-2" style="font-size:0.8rem;max-width:180px;" title="{{ $eapb }}">{{ $eapb }}</span>
                    <div class="progress flex-fill mx-2" style="height:14px;border-radius:4px;">
                        <div class="progress-bar bg-warning" style="width:{{ $topEapb->max() > 0 ? round($n/$topEapb->max()*100) : 0 }}%;"></div>
                    </div>
                    <span class="fw-bold" style="font-size:0.82rem;min-width:25px;text-align:right;">{{ $n }}</span>
                </div>
                @empty
                <div class="text-center text-muted py-3" style="font-size:0.875rem;">Sin datos EAPB.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Colores base
const C = ['#0d6efd','#6f42c1','#198754','#fd7e14','#dc3545','#0dcaf0'];

// Edad
new Chart(document.getElementById('chartEdad'), {
    type: 'bar',
    data: {
        labels: {!! json_encode(array_keys($gruposEdad)) !!},
        datasets: [{ data: {!! json_encode(array_values($gruposEdad)) !!},
            backgroundColor: C, borderRadius: 6 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Sexo
new Chart(document.getElementById('chartSexo'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_keys(array_filter($porSexo))) !!},
        datasets: [{ data: {!! json_encode(array_values(array_filter($porSexo))) !!},
            backgroundColor: ['#0dcaf0','#0d6efd','#6c757d'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }, cutout: '65%' }
});

// Estancia
new Chart(document.getElementById('chartEstancia'), {
    type: 'bar',
    data: {
        labels: {!! json_encode(array_keys($rangosEstancia)) !!},
        datasets: [{ data: {!! json_encode(array_values($rangosEstancia)) !!},
            backgroundColor: ['#198754','#0d6efd','#fd7e14','#dc3545','#6f42c1'], borderRadius: 6 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

@if($totalEgresados > 0)
// Tipo egreso
new Chart(document.getElementById('chartEgreso'), {
    type: 'doughnut',
    data: {
        labels: ['Mejoría','Traslado','Fallecimiento'],
        datasets: [{ data: [{{ $mejoria }},{{ $traslados }},{{ $fallecidos }}],
            backgroundColor: ['#198754','#0dcaf0','#dc3545'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }, cutout: '65%' }
});
@endif

@if($mortalidadMensual->isNotEmpty())
// Mortalidad mensual
new Chart(document.getElementById('chartMortalidadMensual'), {
    type: 'line',
    data: {
        labels: {!! json_encode($mortalidadMensual->pluck('mes')) !!},
        datasets: [
            { label: 'Tasa mortalidad %', data: {!! json_encode($mortalidadMensual->pluck('tasa')) !!},
              borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', fill: true, tension: 0.3, pointRadius: 5 },
            { label: 'Total egresos', data: {!! json_encode($mortalidadMensual->pluck('total')) !!},
              borderColor: '#0d6efd', tension: 0.3, pointRadius: 4, yAxisID: 'y2' }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y:  { beginAtZero: true, title: { display: true, text: 'Mortalidad %' } },
            y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Egresos' } }
        },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});
@endif

@if($criticidadPorSub->isNotEmpty())
// Criticidad por subunidad — gráfico radar/bar doble
new Chart(document.getElementById('chartCriticidad'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($criticidadPorSub->keys()->values()) !!},
        datasets: [
            { label: 'NEWS promedio', data: {!! json_encode($criticidadPorSub->pluck('avgNews')->map(fn($v) => $v ?? 0)->values()) !!},
              backgroundColor: 'rgba(220,53,69,0.7)', borderRadius: 4 },
            { label: 'SOFA promedio', data: {!! json_encode($criticidadPorSub->pluck('avgSofa')->map(fn($v) => $v ?? 0)->values()) !!},
              backgroundColor: 'rgba(253,126,20,0.7)', borderRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: true } },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});
@endif
</script>
@endpush
