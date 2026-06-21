@extends('layouts.app')
@section('title', 'Capacidad UCI')
@section('page-title', 'Administración de Unidades UCI')
@section('content')
<div class="alert alert-info d-flex gap-2"><i class="bi bi-info-circle-fill"></i><div>La capacidad y ocupación se calculan con las camas habilitadas en cada fecha. Despliega una unidad para gestionar sus camas.</div></div>
<div class="accordion" id="unidadesAccordion">
@foreach($unidades as $unidad)
@php
  $cerrada = !$unidad->estaHabilitadaEn(today()); $camasCerradas = $unidad->camasInhabilitadasEn(today());
  $disponibles = $unidad->capacidadDisponibleEn(today());
  $estado = $cerrada ? 'Inhabilitada' : (count($camasCerradas) ? 'Habilitada parcial' : 'Habilitada');
  $color = $cerrada ? 'secondary' : (count($camasCerradas) ? 'warning' : 'success');
  $cierresActivos = $unidad->indisponibilidades->filter(fn($i) => $i->estaActivaEn(today()));
@endphp
<div class="accordion-item mb-2 border rounded-3 overflow-hidden {{ $cerrada ? 'opacity-75' : '' }}">
 <h2 class="accordion-header"><button class="accordion-button collapsed {{ $cerrada ? 'bg-light' : '' }}" data-bs-toggle="collapse" data-bs-target="#unidad{{ $unidad->id }}">
   <div class="w-100 d-flex align-items-center gap-3 flex-wrap"><span class="fw-bold">{{ $unidad->nombre }}</span><span class="badge bg-{{ $color }}">{{ $estado }}</span><span class="text-muted small">U{{ $unidad->cama_desde }}–U{{ $unidad->cama_hasta }}</span><span class="ms-md-auto fw-semibold">{{ $disponibles }}/{{ $unidad->capacidad }} camas habilitadas</span>
   @if($cerrada)<span class="text-muted small">Inhabilitada desde {{ $cierresActivos->first()?->inhabilitada_desde?->format('d/m/Y') }}</span>@endif</div>
 </button></h2>
 <div id="unidad{{ $unidad->id }}" class="accordion-collapse collapse" data-bs-parent="#unidadesAccordion"><div class="accordion-body bg-light">
   <div class="row g-3"><div class="col-lg-8"><div class="row g-2">
   @for($cama=$unidad->cama_desde; $cama<=$unidad->cama_hasta; $cama++)
    @php $cierre=$cierresActivos->first(fn($i) => $i->numero_cama === $cama); @endphp
    <div class="col-6 col-sm-4 col-md-3"><div class="border rounded p-2 h-100 {{ $cierre ? 'bg-secondary text-white' : 'bg-white' }}"><div class="fw-bold">U{{ $cama }}</div>
    @if($cierre)<small class="d-block">{{ $cierre->motivo }}</small><small>{{ $cierre->inhabilitada_desde->format('d/m/Y') }}</small><form method="POST" action="{{ route('unidades-uci.habilitar', $cierre) }}" class="mt-1">@csrf @method('PATCH')<button class="btn btn-sm btn-light w-100">Habilitar</button></form>
    @else <button class="btn btn-sm btn-outline-warning mt-1 w-100" data-bs-toggle="modal" data-bs-target="#modalCama" data-unidad="{{ $unidad->id }}" data-cama="{{ $cama }}">Inhabilitar</button>@endif
    </div></div>
   @endfor
   </div></div><div class="col-lg-4"><div class="card shadow-none"><div class="card-body"><h6>Cerrar unidad completa</h6><p class="small text-muted">La capacidad será 0 desde la fecha indicada.</p><form method="POST" action="{{ route('unidades-uci.inhabilitar', $unidad) }}">@csrf<input type="hidden" name="numero_cama"><div class="mb-2"><input class="form-control form-control-sm" type="date" name="inhabilitada_desde" value="{{ today()->format('Y-m-d') }}" required></div><div class="mb-2"><textarea class="form-control form-control-sm" name="motivo" rows="2" placeholder="Razón del cierre" required></textarea></div><button class="btn btn-sm btn-outline-danger w-100">Inhabilitar unidad</button></form></div></div></div></div>
 </div></div></div>
</div>
@endforeach
</div>
<div class="modal fade" id="modalCama" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="POST" id="formCama">@csrf<div class="modal-header"><h5 class="modal-title">Inhabilitar cama <span id="nombreCama"></span></h5><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="numero_cama" id="numeroCama"><div class="mb-3"><label class="form-label">Desde</label><input class="form-control" type="date" name="inhabilitada_desde" value="{{ today()->format('Y-m-d') }}" required></div><div><label class="form-label">Razón</label><textarea class="form-control" name="motivo" required maxlength="500" placeholder="Ej. reparación de monitor"></textarea></div></div><div class="modal-footer"><button class="btn btn-warning">Inhabilitar cama</button></div></form></div></div></div>
@push('scripts')<script>document.getElementById('modalCama').addEventListener('show.bs.modal',e=>{const b=e.relatedTarget;document.getElementById('numeroCama').value=b.dataset.cama;document.getElementById('nombreCama').textContent='U'+b.dataset.cama;document.getElementById('formCama').action='/administracion/unidades-uci/'+b.dataset.unidad+'/inhabilitar';});</script>@endpush
@endsection
