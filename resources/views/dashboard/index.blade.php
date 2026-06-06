@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard UCI')

@section('content')

{{-- Alerta: sin carga del día --}}
@if(!$cargaHoy)
<div class="alert alert-warning d-flex align-items-center gap-3 mb-3" style="border-radius:10px;">
    <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
    <div>
        <strong>No se ha cargado el archivo de hoy.</strong>
        Los datos mostrados corresponden a la última carga del
        <strong>{{ $ultimaCarga ? $ultimaCarga->created_at->format('d/m/Y H:i') : 'carga desconocida' }}</strong>.
        <a href="{{ route('carga.index') }}" class="alert-link ms-2">Cargar ahora →</a>
    </div>
</div>
@endif

{{-- Alertas clínicas críticas --}}
@if($alertasNews->count() > 0 || $alertasSofa->count() > 0)
<div class="row g-2 mb-3">
    @if($alertasNews->count() > 0)
    <div class="col-md-6">
        <div class="card border-danger" style="border-width:2px!important;">
            <div class="card-header bg-danger text-white py-2 d-flex align-items-center gap-2">
                <i class="bi bi-thermometer-high"></i>
                <strong>NEWS ≥ 5 — {{ $alertasNews->count() }} paciente(s)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($alertasNews->take(5) as $s)
                        @php $p = \App\Models\Paciente::find($s->paciente_id); @endphp
                        <tr>
                            <td style="font-size:0.82rem;" class="ps-3">
                                <a href="{{ route('pacientes.show', $s->paciente_id) }}" class="text-decoration-none fw-semibold">
                                    {{ $p->nombre ?? '—' }}
                                </a>
                            </td>
                            <td style="font-size:0.82rem;">{{ $s->ubicacion }}</td>
                            <td><span class="badge bg-danger">NEWS {{ $s->news }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
    @if($alertasSofa->count() > 0)
    <div class="col-md-6">
        <div class="card border-warning" style="border-width:2px!important;">
            <div class="card-header bg-warning text-dark py-2 d-flex align-items-center gap-2">
                <i class="bi bi-activity"></i>
                <strong>SOFA ≥ 10 — {{ $alertasSofa->count() }} paciente(s)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($alertasSofa->take(5) as $s)
                        @php $p = \App\Models\Paciente::find($s->paciente_id); @endphp
                        <tr>
                            <td style="font-size:0.82rem;" class="ps-3">
                                <a href="{{ route('pacientes.show', $s->paciente_id) }}" class="text-decoration-none fw-semibold">
                                    {{ $p->nombre ?? '—' }}
                                </a>
                            </td>
                            <td style="font-size:0.82rem;">{{ $s->ubicacion }}</td>
                            <td><span class="badge bg-warning text-dark">SOFA {{ $s->sofa }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endif

{{-- KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-people-fill kpi-icon"></i>
            <div class="kpi-number">{{ $totalActivos }}</div>
            <div class="kpi-label">Pacientes activos en UCI</div>
            <div class="kpi-sub">{{ $cargaHoy ? 'Datos actualizados hoy' : 'Sin carga hoy ⚠' }}</div>
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
        <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
            <i class="bi bi-lungs kpi-icon"></i>
            <div class="kpi-number">{{ $conVmiActivo }}</div>
            <div class="kpi-label">Con VMI activo</div>
            <div class="kpi-sub">Ventilación mecánica invasiva</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-heart-pulse kpi-icon"></i>
            <div class="kpi-number">{{ $conVasopresorActivo }}</div>
            <div class="kpi-label">Con vasopresor activo</div>
            <div class="kpi-sub">Soporte hemodinámico</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Ocupación por subunidad --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-building text-primary"></i> Ocupación por Subunidad
            </div>
            <div class="card-body">
                @foreach($capacidades as $sub => $cap)
                @php
                    $ocu = $porSubunidad[$sub] ?? 0;
                    $pct = $cap > 0 ? round($ocu / $cap * 100) : 0;
                    $color = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                @endphp
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.82rem;font-weight:600;">{{ $sub }}</span>
                        <span style="font-size:0.8rem;" class="text-{{ $color }}">{{ $ocu }}/{{ $cap }}</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Ocupación histórica últimos 30 días --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-graph-up text-primary"></i> Ocupación Histórica (últimos 30 días)
            </div>
            <div class="card-body">
                @if($ocupacionHistorica->isEmpty())
                    <div class="text-center text-muted py-4" style="font-size:0.875rem;">
                        <i class="bi bi-bar-chart-line fs-3 d-block mb-2 opacity-25"></i>
                        Sin datos suficientes. Se construirá con cargas diarias.
                    </div>
                @else
                    <canvas id="chartOcupacion" style="max-height:200px;"></canvas>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Criterios --}}
    <div class="col-lg-3 col-sm-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-pie-chart text-primary"></i> Por Criterio
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center">
                <canvas id="chartCriterio" style="max-height:160px;"></canvas>
                <div class="mt-3 w-100">
                    @php $cc = ['ESTANCIA EN UNIDAD CUIDADO INTENSIVO'=>['UCI Intensivo','#dc3545'],'ESTANCIA EN UNIDAD CUIDADO INTERMEDIO'=>['UCI Intermedio','#fd7e14'],'OTROS CRITERIOS(Hosp, Alta)'=>['Otros','#6c757d']]; @endphp
                    @foreach($porCriterio as $c => $n)
                    @php $i = $cc[$c] ?? [$c,'#aaa']; @endphp
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $i[1] }};display:inline-block;flex-shrink:0;"></span>
                        <span style="font-size:0.78rem;">{{ $i[0] }}</span>
                        <span class="ms-auto fw-bold" style="font-size:0.85rem;">{{ $n }}</span>
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
                <p class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Ventilatorio</p>
                @forelse($porVentilatorio as $tipo => $cnt)
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:0.82rem;">{{ $tipo }}</span>
                    <span class="badge bg-primary rounded-pill">{{ $cnt }}</span>
                </div>
                @empty <p class="text-muted small">Sin datos</p> @endforelse
                <hr class="my-2">
                <p class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Hemodinámico</p>
                @forelse($porHemodinamico as $tipo => $cnt)
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:0.82rem;">{{ $tipo }}</span>
                    <span class="badge bg-danger rounded-pill">{{ $cnt }}</span>
                </div>
                @empty <p class="text-muted small">Sin datos</p> @endforelse
            </div>
        </div>
    </div>

    {{-- Promedios escalas --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-clipboard2-pulse text-primary"></i> Promedios Escalas Clínicas
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @php $escalas = ['NEWS'=>['bi-thermometer-half','primary','Alerta temprana'],'BARTHEL'=>['bi-person-walking','success','Funcionalidad'],'RASS'=>['bi-moon-stars','info','Sedación'],'EVA'=>['bi-emoji-frown','warning','Dolor']]; @endphp
                    @foreach($escalas as $e => $info)
                    <div class="col-6">
                        <div class="text-center p-3 rounded-3" style="background:#f8f9fa;">
                            <i class="bi {{ $info[0] }} text-{{ $info[1] }}" style="font-size:1.4rem;"></i>
                            <div class="fw-bold fs-4 mt-1">{{ $promedios[$e] !== null ? $promedios[$e] : '—' }}</div>
                            <div class="fw-semibold" style="font-size:0.82rem;">{{ $e }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $info[2] }}</div>
                            @if($e === 'NEWS' && ($promedios[$e] ?? 0) >= 5)
                                <span class="badge bg-danger mt-1" style="font-size:0.65rem;">ALERTA</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Movilización temprana --}}
                <hr class="my-3">
                <p class="mb-2 fw-semibold" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:1px;">
                    <i class="bi bi-person-arms-up text-success me-1"></i>Movilización Temprana (activos)
                </p>
                <div class="row g-2">
                    <div class="col-4">
                        <div class="text-center p-2 rounded-3" style="background:#e8f5e9;">
                            <div class="fw-bold fs-5 text-success">{{ $movilizacion['temprana'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">&lt; 48 horas</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 rounded-3" style="background:#fff3e0;">
                            <div class="fw-bold fs-5 text-warning">{{ $movilizacion['tardia'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">&gt; 48 horas</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 rounded-3" style="background:#f8f9fa;">
                            <div class="fw-bold fs-5 text-muted">{{ $movilizacion['sin_dato'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">Sin registro</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Pacientes con espera prolongada --}}
@if($pacientesEsperaLarga->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2 text-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Pacientes con espera prolongada para egreso (+4h con criterio hospitalización)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Paciente</th><th>Cama</th><th>Subunidad</th><th>Salida hosp.</th><th>Tiempo esperando</th><th></th></tr></thead>
                <tbody>
                    @foreach($pacientesEsperaLarga as $p)
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:0.875rem;">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                        </td>
                        <td>{{ $p->ultimoSnapshot->ubicacion ?? '—' }}</td>
                        <td style="font-size:0.82rem;">{{ $p->ultimoSnapshot->subunidad ?? '—' }}</td>
                        <td style="font-size:0.82rem;">{{ $p->salida_hospitalizacion->format('d/m/Y H:i') }}</td>
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

{{-- Panel: Soportes prolongados >2 días --}}
@if($pacientesSoporteProlongado->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hourglass-split text-danger"></i>
        <strong>Soporte farmacológico prolongado &gt; 2 días</strong>
        <span class="badge bg-danger ms-1">{{ $pacientesSoporteProlongado->count() }}</span>
        <span class="text-muted ms-2" style="font-size:0.78rem;">Pacientes activos con soporte continuo</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th class="text-center">
                            <i class="bi bi-lungs text-info me-1"></i>VMI
                        </th>
                        <th class="text-center">
                            <i class="bi bi-heart-pulse text-danger me-1"></i>Vasopresor
                        </th>
                        <th class="text-center">
                            <i class="bi bi-activity text-warning me-1"></i>Inotropico
                        </th>
                        <th class="text-center">
                            <i class="bi bi-pulse text-primary me-1"></i>Antiarrítmico
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pacientesSoporteProlongado as $p)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                        </td>
                        <td class="text-center">
                            @if($p->dias_vmi > 2)
                                <span class="badge bg-info text-dark">{{ $p->dias_vmi }}d</span>
                            @elseif($p->dias_vmi > 0)
                                <span class="text-muted">{{ $p->dias_vmi }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($p->dias_vasopresor > 2)
                                <span class="badge bg-danger">{{ $p->dias_vasopresor }}d</span>
                            @elseif($p->dias_vasopresor > 0)
                                <span class="text-muted">{{ $p->dias_vasopresor }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($p->dias_inotropico > 2)
                                <span class="badge bg-warning text-dark">{{ $p->dias_inotropico }}d</span>
                            @elseif($p->dias_inotropico > 0)
                                <span class="text-muted">{{ $p->dias_inotropico }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($p->dias_antiarritmico > 2)
                                <span class="badge bg-primary">{{ $p->dias_antiarritmico }}d</span>
                            @elseif($p->dias_antiarritmico > 0)
                                <span class="text-muted">{{ $p->dias_antiarritmico }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('pacientes.show', $p->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
// Gráfico criterios
const ctxC = document.getElementById('chartCriterio');
if (ctxC) {
    new Chart(ctxC, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($porCriterio->keys()->map(fn($k) => match(true) {
                str_contains($k,'INTENSIVO') => 'UCI Intensivo',
                str_contains($k,'INTERMEDIO') => 'UCI Intermedio',
                default => 'Otros'
            })->values()) !!},
            datasets: [{ data: {!! json_encode($porCriterio->values()) !!}, backgroundColor: ['#dc3545','#fd7e14','#6c757d'], borderWidth: 0 }]
        },
        options: { plugins: { legend: { display: false } }, cutout: '70%', responsive: true, maintainAspectRatio: true }
    });
}

// Gráfico ocupación histórica
const ctxO = document.getElementById('chartOcupacion');
if (ctxO) {
    const hist = {!! json_encode($ocupacionHistorica) !!};
    new Chart(ctxO, {
        type: 'line',
        data: {
            labels: hist.map(h => h.fecha),
            datasets: [{
                label: 'Pacientes activos',
                data: hist.map(h => h.total),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true, tension: 0.3, pointRadius: 3
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: false, ticks: { stepSize: 5 } }, x: { ticks: { maxTicksLimit: 10, maxRotation: 0 } } },
            plugins: { legend: { display: false } }
        }
    });
}
</script>
@endpush
