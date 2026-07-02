@extends('layouts.app')

@section('title', 'PICS · ' . $evaluacion->paciente->nombre . ' · ' . $evaluacion->labelMomento())

@section('content')
<div class="container-fluid">

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-heart-pulse me-2 text-primary"></i>
                Resultado PICS · {{ $evaluacion->labelMomento() }}
                @if($esFamilia)
                    <span class="badge bg-secondary ms-2">Cuidador familiar</span>
                @endif
            </h4>
            <div class="text-muted small mt-1">
                <strong>{{ $evaluacion->paciente->nombre }}</strong>
                · {{ $evaluacion->paciente->identificacion ?? 'Sin ID' }}
                · Evaluado: {{ $evaluacion->fecha_evaluacion->format('d/m/Y') }}
                · Por: {{ $evaluacion->usuario->name ?? '—' }}
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('pics.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-3">

        {{-- ── Columna izquierda: semáforos + scores ─────────────────────────── --}}
        <div class="col-lg-8">

            @if(!$esFamilia)
            {{-- ── Semáforo global ─────────────────────────────────────────── --}}
            @php
                $global = $evaluacion->semaforoGlobal();
                $globalColors = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger','sin_dato'=>'secondary'];
                $globalIcons  = ['verde'=>'check-circle-fill','amarillo'=>'exclamation-triangle-fill','rojo'=>'x-circle-fill','sin_dato'=>'dash-circle'];
                $globalLabels = ['verde'=>'Sin alteraciones clínicas','amarillo'=>'Alteraciones leves a moderadas','rojo'=>'Alteraciones significativas','sin_dato'=>'Datos insuficientes'];
            @endphp
            <div class="card border-0 shadow-sm mb-3 border-start border-{{ $globalColors[$global] }} border-4">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-{{ $globalIcons[$global] }} text-{{ $globalColors[$global] }} fs-2"></i>
                        <div>
                            <div class="fw-bold fs-5">Estado global: {{ $globalLabels[$global] }}</div>
                            <small class="text-muted">Peor dominio evaluado determina el semáforo global</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Dominios PICS ────────────────────────────────────────────── --}}
            <div class="row g-3 mb-3">

                {{-- Cognitivo --}}
                @if($evaluacion->amt_score !== null)
                @php
                    $s = $evaluacion->semaforoAmt();
                    $c = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger'][$s] ?? 'secondary';
                    $interp = match(true) {
                        $evaluacion->amt_score >= 8 => 'Normal (≥8)',
                        $evaluacion->amt_score >= 6 => 'Deterioro leve (6-7)',
                        default                     => 'Deterioro significativo (<6)',
                    };
                @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title"><i class="bi bi-brain me-2 text-primary"></i>Cognitivo (AMT-10)</h6>
                                <span class="badge bg-{{ $c }}">{{ ucfirst($s) }}</span>
                            </div>
                            <div class="display-6 fw-bold text-{{ $c }}">{{ $evaluacion->amt_score }}<small class="fs-6 fw-normal text-muted"> / 10</small></div>
                            <div class="small text-muted mt-1">{{ $interp }}</div>
                            @if($evaluacion->amt_respuestas)
                            <div class="mt-2">
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-{{ $c }}" style="width:{{ $evaluacion->amt_score * 10 }}%"></div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Ansiedad --}}
                @if($evaluacion->hads_ansiedad !== null)
                @php
                    $s = $evaluacion->semaforoAnsiedad();
                    $c = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger'][$s] ?? 'secondary';
                    $interp = match(true) {
                        $evaluacion->hads_ansiedad <= 7  => 'Normal (≤7)',
                        $evaluacion->hads_ansiedad <= 10 => 'Limítrofe (8-10)',
                        default                          => 'Clínico (≥11)',
                    };
                @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title"><i class="bi bi-lightning me-2 text-warning"></i>Ansiedad (HADS-A)</h6>
                                <span class="badge bg-{{ $c }}">{{ ucfirst($s) }}</span>
                            </div>
                            <div class="display-6 fw-bold text-{{ $c }}">{{ $evaluacion->hads_ansiedad }}<small class="fs-6 fw-normal text-muted"> / 21</small></div>
                            <div class="small text-muted mt-1">{{ $interp }}</div>
                            <div class="mt-2">
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-{{ $c }}" style="width:{{ ($evaluacion->hads_ansiedad / 21) * 100 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Depresión --}}
                @if($evaluacion->hads_depresion !== null)
                @php
                    $s = $evaluacion->semaforoDepresion();
                    $c = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger'][$s] ?? 'secondary';
                    $interp = match(true) {
                        $evaluacion->hads_depresion <= 7  => 'Normal (≤7)',
                        $evaluacion->hads_depresion <= 10 => 'Limítrofe (8-10)',
                        default                           => 'Clínico (≥11)',
                    };
                @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title"><i class="bi bi-cloud-drizzle me-2 text-info"></i>Depresión (HADS-D)</h6>
                                <span class="badge bg-{{ $c }}">{{ ucfirst($s) }}</span>
                            </div>
                            <div class="display-6 fw-bold text-{{ $c }}">{{ $evaluacion->hads_depresion }}<small class="fs-6 fw-normal text-muted"> / 21</small></div>
                            <div class="small text-muted mt-1">{{ $interp }}</div>
                            <div class="mt-2">
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-{{ $c }}" style="width:{{ ($evaluacion->hads_depresion / 21) * 100 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- PTSD --}}
                @if($evaluacion->pcptsd_score !== null)
                @php
                    $s = $evaluacion->semaforoPtsd();
                    $c = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger'][$s] ?? 'secondary';
                    $interp = match(true) {
                        $evaluacion->pcptsd_score <= 1 => 'Sin indicadores (≤1)',
                        $evaluacion->pcptsd_score <= 2 => 'Posible (2)',
                        default                        => 'Probable PTSD (≥3)',
                    };
                @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title"><i class="bi bi-shield-exclamation me-2 text-danger"></i>PTSD (PC-PTSD-5)</h6>
                                <span class="badge bg-{{ $c }}">{{ ucfirst($s) }}</span>
                            </div>
                            <div class="display-6 fw-bold text-{{ $c }}">{{ $evaluacion->pcptsd_score }}<small class="fs-6 fw-normal text-muted"> / 5</small></div>
                            <div class="small text-muted mt-1">{{ $interp }}</div>
                            @if($evaluacion->pcptsd_score >= 3)
                            <div class="alert alert-danger p-2 mt-2 small mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i>Referir a salud mental
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif

                {{-- Fatiga --}}
                @if($evaluacion->fatiga_score !== null)
                @php
                    $s = $evaluacion->semaforoFatiga();
                    $c = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger'][$s] ?? 'secondary';
                @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title"><i class="bi bi-battery-half me-2 text-secondary"></i>Fatiga (NRS)</h6>
                                <span class="badge bg-{{ $c }}">{{ ucfirst($s) }}</span>
                            </div>
                            <div class="display-6 fw-bold text-{{ $c }}">{{ $evaluacion->fatiga_score }}<small class="fs-6 fw-normal text-muted"> / 10</small></div>
                            <div class="mt-2">
                                <div class="progress" style="height:6px">
                                    <div class="progress-bar bg-{{ $c }}" style="width:{{ $evaluacion->fatiga_score * 10 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Dolor --}}
                @if($evaluacion->dolor_reposo !== null || $evaluacion->dolor_movimiento !== null)
                @php
                    $s = $evaluacion->semaforoDolor();
                    $c = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger'][$s] ?? 'secondary';
                @endphp
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title"><i class="bi bi-activity me-2 text-danger"></i>Dolor crónico (NRS)</h6>
                                <span class="badge bg-{{ $c }}">{{ ucfirst($s) }}</span>
                            </div>
                            <div class="row g-1 mt-1">
                                <div class="col-6">
                                    <div class="small text-muted">Reposo</div>
                                    <div class="fw-bold text-{{ $c }} fs-4">{{ $evaluacion->dolor_reposo ?? '—' }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">Movimiento</div>
                                    <div class="fw-bold text-{{ $c }} fs-4">{{ $evaluacion->dolor_movimiento ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- PTG (90d/180d) --}}
                @if($conPtg && $evaluacion->ptg_score !== null)
                @php
                    $s = $evaluacion->semaforoPtg();
                    $c = ['verde'=>'success','amarillo'=>'warning','rojo'=>'secondary'][$s] ?? 'secondary';
                    $interp = match(true) {
                        $evaluacion->ptg_score >= 30 => 'Crecimiento significativo (≥30)',
                        $evaluacion->ptg_score >= 15 => 'Crecimiento moderado (15-29)',
                        default                      => 'Crecimiento mínimo (<15)',
                    };
                @endphp
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h6 class="card-title"><i class="bi bi-tree me-2 text-success"></i>Crecimiento Postraumático (PTG-SF)</h6>
                                <span class="badge bg-{{ $c }}">{{ $interp }}</span>
                            </div>
                            <div class="display-6 fw-bold text-{{ $c }}">{{ $evaluacion->ptg_score }}<small class="fs-6 fw-normal text-muted"> / 50</small></div>
                            <div class="mt-2">
                                <div class="progress" style="height:8px">
                                    <div class="progress-bar bg-{{ $c }}" style="width:{{ ($evaluacion->ptg_score / 50) * 100 }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

            </div>

            {{-- Disfagia --}}
            @if($evaluacion->disfagia && $evaluacion->momento === 'egreso')
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cup-straw text-info fs-5"></i>
                        <span class="fw-semibold">Prueba de deglución:</span>
                        @php
                            $dc = ['pasa'=>'success','falla'=>'danger','no_aplica'=>'secondary','pendiente'=>'warning'];
                            $dl = ['pasa'=>'Pasa','falla'=>'Falla — referir fonoaudiología','no_aplica'=>'No aplica','pendiente'=>'Pendiente'];
                        @endphp
                        <span class="badge bg-{{ $dc[$evaluacion->disfagia] ?? 'secondary' }}">
                            {{ $dl[$evaluacion->disfagia] ?? $evaluacion->disfagia }}
                        </span>
                    </div>
                </div>
            </div>
            @endif

            @else
            {{-- ── PICS-F (familia) ────────────────────────────────────────── --}}
            @if($evaluacion->picsf_distress !== null)
            @php
                $distress = $evaluacion->picsf_distress;
                $nivel = match(true) { $distress >= 10 => 'rojo', $distress >= 5 => 'amarillo', default => 'verde' };
                $c     = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger'][$nivel];
                $interp = match(true) {
                    $distress >= 10 => 'Distrés severo del cuidador (≥10) — requiere atención',
                    $distress >= 5  => 'Distrés moderado (5-9)',
                    default         => 'Sin distrés significativo (<5)',
                };
            @endphp
            <div class="card border-0 shadow-sm mb-3 border-start border-{{ $c }} border-4">
                <div class="card-body">
                    <h6><i class="bi bi-people me-2"></i>PICS-F · Distrés del cuidador familiar</h6>
                    <div class="display-6 fw-bold text-{{ $c }}">{{ $distress }}<small class="fs-6 fw-normal text-muted"> / 20</small></div>
                    <div class="mt-1 small text-muted">{{ $interp }}</div>
                    <div class="progress mt-2" style="height:8px">
                        <div class="progress-bar bg-{{ $c }}" style="width:{{ ($distress/20)*100 }}%"></div>
                    </div>
                </div>
            </div>
            @endif
            @endif

            {{-- Observaciones --}}
            @if($evaluacion->observaciones)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="card-title"><i class="bi bi-chat-text me-2 text-muted"></i>Observaciones</h6>
                    <p class="mb-0 small">{{ $evaluacion->observaciones }}</p>
                </div>
            </div>
            @endif

        </div>

        {{-- ── Columna derecha: riesgo + tendencia ───────────────────────────── --}}
        <div class="col-lg-4">

            {{-- Score de riesgo --}}
            @if($riesgo)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-graph-up-arrow me-2"></i>Score de riesgo PICS
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="display-5 fw-bold" style="color:{{ $riesgo->colorNivel() }}">
                            {{ $riesgo->score_total }}
                        </div>
                        <div>
                            <span class="badge bg-{{ $riesgo->badgeClass() }} fs-6">
                                Riesgo {{ ucfirst($riesgo->nivel_riesgo) }}
                            </span>
                            <div class="small text-muted mt-1">Calculado: {{ $riesgo->fecha_calculo->format('d/m/Y') }}</div>
                        </div>
                    </div>
                    <div class="small">
                        <div class="row g-1 mb-2">
                            <div class="col-6 text-muted">Días UCI</div>
                            <div class="col-6 fw-semibold">{{ $riesgo->dias_uci }}d</div>
                            <div class="col-6 text-muted">Días VM</div>
                            <div class="col-6 fw-semibold">{{ $riesgo->dias_vm }}d</div>
                            <div class="col-6 text-muted">Días delirium</div>
                            <div class="col-6 fw-semibold">{{ $riesgo->dias_delirium }}d</div>
                            <div class="col-6 text-muted">Edad</div>
                            <div class="col-6 fw-semibold">{{ $riesgo->edad }} años</div>
                        </div>
                        @if($riesgo->factores)
                        <div class="border-top pt-2 mt-1">
                            @foreach($riesgo->factores as $f)
                            <div class="text-muted"><i class="bi bi-arrow-right-short"></i>{{ $f }}</div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            {{-- Evaluaciones previas --}}
            @if($anteriores->isNotEmpty())
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-clock-history me-2"></i>Evaluaciones anteriores
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($anteriores as $ant)
                        @php
                            $gs = $ant->semaforoGlobal();
                            $gc = ['verde'=>'success','amarillo'=>'warning','rojo'=>'danger','sin_dato'=>'secondary'][$gs];
                        @endphp
                        <a href="{{ route('pics.show', $ant) }}"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold small">{{ $ant->labelMomento() }}</div>
                                <div class="text-muted" style="font-size:0.75rem">{{ $ant->fecha_evaluacion->format('d/m/Y') }}</div>
                            </div>
                            <span class="badge bg-{{ $gc }} rounded-pill">{{ ucfirst($gs) }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            {{-- Recomendaciones --}}
            @if(!$esFamilia)
            @php
                $recs = [];
                if (($evaluacion->amt_score ?? 10) < 8)         $recs[] = ['danger','Valoración neuropsicológica por deterioro cognitivo'];
                if (($evaluacion->hads_ansiedad ?? 0) >= 8)      $recs[] = ['warning','Seguimiento por ansiedad (HADS-A ≥8)'];
                if (($evaluacion->hads_depresion ?? 0) >= 8)     $recs[] = ['warning','Seguimiento por depresión (HADS-D ≥8)'];
                if (($evaluacion->pcptsd_score ?? 0) >= 3)       $recs[] = ['danger','Referir a salud mental — probable PTSD'];
                if (($evaluacion->fatiga_score ?? 0) > 6)        $recs[] = ['warning','Programa de rehabilitación para fatiga crónica'];
                if (max($evaluacion->dolor_reposo ?? 0, $evaluacion->dolor_movimiento ?? 0) > 6)
                                                                   $recs[] = ['danger','Manejo del dolor — NRS > 6'];
                if ($evaluacion->disfagia === 'falla')            $recs[] = ['danger','Referir a fonoaudiología — disfagia detectada'];
            @endphp
            @if(!empty($recs))
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">
                    <i class="bi bi-lightbulb me-2 text-warning"></i>Recomendaciones
                </div>
                <div class="card-body p-2">
                    @foreach($recs as [$rc, $rm])
                    <div class="alert alert-{{ $rc }} p-2 mb-2 small">
                        <i class="bi bi-arrow-right me-1"></i>{{ $rm }}
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body text-center text-success py-3">
                    <i class="bi bi-check-circle-fill fs-3 d-block mb-1"></i>
                    <div class="small fw-semibold">Sin alteraciones clínicas</div>
                    <div class="text-muted" style="font-size:0.75rem">Continuar seguimiento programado</div>
                </div>
            </div>
            @endif
            @endif

        </div>
    </div>

</div>
@endsection
