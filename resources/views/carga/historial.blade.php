@extends('layouts.app')
@section('title', 'Historial de Cargas')
@section('page-title', 'Historial de Cargas de Archivo')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('carga.index') }}" class="btn btn-primary">
        <i class="bi bi-cloud-upload me-2"></i>Nueva carga
    </a>
</div>

{{-- Diagnóstico Barthel (solo aparece cuando se procesa una carga nueva) --}}
@if(session('barthel_debug'))
@foreach(session('barthel_debug') as $info)
<div class="alert alert-info d-flex gap-3 mb-3" style="font-size:0.875rem;">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <div>
        <strong>Diagnóstico Barthel — {{ $info['archivo'] }}</strong><br>
        Columna detectada: <code>{{ $info['columna'] }}</code>
        @if(!empty($info['muestras']))
            &nbsp;· Primeros valores leídos:
            @foreach($info['muestras'] as $m)
                <span class="badge bg-secondary">{{ $m ?: '(vacío)' }}</span>
            @endforeach
        @else
            &nbsp;· <span class="text-warning fw-semibold">La columna Barthel está vacía en todas las filas del archivo.</span>
            Verifique que la columna "BARTHEL" en el Excel tenga datos cargados.
        @endif
    </div>
</div>
@endforeach
@endif

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Archivo</th>
                        <th>Usuario</th>
                        <th class="text-center">Nuevos</th>
                        <th class="text-center">Actualizados</th>
                        <th class="text-center">Omitidos</th>
                        <th>Estado</th>
                        <th class="text-center">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cargas as $carga)
                    <tr>
                        <td style="font-size:0.85rem;">{{ $carga->created_at->format('d/m/Y H:i') }}</td>
                        <td style="font-size:0.85rem;">
                            <i class="bi bi-file-earmark-spreadsheet text-success me-1"></i>
                            {{ $carga->nombre_archivo }}
                        </td>
                        <td style="font-size:0.85rem;">{{ $carga->usuario->name ?? '—' }}</td>
                        <td class="text-center">
                            <span class="badge bg-success rounded-pill">{{ $carga->nuevos }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill">{{ $carga->actualizados }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-secondary rounded-pill">{{ $carga->omitidos }}</span>
                        </td>
                        <td>
                            @if($carga->errores)
                                <span class="badge bg-warning text-dark" title="{{ $carga->errores }}">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Con errores
                                </span>
                            @else
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle me-1"></i>OK
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEliminar"
                                    data-carga-id="{{ $carga->id }}"
                                    data-nombre="{{ $carga->nombre_archivo }}"
                                    data-fecha="{{ $carga->created_at->format('d/m/Y H:i') }}"
                                    data-nuevos="{{ $carga->nuevos }}"
                                    data-actualizados="{{ $carga->actualizados }}"
                                    title="Eliminar esta carga y revertir sus datos">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="bi bi-inbox display-6 d-block mb-2 opacity-25"></i>
                            No hay cargas registradas aún.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($cargas->hasPages())
    <div class="card-footer d-flex justify-content-center">
        {{ $cargas->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- Modal confirmación de eliminación --}}
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalEliminarLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Eliminar carga de archivo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex gap-2 mb-3" style="font-size:0.875rem;">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <div>
                        Esta acción <strong>elimina todos los snapshots</strong> importados en esa carga.
                        Los pacientes que <strong>solo tenían datos de esa carga</strong> serán eliminados.
                        Los pacientes desplazados automáticamente serán restaurados.
                        <strong>No se puede deshacer.</strong>
                    </div>
                </div>
                <table class="table table-sm table-bordered mb-0" style="font-size:0.875rem;">
                    <tr>
                        <th class="bg-light" style="width:140px;">Archivo</th>
                        <td id="modalNombre" class="text-break"></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Fecha carga</th>
                        <td id="modalFecha"></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Pacientes afectados</th>
                        <td id="modalAfectados"></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancelar
                </button>
                <form id="formEliminar" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash3 me-1"></i>Sí, eliminar y revertir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.getElementById('modalEliminar').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;
    document.getElementById('modalNombre').textContent = btn.dataset.nombre;
    document.getElementById('modalFecha').textContent  = btn.dataset.fecha;
    const nuevos = parseInt(btn.dataset.nuevos) || 0;
    const act    = parseInt(btn.dataset.actualizados) || 0;
    document.getElementById('modalAfectados').textContent =
        nuevos + act + ' paciente(s) (' + nuevos + ' nuevos, ' + act + ' actualizados)';
    document.getElementById('formEliminar').action =
        '/carga/' + btn.dataset.cargaId;
});
</script>
@endpush
