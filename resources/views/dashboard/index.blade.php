@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard UCI')

@section('content')

{{-- KPI Row --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-people-fill kpi-icon"></i>
            <div class="kpi-number">{{ $totalActivos }}</div>
            <div class="kpi-label">Pacientes activos en UCI</div>
            <div class="kpi-sub">
                @if($ultimaCarga)
                    Última carga: {{ $ultimaCarga->created_at->format('d/m/Y H:i') }}
                @else
                    Sin cargas registradas
                @endif
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
            <i class="bi bi-hourglass-split kpi-icon"></i>
            <div class="kpi-number">{{ $pendientesEgreso }}</div>
            <div class="kpi-label">Pendientes de egreso UCI</div>
            <div class="kpi-sub">Con criterio hospitalización activo</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#198754,#146c43)">
            <i class="bi bi-hospital kpi-icon"></i>
            <div class="kpi-number">{{ $porCriterio['ESTANCIA EN UNIDAD CUIDADO INTENSIVO'] ?? 0 }}</div>
            <div class="kpi-label">UCI Intensivo</div>
            <div class="kpi-sub">Cuidado intensivo activo</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-activity kpi-icon"></i>
            <div class="kpi-number">{{ $porCriterio['ESTANCIA EN UNIDAD CUIDADO INTERMEDIO'] ?? 0 }}</div>
            <div class="kpi-label">UCI Intermedio</div>
            <div class="kpi-sub">Cuidado intermedio activo</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Ocupación por subunidad --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-building text-primary"></i> Ocupación por Subunidad
            </div>
            <div class="card-body">
                @foreach($capacidades as $sub => $cap)
                @php
                    $ocupados = $porSubunidad[$sub] ?? 0;
                    $pct = $cap > 0 ? round($ocupados / $cap * 100) : 0;
                    $color = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                @endphp
                <div class="mb-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size:0.82rem;font-weight:600;">{{ $sub }}</span>
                        <span style="font-size:0.8rem;" class="text-{{ $color }}">{{ $ocupados }}/{{ $cap }}</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Gráfico criterios --}}
    <div class="col-lg-3 col-sm-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-pie-chart text-primary"></i> Por Criterio
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="chartCriterio" style="max-height:180px;"></canvas>
                <div class="mt-3 w-100">
                    @php
                    $criterioColors = [
                        'ESTANCIA EN UNIDAD CUIDADO INTENSIVO' => ['label'=>'UCI Intensivo','color'=>'#dc3545'],
                        'ESTANCIA EN UNIDAD CUIDADO INTERMEDIO' => ['label'=>'UCI Intermedio','color'=>'#fd7e14'],
                        'OTROS CRITERIOS(Hosp, Alta)' => ['label'=>'Otros','color'=>'#6c757d'],
                    ];
                    @endphp
                    @foreach($porCriterio as $criterio => $cnt)
                    @php $info = $criterioColors[$criterio] ?? ['label'=>$criterio,'color'=>'#aaa']; @endphp
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $info['color'] }};display:inline-block;flex-shrink:0;"></span>
                        <span style="font-size:0.78rem;">{{ $info['label'] }}</span>
                        <span class="ms-auto fw-bold" style="font-size:0.85rem;">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Soporte --}}
    <div class="col-lg-3 col-sm-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-lungs text-primary"></i> Soporte Activo
            </div>
            <div class="card-body">
                <p class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Ventilatorio</p>
                @forelse($porVentilatorio as $tipo => $cnt)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:0.82rem;">{{ $tipo }}</span>
                    <span class="badge bg-primary rounded-pill">{{ $cnt }}</span>
                </div>
                @empty
                <p class="text-muted small">Sin datos</p>
                @endforelse

                <hr class="my-2">
                <p class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Hemodinámico</p>
                @forelse($porHemodinamico as $tipo => $cnt)
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:0.82rem;">{{ $tipo }}</span>
                    <span class="badge bg-danger rounded-pill">{{ $cnt }}</span>
                </div>
                @empty
                <p class="text-muted small">Sin datos</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Pacientes pendientes de egreso prolongado --}}
@if($pacientesEsperaLarga->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2 text-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Pacientes con espera prolongada para egreso (más de 4h con criterio hospitalización)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th>Cama</th>
                        <th>Subunidad</th>
                        <th>Fecha salida hosp.</th>
                        <th>Tiempo esperando egreso</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pacientesEsperaLarga as $p)
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:0.875rem;">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.75rem;">{{ $p->documento }}</div>
                        </td>
                        <td>{{ $p->ultimoSnapshot->ubicacion ?? '—' }}</td>
                        <td>{{ $p->ultimoSnapshot->subunidad ?? '—' }}</td>
                        <td>{{ $p->salida_hospitalizacion->format('d/m/Y H:i') }}</td>
                        <td><span class="tiempo-espera">{{ $p->tiempoEsperaHospitalizacion() }}</span></td>
                        <td><a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Promedios escalas --}}
<div class="card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-graph-up text-primary"></i> Promedios Escalas Clínicas (pacientes activos)
    </div>
    <div class="card-body">
        <div class="row g-3">
            @php
            $escalas = [
                'NEWS' => ['icon'=>'bi-thermometer-half','color'=>'primary','desc'=>'Alerta temprana'],
                'BARTHEL' => ['icon'=>'bi-person-walking','color'=>'success','desc'=>'Funcionalidad'],
                'RASS' => ['icon'=>'bi-moon-stars','color'=>'info','desc'=>'Sedación/agitación'],
                'EVA' => ['icon'=>'bi-emoji-frown','color'=>'warning','desc'=>'Dolor'],
            ];
            @endphp
            @foreach($escalas as $escala => $info)
            <div class="col-6 col-md-3">
                <div class="text-center p-3 rounded-3" style="background:#f8f9fa;">
                    <i class="bi {{ $info['icon'] }} text-{{ $info['color'] }}" style="font-size:1.5rem;"></i>
                    <div class="fw-bold fs-4 mt-1" style="color:#2d2d2d;">
                        {{ $promedios[$escala] ? number_format($promedios[$escala], 1) : '—' }}
                    </div>
                    <div class="fw-semibold" style="font-size:0.85rem;">{{ $escala }}</div>
                    <div class="text-muted" style="font-size:0.75rem;">{{ $info['desc'] }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const ctx = document.getElementById('chartCriterio');
if (ctx) {
    const data = {!! json_encode([
        'labels' => $porCriterio->keys()->map(fn($k) => match($k) {
            'ESTANCIA EN UNIDAD CUIDADO INTENSIVO' => 'UCI Intensivo',
            'ESTANCIA EN UNIDAD CUIDADO INTERMEDIO' => 'UCI Intermedio',
            'OTROS CRITERIOS(Hosp, Alta)' => 'Otros',
            default => $k
        })->values(),
        'data' => $porCriterio->values(),
    ]) !!};
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.data,
                backgroundColor: ['#dc3545','#fd7e14','#6c757d','#0d6efd'],
                borderWidth: 0,
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            cutout: '70%',
            responsive: true,
            maintainAspectRatio: true,
        }
    });
}
</script>
@endpush
