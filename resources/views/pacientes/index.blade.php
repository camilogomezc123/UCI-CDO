@extends('layouts.app')
@section('title', 'Pacientes')
@section('page-title', 'Pacientes Activos en UCI')

@push('styles')
<style>
    .pacientes-activos-table .col-expandible { display: none; }
    body.sidebar-collapsed .pacientes-activos-table .col-expandible { display: table-cell; }
    body.sidebar-collapsed .pacientes-activos-table .ubicacion-subunidad,
    body.sidebar-collapsed .pacientes-activos-table .estancia-ingreso { display: none; }
    .pacientes-activos-table .acciones-paciente { display: flex; flex-direction: column; gap: .3rem; }
    .pacientes-activos-table .acciones-paciente .btn { width: 2rem; }
</style>
@endpush

@section('content')
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('pacientes.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:0.8rem;">Buscar paciente</label>
                <input type="text" name="busqueda" value="{{ request('busqueda') }}" class="form-control form-control-sm" placeholder="Nombre o documento...">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:0.8rem;">Subunidad</label>
                <select name="subunidad" class="form-select form-select-sm">
                    <option value="">Todas las subunidades</option>
                    @foreach($subunidades as $sub)
                        <option value="{{ $sub }}" {{ request('subunidad') == $sub ? 'selected' : '' }}>{{ $sub }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:0.8rem;">Criterio</label>
                <select name="criterio" class="form-select form-select-sm">
                    <option value="">Todos los criterios</option>
                    @foreach($criterios as $c)
                        <option value="{{ $c }}" {{ request('criterio') == $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:0.8rem;">CIE-10</label>
                <input type="text" name="cie10" value="{{ request('cie10') }}" class="form-control form-control-sm" placeholder="Ej: J96, K35...">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-fill">
                    <i class="bi bi-search"></i>
                </button>
                <a href="{{ route('pacientes.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

            {{-- Filtros rápidos --}}
            <div class="col-12 d-flex gap-2 flex-wrap">
                <a href="{{ route('pacientes.index', ['filtro'=>'pendiente_egreso']) }}"
                   class="btn btn-sm {{ request('filtro') == 'pendiente_egreso' ? 'btn-danger' : 'btn-outline-danger' }}">
                    <i class="bi bi-hourglass-split me-1"></i>Pendiente egreso
                </a>
                <a href="{{ route('pacientes.index', ['filtro'=>'sin_ingreso']) }}"
                   class="btn btn-sm {{ request('filtro') == 'sin_ingreso' ? 'btn-warning' : 'btn-outline-warning' }}">
                    <i class="bi bi-clock me-1"></i>Sin fecha ingreso UCI
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-people me-2 text-primary"></i>{{ $pacientes->total() }} paciente(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 pacientes-activos-table">
                <thead>
                    <tr>
                        <th title="Cama y subunidad">Ubicación</th>
                        <th class="col-expandible">Subunidad</th>
                        <th>Paciente</th>
                        <th title="UCI, UCIN/intermedio o hospitalización/traslado">Clasificación</th>
                        <th title="Soporte ventilatorio y hemodinámico">Soportes</th>
                        <th title="Fecha de ingreso y tiempo transcurrido">Estancia UCI</th>
                        <th class="col-expandible">Ingreso UCI</th>
                        <th title="Estado de egreso y resultado CAM-UCI de hoy">Seguimiento</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pacientes as $p)
                    <tr>
                        <td class="text-nowrap">
                            <span class="badge bg-secondary rounded-pill" style="font-size:0.8rem;">{{ $p->ubicacion ?? '—' }}</span>
                            <div class="text-muted mt-1 ubicacion-subunidad" style="font-size:0.68rem;">{{ $p->subunidad ?? '—' }}</div>
                        </td>
                        <td class="col-expandible" style="font-size:0.76rem;">{{ $p->subunidad ?? '—' }}</td>
                        <td>
                            <div class="fw-semibold" style="font-size:0.875rem;">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                        </td>
                        <td>
                            @php
                                $criterio = $p->criterio_atencion ?? '';
                                $clasificacion = $p->salida_hospitalizacion && !$p->egreso_uci ? 'traslado'
                                    : (str_contains(strtoupper($criterio), 'INTERMEDIO') || $p->subunidad === 'UCIN' ? 'ucin' : 'uci');
                                $cls = match($clasificacion) {
                                    'uci' => 'criterio-intensivo', 'ucin' => 'criterio-intermedio', default => 'criterio-traslado',
                                };
                            @endphp
                            <span class="badge badge-criterio {{ $cls }}">
                                {{ match($clasificacion) {
                                    'uci' => 'UCI', 'ucin' => 'UCIN / Interm.', default => 'Hosp. / Traslado',
                                } }}
                            </span>
                        </td>
                        <td style="font-size:0.72rem;max-width:145px;">
                            @if($p->soporte_ventilatorio)
                                <span class="badge bg-info text-dark me-1" title="Ventilatorio">{{ $p->soporte_ventilatorio }}</span>
                            @endif
                            @if($p->soporte_hemodinamico)
                                <span class="badge bg-danger me-1" title="Hemodinámico">{{ $p->soporte_hemodinamico }}</span>
                            @endif
                        </td>
                        <td style="font-size:0.76rem;" class="text-nowrap">
                            @if($p->ingreso_uci)
                                <div class="estancia-ingreso">{{ $p->ingreso_uci->format('d/m/y H:i') }}</div>
                                <div class="tiempo-uci">{{ $p->tiempoEnUciTexto() }}</div>
                            @else
                                <span class="text-warning fw-semibold" style="font-size:0.75rem;">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Sin registrar
                                </span>
                            @endif
                        </td>
                        <td class="col-expandible text-nowrap" style="font-size:0.76rem;">
                            {{ $p->ingreso_uci?->format('d/m/Y H:i') ?? 'Sin registrar' }}
                        </td>
                        <td>
                            @if($p->egreso_uci)
                                <span class="badge bg-success">Egresado</span>
                            @elseif($p->salida_hospitalizacion)
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-hourglass-split me-1"></i>Esp. egreso
                                </span>
                                <div class="tiempo-espera" style="font-size:0.72rem;">{{ $p->tiempoEsperaHospitalizacion() }}</div>
                            @else
                                <span class="badge bg-secondary">En UCI</span>
                            @endif
                            <div class="mt-1">
                            @php $cam = $camHoy[$p->id] ?? null; @endphp
                            @if($cam === 'positivo')
                                <span class="badge bg-danger" style="font-size:0.7rem;" title="Delirium presente">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>POSITIVO
                                </span>
                            @elseif($cam === 'negativo')
                                <span class="badge bg-success" style="font-size:0.7rem;" title="Sin delirium">
                                    <i class="bi bi-check-circle-fill me-1"></i>NEGATIVO
                                </span>
                            @elseif($cam === 'no_evaluable')
                                <span class="badge bg-secondary" style="font-size:0.7rem;" title="RASS ≤ -3">
                                    <i class="bi bi-dash-circle me-1"></i>No eval.
                                </span>
                            @else
                                <span class="text-muted" style="font-size:0.72rem;">
                                    <i class="bi bi-clock me-1"></i>Pendiente
                                </span>
                            @endif
                            </div>
                        </td>
                        <td>
                            <div class="acciones-paciente">
                            <a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                            @if(!$p->trazadorSepsis || $p->trazadorSepsis->estado === 'CERRADO')
                            <form method="POST" action="{{ route('trazadores.marcar', $p) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="tipo_trazador" value="sepsis">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Marcar como trazador Sepsis">
                                    <i class="bi bi-clipboard2-pulse"></i>
                                </button>
                            </form>
                            @else
                            <a href="{{ route('trazadores.edit', $p->trazadorSepsis) }}"
                               class="btn btn-sm btn-danger" title="Trazador Sepsis abierto">
                                <i class="bi bi-clipboard2-pulse"></i>
                            </a>
                            @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-people display-6 d-block mb-2 opacity-25"></i>
                            No hay pacientes que coincidan con el filtro.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($pacientes->hasPages())
    <div class="card-footer d-flex justify-content-center">
        {{ $pacientes->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>
@endsection
