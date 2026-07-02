@extends('layouts.app')
@section('title', 'Rondas UCI · ' . $fecha->format('d/m/Y'))
@section('page-title', 'Ronda UCI · ' . $fecha->format('d/m/Y'))

@push('styles')
<style>
    .ronda-card { border-left: 4px solid #dee2e6; }
    .ronda-card.delirium { border-left-color: #dc3545; }
    .ronda-card.sin-goc  { border-left-color: #ffc107; }
    .bloque-label {
        font-size: 0.67rem; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; color: #888; margin-bottom: 0.25rem;
    }
    .input-vol { width: 80px; }
    .balance-total { font-size: 1rem; font-weight: 700; min-width: 90px; text-align: center; }
    .campo-condicional { display: none; }
</style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-clipboard-heart me-2 text-primary"></i>Ronda UCI</h4>
            <small class="text-muted">Registro diario ABCDEF+S · {{ $fecha->translatedFormat('l d/m/Y') }}</small>
        </div>
        <form class="d-flex gap-2" method="GET">
            <input type="date" name="fecha" class="form-control form-control-sm"
                   value="{{ $fecha->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
            <button class="btn btn-sm btn-outline-secondary">Ver</button>
        </form>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show py-2">
        <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- Resumen de pendientes --}}
    <div class="row g-2 mb-3">
        @php $pends = [
            ['Sin CAM-UCI',      $pendientes['sin_cam'],       'warning',  'bi-brain'],
            ['Sin bundle VM',    $pendientes['sin_bundle'],    'warning',  'bi-lungs'],
            ['Delirium activo',  $pendientes['delirium'],      'danger',   'bi-exclamation-triangle-fill'],
            ['Sin nutrición',    $pendientes['sin_nutricion'], 'info',     'bi-egg-fried'],
            ['Sin balance',      $pendientes['sin_balance'],   'secondary','bi-droplet-half'],
            ['Sin GoC',          $pendientes['sin_goc'],       'dark',     'bi-heart-fill'],
        ]; @endphp
        @foreach($pends as [$label, $n, $color, $icon])
        <div class="col">
            <div class="card border-0 shadow-sm text-center py-2 {{ $n > 0 ? 'border-'.$color.' border-start border-3' : '' }}">
                <i class="bi {{ $icon }} text-{{ $color }} d-block mb-1"></i>
                <div class="fw-bold {{ $n > 0 ? 'text-'.$color : 'text-muted' }}">{{ $n }}</div>
                <div class="text-muted" style="font-size:0.65rem">{{ $label }}</div>
            </div>
        </div>
        @endforeach
    </div>

    <form action="{{ route('rondas-uci.guardar') }}" method="POST">
        @csrf
        <input type="hidden" name="fecha" value="{{ $fecha->toDateString() }}">

        @forelse($pacientes as $p)
        @php
            $cam    = $cams[$p->id]    ?? null;
            $bundle = $bundles[$p->id] ?? null;
            $bal    = $balances[$p->id]?? null;
            $nut    = $nuts[$p->id]    ?? null;
            $goc    = $gocs[$p->id]    ?? null;
            $disp   = $disps[$p->id]   ?? collect();
            $atb    = $atbs[$p->id]    ?? collect();
            $esVm   = str_contains(strtolower($p->soporte_ventilatorio ?? ''), 'vmi')
                   || str_contains(strtolower($p->soporte_ventilatorio ?? ''), 'mecanic')
                   || str_contains(strtolower($p->soporte_ventilatorio ?? ''), 'invasiv');
            $tieneDelirium = $cam?->resultado === 'positivo';
        @endphp

        <div class="card border-0 shadow-sm mb-3 ronda-card {{ $tieneDelirium ? 'delirium' : ($goc === null ? 'sin-goc' : '') }}">

            {{-- Header del paciente --}}
            <div class="card-header bg-white py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="{{ route('pacientes.show', $p) }}" class="fw-bold text-decoration-none text-dark">{{ $p->nombre }}</a>
                    <span class="badge bg-light text-dark border" style="font-size:0.7rem;">{{ $p->ubicacion ?? '—' }}</span>
                    @if($p->soporte_ventilatorio)<span class="badge bg-info text-white">VM: {{ Str::limit($p->soporte_ventilatorio, 18) }}</span>@endif
                    @if($p->soporte_hemodinamico)<span class="badge bg-warning text-dark">HD: {{ Str::limit($p->soporte_hemodinamico, 18) }}</span>@endif
                    @if($goc)
                    <span class="badge bg-{{ $goc->badgeNivel() }}">
                        <i class="bi {{ $goc->iconNivel() }} me-1"></i>{{ $goc->labelNivel() }}
                    </span>
                    @else
                    <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Sin GoC</span>
                    @endif
                    @foreach($disp as $d_)
                    @php [$tl,$ti,$tc] = \App\Models\Dispositivo::tipos()[$d_->tipo] ?? [$d_->tipo,'bi-circle','secondary']; @endphp
                    <span class="badge bg-{{ $tc }}-subtle text-{{ $tc }} border border-{{ $tc }}" style="font-size:0.65rem;">
                        <i class="bi {{ $ti }} me-1"></i>{{ $tl }} d{{ $d_->diasDispositivo() }}
                    </span>
                    @endforeach
                    @foreach($atb as $a_)
                    <span class="badge bg-danger-subtle text-danger border border-danger" style="font-size:0.65rem;">
                        <i class="bi bi-capsule me-1"></i>{{ Str::limit($a_->antibiotico,15) }} d{{ $a_->diasTratamiento() }}
                    </span>
                    @endforeach
                    <span class="ms-auto d-flex gap-2">
                        @if($p->news)<span class="badge bg-secondary">NEWS {{ $p->news }}</span>@endif
                        @if($p->sofa)<span class="badge bg-secondary">SOFA {{ Str::before($p->sofa,' ') }}</span>@endif
                    </span>
                </div>
                @if($p->diagnosticos)
                <div class="mt-1 small text-muted"><i class="bi bi-activity me-1"></i>{{ Str::limit($p->diagnosticos, 120) }}</div>
                @endif
            </div>

            <div class="card-body p-2">
                <div class="row g-2">

                    {{-- ═══ A: DOLOR · D: DELIRIUM ════════════════════════════════════════ --}}
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded p-2 h-100 bg-light">
                            <div class="bloque-label">
                                <span class="badge bg-danger me-1">A</span>Dolor ·
                                <span class="badge bg-primary ms-1">D</span>Delirium
                            </div>
                            <div class="text-muted mb-2" style="font-size:0.73rem;">
                                EVA={{ $p->eva ?? '—' }} · BPS={{ $p->bps ?? '—' }} · RASS actual={{ $p->rass ?? '—' }}
                            </div>
                            <div class="row g-1">
                                <div class="col-6">
                                    <label class="form-label small mb-0">CAM-UCI</label>
                                    <select name="cam[{{ $p->id }}][resultado]"
                                            class="form-select form-select-sm cam-sel" data-pid="{{ $p->id }}">
                                        <option value="">—</option>
                                        <option value="negativo"     {{ $cam?->resultado==='negativo'     ?'selected':'' }}>Negativo ✓</option>
                                        <option value="positivo"     {{ $cam?->resultado==='positivo'     ?'selected':'' }}>Positivo ✗</option>
                                        <option value="no_evaluable" {{ $cam?->resultado==='no_evaluable' ?'selected':'' }}>No evaluable</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">RASS momento</label>
                                    <input type="number" name="cam[{{ $p->id }}][rass_momento]"
                                           min="-5" max="4" step="1" placeholder="−"
                                           class="form-control form-control-sm"
                                           value="{{ $cam?->rass_momento }}">
                                </div>

                                {{-- Subtipo: aparece solo si CAM = positivo --}}
                                <div class="col-6 campo-condicional cam-subtipo-{{ $p->id }}"
                                     style="{{ $tieneDelirium ? 'display:block' : '' }}">
                                    <label class="form-label small mb-0">Subtipo delirium</label>
                                    <select name="cam[{{ $p->id }}][subtipo_delirium]" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        <option value="hiperactivo" {{ $cam?->subtipo_delirium==='hiperactivo'?'selected':'' }}>Hiperactivo</option>
                                        <option value="hipoactivo"  {{ $cam?->subtipo_delirium==='hipoactivo' ?'selected':'' }}>Hipoactivo</option>
                                        <option value="mixto"       {{ $cam?->subtipo_delirium==='mixto'      ?'selected':'' }}>Mixto</option>
                                    </select>
                                </div>
                                {{-- Observación CAM: aparece solo si positivo --}}
                                <div class="col-6 campo-condicional cam-subtipo-{{ $p->id }}"
                                     style="{{ $tieneDelirium ? 'display:block' : '' }}">
                                    <label class="form-label small mb-0">Observación</label>
                                    <input type="text" name="cam[{{ $p->id }}][observacion]"
                                           class="form-control form-control-sm"
                                           value="{{ $cam?->observacion }}" placeholder="Notas...">
                                </div>

                                <div class="col-6">
                                    <label class="form-label small mb-0">Objetivo RASS</label>
                                    <select name="paciente[{{ $p->id }}][rass_objetivo]" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach(range(-5,2) as $r)
                                        <option value="{{ $r }}" {{ ($p->rass_objetivo ?? '') == $r ? 'selected':'' }}>{{ $r > 0 ? '+'.$r : $r }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ B+C: SAT / SBT / SEDACIÓN ═══════════════════════════════════════ --}}
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded p-2 h-100 bg-light">
                            <div class="bloque-label">
                                <span class="badge bg-info me-1">B</span>SAT/SBT ·
                                <span class="badge bg-warning text-dark ms-1">C</span>Sedación
                            </div>
                            <div class="row g-1">
                                @foreach([
                                    ['vacacion_sedacion','Vacación sedación (SAT)'],
                                    ['sbt','Prueba resp. espontánea (SBT)'],
                                    ['cabecera_elevada','Cabecera 30-45°'],
                                    ['higiene_oral','Higiene oral'],
                                    ['profilaxis_tvp','Profilaxis TVP'],
                                    ['profilaxis_upp','Profilaxis UPP'],
                                ] as [$campo, $label])
                                <div class="col-12">
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
                                        <option {{ $bundle?->sat_resultado===$opt?'selected':'' }}>{{ ucfirst($opt) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Resultado SBT</label>
                                    <select name="bundle[{{ $p->id }}][sbt_resultado]" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach(['exitoso','fallido','contraindicado'] as $opt)
                                        <option {{ $bundle?->sbt_resultado===$opt?'selected':'' }}>{{ ucfirst($opt) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- ═══ E+F+S: MOVILIZACIÓN · FAMILIA · SUEÑO ═══════════════════════════ --}}
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded p-2 h-100 bg-light">
                            <div class="bloque-label">
                                <span class="badge bg-success me-1">E</span>Movilización ·
                                <span class="badge bg-secondary ms-1">F</span>Familia ·
                                <span class="badge bg-dark ms-1">S</span>Sueño
                            </div>
                            <div class="row g-1">
                                <div class="col-12">
                                    <label class="form-label small mb-0">Nivel movilización</label>
                                    <select name="bundle[{{ $p->id }}][nivel_movilizacion]"
                                            class="form-select form-select-sm mov-sel" data-pid="{{ $p->id }}">
                                        <option value="">— No evaluado</option>
                                        @foreach([
                                            '0:0 — Pasiva en cama',
                                            '1:1 — Activa en cama',
                                            '2:2 — Sedestación borde',
                                            '3:3 — Bipedestación',
                                            '4:4 — Deambulación asistida',
                                            '5:5 — Deambulación independiente',
                                        ] as $opt)
                                        @php [$v,$l] = explode(':',$opt,2); @endphp
                                        <option value="{{ $v }}" {{ $bundle?->nivel_movilizacion==$v?'selected':'' }}>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Motivo no movilización: aparece si nivel = 0 --}}
                                <div class="col-12 campo-condicional motivo-mov-{{ $p->id }}"
                                     style="{{ $bundle?->nivel_movilizacion == 0 ? 'display:block' : '' }}">
                                    <label class="form-label small mb-0">Motivo no movilización</label>
                                    <select name="bundle[{{ $p->id }}][motivo_no_movilizacion]" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach([
                                            'Inestabilidad hemodinámica',
                                            'FiO₂ > 60% o PEEP alto',
                                            'RASS < −3',
                                            'Procedimiento / cirugía reciente',
                                            'Fractura / trauma',
                                            'Agitación no controlada',
                                            'Otro',
                                        ] as $opt)
                                        <option {{ $bundle?->motivo_no_movilizacion===$opt?'selected':'' }}>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12 d-flex gap-3 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="bundle[{{ $p->id }}][familia_involucrada]" value="1"
                                               {{ $bundle?->familia_involucrada?'checked':'' }}>
                                        <label class="form-check-label small">Familia contactada</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="bundle[{{ $p->id }}][familia_reunion_clinica]" value="1"
                                               {{ $bundle?->familia_reunion_clinica?'checked':'' }}>
                                        <label class="form-check-label small">Reunión clínica</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">RCSQ sueño (0-100)</label>
                                    <input type="number" name="bundle[{{ $p->id }}][rcsq_score]"
                                           min="0" max="100" class="form-control form-control-sm"
                                           value="{{ $bundle?->rcsq_score }}" placeholder="0–100">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small mb-0">Interrupciones noche</label>
                                    <input type="number" name="bundle[{{ $p->id }}][interrupciones_nocturnas]"
                                           min="0" class="form-control form-control-sm"
                                           value="{{ $bundle?->interrupciones_nocturnas }}" placeholder="#">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ NUTRICIÓN ════════════════════════════════════════════════════════ --}}
                    <div class="col-xl-3 col-md-6">
                        <div class="border rounded p-2 h-100 bg-light">
                            <div class="bloque-label"><i class="bi bi-egg-fried me-1"></i>Nutrición</div>
                            <div class="row g-1">
                                <div class="col-12">
                                    <label class="form-label small mb-0">Vía nutricional</label>
                                    <select name="nutricion[{{ $p->id }}][via]"
                                            class="form-select form-select-sm nut-via-sel" data-pid="{{ $p->id }}">
                                        <option value="">—</option>
                                        @foreach(\App\Models\NutricionDiaria::vias() as $vk => [$vl,$vi,$vc])
                                        <option value="{{ $vk }}" {{ $nut?->via===$vk?'selected':'' }}>{{ $vl }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Campos de kcal/proteína: se ocultan si via=ayuno --}}
                                <div class="col-6 campo-nut-{{ $p->id }}" style="{{ $nut?->via==='ayuno' ? 'display:none':'' }}">
                                    <label class="form-label small mb-0">Kcal aportadas</label>
                                    <input type="number" name="nutricion[{{ $p->id }}][kcal_aportadas]"
                                           class="form-control form-control-sm" value="{{ $nut?->kcal_aportadas }}" placeholder="kcal">
                                </div>
                                <div class="col-6 campo-nut-{{ $p->id }}" style="{{ $nut?->via==='ayuno' ? 'display:none':'' }}">
                                    <label class="form-label small mb-0">Meta kcal</label>
                                    <input type="number" name="nutricion[{{ $p->id }}][kcal_meta]"
                                           class="form-control form-control-sm" value="{{ $nut?->kcal_meta }}" placeholder="kcal">
                                </div>
                                <div class="col-6 campo-nut-{{ $p->id }}" style="{{ $nut?->via==='ayuno' ? 'display:none':'' }}">
                                    <label class="form-label small mb-0">Prot. aportadas (g)</label>
                                    <input type="number" name="nutricion[{{ $p->id }}][proteinas_g_aportadas]"
                                           class="form-control form-control-sm" value="{{ $nut?->proteinas_g_aportadas }}" placeholder="g">
                                </div>
                                <div class="col-6 campo-nut-{{ $p->id }}" style="{{ $nut?->via==='ayuno' ? 'display:none':'' }}">
                                    <label class="form-label small mb-0">Meta proteínas (g)</label>
                                    <input type="number" name="nutricion[{{ $p->id }}][proteinas_g_meta]"
                                           class="form-control form-control-sm" value="{{ $nut?->proteinas_g_meta }}" placeholder="g">
                                </div>

                                {{-- Motivo suspensión: aparece si via=ayuno --}}
                                <div class="col-12 campo-condicional motivo-ayuno-{{ $p->id }}"
                                     style="{{ $nut?->via==='ayuno' ? 'display:block':'' }}">
                                    <label class="form-label small mb-0">Motivo ayuno/suspensión</label>
                                    <select name="nutricion[{{ $p->id }}][motivo_suspension]" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach([
                                            'Procedimiento / cirugía',
                                            'Inestabilidad hemodinámica',
                                            'Íleo / distensión abdominal',
                                            'Vómito persistente',
                                            'Disfunción GI alta',
                                            'Extubación / prueba oral',
                                            'Indicación médica',
                                            'Otro',
                                        ] as $opt)
                                        <option {{ ($nut?->motivo_suspension===$opt)?'selected':'' }}>{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- NUTRIC score: solo cuando no existe aún --}}
                                <div class="col-12">
                                    <label class="form-label small mb-0">
                                        NUTRIC score
                                        @if($p->nutric_score)<span class="badge bg-secondary ms-1">{{ $p->nutric_score }}</span>@endif
                                    </label>
                                    <input type="number" name="paciente[{{ $p->id }}][nutric_score]"
                                           class="form-control form-control-sm" min="0" max="10"
                                           value="{{ $p->nutric_score }}" placeholder="0–10">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ BALANCE HÍDRICO ══════════════════════════════════════════════════ --}}
                    <div class="col-12">
                        <div class="border rounded p-2 bg-light">
                            <div class="bloque-label d-flex align-items-center gap-2">
                                <i class="bi bi-droplet-half me-1"></i>Balance hídrico diario
                                {{-- Badge del balance calculado --}}
                                <span id="badge-balance-{{ $p->id }}" class="badge ms-auto balance-total
                                    @if($bal) bg-{{ $bal->semaforo() }} @else bg-secondary @endif">
                                    @if($bal)
                                        {{ $bal->balance() > 0 ? '+' : '' }}{{ number_format($bal->balance()) }} mL
                                    @else — @endif
                                </span>
                            </div>
                            <div class="row g-1 mt-1">
                                {{-- INGRESOS --}}
                                <div class="col-md-6">
                                    <div class="bloque-label text-success"><i class="bi bi-plus-circle me-1"></i>Ingresos (mL)</div>
                                    <div class="row g-1">
                                        @foreach([
                                            ['vol_cristaloides',    'Cristaloides IV'],
                                            ['vol_coloides',        'Coloides (albumina)'],
                                            ['vol_hemoderivados',   'Hemoderivados'],
                                            ['vol_nutricion',       'Nutrición (NE/NP) mL'],
                                            ['vol_medicamentos',    'Medicamentos (infusiones)'],
                                            ['vol_otros_ingresos',  'Otros ingresos'],
                                        ] as [$campo, $label])
                                        <div class="col-6 col-lg-4">
                                            <label class="form-label small mb-0" style="font-size:0.72rem;">{{ $label }}</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" min="0"
                                                       name="balance[{{ $p->id }}][{{ $campo }}]"
                                                       class="form-control form-control-sm ingreso-{{ $p->id }} vol-input"
                                                       data-pid="{{ $p->id }}"
                                                       value="{{ $bal?->$campo ?? 0 }}">
                                                <span class="input-group-text px-1" style="font-size:0.65rem;">mL</span>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                {{-- EGRESOS --}}
                                <div class="col-md-6">
                                    <div class="bloque-label text-danger"><i class="bi bi-dash-circle me-1"></i>Egresos (mL)</div>
                                    <div class="row g-1">
                                        @foreach([
                                            ['vol_diuresis',             'Diuresis'],
                                            ['vol_drenajes',             'Drenajes quirúrgicos'],
                                            ['vol_perdidas_insensibles', 'Pérdidas insensibles'],
                                            ['vol_otros_egresos',        'Otros egresos'],
                                        ] as [$campo, $label])
                                        <div class="col-6">
                                            <label class="form-label small mb-0" style="font-size:0.72rem;">{{ $label }}</label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" min="0"
                                                       name="balance[{{ $p->id }}][{{ $campo }}]"
                                                       class="form-control form-control-sm egreso-{{ $p->id }} vol-input"
                                                       data-pid="{{ $p->id }}"
                                                       value="{{ $bal?->$campo ?? 0 }}">
                                                <span class="input-group-text px-1" style="font-size:0.65rem;">mL</span>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══ ATB ACTIVOS — PCT CONTROL ═══════════════════════════════════════ --}}
                    @if($atb->isNotEmpty())
                    <div class="col-md-6">
                        <div class="border rounded p-2 bg-light">
                            <div class="bloque-label"><i class="bi bi-capsule me-1"></i>ATB activos — Control PCT</div>
                            <div class="row g-1">
                                @foreach($atb as $a_)
                                <div class="col-12 d-flex align-items-center gap-2 flex-wrap">
                                    <span class="badge bg-danger-subtle text-danger border border-danger" style="font-size:0.72rem; white-space:nowrap;">
                                        {{ $a_->antibiotico }} · d{{ $a_->diasTratamiento() }}
                                        @if($a_->foco) · {{ $a_->foco }}@endif
                                    </span>
                                    <div class="d-flex align-items-center gap-1 ms-auto">
                                        <label class="small mb-0 text-nowrap">PCT control (ng/mL)</label>
                                        <input type="number" step="0.01" min="0"
                                               name="pct[{{ $a_->id }}]"
                                               class="form-control form-control-sm" style="width:90px;"
                                               value="{{ $a_->pct_control_72h }}"
                                               placeholder="{{ $a_->pct_inicio ? 'inicio '.$a_->pct_inicio : 'ng/mL' }}">
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- ═══ GOAL OF CARE (registro rápido si no existe) ═══════════════════ --}}
                    @if($goc === null)
                    <div class="col-md-{{ $atb->isNotEmpty() ? '6' : '12' }}">
                        <div class="border border-warning rounded p-2 bg-warning-subtle">
                            <div class="bloque-label text-warning"><i class="bi bi-heart me-1"></i>Goal of Care — Registro en ronda</div>
                            <div class="row g-1">
                                <div class="col-md-5">
                                    <label class="form-label small mb-0">Nivel de esfuerzo</label>
                                    <div class="d-flex gap-1 flex-wrap mt-1">
                                        @foreach(['maximo' => ['Máximo','success'], 'limitado' => ['LET','warning'], 'confort' => ['Confort','secondary']] as $nk => [$nl,$nc])
                                        <div class="form-check form-check-inline me-0">
                                            <input class="form-check-input" type="radio"
                                                   name="goc[{{ $p->id }}][nivel_esfuerzo]"
                                                   id="goc_{{ $p->id }}_{{ $nk }}" value="{{ $nk }}">
                                            <label class="form-check-label small badge bg-{{ $nc }}" for="goc_{{ $p->id }}_{{ $nk }}">{{ $nl }}</label>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="goc[{{ $p->id }}][dnr]" value="1">
                                        <label class="form-check-label small fw-semibold text-danger">No RCP (DNR)</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small mb-0">Quién participó</label>
                                    <input type="text" name="goc[{{ $p->id }}][quien_participo]"
                                           class="form-control form-control-sm" placeholder="Ej: médico, familia">
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>{{-- /row --}}
            </div>{{-- /card-body --}}
        </div>{{-- /card paciente --}}
        @empty
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>No hay pacientes activos en UCI hoy.</div>
        @endforelse

        @if($pacientes->isNotEmpty())
        <div class="d-flex justify-content-end gap-2 mb-4 position-sticky" style="bottom:1rem; z-index:10;">
            <button type="submit" class="btn btn-primary btn-lg shadow px-5">
                <i class="bi bi-save me-2"></i>Guardar ronda completa
            </button>
        </div>
        @endif
    </form>

</div>
@endsection

@push('scripts')
<script>
// ── CAM-UCI: mostrar subtipo + observación cuando positivo ────────────────────
document.querySelectorAll('.cam-sel').forEach(sel => {
    sel.addEventListener('change', function () {
        const pid = this.dataset.pid;
        const show = this.value === 'positivo';
        document.querySelectorAll('.cam-subtipo-' + pid).forEach(el => {
            el.style.display = show ? 'block' : 'none';
        });
    });
});

// ── Bundle E: mostrar motivo cuando nivel = 0 ─────────────────────────────────
document.querySelectorAll('.mov-sel').forEach(sel => {
    sel.addEventListener('change', function () {
        const pid = this.dataset.pid;
        const el = document.querySelector('.motivo-mov-' + pid);
        if (el) el.style.display = (this.value === '0') ? 'block' : 'none';
    });
});

// ── Nutrición: mostrar motivo ayuno / ocultar kcal cuando via=ayuno ──────────
document.querySelectorAll('.nut-via-sel').forEach(sel => {
    sel.addEventListener('change', function () {
        const pid = this.dataset.pid;
        const esAyuno = this.value === 'ayuno';
        document.querySelectorAll('.campo-nut-' + pid).forEach(el => {
            el.style.display = esAyuno ? 'none' : '';
        });
        const motivo = document.querySelector('.motivo-ayuno-' + pid);
        if (motivo) motivo.style.display = esAyuno ? 'block' : 'none';
    });
});

// ── Balance hídrico: cálculo en vivo ─────────────────────────────────────────
function recalcBalance(pid) {
    let ingresos = 0, egresos = 0;
    document.querySelectorAll('.ingreso-' + pid).forEach(i => ingresos += parseInt(i.value || 0));
    document.querySelectorAll('.egreso-' + pid).forEach(i => egresos  += parseInt(i.value || 0));
    const bal = ingresos - egresos;
    const badge = document.getElementById('badge-balance-' + pid);
    if (!badge) return;
    badge.textContent = (bal > 0 ? '+' : '') + bal.toLocaleString() + ' mL';
    badge.className = 'badge ms-auto balance-total ' + (
        bal > 1000  ? 'bg-danger'   :
        bal > 500   ? 'bg-warning text-dark' :
        bal < -500  ? 'bg-info text-dark'  :
        'bg-success'
    );
}
document.querySelectorAll('.vol-input').forEach(input => {
    input.addEventListener('input', function () {
        recalcBalance(this.dataset.pid);
    });
});
// Recalcular al cargar
document.querySelectorAll('.vol-input').forEach(input => {
    if (input.value && parseInt(input.value) !== 0) recalcBalance(input.dataset.pid);
});
</script>
@endpush
