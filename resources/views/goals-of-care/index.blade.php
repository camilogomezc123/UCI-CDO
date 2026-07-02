@extends('layouts.app')
@section('title', 'Goals of Care / LET')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-heart-fill me-2 text-secondary"></i>Goals of Care · Limitación del Esfuerzo Terapéutico</h4>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3 mb-4">
        {{-- Sin GoC --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-exclamation-circle me-2 text-warning"></i>Sin conversación GoC registrada
                    <span class="badge bg-warning text-dark ms-2">{{ $sinGoc->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse($sinGoc as $p)
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <div>
                            <div class="fw-semibold small">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.7rem">
                                UCI desde {{ $p->ingreso_uci?->format('d/m/Y') ?? '—' }}
                                @if($p->ultimoSnapshot?->soporte_ventilatorio)
                                · <span class="badge bg-info-subtle text-info">VM</span>
                                @endif
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#modalGoc{{ $p->id }}">
                            <i class="bi bi-plus me-1"></i>Registrar
                        </button>
                    </div>
                    @empty
                    <div class="text-center py-4 text-success">
                        <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                        Todos los pacientes tienen GoC registrado
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Con GoC --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-check-circle me-2 text-success"></i>Con GoC vigente
                    <span class="badge bg-success ms-2">{{ $conGoc->count() }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse($conGoc as $p)
                    @php $goc = $p->goalOfCare; @endphp
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <div>
                            <div class="fw-semibold small">{{ $p->nombre }}</div>
                            <div class="mt-1">
                                <span class="badge bg-{{ $goc->badgeNivel() }}">
                                    <i class="bi {{ $goc->iconNivel() }} me-1"></i>{{ $goc->labelNivel() }}
                                </span>
                                @if($goc->dnr)<span class="badge bg-dark ms-1">DNR</span>@endif
                                @if($goc->tiempo_limitado_hasta)
                                <span class="badge bg-warning text-dark ms-1">
                                    <i class="bi bi-clock me-1"></i>Hasta {{ $goc->tiempo_limitado_hasta->format('d/m/Y') }}
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-muted" style="font-size:0.7rem">{{ $goc->fecha_conversacion->format('d/m/Y') }}</div>
                            <button class="btn btn-xs btn-outline-secondary mt-1"
                                data-bs-toggle="modal" data-bs-target="#modalUpdate{{ $goc->id }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4 text-muted">Sin pacientes con GoC registrado.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- Histórico --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-clock-history me-2"></i>Historial de conversaciones GoC (últimas 30)
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr><th>Paciente</th><th>Fecha</th><th>Nivel</th><th>DNR</th><th>Participaron</th><th>Plan</th></tr>
                    </thead>
                    <tbody>
                        @forelse($historico as $goc)
                        <tr>
                            <td class="fw-semibold">{{ $goc->paciente->nombre }}</td>
                            <td>{{ $goc->fecha_conversacion->format('d/m/Y') }}</td>
                            <td><span class="badge bg-{{ $goc->badgeNivel() }}">{{ $goc->labelNivel() }}</span></td>
                            <td>{{ $goc->dnr ? '✓' : '—' }}</td>
                            <td>{{ Str::limit($goc->quien_participo ?? '—', 40) }}</td>
                            <td>{{ Str::limit($goc->plan_cuidados ?? '—', 50) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="text-center py-4 text-muted">Sin registros históricos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modales de registro y edición --}}
@foreach($pacientes as $p)
<div class="modal fade" id="modalGoc{{ $p->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('goals-of-care.store') }}" method="POST">
            @csrf
            <input type="hidden" name="paciente_id" value="{{ $p->id }}">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-heart-fill me-2"></i>GoC · {{ $p->nombre }}</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('goals-of-care._form', ['goc' => null, 'niveles' => $niveles])
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Guardar conversación</button></div>
            </div>
        </form>
    </div>
</div>
@endforeach

@foreach($conGoc as $p)
@php $goc = $p->goalOfCare; @endphp
<div class="modal fade" id="modalUpdate{{ $goc->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('goals-of-care.update', $goc) }}" method="POST">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Actualizar GoC · {{ $p->nombre }}</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @include('goals-of-care._form', ['goc' => $goc, 'niveles' => $niveles])
                </div>
                <div class="modal-footer"><button class="btn btn-warning">Actualizar</button></div>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
