@extends('layouts.app')
@section('title', 'Rondas UCI · ' . $fecha->format('d/m/Y'))

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Ronda UCI</h4>
            <small class="text-muted">Registro diario ABCDEF+S · {{ $fecha->format('l d/m/Y') }}</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form class="d-flex gap-2" method="GET">
                <input type="date" name="fecha" class="form-control form-control-sm"
                       value="{{ $fecha->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
                <button class="btn btn-sm btn-outline-secondary">Ver</button>
            </form>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    {{-- Resumen de pendientes --}}
    <div class="row g-2 mb-3">
        @php $pends = [
            ['Sin CAM-UCI',      $pendientes['sin_cam'],      'warning','bi-brain'],
            ['Sin bundle VM',    $pendientes['sin_bundle'],   'warning','bi-lungs'],
            ['Delirium activo',  $pendientes['delirium'],     'danger', 'bi-exclamation-triangle-fill'],
            ['Sin nutrición',    $pendientes['sin_nutricion'],'info',   'bi-egg-fried'],
            ['Sin balance',      $pendientes['sin_balance'],  'secondary','bi-droplet-half'],
            ['Sin GoC',          $pendientes['sin_goc'],      'dark',   'bi-heart-fill'],
        ]; @endphp
        @foreach($pends as [$label,$n,$color,$icon])
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-2 {{ $n > 0 ? 'border-'.$color.' border-start border-3' : '' }}">
                <i class="bi {{ $icon }} text-{{ $color }} d-block"></i>
                <div class="fw-bold {{ $n > 0 ? 'text-'.$color : 'text-muted' }}">{{ $n }}</div>
                <div class="text-muted" style="font-size:0.65rem">{{ $label }}</div>
            </div>
        </div>
        @endforeach
    </div>

    <form action="{{ route('rondas-uci.guardar') }}" method="POST">
        @csrf
        <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">

        @foreach($pacientes as $p)
        @php
            $cam    = $cams[$p->id] ?? null;
            $bundle = $bundles[$p->id] ?? null;
            $bal    = $balances[$p->id] ?? null;
            $nut    = $nuts[$p->id] ?? null;
            $goc    = $gocs[$p->id] ?? null;
            $disp   = $disps[$p->id] ?? collect();
            $atb    = $atbs[$p->id] ?? collect();
            $esVm   = str_contains(strtolower($p->soporte_ventilatorio ?? ''), 'vmi')
                   || str_contains(strtolower($p->soporte_ventilatorio ?? ''), 'mecanic')
                   || str_contains(strtolower($p->soporte_ventilatorio ?? ''), 'invasiv');
        @endphp

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-bold">{{ $p->nombre }}</span>
                    <span class="badge bg-light text-dark border">{{ $p->ubicacion ?? '—' }}</span>
                    @if($p->soporte_ventilatorio)<span class="badge bg-info-subtle text-info">VM: {{ Str::limit($p->soporte_ventilatorio, 20) }}</span>@endif
                    @if($p->soporte_hemodinamico)<span class="badge bg-warning-subtle text-warning">HD: {{ Str::limit($p->soporte_hemodinamico, 20) }}</span>@endif
                    @if($goc)<span class="badge bg-{{ $goc->badgeNivel() }}">{{ $goc->labelNivel() }}</span>@endif
                    @foreach($disp as $d)
                    @php [$tl,$ti,$tc] = \App\Models\Dispositivo::tipos()[$d->tipo] ?? [$d->tipo,'bi-circle','secondary']; @endphp
                    <span class="badge bg-{{ $tc }}-subtle text-{{ $tc }} border border-{{ $tc }}">
                        <i class="bi {{ $ti }} me-1"></i>{{ $tl }} {{ $d->diasDispositivo() }}d
                    </span>
                    @endforeach
                    @foreach($atb as $a)
                    <span class="badge bg-danger-subtle text-danger border border-danger">
                        <i class="bi bi-capsule me-1"></i>{{ $a->antibiotico }} d{{ $a->diasTratamiento() }}
                    </span>
                    @endforeach
                    @if($p->news)<span class="badge bg-secondary">NEWS {{ $p->news }}</span>@endif
                    @if($p->sofa)<span class="badge bg-secondary">SOFA {{ Str::before($p->sofa,' ') }}</span>@endif
                </div>
                @if($p->metas_clinicas)
                <div class="mt-1 small text-muted"><i class="bi bi-target me-1"></i><em>{{ $p->metas_clinicas }}</em></div>
                @endif
            </div>

            <div class="card-body p-2">
                <div class="row g-2">

                    {{-- A: Dolor / CAM (D) en una tarjeta --}}
                    <div class="col-md-4">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <div class="small fw-semibold mb-2">
                                    <span class="badge bg-danger me-1">A</span>Dolor ·
                                    <span class="badge bg-primary">D</span>Delirium
                                </div>
                                <div class="small text-muted mb-1">Actuales: EVA={{ $p->eva ?? '—' }} BPS={{ $p->bps ?? '—' }} RASS={{ $p->rass ?? '—' }}</div>
                                <div class="row g-1">
                                    <div class="col-6">
                                        <label class="form-label small mb-0">CAM-UCI</label>
                                        <select name="cam[{{ $p->id }}][resultado]" class="form-select form-select-sm cam-select" data-pid="{{ $p->id }}">
                                            <option value="">—</option>
                                            <option value="negativo"    {{ $cam?->resultado === 'negativo' ? 'selected':'' }}>Negativo ✓</option>
                                            <option value="positivo"    {{ $cam?->resultado === 'positivo' ? 'selected':'' }}>Positivo ✗</option>
                                            <option value="no_evaluable"{{ $cam?->resultado === 'no_evaluable' ? 'selected':'' }}>No eval.</option>
                                        </select>
                                    </div>
                                    <div class="col-6 cam-subtipo-{{ $p->id }}" style="{{ $cam?->resultado !== 'positivo' ? 'display:none' : '' }}">
                                        <label class="form-label small mb-0">Subtipo</label>
                                        <select name="cam[{{ $p->id }}][subtipo_delirium]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            <option value="hiperactivo" {{ $cam?->subtipo_delirium === 'hiperactivo' ? 'selected':'' }}>Hiperactivo</option>
                                            <option value="hipoactivo"  {{ $cam?->subtipo_delirium === 'hipoactivo' ? 'selected':'' }}>Hipoactivo</option>
                                            <option value="mixto"       {{ $cam?->subtipo_delirium === 'mixto' ? 'selected':'' }}>Mixto</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">RASS momento</label>
                                        <input type="number" name="cam[{{ $p->id }}][rass_momento]" min="-5" max="4" step="1"
                                               class="form-control form-control-sm" value="{{ $cam?->rass_momento }}">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Objetivo RASS</label>
                                        <select name="bundle[{{ $p->id }}][rass_objetivo]" class="form-select form-select-sm">
                                            @foreach(range(-5,2) as $r)<option value="{{ $r }}" {{ ($p->rass_objetivo ?? -2) == $r ? 'selected':'' }}>{{ $r }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- B+C: SAT/SBT + Ventilador --}}
                    <div class="col-md-4">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <div class="small fw-semibold mb-2">
                                    <span class="badge bg-info">B</span>SAT/SBT ·
                                    <span class="badge bg-warning text-dark">C</span>Sedación
                                </div>
                                <div class="row g-1">
                                    @foreach([
                                        ['vacacion_sedacion', 'SAT'],['sbt','SBT'],
                                        ['cabecera_elevada','Cabecera 30-45°'],['higiene_oral','Higiene oral'],
                                        ['profilaxis_tvp','Profilaxis TVP'],['profilaxis_upp','Profilaxis UPP'],
                                    ] as [$campo,$label])
                                    <div class="col-6">
                                        <div class="form-check form-check-sm">
                                            <input class="form-check-input" type="checkbox"
                                                   name="bundle[{{ $p->id }}][{{ $campo }}]" value="1"
                                                   id="{{ $campo }}_{{ $p->id }}"
                                                   {{ $bundle?->$campo ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="{{ $campo }}_{{ $p->id }}">{{ $label }}</label>
                                        </div>
                                    </div>
                                    @endforeach
                                    @if($esVm)
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Resultado SAT</label>
                                        <select name="bundle[{{ $p->id }}][sat_resultado]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach(['exitoso','fallido','contraindicado'] as $opt)
                                            <option {{ $bundle?->sat_resultado === $opt ? 'selected':'' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Resultado SBT</label>
                                        <select name="bundle[{{ $p->id }}][sbt_resultado]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach(['exitoso','fallido','contraindicado'] as $opt)
                                            <option {{ $bundle?->sbt_resultado === $opt ? 'selected':'' }}>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- E+F+S: Movilización + Familia + Sueño --}}
                    <div class="col-md-4">
                        <div class="card border-0 bg-light h-100">
                            <div class="card-body p-2">
                                <div class="small fw-semibold mb-2">
                                    <span class="badge bg-success">E</span>Movilización ·
                                    <span class="badge bg-secondary">F</span>Familia ·
                                    <span class="badge bg-dark">S</span>Sueño
                                </div>
                                <div class="row g-1">
                                    <div class="col-12">
                                        <label class="form-label small mb-0">Nivel movilización (0-4)</label>
                                        <select name="bundle[{{ $p->id }}][nivel_movilizacion]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach(['0:Pasiva en cama','1:Activa en cama','2:Sedestación','3:Bipedestación','4:Deambulación'] as $opt)
                                            @php [$v,$l] = explode(':', $opt); @endphp
                                            <option value="{{ $v }}" {{ $bundle?->nivel_movilizacion == $v ? 'selected':'' }}>{{ $v }} – {{ $l }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="bundle[{{ $p->id }}][familia_involucrada]" value="1"
                                                       {{ $bundle?->familia_involucrada ? 'checked':'' }}>
                                                <label class="form-check-label small">Familia contacto</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="bundle[{{ $p->id }}][familia_reunion_clinica]" value="1"
                                                       {{ $bundle?->familia_reunion_clinica ? 'checked':'' }}>
                                                <label class="form-check-label small">Reunión clínica</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">RCSQ (sueño 0-100)</label>
                                        <input type="number" name="bundle[{{ $p->id }}][rcsq_score]" min="0" max="100"
                                               class="form-control form-control-sm" value="{{ $bundle?->rcsq_score }}" placeholder="0-100">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small mb-0">Interrupciones</label>
                                        <input type="number" name="bundle[{{ $p->id }}][interrupciones_nocturnas]" min="0"
                                               class="form-control form-control-sm" value="{{ $bundle?->interrupciones_nocturnas }}" placeholder="#">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Nutrición --}}
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body p-2">
                                <div class="small fw-semibold mb-1"><i class="bi bi-egg-fried me-1 text-warning"></i>Nutrición</div>
                                <div class="row g-1">
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Vía</label>
                                        <select name="nutricion[{{ $p->id }}][via]" class="form-select form-select-sm">
                                            <option value="">—</option>
                                            @foreach(\App\Models\NutricionDiaria::vias() as $vk => [$vl,$vi,$vc])
                                            <option value="{{ $vk }}" {{ $nut?->via === $vk ? 'selected':'' }}>{{ $vl }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Kcal (aport/meta)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="nutricion[{{ $p->id }}][kcal_aportadas]" class="form-control" value="{{ $nut?->kcal_aportadas }}" placeholder="aport">
                                            <input type="number" name="nutricion[{{ $p->id }}][kcal_meta]" class="form-control" value="{{ $nut?->kcal_meta }}" placeholder="meta">
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Prot g (aport/meta)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="nutricion[{{ $p->id }}][proteinas_g_aportadas]" class="form-control" value="{{ $nut?->proteinas_g_aportadas }}" placeholder="aport">
                                            <input type="number" name="nutricion[{{ $p->id }}][proteinas_g_meta]" class="form-control" value="{{ $nut?->proteinas_g_meta }}" placeholder="meta">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Balance hídrico rápido --}}
                    <div class="col-md-6">
                        <div class="card border-0 bg-light">
                            <div class="card-body p-2">
                                <div class="small fw-semibold mb-1"><i class="bi bi-droplet-half me-1 text-info"></i>Balance hídrico</div>
                                <div class="row g-1">
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Cristaloides mL</label>
                                        <input type="number" name="balance[{{ $p->id }}][vol_cristaloides]" class="form-control form-control-sm" value="{{ $bal?->vol_cristaloides ?? 0 }}" min="0">
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label small mb-0">Diuresis mL</label>
                                        <input type="number" name="balance[{{ $p->id }}][vol_diuresis]" class="form-control form-control-sm" value="{{ $bal?->vol_diuresis ?? 0 }}" min="0">
                                    </div>
                                    <div class="col-4">
                                        @if($bal)
                                        @php $b = $bal->balance(); $bs = $bal->semaforo(); @endphp
                                        <label class="form-label small mb-0">Balance</label>
                                        <div class="badge bg-{{ $bs }} w-100 text-center py-2">{{ $b > 0 ? '+':'' }}{{ number_format($b) }} mL</div>
                                        @endif
                                    </div>
                                    {{-- Campos ocultos para completar balance --}}
                                    @foreach(['vol_coloides','vol_hemoderivados','vol_nutricion','vol_medicamentos','vol_otros_ingresos','vol_drenajes','vol_perdidas_insensibles','vol_otros_egresos'] as $c)
                                    <input type="hidden" name="balance[{{ $p->id }}][{{ $c }}]" value="{{ $bal?->$c ?? 0 }}">
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                </div>{{-- /row --}}
            </div>{{-- /card-body --}}
        </div>{{-- /card paciente --}}
        @endforeach

        @if($pacientes->isNotEmpty())
        <div class="d-flex justify-content-end gap-2 mb-4 position-sticky" style="bottom:1rem">
            <button type="submit" class="btn btn-primary btn-lg shadow">
                <i class="bi bi-save me-2"></i>Guardar ronda completa
            </button>
        </div>
        @endif
    </form>

</div>
@endsection

@push('scripts')
<script>
// Mostrar subtipo de delirium solo cuando resultado = positivo
document.querySelectorAll('.cam-select').forEach(sel => {
    sel.addEventListener('change', function() {
        const pid = this.dataset.pid;
        document.querySelector('.cam-subtipo-'+pid).style.display = this.value === 'positivo' ? '' : 'none';
    });
});
</script>
@endpush
