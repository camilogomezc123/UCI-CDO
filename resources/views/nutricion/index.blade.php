@extends('layouts.app')
@section('title', 'Nutrición y ATB · UCI')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-egg-fried me-2 text-warning"></i>Nutrición clínica y Antibióticos</h4>
        <form class="d-flex gap-2">
            <input type="date" name="fecha" class="form-control form-control-sm"
                   value="{{ $fecha->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
            <button class="btn btn-sm btn-outline-primary">Ver</button>
        </form>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Tendencia calórica --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-graph-up me-2"></i>Cumplimiento meta calórica — últimos 7 días</div>
        <div class="card-body"><canvas id="chartNut" height="60"></canvas></div>
    </div>

    {{-- Tabs: Nutrición | ATB --}}
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabNut">
            <i class="bi bi-egg-fried me-1"></i>Nutrición</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabAtb">
            <i class="bi bi-capsule me-1"></i>Antibióticos activos
            @php $totalAtb = $atbs->flatten()->count(); @endphp
            @if($totalAtb > 0)<span class="badge bg-danger ms-1">{{ $totalAtb }}</span>@endif
        </a></li>
    </ul>

    <div class="tab-content">
        {{-- Tab Nutrición --}}
        <div class="tab-pane fade show active" id="tabNut">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th>Paciente</th><th>Vía</th><th>kcal</th><th>Proteínas (g)</th><th>% Kcal</th><th>% Prot</th><th>Acción</th></tr>
                            </thead>
                            <tbody>
                                @foreach($pacientes as $p)
                                @php $n = $nutricion[$p->id] ?? null; @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $p->nombre }}</td>
                                    <td>
                                        @if($n?->via)
                                        @php [$vl,$vi,$vc] = \App\Models\NutricionDiaria::vias()[$n->via] ?? [$n->via,'bi-circle','secondary']; @endphp
                                        <span class="badge bg-{{ $vc }}"><i class="bi {{ $vi }} me-1"></i>{{ $vl }}</span>
                                        @else<span class="text-muted">—</span>@endif
                                    </td>
                                    <td>{{ $n ? ($n->kcal_aportadas ?? '—').' / '.($n->kcal_meta ?? '—') : '—' }}</td>
                                    <td>{{ $n ? ($n->proteinas_g_aportadas ?? '—').' / '.($n->proteinas_g_meta ?? '—') : '—' }}</td>
                                    <td>
                                        @if($n?->pctKcal() !== null)
                                        <div class="progress" style="height:16px;min-width:60px">
                                            <div class="progress-bar bg-{{ $n->semaforoKcal() }}" style="width:{{ min(100,$n->pctKcal()) }}%">
                                                {{ $n->pctKcal() }}%
                                            </div>
                                        </div>
                                        @else<span class="text-muted">—</span>@endif
                                    </td>
                                    <td>
                                        @if($n?->pctProteinas() !== null)
                                        <div class="progress" style="height:16px;min-width:60px">
                                            <div class="progress-bar bg-{{ $n->semaforoProteinas() }}" style="width:{{ min(100,$n->pctProteinas()) }}%">
                                                {{ $n->pctProteinas() }}%
                                            </div>
                                        </div>
                                        @else<span class="text-muted">—</span>@endif
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary"
                                            data-bs-toggle="modal" data-bs-target="#modalNut{{ $p->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>

                                {{-- Modal nutrición --}}
                                <div class="modal fade" id="modalNut{{ $p->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form action="{{ route('nutricion.store') }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="paciente_id" value="{{ $p->id }}">
                                            <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                                            <div class="modal-content">
                                                <div class="modal-header"><h5 class="modal-title">Nutrición · {{ $p->nombre }}</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label small fw-semibold">Vía de nutrición</label>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @foreach(\App\Models\NutricionDiaria::vias() as $vk => [$vl,$vi,$vc])
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio" name="via" id="via_{{ $p->id }}_{{ $vk }}" value="{{ $vk }}" {{ $n?->via === $vk ? 'checked' : '' }}>
                                                                <label class="form-check-label small" for="via_{{ $p->id }}_{{ $vk }}"><span class="badge bg-{{ $vc }}"><i class="bi {{ $vi }} me-1"></i>{{ $vl }}</span></label>
                                                            </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="row g-2">
                                                        <div class="col-6"><label class="form-label small">Kcal meta/día</label><input type="number" name="kcal_meta" class="form-control form-control-sm" value="{{ $n?->kcal_meta }}" placeholder="2000"></div>
                                                        <div class="col-6"><label class="form-label small">Kcal aportadas</label><input type="number" name="kcal_aportadas" class="form-control form-control-sm" value="{{ $n?->kcal_aportadas }}" placeholder="1800"></div>
                                                        <div class="col-6"><label class="form-label small">Proteínas meta (g)</label><input type="number" name="proteinas_g_meta" class="form-control form-control-sm" value="{{ $n?->proteinas_g_meta }}" placeholder="80"></div>
                                                        <div class="col-6"><label class="form-label small">Proteínas aportadas (g)</label><input type="number" name="proteinas_g_aportadas" class="form-control form-control-sm" value="{{ $n?->proteinas_g_aportadas }}" placeholder="70"></div>
                                                    </div>
                                                    <div class="mt-2"><label class="form-label small">Motivo suspensión (si aplica)</label><input name="motivo_suspension" class="form-control form-control-sm" value="{{ $n?->motivo_suspension }}" placeholder="Procedimiento, intolerancia..."></div>
                                                    <div class="mt-2"><label class="form-label small">Observaciones</label><textarea name="observaciones" class="form-control form-control-sm" rows="2">{{ $n?->observaciones }}</textarea></div>
                                                </div>
                                                <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tab ATB --}}
        <div class="tab-pane fade" id="tabAtb">
            <div class="d-flex justify-content-end mb-2">
                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#modalNuevoAtb">
                    <i class="bi bi-plus me-1"></i>Agregar antibiótico
                </button>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr><th>Paciente</th><th>ATB</th><th>Foco</th><th>Inicio</th><th>Días</th><th>Cultivo</th><th>PCT</th><th>Acciones</th></tr>
                            </thead>
                            <tbody>
                                @foreach($pacientes as $p)
                                @foreach($atbs[$p->id] ?? [] as $atb)
                                <tr>
                                    <td class="fw-semibold">{{ $p->nombre }}</td>
                                    <td><span class="badge bg-danger">{{ $atb->antibiotico }}</span><div class="text-muted" style="font-size:0.7rem">{{ $atb->dosis }} {{ $atb->via }}</div></td>
                                    <td>{{ $atb->foco ?? '—' }}</td>
                                    <td>{{ $atb->fecha_inicio->format('d/m') }}</td>
                                    <td><span class="badge {{ $atb->diasTratamiento() >= 7 ? 'bg-warning text-dark' : 'bg-light text-dark' }}">{{ $atb->diasTratamiento() }}d</span></td>
                                    <td>
                                        @if($atb->cultivo_disponible)
                                        <span class="badge bg-success"><i class="bi bi-check me-1"></i>Sí</span>
                                        @if($atb->resultado_cultivo)<div class="text-muted" style="font-size:0.7rem">{{ Str::limit($atb->resultado_cultivo,20) }}</div>@endif
                                        @else
                                        <span class="badge bg-secondary">Pendiente</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($atb->pct_inicio !== null)
                                        <div class="small">Inicio: <strong>{{ $atb->pct_inicio }}</strong></div>
                                        @if($atb->pct_control_72h !== null)<div class="small">72h: <strong>{{ $atb->pct_control_72h }}</strong></div>@endif
                                        @else<span class="text-muted">—</span>@endif
                                    </td>
                                    <td>
                                        <form action="{{ route('nutricion.atb.suspender', $atb) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="fecha_fin" value="{{ today()->toDateString() }}">
                                            <button class="btn btn-xs btn-outline-secondary" onclick="return confirm('¿Suspender ATB?')">
                                                <i class="bi bi-x-circle me-1"></i>Suspender
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                                @endforeach
                                @if($atbs->isEmpty())
                                <tr><td colspan="8" class="text-center py-4 text-muted">No hay antibióticos activos.</td></tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal nuevo ATB --}}
<div class="modal fade" id="modalNuevoAtb" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('nutricion.atb.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title"><i class="bi bi-capsule me-2"></i>Nuevo antibiótico</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label small">Paciente</label>
                        <select name="paciente_id" class="form-select form-select-sm" required>
                            <option value="">Seleccionar...</option>
                            @foreach($pacientes as $p)<option value="{{ $p->id }}">{{ $p->nombre }}</option>@endforeach
                        </select></div>
                    <div class="row g-2">
                        <div class="col-8"><label class="form-label small">Antibiótico</label><input name="antibiotico" class="form-control form-control-sm" required placeholder="Meropenem, Vancomicina..."></div>
                        <div class="col-4"><label class="form-label small">Vía</label>
                            <select name="via" class="form-select form-select-sm"><option value="iv">IV</option><option value="oral">Oral</option></select></div>
                        <div class="col-6"><label class="form-label small">Dosis</label><input name="dosis" class="form-control form-control-sm" placeholder="1g c/8h"></div>
                        <div class="col-6"><label class="form-label small">Fecha inicio</label><input type="date" name="fecha_inicio" class="form-control form-control-sm" value="{{ today()->toDateString() }}" required></div>
                        <div class="col-12"><label class="form-label small">Foco</label>
                            <select name="foco" class="form-select form-select-sm">
                                <option value="">—</option>
                                @foreach(\App\Models\AntibioticosUci::focosComunes() as $f)<option>{{ $f }}</option>@endforeach
                            </select></div>
                        <div class="col-6"><label class="form-label small">PCT inicio (ng/mL)</label><input type="number" step="0.01" name="pct_inicio" class="form-control form-control-sm"></div>
                        <div class="col-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="cultivo_disponible" value="1" id="cultCheck"><label class="form-check-label small" for="cultCheck">Cultivo disponible</label></div></div>
                        <div class="col-12"><label class="form-label small">Observaciones</label><textarea name="observaciones" class="form-control form-control-sm" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-danger">Registrar</button></div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
const nt = @json($tendencia);
new Chart(document.getElementById('chartNut'), {
    type: 'bar',
    data: {
        labels: nt.map(d => d.fecha),
        datasets: [{
            label: '% Meta calórica promedio',
            data: nt.map(d => d.pct_avg),
            backgroundColor: nt.map(d => d.pct_avg >= 80 ? 'rgba(25,135,84,0.7)' : d.pct_avg >= 60 ? 'rgba(255,193,7,0.7)' : 'rgba(220,53,69,0.7)'),
        }]
    },
    options: {
        responsive: true,
        scales: { y: { min: 0, max: 110, title: { display: true, text: '%' } } },
        plugins: { legend: { display: false } }
    }
});
</script>
@endpush
@endsection
