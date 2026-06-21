@extends('layouts.app')
@section('title', 'Capacidad UCI')
@section('page-title', 'Administración de Unidades UCI')
@section('content')
<div class="alert alert-info d-flex gap-2"><i class="bi bi-info-circle-fill"></i><div>Las fechas se aplican a la capacidad, a la ocupación histórica y a las alertas de archivos incompletos. Una unidad inhabilitada no se exigirá desde su fecha de cierre.</div></div>
<div class="card"><div class="card-header"><i class="bi bi-hospital me-2 text-primary"></i>Unidades y camas configuradas</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Unidad</th><th>Camas</th><th>Capacidad</th><th>Habilitada desde</th><th>Inhabilitada desde</th><th></th></tr></thead><tbody>
@foreach($unidades as $unidad)<tr><form method="POST" action="{{ route('unidades-uci.update', $unidad) }}">@csrf @method('PATCH')
<td class="fw-semibold">{{ $unidad->nombre }}</td><td>U{{ $unidad->cama_desde }} – U{{ $unidad->cama_hasta }}</td><td>{{ $unidad->capacidad }}</td>
<td><input class="form-control form-control-sm" type="date" name="habilitada_desde" value="{{ $unidad->habilitada_desde?->format('Y-m-d') }}"></td>
<td><input class="form-control form-control-sm" type="date" name="inhabilitada_desde" value="{{ $unidad->inhabilitada_desde?->format('Y-m-d') }}"></td>
<td><button class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> Guardar</button></td></form></tr>@endforeach
</tbody></table></div></div>
@endsection
