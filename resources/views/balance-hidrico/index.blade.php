@extends('layouts.app')
@section('title', 'Balance Hídrico')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><i class="bi bi-droplet-half me-2 text-info"></i>Balance Hídrico UCI</h4>
        <form class="d-flex gap-2 align-items-center">
            <input type="date" name="fecha" class="form-control form-control-sm"
                   value="{{ $fecha->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
            <button class="btn btn-sm btn-outline-primary">Ver</button>
        </form>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Tablero del día --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-table me-2"></i>Balance del día · {{ $fecha->format('d/m/Y') }}
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Paciente</th>
                            <th class="text-center text-success">Ingresos (mL)</th>
                            <th class="text-center text-danger">Egresos (mL)</th>
                            <th class="text-center">Balance día</th>
                            <th class="text-center">Balance acumulado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pacientes as $p)
                        @php
                            $b = $balances[$p->id] ?? null;
                            $acum = $acumulados[$p->id] ?? null;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $p->nombre }}</div>
                                <div class="text-muted" style="font-size:0.7rem">{{ $p->ultimoSnapshot?->ubicacion ?? '—' }}</div>
                            </td>
                            <td class="text-center fw-semibold text-success">
                                {{ $b ? number_format($b->totalIngresos()) : '—' }}
                            </td>
                            <td class="text-center fw-semibold text-danger">
                                {{ $b ? number_format($b->totalEgresos()) : '—' }}
                            </td>
                            <td class="text-center">
                                @if($b)
                                @php $sem = $b->semaforo(); $bal = $b->balance(); @endphp
                                <span class="badge bg-{{ $sem }}">
                                    {{ $bal > 0 ? '+' : '' }}{{ number_format($bal) }} mL
                                </span>
                                @else
                                <span class="text-muted">Sin registro</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($acum !== null)
                                @php $semA = $acum > 1000 ? 'danger' : ($acum > 500 ? 'warning' : 'success'); @endphp
                                <span class="badge bg-{{ $semA }}">
                                    {{ $acum > 0 ? '+' : '' }}{{ number_format($acum) }} mL
                                </span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalBalance{{ $p->id }}">
                                    <i class="bi bi-pencil"></i>
                                    {{ $b ? 'Editar' : 'Registrar' }}
                                </button>
                            </td>
                        </tr>

                        {{-- Modal por paciente --}}
                        <div class="modal fade" id="modalBalance{{ $p->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <form action="{{ route('balance-hidrico.store') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="paciente_id" value="{{ $p->id }}">
                                    <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-droplet-half me-2"></i>Balance · {{ $p->nombre }} · {{ $fecha->format('d/m/Y') }}</h5>
                                            <button class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <h6 class="text-success"><i class="bi bi-arrow-down-circle me-1"></i>Ingresos</h6>
                                                    @foreach([
                                                        ['vol_cristaloides','Cristaloides (SF, RL, DAD...)'],
                                                        ['vol_coloides','Coloides (albúmina...)'],
                                                        ['vol_hemoderivados','Hemoderivados'],
                                                        ['vol_nutricion','Nutrición (enteral/parenteral)'],
                                                        ['vol_medicamentos','Medicamentos (diluciones)'],
                                                        ['vol_otros_ingresos','Otros ingresos'],
                                                    ] as [$campo,$label])
                                                    <div class="mb-2">
                                                        <label class="form-label small">{{ $label }}</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" name="{{ $campo }}" class="form-control balance-ing"
                                                                   min="0" value="{{ $b?->$campo ?? 0 }}">
                                                            <span class="input-group-text">mL</span>
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                    <div class="alert alert-success p-2 small mt-2">Total ingresos: <strong id="tot-ing-{{ $p->id }}">0</strong> mL</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="text-danger"><i class="bi bi-arrow-up-circle me-1"></i>Egresos</h6>
                                                    @foreach([
                                                        ['vol_diuresis','Diuresis'],
                                                        ['vol_drenajes','Drenajes quirúrgicos'],
                                                        ['vol_perdidas_insensibles','Pérdidas insensibles (est.)'],
                                                        ['vol_otros_egresos','Otros egresos'],
                                                    ] as [$campo,$label])
                                                    <div class="mb-2">
                                                        <label class="form-label small">{{ $label }}</label>
                                                        <div class="input-group input-group-sm">
                                                            <input type="number" name="{{ $campo }}" class="form-control balance-egr"
                                                                   min="0" value="{{ $b?->$campo ?? 0 }}">
                                                            <span class="input-group-text">mL</span>
                                                        </div>
                                                    </div>
                                                    @endforeach
                                                    <div class="alert alert-danger p-2 small mt-2">Total egresos: <strong id="tot-egr-{{ $p->id }}">0</strong> mL</div>
                                                    <div class="alert alert-info p-2 small">Balance: <strong id="tot-bal-{{ $p->id }}">0</strong> mL</div>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <label class="form-label small">Observaciones</label>
                                                <textarea name="observaciones" class="form-control form-control-sm" rows="2">{{ $b?->observaciones }}</textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary">Guardar balance</button>
                                        </div>
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
@endsection

@push('scripts')
<script>
function calcBalance(pid) {
    const ing = Array.from(document.querySelectorAll('#modalBalance'+pid+' .balance-ing'))
        .reduce((s,el) => s + (parseInt(el.value)||0), 0);
    const egr = Array.from(document.querySelectorAll('#modalBalance'+pid+' .balance-egr'))
        .reduce((s,el) => s + (parseInt(el.value)||0), 0);
    document.getElementById('tot-ing-'+pid).textContent = ing.toLocaleString();
    document.getElementById('tot-egr-'+pid).textContent = egr.toLocaleString();
    const bal = ing - egr;
    const el = document.getElementById('tot-bal-'+pid);
    el.textContent = (bal > 0 ? '+' : '') + bal.toLocaleString();
    el.parentElement.className = 'alert p-2 small ' +
        (bal > 1000 ? 'alert-danger' : bal > 500 ? 'alert-warning' : 'alert-success');
}
document.querySelectorAll('.balance-ing, .balance-egr').forEach(el => {
    const pid = el.closest('.modal').id.replace('modalBalance','');
    el.addEventListener('input', () => calcBalance(pid));
});
// Inicializar al cargar
document.addEventListener('DOMContentLoaded', () => {
    @foreach($pacientes as $p)
    calcBalance({{ $p->id }});
    @endforeach
});
</script>
@endpush
