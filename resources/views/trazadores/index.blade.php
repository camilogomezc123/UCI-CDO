@extends('layouts.app')
@section('title', 'Trazadores UCI')
@section('page-title', 'Pacientes Trazadores')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-clipboard2-pulse kpi-icon"></i>
            <div class="kpi-number">{{ $activos->count() }}</div>
            <div class="kpi-label">Trazadores activos</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6c757d,#495057)">
            <i class="bi bi-bar-chart-line kpi-icon"></i>
            <div class="kpi-number">{{ $estadisticas->count() }}</div>
            <div class="kpi-label">En estadísticas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-clock-history kpi-icon"></i>
            <div class="kpi-number">{{ $pendientesDespues->count() }}</div>
            <div class="kpi-label">Pendientes encuesta DESPUÉS</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#198754,#146c43)">
            <i class="bi bi-check2-circle kpi-icon"></i>
            <div class="kpi-number">{{ $cerrados->count() }}</div>
            <div class="kpi-label">Casos cerrados</div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="tabTrazadores">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#activos">
            <i class="bi bi-activity me-1"></i>Activos
            @if($activos->count() > 0)
                <span class="badge bg-primary ms-1">{{ $activos->count() }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#pendientes">
            <i class="bi bi-clock me-1"></i>Pendientes DESPUÉS
            @if($pendientesDespues->count() > 0)
                <span class="badge bg-warning text-dark ms-1">{{ $pendientesDespues->count() }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#estadisticas">
            <i class="bi bi-bar-chart me-1"></i>Estadísticas
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#cerrados">
            <i class="bi bi-archive me-1"></i>Cerrados ({{ $cerrados->count() }})
        </a>
    </li>
</ul>

<div class="tab-content">

    {{-- ── Activos ──────────────────────────────────────────────────────── --}}
    <div class="tab-pane fade show active" id="activos">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Trazadores en diligenciamiento inicial
            </div>
            <div class="card-body p-0">
                @if($activos->isEmpty())
                    <p class="text-muted p-3 mb-0">No hay trazadores en diligenciamiento. Márquelos desde el <a href="{{ route('pacientes.index') }}">censo de UCI</a>.</p>
                @else
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Paciente</th><th>Tipo</th><th>Marcado</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    @foreach($activos as $t)
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:.875rem;">{{ $t->paciente->nombre ?? '—' }}</div>
                            <small class="text-muted">{{ $t->paciente->documento ?? '' }}</small>
                        </td>
                        <td><span class="badge bg-danger">{{ strtoupper($t->tipo_trazador) }}</span></td>
                        <td style="font-size:.82rem;">{{ $t->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('trazadores.edit', $t) }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-pencil me-1"></i>Completar
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Pendientes DESPUÉS ───────────────────────────────────────────── --}}
    <div class="tab-pane fade" id="pendientes">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock me-2 text-warning"></i>Pendientes de encuesta DESPUÉS (90 días cumplidos)
            </div>
            <div class="card-body p-0">
                @if($pendientesDespues->isEmpty())
                    <p class="text-muted p-3 mb-0">Ningún trazador pendiente de encuesta DESPUÉS.</p>
                @else
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Paciente</th><th>Tipo</th><th>Fecha objetivo</th><th>Días</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    @foreach($pendientesDespues as $t)
                    <tr class="table-warning">
                        <td>
                            <div class="fw-semibold" style="font-size:.875rem;">{{ $t->paciente->nombre ?? '—' }}</div>
                            <small class="text-muted">{{ $t->paciente->documento ?? '' }}</small>
                        </td>
                        <td><span class="badge bg-danger">{{ strtoupper($t->tipo_trazador) }}</span></td>
                        <td style="font-size:.82rem;">{{ $t->fecha_objetivo_despues?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            @php $dias = $t->diasRestantes(); @endphp
                            @if($dias !== null && $dias < 0)
                                <span class="badge bg-danger">{{ abs($dias) }}d vencido</span>
                            @elseif($dias === 0)
                                <span class="badge bg-warning text-dark">Hoy</span>
                            @else
                                <span class="badge bg-secondary">{{ $dias }}d restantes</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('trazadores.despues.edit', $t) }}" class="btn btn-sm btn-warning">
                                <i class="bi bi-pencil me-1"></i>Encuesta DESPUÉS
                            </a>
                            <a href="{{ route('trazadores.show', $t) }}" class="btn btn-sm btn-outline-secondary ms-1">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Estadísticas ─────────────────────────────────────────────────── --}}
    <div class="tab-pane fade" id="estadisticas">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-bar-chart-line me-2 text-secondary"></i>Trazadores en seguimiento y cerrados con indicadores
            </div>
            <div class="card-body p-0">
                @if($estadisticas->isEmpty())
                    <p class="text-muted p-3 mb-0">Aún no hay trazadores con parte inicial guardada.</p>
                @else
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Paciente</th><th>Tipo</th><th>Estado</th>
                        <th>Reanim.</th><th>ABCDEF</th><th>Global</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    @foreach($estadisticas as $t)
                    @php
                        $r   = $t->resultados ?? [];
                        $re  = $r['adherencia_reanimacion_pct'] ?? null;
                        $ab  = $r['adherencia_abcdef_pct'] ?? null;
                        $gl  = $r['puntuacion_global_pct'] ?? null;
                        $banda = $t->getBandaGlobal();
                        $bandaColor = match($banda) { 'verde'=>'success','amarillo'=>'warning','rojo'=>'danger', default=>'secondary' };
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:.875rem;">{{ $t->paciente->nombre ?? '—' }}</div>
                            <small class="text-muted">{{ $t->paciente->documento ?? '' }}</small>
                        </td>
                        <td><span class="badge bg-danger">{{ strtoupper($t->tipo_trazador) }}</span></td>
                        <td>
                            @if($t->estado === 'SEGUIMIENTO_90D')
                                <span class="badge bg-info text-dark">Seguimiento</span>
                            @else
                                <span class="badge bg-success">Cerrado</span>
                            @endif
                        </td>
                        <td>{{ $re !== null ? $re.'%' : '—' }}</td>
                        <td>{{ $ab !== null ? $ab.'%' : '—' }}</td>
                        <td>
                            @if($gl !== null)
                                <span class="badge bg-{{ $bandaColor }}">{{ $gl }}%</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('trazadores.show', $t) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>Ver
                            </a>
                            <a href="{{ route('trazadores.edit', $t) }}" class="btn btn-sm btn-outline-secondary ms-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Cerrados ─────────────────────────────────────────────────────── --}}
    <div class="tab-pane fade" id="cerrados">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-archive me-2 text-success"></i>Casos cerrados</span>
                {{-- Exportación Excel (Paso 6) --}}
                <a href="#" class="btn btn-sm btn-outline-success disabled">
                    <i class="bi bi-file-earmark-excel me-1"></i>Exportar (próx.)
                </a>
            </div>
            <div class="card-body p-0">
                @if($cerrados->isEmpty())
                    <p class="text-muted p-3 mb-0">No hay casos cerrados aún.</p>
                @else
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Paciente</th><th>Tipo</th><th>Cierre</th>
                        <th>Global</th><th>Barthel Δ</th><th>Acciones</th>
                    </tr></thead>
                    <tbody>
                    @foreach($cerrados as $t)
                    @php
                        $r   = $t->resultados ?? [];
                        $gl  = $r['puntuacion_global_pct'] ?? null;
                        $comp = $r['comparativo'] ?? [];
                        $bartDelta = $comp['barthel_total'] ?? null;
                        $banda = $t->getBandaGlobal();
                        $bandaColor = match($banda) { 'verde'=>'success','amarillo'=>'warning','rojo'=>'danger', default=>'secondary' };
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:.875rem;">{{ $t->paciente->nombre ?? '—' }}</div>
                            <small class="text-muted">{{ $t->paciente->documento ?? '' }}</small>
                        </td>
                        <td><span class="badge bg-danger">{{ strtoupper($t->tipo_trazador) }}</span></td>
                        <td style="font-size:.82rem;">{{ $t->fecha_cierre?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            @if($gl !== null)
                                <span class="badge bg-{{ $bandaColor }}">{{ $gl }}%</span>
                            @else —
                            @endif
                        </td>
                        <td>
                            @if($bartDelta !== null)
                                <span class="{{ $bartDelta >= 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                    {{ $bartDelta >= 0 ? '+' : '' }}{{ $bartDelta }}
                                </span>
                            @else —
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('trazadores.show', $t) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>Ver
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection
