@extends('layouts.app')
@section('title', 'Reportes')
@section('page-title', 'Reportes por Subunidad UCI')

@section('content')

{{-- Selector de subunidad --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="d-flex align-items-center gap-3 flex-wrap">
            <label class="fw-semibold mb-0" style="font-size:0.875rem;">Subunidad:</label>
            <div class="btn-group flex-wrap">
                <a href="{{ route('reportes.index') }}"
                   class="btn btn-sm {{ $subunidadFiltro === 'todas' ? 'btn-dark' : 'btn-outline-secondary' }}">
                   Todas
                </a>
                @foreach(['UCI Quirúrgica','UCI Cardiovascular','UCI Respiratoria','UCI General','UCI Neurovascular','UCIN','UCI Torre C','UCI Torre B'] as $sub)
                <a href="{{ route('reportes.index', ['subunidad'=>$sub]) }}"
                   class="btn btn-sm {{ $subunidadFiltro === $sub ? 'btn-dark' : 'btn-outline-secondary' }}">
                   {{ $sub }}
                </a>
                @endforeach
            </div>
        </form>
    </div>
</div>

{{-- Tabla de subunidades --}}
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-building me-2 text-primary"></i>Resumen por Subunidad</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Subunidad</th>
                        <th class="text-center">Capacidad</th>
                        <th class="text-center">Ocupados</th>
                        <th class="text-center">Vacíos</th>
                        <th class="text-center">% Ocup.</th>
                        <th class="text-center">UCI</th>
                        <th class="text-center">UCIN / Interm.</th>
                        <th class="text-center">Hosp. / Traslado</th>
                        <th class="text-center">VMI</th>
                        <th class="text-center">Vasopresor</th>
                        <th class="text-center">Pend. Egreso</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalOcupados = 0; $totalCapacidad = 0; @endphp
                    @foreach($porSubunidad as $sub => $datos)
                    @php
                        $pct = $datos['capacidad'] > 0 ? round($datos['total']/$datos['capacidad']*100) : 0;
                        $colorPct = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                        $totalOcupados += $datos['total'];
                        $totalCapacidad += $datos['capacidad'];
                        $activo = $subunidadFiltro === $sub ? 'fw-bold' : '';
                    @endphp
                    <tr class="{{ $activo }}">
                        <td style="font-size:0.875rem;">
                            <a href="{{ route('reportes.index', ['subunidad'=>$sub]) }}" class="text-decoration-none text-dark">
                                {{ $sub }}
                            </a>
                        </td>
                        <td class="text-center">{{ $datos['capacidad'] }}</td>
                        <td class="text-center fw-semibold">{{ $datos['total'] }}</td>
                        <td class="text-center text-muted">{{ $datos['capacidad'] - $datos['total'] }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $colorPct }}">{{ $pct }}%</span>
                        </td>
                        <td class="text-center"><span class="badge" style="background:#dc354520;color:#dc3545;">{{ $datos['uci'] }}</span></td>
                        <td class="text-center"><span class="badge" style="background:#ffc10720;color:#9a7200;">{{ $datos['ucin'] }}</span></td>
                        <td class="text-center"><span class="badge" style="background:#19875420;color:#198754;">{{ $datos['traslado'] }}</span></td>
                        <td class="text-center"><span class="badge bg-info text-dark">{{ $datos['con_vmi'] }}</span></td>
                        <td class="text-center"><span class="badge bg-danger">{{ $datos['con_vasopresor'] }}</span></td>
                        <td class="text-center">
                            @if($datos['pendiente_egreso'] > 0)
                                <span class="badge bg-warning text-dark">{{ $datos['pendiente_egreso'] }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-dark fw-bold">
                        <td>TOTAL</td>
                        <td class="text-center">{{ $totalCapacidad }}</td>
                        <td class="text-center">{{ $totalOcupados }}</td>
                        <td class="text-center">{{ $totalCapacidad - $totalOcupados }}</td>
                        <td class="text-center">
                            <span class="badge bg-light text-dark">
                                {{ $totalCapacidad > 0 ? round($totalOcupados/$totalCapacidad*100) : 0 }}%
                            </span>
                        </td>
                        <td colspan="6"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">

    {{-- Gráfico de barras por subunidad --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-primary"></i>Ocupación por Subunidad</div>
            <div class="card-body">
                <canvas id="chartSubunidad" style="max-height:260px;"></canvas>
            </div>
        </div>
    </div>

    {{-- Pacientes pendientes de egreso --}}
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2 text-danger">
                <i class="bi bi-hourglass-split"></i>
                Pacientes Pendientes de Egreso ({{ $pacientesEspera->count() }})
            </div>
            <div class="card-body p-0">
                @if($pacientesEspera->isEmpty())
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                        Ningún paciente pendiente de egreso.
                    </div>
                @else
                <div class="table-responsive" style="max-height:280px;overflow-y:auto;">
                    <table class="table table-sm mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th>Paciente</th>
                                <th>Cama</th>
                                <th>Indicación médica</th>
                                <th>Tiempo espera</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pacientesEspera as $p)
                            <tr>
                                <td style="font-size:0.82rem;">
                                    <a href="{{ route('pacientes.show', $p) }}" class="text-decoration-none fw-semibold">
                                        {{ Str::limit($p->nombre, 25) }}
                                    </a>
                                </td>
                                <td style="font-size:0.82rem;">{{ $p->ultimoSnapshot->ubicacion ?? '—' }}</td>
                                <td style="font-size:0.8rem;">{{ $p->salida_hospitalizacion->format('d/m H:i') }}</td>
                                <td><span class="tiempo-espera" style="font-size:0.82rem;">{{ $p->tiempoEsperaHospitalizacion() }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Pacientes sin fecha de ingreso UCI --}}
    @if($sinIngreso->count() > 0)
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2 text-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Pacientes sin fecha de ingreso UCI registrada ({{ $sinIngreso->count() }})
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Documento</th>
                                <th>Cama</th>
                                <th>Subunidad</th>
                                <th>Criterio</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sinIngreso as $p)
                            <tr>
                                <td style="font-size:0.85rem;">{{ $p->nombre }}</td>
                                <td style="font-size:0.82rem;color:#888;">{{ $p->documento }}</td>
                                <td style="font-size:0.85rem;">{{ $p->ultimoSnapshot->ubicacion ?? '—' }}</td>
                                <td style="font-size:0.82rem;">{{ $p->ultimoSnapshot->subunidad ?? '—' }}</td>
                                <td style="font-size:0.82rem;">{{ $p->ultimoSnapshot->criterio_atencion ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-warning text-dark">
                                        <i class="bi bi-clock me-1"></i>Registrar
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
const subunidades = @json(array_keys($porSubunidad->toArray()));
const ocupados = @json($porSubunidad->pluck('total')->values());
const capacidades = @json($porSubunidad->pluck('capacidad')->values());

new Chart(document.getElementById('chartSubunidad'), {
    type: 'bar',
    data: {
        labels: subunidades.map(s => s.replace('UCI ', '')),
        datasets: [
            {
                label: 'Ocupados',
                data: ocupados,
                backgroundColor: '#0d6efd',
                borderRadius: 4,
            },
            {
                label: 'Vacíos',
                data: capacidades.map((c, i) => c - ocupados[i]),
                backgroundColor: '#e9ecef',
                borderRadius: 4,
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            x: { stacked: true },
            y: { stacked: true, ticks: { stepSize: 1 } }
        },
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>
@endpush
