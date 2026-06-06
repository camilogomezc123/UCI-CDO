@extends('layouts.app')
@section('title', 'Reportes Periódicos')
@section('page-title', 'Reportes Periódicos UCI')

@section('content')

{{-- Selector período --}}
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('reportes.periodicos') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:0.8rem;">Tipo de reporte</label>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="{{ route('reportes.periodicos', ['tipo'=>'semanal','fecha'=>request('fecha')]) }}"
                       class="btn btn-{{ $tipo=='semanal'?'primary':'outline-primary' }}">
                        <i class="bi bi-calendar-week me-1"></i>Semanal
                    </a>
                    <a href="{{ route('reportes.periodicos', ['tipo'=>'mensual','fecha'=>request('fecha')]) }}"
                       class="btn btn-{{ $tipo=='mensual'?'primary':'outline-primary' }}">
                        <i class="bi bi-calendar-month me-1"></i>Mensual
                    </a>
                    <a href="{{ route('reportes.periodicos', ['tipo'=>'trimestral','fecha'=>request('fecha')]) }}"
                       class="btn btn-{{ $tipo=='trimestral'?'primary':'outline-primary' }}">
                        <i class="bi bi-calendar3-range me-1"></i>Trimestral
                    </a>
                    <a href="{{ route('reportes.periodicos', ['tipo'=>'anual','fecha'=>request('fecha')]) }}"
                       class="btn btn-{{ $tipo=='anual'?'primary':'outline-primary' }}">
                        <i class="bi bi-calendar4-range me-1"></i>Anual
                    </a>
                </div>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:0.8rem;">Fecha de referencia</label>
                <input type="date" name="fecha" value="{{ request('fecha', now()->format('Y-m-d')) }}"
                       class="form-control form-control-sm" style="width:160px;">
            </div>
            <div class="col-auto">
                <input type="hidden" name="tipo" value="{{ $tipo }}">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-clockwise me-1"></i>Actualizar
                </button>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                {{-- Descargar Excel --}}
                <a href="{{ route('reportes.periodicos.descargar', ['tipo'=>$tipo,'fecha'=>request('fecha', now()->format('Y-m-d'))]) }}"
                   class="btn btn-sm btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>Descargar Excel
                </a>
                <button onclick="window.print()" type="button" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer me-1"></i>Imprimir / PDF
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Encabezado del período --}}
<div class="alert d-flex align-items-center gap-3 mb-4" style="background:linear-gradient(135deg,#0d6efd,#0a58ca);color:#fff;border-radius:12px;border:none;">
    <i class="bi bi-calendar-range fs-3"></i>
    <div>
        <strong style="font-size:1.1rem;">{{ $etiquetaPeriodo }}</strong>
        <div style="font-size:0.9rem;opacity:0.85;">
            Del {{ $datos['periodo']['inicio'] }} al {{ $datos['periodo']['fin'] }}
            &nbsp;·&nbsp; {{ $datos['totalCargas'] }} carga(s) de datos en el período
        </div>
    </div>
</div>

{{-- KPIs principales --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-person-add kpi-icon"></i>
            <div class="kpi-number">{{ $datos['nuevosIngresos'] }}</div>
            <div class="kpi-label">Nuevos ingresos</div>
            <div class="kpi-sub">Pacientes nuevos en UCI</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#198754,#157347)">
            <i class="bi bi-person-check kpi-icon"></i>
            <div class="kpi-number">{{ $datos['totalEgresados'] }}</div>
            <div class="kpi-label">Egresos UCI</div>
            <div class="kpi-sub">Promedio estancia: <strong>{{ $datos['avgEstanciaEgresados'] }}d</strong></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-hospital kpi-icon"></i>
            <div class="kpi-number">{{ $datos['totalSalidaHosp'] }}</div>
            <div class="kpi-label">Salidas a hospitalización</div>
            <div class="kpi-sub">Espera promedio egreso: <strong>{{ $datos['avgEsperaEgreso'] }}h</strong></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
            <i class="bi bi-people kpi-icon"></i>
            <div class="kpi-number">{{ $datos['avgOcupacion'] }}</div>
            <div class="kpi-label">Ocupación promedio/día</div>
            <div class="kpi-sub">Rotación camas: <strong>{{ $datos['rotacionCamas'] }}</strong></div>
        </div>
    </div>
</div>

{{-- Indicadores clínicos y de calidad --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Escalas Clínicas (promedio)</div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($datos['promediosEscalas'] as $escala => $val)
                    <div class="col-6">
                        <div class="text-center p-3 rounded" style="background:#f8f9fa;">
                            <div class="fw-bold" style="font-size:1.4rem;">{{ $val ?: '—' }}</div>
                            <div style="font-size:0.78rem;color:#6c757d;">{{ $escala }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <hr class="my-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:0.85rem;"><i class="bi bi-thermometer-high text-danger me-2"></i>Pacientes NEWS ≥ 5</span>
                    <span class="badge bg-danger rounded-pill fs-6">{{ $datos['alertasNews'] }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:0.85rem;"><i class="bi bi-activity text-warning me-2"></i>Pacientes SOFA ≥ 10</span>
                    <span class="badge bg-warning text-dark rounded-pill fs-6">{{ $datos['alertasSofa'] }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:0.85rem;"><i class="bi bi-lungs text-info me-2"></i>Pacientes con VMI</span>
                    <span class="badge bg-info text-dark rounded-pill fs-6">{{ $datos['conVmi'] }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:0.85rem;"><i class="bi bi-person-arms-up text-success me-2"></i>Movilización &lt; 48h</span>
                    <span class="badge bg-success rounded-pill fs-6">{{ $datos['movilizacionTemprana'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-building me-2 text-primary"></i>Pacientes por Subunidad</div>
            <div class="card-body">
                @if(empty($datos['porSubunidad']))
                    <div class="text-center text-muted py-3" style="font-size:0.85rem;">Sin datos en el período.</div>
                @else
                @php $maxSub = max($datos['porSubunidad']); @endphp
                @foreach($datos['porSubunidad'] as $sub => $n)
                <div class="mb-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.78rem;font-weight:600;">{{ $sub }}</span>
                        <span class="fw-bold" style="font-size:0.82rem;">{{ $n }}</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div class="progress-bar" style="width:{{ $maxSub>0?round($n/$maxSub*100):0 }}%;background:#0d6efd;"></div>
                    </div>
                </div>
                @endforeach
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-x me-2 text-primary"></i>Causas Estancia Prolongada</div>
            <div class="card-body">
                @if(array_sum($datos['distribucionCausas']) == 0)
                    <div class="text-center text-muted py-3" style="font-size:0.85rem;">Sin causas registradas.</div>
                @else
                    <canvas id="chartCausasPeriodo" style="max-height:180px;"></canvas>
                @endif
                <hr class="my-2">
                @foreach($datos['distribucionCausas'] as $causa => $n)
                <div class="d-flex justify-content-between mb-1">
                    <span style="font-size:0.8rem;">{{ $causa }}</span>
                    <strong style="font-size:0.85rem;">{{ $n }}</strong>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- Gráfico ocupación diaria (solo semanal / mensual) --}}
@if(count($datos['ocupacionDiaria']) > 1 && in_array($tipo, ['semanal','mensual']))
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-graph-up me-2 text-primary"></i>Ocupación Diaria en el Período</div>
    <div class="card-body">
        <canvas id="chartOcupacionPeriodo" style="max-height:200px;"></canvas>
    </div>
</div>
@endif

{{-- ── Desglose Mes a Mes (trimestral / anual) ── --}}
@if(!empty($mesMes))
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-table text-primary"></i>
        <span>Desglose Mes a Mes</span>
        <span class="badge bg-secondary ms-1" style="font-size:0.7rem;">{{ count($mesMes) }} meses</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">
                <thead class="table-primary">
                    <tr>
                        <th>Mes</th>
                        <th class="text-center">Nuevos ingresos</th>
                        <th class="text-center">Egresos UCI</th>
                        <th class="text-center">Sal. Hosp.</th>
                        <th class="text-center">Estancia prom. (d)</th>
                        <th class="text-center">Ocup. prom./día</th>
                        <th class="text-center">NEWS ≥ 5</th>
                        <th class="text-center">SOFA ≥ 10</th>
                        <th class="text-center">VMI</th>
                        <th class="text-center">Moviliz. &lt;48h</th>
                        <th class="text-center">NEWS prom.</th>
                        <th class="text-center">SOFA prom.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mesMes as $entrada)
                    @php $d = $entrada['datos']; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $entrada['mes'] }}</td>
                        <td class="text-center">
                            @if($d['nuevosIngresos'] > 0)
                                <span class="badge bg-primary rounded-pill">{{ $d['nuevosIngresos'] }}</span>
                            @else <span class="text-muted">0</span> @endif
                        </td>
                        <td class="text-center">
                            @if($d['totalEgresados'] > 0)
                                <span class="badge bg-success rounded-pill">{{ $d['totalEgresados'] }}</span>
                            @else <span class="text-muted">0</span> @endif
                        </td>
                        <td class="text-center">{{ $d['totalSalidaHosp'] ?: '—' }}</td>
                        <td class="text-center">{{ $d['avgEstanciaEgresados'] ?: '—' }}</td>
                        <td class="text-center">{{ $d['avgOcupacion'] ?: '—' }}</td>
                        <td class="text-center">
                            @if($d['alertasNews'] > 0)
                                <span class="badge bg-danger rounded-pill">{{ $d['alertasNews'] }}</span>
                            @else <span class="text-muted">0</span> @endif
                        </td>
                        <td class="text-center">
                            @if($d['alertasSofa'] > 0)
                                <span class="badge bg-warning text-dark rounded-pill">{{ $d['alertasSofa'] }}</span>
                            @else <span class="text-muted">0</span> @endif
                        </td>
                        <td class="text-center">{{ $d['conVmi'] ?: '—' }}</td>
                        <td class="text-center">{{ $d['movilizacionTemprana'] ?: '—' }}</td>
                        <td class="text-center">{{ $d['promediosEscalas']['NEWS'] ?: '—' }}</td>
                        <td class="text-center">{{ $d['promediosEscalas']['SOFA'] ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
                {{-- Totales / promedios al pie --}}
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td>TOTAL / PROM.</td>
                        <td class="text-center">{{ $datos['nuevosIngresos'] }}</td>
                        <td class="text-center">{{ $datos['totalEgresados'] }}</td>
                        <td class="text-center">{{ $datos['totalSalidaHosp'] }}</td>
                        <td class="text-center">{{ $datos['avgEstanciaEgresados'] }}</td>
                        <td class="text-center">{{ $datos['avgOcupacion'] }}</td>
                        <td class="text-center">{{ $datos['alertasNews'] }}</td>
                        <td class="text-center">{{ $datos['alertasSofa'] }}</td>
                        <td class="text-center">{{ $datos['conVmi'] }}</td>
                        <td class="text-center">{{ $datos['movilizacionTemprana'] }}</td>
                        <td class="text-center">{{ $datos['promediosEscalas']['NEWS'] }}</td>
                        <td class="text-center">{{ $datos['promediosEscalas']['SOFA'] }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Resumen narrativo --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-file-text text-primary"></i>
        Resumen del Período
        <span class="badge bg-secondary ms-2" style="font-size:0.7rem;">Generado automáticamente</span>
    </div>
    <div class="card-body" style="font-size:0.875rem;line-height:1.8;">
        <p>
            Durante el período comprendido entre el <strong>{{ $datos['periodo']['inicio'] }}</strong> y el
            <strong>{{ $datos['periodo']['fin'] }}</strong>, la UCI de Clínica de Occidente registró
            <strong>{{ $datos['nuevosIngresos'] }}</strong> nuevo(s) ingreso(s) y
            <strong>{{ $datos['totalEgresados'] }}</strong> egreso(s) efectivo(s).
            La ocupación promedio diaria fue de <strong>{{ $datos['avgOcupacion'] }}</strong> pacientes,
            con una rotación de camas de <strong>{{ $datos['rotacionCamas'] }}</strong>.
        </p>
        @if($datos['totalEgresados'] > 0)
        <p>
            El tiempo promedio de estancia en UCI para los pacientes egresados en este período fue de
            <strong>{{ $datos['avgEstanciaEgresados'] }} días</strong>.
            @if($datos['totalSalidaHosp'] > 0)
            De los <strong>{{ $datos['totalSalidaHosp'] }}</strong> paciente(s) con criterio de hospitalización,
            el tiempo de espera promedio entre la decisión de salida y el egreso efectivo fue de
            <strong>{{ $datos['avgEsperaEgreso'] }} horas</strong>.
            @endif
        </p>
        @endif
        @if($datos['alertasNews'] > 0 || $datos['alertasSofa'] > 0)
        <p>
            En cuanto a indicadores de gravedad clínica,
            @if($datos['alertasNews'] > 0)
            <strong>{{ $datos['alertasNews'] }}</strong> paciente(s) presentaron NEWS ≥ 5
            @endif
            @if($datos['alertasNews'] > 0 && $datos['alertasSofa'] > 0) y @endif
            @if($datos['alertasSofa'] > 0)
            <strong>{{ $datos['alertasSofa'] }}</strong> paciente(s) presentaron SOFA ≥ 10
            @endif
            durante el período.
            @if($datos['conVmi'] > 0)
            Se registraron <strong>{{ $datos['conVmi'] }}</strong> paciente(s) con ventilación mecánica invasiva.
            @endif
            @if($datos['movilizacionTemprana'] > 0)
            La movilización temprana (&lt; 48 horas) se aplicó a <strong>{{ $datos['movilizacionTemprana'] }}</strong> paciente(s).
            @endif
        </p>
        @endif
        @php
            $causaPrincipal = array_key_first(array_filter($datos['distribucionCausas'], fn($v) => $v > 0));
        @endphp
        @if($causaPrincipal)
        <p>
            La principal causa de estancia prolongada registrada fue
            <strong>{{ strtolower($causaPrincipal) }}</strong>
            ({{ $datos['distribucionCausas'][$causaPrincipal] }} caso(s)).
        </p>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<script>
@if(array_sum($datos['distribucionCausas']) > 0)
new Chart(document.getElementById('chartCausasPeriodo'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_keys($datos['distribucionCausas'])) !!},
        datasets: [{
            data: {!! json_encode(array_values($datos['distribucionCausas'])) !!},
            backgroundColor: ['#dc3545','#fd7e14','#0dcaf0','#0d6efd','#6c757d','#198754'],
            borderWidth: 0,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, cutout: '60%' }
});
@endif

@if(count($datos['ocupacionDiaria']) > 1 && in_array($tipo, ['semanal','mensual']))
new Chart(document.getElementById('chartOcupacionPeriodo'), {
    type: 'line',
    data: {
        labels: {!! json_encode(array_column($datos['ocupacionDiaria'], 'fecha')) !!},
        datasets: [{
            label: 'Pacientes',
            data: {!! json_encode(array_column($datos['ocupacionDiaria'], 'total')) !!},
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.1)',
            fill: true, tension: 0.3, pointRadius: 4,
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: false, ticks: { stepSize: 5 } },
            x: { ticks: { maxTicksLimit: 14, maxRotation: 0 } }
        },
        plugins: { legend: { display: false } }
    }
});
@endif
</script>
@endpush
