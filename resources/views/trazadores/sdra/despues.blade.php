@extends('layouts.app')
@section('title', 'SDRA Seguimiento · ' . $paciente->nombre)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-lungs me-2 text-info"></i>SDRA · Seguimiento 90 días · {{ $paciente->nombre }}</h4>
        <a href="{{ route('trazadores.show', $trazador) }}" class="btn btn-outline-secondary btn-sm">Cancelar</a>
    </div>

    <form action="{{ route('trazadores.despues.store', $trazador) }}" method="POST">
        @csrf
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-calendar-check me-2"></i>Estado del paciente a los 90 días</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Estado vital</label>
                        <select name="datos[encuesta_despues][estado_vital]" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach(['Vivo sin secuelas', 'Vivo con secuelas respiratorias', 'Vivo en rehabilitación', 'Fallecido post-UCI', 'Sin contacto'] as $opt)
                            <option>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">CVF (% predicho) al alta hospitalaria</label>
                        <input type="number" name="datos[encuesta_despues][cvf_pct]" class="form-control form-control-sm" placeholder="0-100">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Barthel al egreso hospital (0-100)</label>
                        <input type="number" name="datos[encuesta_despues][barthel]" class="form-control form-control-sm" min="0" max="100">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Requirió oxígeno domiciliario</label>
                        <select name="datos[encuesta_despues][o2_domiciliario]" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option>Sí</option><option>No</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Retorno al trabajo/actividad habitual</label>
                        <select name="datos[encuesta_despues][retorno_actividad]" class="form-select form-select-sm">
                            <option value="">—</option>
                            <option>Sí, completo</option><option>Sí, parcial</option><option>No</option>
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
                <i class="bi bi-save me-1"></i>Cerrar trazador SDRA
            </button>
        </div>
    </form>
</div>
@endsection
