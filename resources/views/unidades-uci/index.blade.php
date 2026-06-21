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
  $estadoClase = $cerrada ? 'unidad-cerrada' : (count($camasCerradas) ? 'unidad-parcial' : 'unidad-habilitada');
  $cierresActivos = $unidad->indisponibilidades->filter(fn($i) => $i->estaActivaEn(today()));
@endphp
<div class="accordion-item mb-3 border rounded-3 overflow-hidden unidad-card {{ $estadoClase }}">
 <h2 class="accordion-header"><button class="accordion-button collapsed unidad-toggle" data-bs-toggle="collapse" data-bs-target="#unidad{{ $unidad->id }}">
   <div class="unidad-resumen w-100">
     <div class="unidad-identidad"><span class="fw-bold">{{ $unidad->nombre }}</span><span class="badge bg-{{ $color }} mt-1">{{ $estado }}</span></div>
     <div class="unidad-meta"><span class="unidad-meta-label">Camas</span><span>U{{ $unidad->cama_desde }}–U{{ $unidad->cama_hasta }}</span></div>
     <div class="unidad-meta"><span class="unidad-meta-label">Disponibles</span><strong>{{ $disponibles }}/{{ $unidad->capacidad }}</strong></div>
     <div class="unidad-meta {{ $cerrada ? '' : 'text-muted' }}"><span class="unidad-meta-label">Estado desde</span><span>{{ $cerrada ? $cierresActivos->first()?->inhabilitada_desde?->format('d/m/Y') : 'Operativa' }}</span></div>
   </div>
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
@push('styles')<style>
.unidad-card { box-shadow:0 2px 10px rgba(0,0,0,.05); }
.unidad-card.unidad-habilitada { border-left:5px solid #198754!important; }
.unidad-card.unidad-parcial { border-left:5px solid #ffc107!important; }
.unidad-card.unidad-cerrada { border-left:5px solid #6c757d!important; }
.unidad-toggle { padding:1rem 3rem 1rem 1.1rem; background:#fff; }
.unidad-cerrada .unidad-toggle { background:#f2f3f5; }
.unidad-parcial .unidad-toggle { background:#fffdf3; }
.unidad-resumen { display:grid; grid-template-columns:minmax(210px,1.7fr) minmax(110px,.8fr) minmax(120px,.8fr) minmax(135px,1fr); gap:1rem; align-items:center; }
.unidad-identidad { display:flex; flex-direction:column; align-items:flex-start; font-size:1rem; }
.unidad-meta { display:flex; flex-direction:column; font-size:.88rem; }
.unidad-meta-label { color:#6c757d; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; margin-bottom:.15rem; }
@media (max-width: 768px) { .unidad-resumen { grid-template-columns:1fr 1fr; gap:.75rem; } }
</style>@endpush
@endsection
