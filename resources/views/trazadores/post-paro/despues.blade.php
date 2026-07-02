@extends('layouts.app')
@section('title', 'Post-paro Seguimiento · ' . $paciente->nombre)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-heart-pulse me-2 text-danger"></i>Post-paro · Seguimiento neurológico 90 días · {{ $paciente->nombre }}</h4>
        <a href="{{ route('trazadores.show', $trazador) }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
    </div>

    <form action="{{ route('trazadores.despues.store', $trazador) }}" method="POST">
        @csrf
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-check me-2"></i>Evaluación neurológica a los 90 días</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Estado vital</label>
                        <select name="datos[encuesta_despues][estado_vital]" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach(['Vivo sin secuelas neurológicas', 'Vivo con discapacidad leve (CPC 2)', 'Vivo con discapacidad severa (CPC 3)', 'Estado vegetativo (CPC 4)', 'Fallecido post-UCI', 'Sin contacto'] as $opt)
                            <option>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">CPC a 90 días</label>
                        <select name="datos[encuesta_despues][cpc_90d]" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach(['CPC 1 - Sin incapacidad', 'CPC 2 - Incapacidad moderada', 'CPC 3 - Incapacidad severa', 'CPC 4 - Coma/estado vegetativo', 'CPC 5 - Muerte cerebral/fallecido'] as $opt)
                            <option>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">MRS (Modified Rankin Scale) 0-6</label>
                        <input type="number" name="datos[encuesta_despues][mrs]" class="form-control form-control-sm" min="0" max="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Retorno a vida independiente</label>
                        <select name="datos[encuesta_despues][vida_independiente]" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option>Sí, completamente</option><option>Sí, con ayuda parcial</option>
                            <option>No, depende de cuidador</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Secuelas cognitivas reportadas</label>
                        <select name="datos[encuesta_despues][secuelas_cognitivas]" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option>Ninguna</option><option>Leves (memoria/atención)</option>
                            <option>Moderadas</option><option>Severas</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Observaciones</label>
                        <textarea name="datos[encuesta_despues][observaciones]" class="form-control form-control-sm" rows="3"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-warning px-4">
                <i class="bi bi-save me-1"></i>Cerrar trazador Post-paro
            </button>
        </div>
    </form>
</div>
@endsection
