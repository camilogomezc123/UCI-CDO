@extends('layouts.app')

@section('title', 'Síndrome Post-UCI (PICS)')

@section('content')
<div class="container-fluid">

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-heart-pulse me-2 text-primary"></i>Seguimiento Post-UCI (PICS)</h2>
            <small class="text-muted">Pacientes egresados en los últimos 180 días · {{ now()->format('d/m/Y') }}</small>
        </div>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Cards resumen --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-primary">{{ $stats['total'] }}</div>
                    <div class="small text-muted">Egresados últimos 180d</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-warning">{{ $stats['pendientes'] }}</div>
                    <div class="small text-muted">Con evaluación pendiente</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-danger">{{ $stats['alto_riesgo'] }}</div>
                    <div class="small text-muted">Alto riesgo PICS</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-success">{{ $stats['evaluados'] }}</div>
                    <div class="small text-muted">Con al menos 1 evaluación</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de pacientes --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="bi bi-people me-2"></i>Pacientes egresados UCI</h5>
        </div>
        <div class="card-body p-0">
            @if($pacientes->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No hay pacientes egresados en los últimos 180 días.
                </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Paciente</th>
                            <th>Egreso UCI</th>
                            <th>Días</th>
                            <th>Riesgo PICS</th>
                            <th>Evaluaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pacientes as $item)
                        @php $p = $item['paciente']; @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $p->nombre }}</div>
                                <small class="text-muted">{{ $p->identificacion ?? 'Sin ID' }}</small>
                            </td>
                            <td>
                                <span class="text-nowrap">{{ $p->egreso_uci?->format('d/m/Y') ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">{{ $item['dias'] }}d</span>
                            </td>
                            <td>
                                @if($item['riesgo'])
                                    <span class="badge bg-{{ $item['riesgo']->badgeClass() }}">
                                        {{ ucfirst($item['riesgo']->nivel_riesgo) }}
                                        <small>({{ $item['riesgo']->score_total }}pts)</small>
                                    </span>
                                @else
                                    <button class="btn btn-xs btn-outline-secondary"
                                        onclick="calcularRiesgo({{ $p->id }})"
                                        title="Calcular score de riesgo PICS">
                                        <i class="bi bi-calculator"></i> Calcular
                                    </button>
                                    <form id="form-riesgo-{{ $p->id }}"
                                        action="{{ route('pics.calcularRiesgo', $p) }}"
                                        method="POST" class="d-none">
                                        @csrf
                                    </form>
                                @endif
                            </td>
                            <td>
                                @foreach($item['evaluaciones'] as $mom => $evals)
                                    @foreach($evals as $ev)
                                        <a href="{{ route('pics.show', $ev) }}"
                                           class="badge text-decoration-none me-1
                                               @if($ev->semaforoGlobal() === 'verde') bg-success
                                               @elseif($ev->semaforoGlobal() === 'rojo') bg-danger
                                               @else bg-warning text-dark @endif"
                                           title="{{ $ev->labelMomento() }} · {{ $ev->tipo }}">
                                            {{ $ev->labelMomento() }}
                                        </a>
                                    @endforeach
                                @endforeach
                                @foreach($item['disponibles'] as $disp)
                                    <span class="badge bg-primary-subtle text-primary border border-primary me-1">
                                        <i class="bi bi-clock"></i> {{ $disp['label'] }}
                                    </span>
                                @endforeach
                                @foreach($item['pendientes'] as $pend)
                                    <span class="badge bg-danger-subtle text-danger border border-danger me-1"
                                          title="Fuera de ventana">
                                        <i class="bi bi-exclamation-triangle"></i> {{ $pend['label'] }}
                                    </span>
                                @endforeach
                                @if($item['evaluaciones']->isEmpty() && empty($item['disponibles']) && empty($item['pendientes']))
                                    <span class="text-muted small">Sin evaluaciones aún</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @foreach($item['disponibles'] as $disp)
                                    <a href="{{ route('pics.create', [$p, $disp['momento']]) }}"
                                       class="btn btn-primary" title="Evaluar {{ $disp['label'] }}">
                                        <i class="bi bi-clipboard-plus"></i>
                                        <span class="d-none d-lg-inline ms-1">{{ $disp['label'] }}</span>
                                    </a>
                                    @endforeach
                                    {{-- Link familiar --}}
                                    @if(!empty($item['disponibles']))
                                    <a href="{{ route('pics.create.familia', [$p, $item['disponibles'][0]['momento']]) }}"
                                       class="btn btn-outline-secondary" title="Evaluar familiar">
                                        <i class="bi bi-people"></i>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

</div>

@push('scripts')
<script>
function calcularRiesgo(id) {
    if (confirm('¿Calcular el score de riesgo PICS para este paciente?')) {
        document.getElementById('form-riesgo-' + id).submit();
    }
}
</script>
@endpush
@endsection
