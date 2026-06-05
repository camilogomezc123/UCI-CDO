@extends('layouts.app')
@section('title', 'Historial de Cargas')
@section('page-title', 'Historial de Cargas de Archivo')

@section('content')
<div class="d-flex justify-content-end mb-3">
    <a href="{{ route('carga.index') }}" class="btn btn-primary">
        <i class="bi bi-cloud-upload me-2"></i>Nueva carga
    </a>
</div>

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
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
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
@endsection
