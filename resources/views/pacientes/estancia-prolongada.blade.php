@extends('layouts.app')
@section('title', 'Estancias Prolongadas')
@section('page-title', 'Pacientes con Estancia Prolongada (>5 días)')

@push('styles')
<style>
    .chart-causas-wrap { height: 300px; position: relative; }
    .estancia-causas { flex: 1 1 260px; margin-left: 0 !important; padding-right: 1.5rem; }
</style>
@endpush

@section('content')

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
            <i class="bi bi-calendar-x kpi-icon"></i>
            <div class="kpi-number">{{ $pacientes->count() }}</div>
            <div class="kpi-label">Pacientes >5 días en UCI</div>
            <div class="kpi-sub">Con fecha de ingreso registrada</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
            <i class="bi bi-clock kpi-icon"></i>
            <div class="kpi-number">{{ $pacientes->count() > 0 ? round($pacientes->avg(fn($p)=>$p->diasEnUci())) : '—' }}</div>
            <div class="kpi-label">Días promedio en UCI</div>
            <div class="kpi-sub">Estancias actuales prolongadas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-clipboard2-x kpi-icon"></i>
            <div class="kpi-number">{{ $pacientes->filter(fn($p)=>$p->causaEstancia)->count() }}</div>
            <div class="kpi-label">Con causa registrada</div>
            <div class="kpi-sub">De {{ $pacientes->count() }} con estancia prolongada</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-person-x kpi-icon"></i>
            <div class="kpi-number">{{ $pacientes->filter(fn($p)=>!$p->causaEstancia)->count() }}</div>
            <div class="kpi-label">Sin causa registrada</div>
            <div class="kpi-sub">Pendientes de clasificar</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Gráfico de causas --}}
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-primary"></i>Distribución de Causas</div>
            <div class="card-body">
                @if(array_sum($distribucionCausas) == 0)
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-clipboard2 fs-3 d-block mb-2 opacity-25"></i>
                        Aún no hay causas registradas.
                    </div>
                @else
                    <div class="chart-causas-wrap"><canvas id="chartCausas"></canvas></div>
                @endif
            </div>
        </div>
    </div>

    {{-- Promedio días por subunidad (egresados) --}}
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-stopwatch me-2 text-primary"></i>Promedio de Días por Subunidad (histórico egresados)</div>
            <div class="card-body">
                @if($promedioPorSubunidad->isEmpty())
                    <div class="text-center text-muted py-4" style="font-size:0.875rem;">
                        Sin pacientes egresados con fechas registradas aún.
                    </div>
                @else
                    @foreach($promedioPorSubunidad->sortByDesc(fn($v)=>$v) as $sub => $dias)
                    <div class="mb-2 d-flex align-items-center gap-3">
                        <span style="font-size:0.82rem;font-weight:600;min-width:160px;">{{ $sub }}</span>
                        <div class="progress flex-fill" style="height:18px;border-radius:6px;">
                            <div class="progress-bar" style="width:{{ min($dias*3,100) }}%;background:#6f42c1;">
                                <span style="font-size:0.75rem;">{{ $dias }}d</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Tabla de pacientes --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <span><i class="bi bi-people me-2 text-primary"></i>{{ $pacientes->count() }} paciente(s) con estancia > 5 días</span>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="causa" class="text-muted" style="font-size:0.78rem;">Filtrar causa:</label>
            <select id="causa" name="causa" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:190px;">
                <option value="todas" @selected($causaFiltro === 'todas')>Todas las causas</option>
                <option value="sin_causa" @selected($causaFiltro === 'sin_causa')>Sin causa registrada</option>
                @foreach($etiquetas as $campo => $info)
                <option value="{{ $campo }}" @selected($causaFiltro === $campo)>{{ $info['label'] }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="acordeonEstancias">
            @forelse($pacientes as $p)
            @php
                $causa = $p->causaEstancia;
                $etiquetas = \App\Models\CausaEstancia::etiquetas();
                $diasUci = $p->diasEnUci();
                $colorDias = $diasUci >= 15 ? 'danger' : ($diasUci >= 10 ? 'warning' : 'primary');
            @endphp
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-3" type="button"
                            data-bs-toggle="collapse" data-bs-target="#est{{ $p->id }}">
                        <div class="d-flex align-items-center gap-3 w-100 flex-wrap">
                            <span class="badge bg-{{ $colorDias }} rounded-pill" style="font-size:0.85rem;min-width:55px;">
                                {{ $diasUci }}d
                            </span>
                            <div>
                                <div class="fw-semibold" style="font-size:0.9rem;">{{ $p->nombre }}</div>
                                <div class="text-muted" style="font-size:0.75rem;">{{ $p->documento }}</div>
                                <div class="mt-1 d-flex align-items-center gap-1">
                                    <span class="badge bg-secondary" style="font-size:0.75rem;">{{ $p->ultimoSnapshot->ubicacion ?? '—' }}</span>
                                    <span class="badge" style="background:#e8f0ff;color:#0d6efd;font-size:0.72rem;">{{ $p->ultimoSnapshot->subunidad ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="ms-auto me-3 d-flex gap-1 flex-wrap estancia-causas">
                                @if($causa)
                                    @foreach($etiquetas as $campo => $info)
                                        @if($causa->$campo)
                                        <span class="badge bg-{{ $info['color'] }}" style="font-size:0.68rem;">
                                            <i class="bi {{ $info['icon'] }} me-1"></i>{{ $info['label'] }}
                                        </span>
                                        @endif
                                    @endforeach
                                @else
                                    <span class="badge bg-light text-muted border" style="font-size:0.72rem;">
                                        <i class="bi bi-question-circle me-1"></i>Sin causa registrada
                                    </span>
                                @endif
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="est{{ $p->id }}" class="accordion-collapse collapse">
                    <div class="accordion-body bg-light">
                        <div class="row g-3">
                            {{-- Info clínica rápida --}}
                            <div class="col-md-4">
                                <div class="card h-100 shadow-none">
                                    <div class="card-body py-2">
                                        <p class="text-muted mb-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Resumen clínico</p>
                                        <div style="font-size:0.82rem;" class="mb-1">
                                            <i class="bi bi-calendar-check me-1 text-primary"></i>
                                            Ingreso UCI: <strong>{{ $p->ingreso_uci->format('d/m/Y H:i') }}</strong>
                                        </div>
                                        @if($p->ultimoSnapshot)
                                        <div style="font-size:0.82rem;" class="mb-1">
                                            <i class="bi bi-clipboard-pulse me-1 text-warning"></i>
                                            Criterio: {{ $p->ultimoSnapshot->criterio_atencion ?? '—' }}
                                        </div>
                                        @if($p->ultimoSnapshot->soporte_ventilatorio)
                                        <div style="font-size:0.82rem;" class="mb-1">
                                            <i class="bi bi-lungs me-1 text-info"></i>
                                            Vent: {{ $p->ultimoSnapshot->soporte_ventilatorio }}
                                        </div>
                                        @endif
                                        @if($p->ultimoSnapshot->news)
                                        <div style="font-size:0.82rem;" class="mb-1">
                                            <i class="bi bi-thermometer-half me-1 text-danger"></i>
                                            NEWS: <strong class="{{ $p->ultimoSnapshot->news >= 5 ? 'text-danger' : '' }}">{{ $p->ultimoSnapshot->news }}</strong>
                                        </div>
                                        @endif
                                        @endif
                                        <a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-outline-primary mt-2 w-100">
                                            <i class="bi bi-eye me-1"></i>Ver detalle completo
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- Formulario causas --}}
                            <div class="col-md-8">
                                <div class="card h-100 shadow-none">
                                    <div class="card-body py-2">
                                        <p class="text-muted mb-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">
                                            Causas de estancia prolongada
                                        </p>
                                        <form method="POST" action="{{ route('pacientes.guardar-causa', $p) }}">
                                            @csrf
                                            <div class="row g-2 mb-2">
                                                @foreach($etiquetas as $campo => $info)
                                                <div class="col-sm-6">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                               name="{{ $campo }}" id="{{ $campo }}_{{ $p->id }}"
                                                               {{ ($causa && $causa->$campo) ? 'checked' : '' }}>
                                                        <label class="form-check-label" for="{{ $campo }}_{{ $p->id }}"
                                                               style="font-size:0.82rem;">
                                                            <i class="bi {{ $info['icon'] }} text-{{ $info['color'] }} me-1"></i>
                                                            {{ $info['label'] }}
                                                        </label>
                                                    </div>
                                                </div>
                                                @endforeach
                                            </div>
                                            <div class="mb-2">
                                                <textarea name="observaciones" class="form-control form-control-sm"
                                                          rows="2" placeholder="Observaciones adicionales..."
                                                          style="font-size:0.82rem;">{{ $causa->observaciones ?? '' }}</textarea>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="bi bi-check-lg me-1"></i>
                                                {{ $causa ? 'Actualizar causas' : 'Registrar causas' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-4 text-center text-muted">
                <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                No hay pacientes con más de 5 días en UCI actualmente.
            </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const ctxCausas = document.getElementById('chartCausas');
if (ctxCausas) {
    const causas = {!! json_encode($distribucionCausas) !!};
    new Chart(ctxCausas, {
        type: 'bar',
        data: {
            labels: Object.keys(causas),
            datasets: [{
                data: Object.values(causas),
                backgroundColor: ['#dc3545','#fd7e14','#0dcaf0','#0d6efd','#6c757d','#198754'],
                borderRadius: 5,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}
</script>
@endpush
