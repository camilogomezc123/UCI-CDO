@extends('layouts.app')
@section('title', 'Trazador SDRA · ' . $paciente->nombre)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-lungs me-2 text-info"></i>Trazador SDRA</h4>
            <div class="text-muted small">{{ $paciente->nombre }} · {{ $paciente->identificacion ?? $paciente->documento ?? '' }}</div>
        </div>
        <a href="{{ route('trazadores.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif

    <form action="{{ route('trazadores.store', $trazador) }}" method="POST">
        @csrf

        @foreach($modelo['secciones'] ?? [] as $seccion)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold py-2">
                <i class="bi bi-clipboard me-2 text-info"></i>{{ $seccion['titulo'] }}
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($seccion['campos'] ?? [] as $campo)
                    <div class="{{ in_array($campo['tipo'], ['text','number','date']) ? 'col-md-4' : 'col-md-6' }}">
                        <label class="form-label small fw-semibold">{{ $campo['label'] }}</label>
                        @if($campo['tipo'] === 'select')
                        <select name="datos[{{ $seccion['id'] }}][{{ $campo['id'] }}]" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach($catalogos[$campo['catalogo']] ?? [] as $opt)
                            <option value="{{ $opt }}" {{ (($trazador->datos[$seccion['id']][$campo['id']] ?? '') === $opt) ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                        @elseif($campo['tipo'] === 'number')
                        <input type="number" step="0.1" name="datos[{{ $seccion['id'] }}][{{ $campo['id'] }}]"
                               class="form-control form-control-sm"
                               value="{{ $trazador->datos[$seccion['id']][$campo['id']] ?? '' }}">
                        @elseif($campo['tipo'] === 'date')
                        <input type="date" name="datos[{{ $seccion['id'] }}][{{ $campo['id'] }}]"
                               class="form-control form-control-sm"
                               value="{{ $trazador->datos[$seccion['id']][$campo['id']] ?? '' }}">
                        @else
                        <input type="text" name="datos[{{ $seccion['id'] }}][{{ $campo['id'] }}]"
                               class="form-control form-control-sm"
                               value="{{ $trazador->datos[$seccion['id']][$campo['id']] ?? '' }}">
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('trazadores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-save me-1"></i>Guardar trazador SDRA
            </button>
        </div>
    </form>
</div>
@endsection
