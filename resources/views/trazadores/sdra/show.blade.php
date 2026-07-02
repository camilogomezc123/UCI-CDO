@extends('layouts.app')
@section('title', 'SDRA · ' . $paciente->nombre)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-lungs me-2 text-info"></i>Trazador SDRA · {{ $paciente->nombre }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('trazadores.edit', $trazador) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil me-1"></i>Editar
            </a>
            <a href="{{ route('trazadores.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    @foreach($modelo['secciones'] ?? [] as $seccion)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-clipboard me-2 text-info"></i>{{ $seccion['titulo'] }}</div>
        <div class="card-body">
            <div class="row g-2">
                @foreach($seccion['campos'] ?? [] as $campo)
                @php $val = $trazador->datos[$seccion['id']][$campo['id']] ?? null; @endphp
                <div class="col-md-4">
                    <div class="small text-muted">{{ $campo['label'] }}</div>
                    <div class="fw-semibold">{{ $val ?? '—' }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endforeach

    @if($trazador->estado === 'PENDIENTE_DESPUES' || $trazador->estado === 'CERRADO')
    <div class="d-flex justify-content-end mb-3">
        @if($trazador->estado === 'PENDIENTE_DESPUES')
        <a href="{{ route('trazadores.despues.edit', $trazador) }}" class="btn btn-warning">
            <i class="bi bi-clipboard-check me-1"></i>Encuesta de seguimiento (90 días)
        </a>
        @endif
    </div>
    @endif
</div>
@endsection
