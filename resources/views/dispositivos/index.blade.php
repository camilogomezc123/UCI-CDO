@extends('layouts.app')
@section('title', 'Dispositivos e IAAS')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-plug me-2 text-danger"></i>Dispositivos invasivos e IAAS</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalNuevo">
            <i class="bi bi-plus me-1"></i>Registrar dispositivo
        </button>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        @php $kpiList = [
            ['CVC activos',        $kpis['cvc'],      'danger',   'bi-plug'],
            ['VM activos',         $kpis['vm'],       'info',     'bi-lungs'],
            ['Sondas vesicales',   $kpis['sv'],       'warning',  'bi-droplet-fill'],
            ['IAAS últimos 30d',   $kpis['iaas_30d'], 'dark',     'bi-bacteria'],
        ]; @endphp
        @foreach($kpiList as [$label,$val,$color,$icon])
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <i class="bi {{ $icon }} text-{{ $color }} fs-3 d-block mb-1"></i>
                    <div class="fs-2 fw-bold text-{{ $color }}">{{ $val }}</div>
                    <div class="small text-muted">{{ $label }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Tasas IAAS --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-graph-up me-2"></i>Tasas IAAS (por 1000 días-dispositivo)
        </div>
        <div class="card-body">
            <div class="row g-3 text-center">
                @foreach([['CLABSI','clabsi','CVC','danger'],['CAUTI','cauti','Sonda vesical','warning'],['VAP','vap','VM','info']] as [$nombre,$key,$disp,$color])
                <div class="col-md-4">
                    <div class="fs-4 fw-bold text-{{ $color }}">{{ $tasas[$key] }}</div>
                    <div class="small text-muted">{{ $nombre }} / 1000 días-{{ $disp }}</div>
                    <div class="small {{ $tasas[$key] == 0 ? 'text-success' : 'text-danger' }}">
                        Meta: 0 · {{ $tasas[$key] == 0 ? 'OK' : 'Alerta' }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="d-flex gap-2 mb-3 flex-wrap">
        @foreach(['activos'=>'Activos','iaas'=>'Con IAAS','todos'=>'Todos'] as $val => $label)
        <a href="{{ route('dispositivos.index', ['filtro'=>$val,'tipo'=>$tipo]) }}"
           class="btn btn-sm {{ $filtro === $val ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $label }}</a>
        @endforeach
        <span class="ms-2 border-start ps-2">Tipo:</span>
        @foreach(\App\Models\Dispositivo::tipos() as $tk => [$tl,$ti,$tc])
        <a href="{{ route('dispositivos.index', ['filtro'=>$filtro,'tipo'=>$tk]) }}"
           class="btn btn-sm {{ $tipo === $tk ? 'btn-'.$tc : 'btn-outline-'.$tc }}">{{ $tl }}</a>
        @endforeach
        @if($tipo)
        <a href="{{ route('dispositivos.index', ['filtro'=>$filtro]) }}" class="btn btn-sm btn-light">Limpiar</a>
        @endif
    </div>

    {{-- Tabla --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Paciente</th><th>Tipo</th><th>Sitio / vía</th>
                            <th>Inicio</th><th>Días</th><th>IAAS</th><th>Estado</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dispositivos as $d)
                        @php [$tl,$ti,$tc] = \App\Models\Dispositivo::tipos()[$d->tipo] ?? [$d->tipo,'bi-circle','secondary']; @endphp
                        <tr>
                            <td class="fw-semibold">{{ $d->paciente->nombre }}</td>
                            <td><span class="badge bg-{{ $tc }}"><i class="bi {{ $ti }} me-1"></i>{{ $tl }}</span></td>
                            <td>{{ $d->sitio_insercion ?? $d->via_acceso ?? '—' }}</td>
                            <td>{{ $d->fecha_inicio->format('d/m/Y') }}</td>
                            <td><span class="badge bg-light text-dark">{{ $d->diasDispositivo() }}d</span></td>
                            <td>
                                @if($d->evento_iaas)
                                <span class="badge bg-danger">{{ $d->tipo_iaas }}</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($d->activo)
                                <span class="badge bg-success">Activo</span>
                                @else
                                <span class="badge bg-secondary">Retirado {{ $d->fecha_retiro?->format('d/m') }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    @if($d->activo)
                                    <button class="btn btn-outline-secondary" title="Retirar"
                                        onclick="retirar({{ $d->id }})"><i class="bi bi-x-circle"></i></button>
                                    <form id="f-ret-{{ $d->id }}" action="{{ route('dispositivos.retirar', $d) }}" method="POST" class="d-none">@csrf @method('PATCH')<input type="hidden" name="fecha_retiro" value="{{ today()->toDateString() }}"></form>
                                    @endif
                                    @if(!$d->evento_iaas)
                                    <button class="btn btn-outline-danger btn-sm" title="Registrar IAAS"
                                        data-bs-toggle="modal" data-bs-target="#modalIaas{{ $d->id }}">
                                        <i class="bi bi-bacteria"></i>
                                    </button>
                                    @endif
                                </div>
                                {{-- Modal IAAS --}}
                                <div class="modal fade" id="modalIaas{{ $d->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form action="{{ route('dispositivos.iaas', $d) }}" method="POST">
                                            @csrf @method('PATCH')
                                            <div class="modal-content">
                                                <div class="modal-header"><h5 class="modal-title">Registrar IAAS · {{ $tl }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                                                <div class="modal-body">
                                                    <div class="mb-2"><label class="form-label small">Tipo IAAS</label>
                                                        <select name="tipo_iaas" class="form-select form-select-sm" required>
                                                            @foreach(\App\Models\Dispositivo::tiposIaas() as $ti2)<option>{{ $ti2 }}</option>@endforeach
                                                        </select></div>
                                                    <div class="mb-2"><label class="form-label small">Organismo</label><input name="organismo" class="form-control form-control-sm" placeholder="Ej: E. coli, S. aureus"></div>
                                                    <div class="mb-2"><label class="form-label small">Sensibilidad</label>
                                                        <select name="sensibilidad" class="form-select form-select-sm">
                                                            <option value="">—</option><option>sensible</option><option>resistente</option><option>MDR</option>
                                                        </select></div>
                                                    <div class="mb-2"><label class="form-label small">Observaciones</label><textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea></div>
                                                </div>
                                                <div class="modal-footer"><button class="btn btn-danger">Registrar evento</button></div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="8" class="text-center py-4 text-muted">No hay dispositivos para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $dispositivos->links() }}</div>
        </div>
    </div>
</div>

{{-- Modal nuevo dispositivo --}}
<div class="modal fade" id="modalNuevo" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('dispositivos.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-plug me-2"></i>Nuevo dispositivo</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label">Paciente</label>
                        <select name="paciente_id" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            @foreach($pacientes as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
                        </select></div>
                    <div class="mb-3"><label class="form-label">Tipo de dispositivo</label>
                        <select name="tipo" class="form-select" required>
                            @foreach(\App\Models\Dispositivo::tipos() as $tk => [$tl,$ti,$tc])<option value="{{ $tk }}">{{ $tl }}</option>@endforeach
                        </select></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Fecha inicio</label><input type="date" name="fecha_inicio" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required></div>
                        <div class="col-6"><label class="form-label small">Sitio inserción</label><input name="sitio_insercion" class="form-control form-control-sm" placeholder="Ej: yugular derecha"></div>
                    </div>
                    <div class="mt-2"><label class="form-label small">Observaciones</label><textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function retirar(id) {
    if (confirm('¿Registrar retiro del dispositivo hoy?')) document.getElementById('f-ret-'+id).submit();
}
</script>
@endpush
@endsection
