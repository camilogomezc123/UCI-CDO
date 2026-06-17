@extends('layouts.app')
@section('title', 'Reingresos a UCI')
@section('page-title', 'Reingresos a UCI')

@section('content')

@if(!empty($necesitaMigracion))
<div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div>
        <strong>Migraciones pendientes</strong><br>
        La tabla de reingresos aún no existe en esta base de datos.
        Ejecuta <code>php artisan migrate</code> en el servidor para activar este módulo.
    </div>
</div>
@else
{{-- Filtro de período --}}
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('reingresos.index') }}" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:0.8rem;">Desde</label>
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                       class="form-control form-control-sm" style="width:150px;">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1" style="font-size:0.8rem;">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                       class="form-control form-control-sm" style="width:150px;">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="{{ route('reingresos.index') }}" class="btn btn-sm btn-outline-secondary ms-1">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
            <div class="col-auto ms-auto">
                <a href="{{ route('reingresos.descargar', ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')]) }}"
                   class="btn btn-sm btn-success">
                    <i class="bi bi-file-earmark-excel me-1"></i>Descargar Excel
                </a>
            </div>
        </form>
    </div>
</div>

{{-- KPIs globales --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
            <i class="bi bi-arrow-repeat kpi-icon"></i>
            <div class="kpi-number">{{ $conReingreso }}</div>
            <div class="kpi-label">Pacientes con reingreso</div>
            <div class="kpi-sub">De {{ $totalPacientes }} registrados</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-percent kpi-icon"></i>
            <div class="kpi-number">{{ $pctReingreso }}%</div>
            <div class="kpi-label">Tasa de reingreso</div>
            <div class="kpi-sub">Sobre total de pacientes</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-journal-check kpi-icon"></i>
            <div class="kpi-number">{{ $totalEpisodios }}</div>
            <div class="kpi-label">Episodios archivados</div>
            <div class="kpi-sub">Episodios completados</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
            <i class="bi bi-person-lines-fill kpi-icon"></i>
            <div class="kpi-number">{{ $activosReingreso->count() }}</div>
            <div class="kpi-label">Reingresos activos ahora</div>
            <div class="kpi-sub">Actualmente en UCI</div>
        </div>
    </div>
</div>

{{-- Reingresos activos en UCI ahora --}}
@if($activosReingreso->count() > 0)
<div class="card mb-4 border-danger" style="border-width:2px!important;">
    <div class="card-header bg-danger text-white d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong>Pacientes en reingreso actualmente en UCI ({{ $activosReingreso->count() }})</strong>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0" style="font-size:0.82rem;">
            <thead class="table-light">
                <tr>
                    <th>Paciente</th>
                    <th>Subunidad</th>
                    <th class="text-center">Episodio N°</th>
                    <th class="text-center">Ingreso actual</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($activosReingreso as $p)
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $p->nombre }}</div>
                        <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                    </td>
                    <td style="font-size:0.78rem;">{{ $p->ultimoSnapshot?->subunidad ?? '—' }}</td>
                    <td class="text-center">
                        <span class="badge bg-danger">Episodio {{ $p->numero_ingresos }}</span>
                    </td>
                    <td class="text-center">
                        {{ $p->ingreso_uci?->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td>
                        <a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-outline-primary py-0">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Reingresos detectados en el período --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-calendar-range text-primary"></i>
        <span class="fw-semibold">Reingresos detectados en el período</span>
        <span class="badge bg-secondary ms-1">{{ $episodiosEnPeriodo->count() }}</span>
        <span class="ms-auto text-muted" style="font-size:0.78rem;">
            {{ $desde->format('d/m/Y') }} — {{ $hasta->format('d/m/Y') }}
        </span>
    </div>
    <div class="card-body p-0">
        @if($episodiosEnPeriodo->isEmpty())
            <div class="text-center text-muted py-4" style="font-size:0.85rem;">
                <i class="bi bi-check-circle display-6 d-block mb-2 opacity-25"></i>
                No se detectaron reingresos en el período seleccionado.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:0.82rem;">
                <thead class="table-primary">
                    <tr>
                        <th>Paciente</th>
                        <th class="text-center">Ep.</th>
                        <th class="text-center">Ingreso anterior</th>
                        <th class="text-center">Egreso anterior</th>
                        <th class="text-center">Tipo egreso</th>
                        <th class="text-center">Reingreso detectado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($episodiosEnPeriodo as $ep)
                    @php $p = $ep->paciente; @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary">{{ $ep->numero_episodio }}</span>
                            @if($ep->es_reingreso)
                                <span class="badge bg-warning text-dark" style="font-size:0.65rem;">R</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $ep->ingreso_uci?->format('d/m/Y') ?? '—' }}</td>
                        <td class="text-center">{{ $ep->egreso_uci?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-center">
                            @php
                                $color = match($ep->tipo_egreso) {
                                    'fallecimiento' => 'danger',
                                    'mejoria'       => 'success',
                                    'traslado'      => 'warning',
                                    'alta_casa'     => 'info',
                                    default         => 'secondary',
                                };
                            @endphp
                            <span class="badge bg-{{ $color }}" style="font-size:0.7rem;">
                                {{ $ep->tipoEgresoLabel() }}
                            </span>
                        </td>
                        <td class="text-center text-muted" style="font-size:0.75rem;">
                            {{ $ep->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td>
                            <a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-outline-primary py-0">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- Historial completo por paciente --}}
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-people text-primary"></i>
        <span class="fw-semibold">Historial de episodios por paciente</span>
        <span class="badge bg-secondary ms-1">{{ $historialCompleto->count() }} paciente(s)</span>
    </div>
    <div class="card-body p-0">
        @if($historialCompleto->isEmpty())
            <div class="text-center text-muted py-4" style="font-size:0.85rem;">
                Sin reingresos registrados hasta el momento.
            </div>
        @else
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" style="font-size:0.8rem;">
                <thead class="table-light">
                    <tr>
                        <th>Paciente</th>
                        <th class="text-center">Total episodios</th>
                        <th>Episodio 1 (inicial)</th>
                        <th>Episodio 2+</th>
                        <th class="text-center">Estado actual</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($historialCompleto as $p)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-purple" style="background:#6f42c1;font-size:0.8rem;">{{ $p->numero_ingresos }}</span>
                        </td>
                        <td style="font-size:0.75rem;">
                            @php $ep1 = $p->episodios->where('numero_episodio', 1)->first(); @endphp
                            @if($ep1)
                                <div>Ing: {{ $ep1->ingreso_uci?->format('d/m/Y') ?? '—' }}</div>
                                <div class="text-muted">Egr: {{ $ep1->egreso_uci?->format('d/m/Y') ?? '—' }} · {{ $ep1->tipoEgresoLabel() }}</div>
                            @else
                                <span class="text-muted">Sin archivar</span>
                            @endif
                        </td>
                        <td style="font-size:0.75rem;">
                            @foreach($p->episodios->where('numero_episodio', '>', 1) as $ep)
                                <div>Ep.{{ $ep->numero_episodio }}: {{ $ep->ingreso_uci?->format('d/m/Y') ?? '—' }} → {{ $ep->egreso_uci?->format('d/m/Y') ?? '—' }}</div>
                            @endforeach
                        </td>
                        <td class="text-center">
                            @if($p->activo)
                                <span class="badge bg-success">En UCI</span>
                                @if($p->esReingreso())
                                    <span class="badge bg-danger" style="font-size:0.65rem;">Ep.{{ $p->numero_ingresos }}</span>
                                @endif
                            @else
                                <span class="badge bg-secondary">Egresado</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-outline-primary py-0">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

@endif

@endsection
