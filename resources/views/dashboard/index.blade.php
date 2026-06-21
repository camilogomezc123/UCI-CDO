@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard UCI')

@section('content')

{{-- Alerta: sin carga del día --}}
@if(!$cargaHoy)
<div class="alert alert-warning d-flex align-items-center gap-3 mb-3" style="border-radius:10px;">
    <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0"></i>
    <div>
        <strong>No se ha cargado el archivo de hoy.</strong>
        Los datos mostrados corresponden a la última carga del
        <strong>{{ $ultimaCarga ? $ultimaCarga->created_at->format('d/m/Y H:i') : 'carga desconocida' }}</strong>.
        <a href="{{ route('carga.index') }}" class="alert-link ms-2">Cargar ahora →</a>
    </div>
</div>
@endif

{{-- Alerta CAM-UCI positivo --}}
@if($camPositivosHoy->count() > 0)
<div class="card border-danger mb-3" style="border-width:2px!important;">
    <div class="card-header bg-danger text-white py-2 d-flex align-items-center gap-2">
        <i class="bi bi-brain"></i>
        <strong>CAM-UCI Positivo — {{ $camPositivosHoy->count() }} paciente(s) con delirium hoy</strong>
        <span class="ms-auto badge bg-white text-danger" style="font-size:0.78rem;">{{ $camPctHoy }}% de evaluados</span>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0">
            <tbody>
                @foreach($camPositivosHoy->take(6) as $cam)
                @php $p = $cam->paciente; @endphp
                <tr>
                    <td style="font-size:0.82rem;" class="ps-3">
                        <a href="{{ route('pacientes.show', $p) }}" class="text-decoration-none fw-semibold">
                            {{ $p->nombre }}
                        </a>
                    </td>
                    <td style="font-size:0.82rem; color:#888;">{{ $p->documento }}</td>
                    <td><span class="badge bg-danger">Delirium +</span></td>
                    @if($cam->observacion)
                    <td style="font-size:0.78rem; color:#888;" class="pe-3">{{ $cam->observacion }}</td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Alertas clínicas críticas --}}
@if($alertasNews->count() > 0 || $alertasSofa->count() > 0)
<div class="row g-2 mb-3">
    @if($alertasNews->count() > 0)
    <div class="col-md-6">
        <div class="card border-danger" style="border-width:2px!important;">
            <div class="card-header bg-danger text-white py-2 d-flex align-items-center gap-2">
                <i class="bi bi-thermometer-high"></i>
                <strong>NEWS ≥ 5 — {{ $alertasNews->count() }} paciente(s)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($alertasNews->take(5) as $s)
                        @php $p = \App\Models\Paciente::find($s->paciente_id); @endphp
                        <tr>
                            <td style="font-size:0.82rem;" class="ps-3">
                                <a href="{{ route('pacientes.show', $s->paciente_id) }}" class="text-decoration-none fw-semibold">
                                    {{ $p->nombre ?? '—' }}
                                </a>
                            </td>
                            <td style="font-size:0.82rem;">{{ $s->ubicacion }}</td>
                            <td><span class="badge bg-danger">NEWS {{ $s->news }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    @endif
    @if($alertasSofa->count() > 0)
    <div class="col-md-6">
        <div class="card border-warning" style="border-width:2px!important;">
            <div class="card-header bg-warning text-dark py-2 d-flex align-items-center gap-2">
                <i class="bi bi-activity"></i>
                <strong>SOFA ≥ 10 — {{ $alertasSofa->count() }} paciente(s)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($alertasSofa->take(5) as $s)
                        @php $p = \App\Models\Paciente::find($s->paciente_id); @endphp
                        <tr>
                            <td style="font-size:0.82rem;" class="ps-3">
                                <a href="{{ route('pacientes.show', $s->paciente_id) }}" class="text-decoration-none fw-semibold">
                                    {{ $p->nombre ?? '—' }}
                                </a>
                            </td>
                            <td style="font-size:0.82rem;">{{ $s->ubicacion }}</td>
                            <td><span class="badge bg-warning text-dark">SOFA {{ $s->sofa }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>
@endif

{{-- Alertas dolor --}}
@if($alertasDolor->count() > 0)
<div class="row g-2 mb-3">
    <div class="col-12">
        <div class="card" style="border:2px solid #e05c00;">
            <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#fff3e0;">
                <i class="bi bi-emoji-frown" style="color:#e05c00;"></i>
                <strong style="color:#e05c00;">Dolor no controlado — {{ $alertasDolor->count() }} paciente(s)</strong>
                <span style="font-size:0.75rem;color:#888;" class="ms-2">EVA > 4 o BPS > 6</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($alertasDolor->take(6) as $s)
                        @php $p = \App\Models\Paciente::find($s->paciente_id); @endphp
                        <tr>
                            <td style="font-size:0.82rem;" class="ps-3">
                                <a href="{{ route('pacientes.show', $s->paciente_id) }}" class="text-decoration-none fw-semibold">
                                    {{ $p->nombre ?? '—' }}
                                </a>
                            </td>
                            <td style="font-size:0.82rem;">{{ $s->ubicacion }}</td>
                            @if($s->eva !== null && (float)$s->eva > 4)
                            <td><span class="badge" style="background:#ffc107;color:#000;">EVA {{ $s->eva }}</span></td>
                            @else <td></td> @endif
                            @if($s->bps !== null && (float)$s->bps > 6)
                            <td><span class="badge" style="background:#e05c00;color:#fff;">BPS {{ $s->bps }}</span></td>
                            @else <td></td> @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif

{{-- KPIs --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <i class="bi bi-people-fill kpi-icon"></i>
            <div class="kpi-number">{{ $totalActivos }}</div>
            <div class="kpi-label">Pacientes activos en UCI</div>
            <div class="kpi-sub">{{ $cargaHoy ? 'Datos actualizados hoy' : 'Sin carga hoy ⚠' }}</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
            <i class="bi bi-hourglass-split kpi-icon"></i>
            <div class="kpi-number">{{ $pendientesEgreso }}</div>
            <div class="kpi-label">Pendientes de egreso UCI</div>
            <div class="kpi-sub">Con indicación médica para hospitalización</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
            <i class="bi bi-lungs kpi-icon"></i>
            <div class="kpi-number">{{ $conVmiActivo }}</div>
            <div class="kpi-label">Con VMI activo</div>
            <div class="kpi-sub">Ventilación mecánica invasiva</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#fd7e14,#e06000)">
            <i class="bi bi-heart-pulse kpi-icon"></i>
            <div class="kpi-number">{{ $conVasopresorActivo }}</div>
            <div class="kpi-label">Con vasopresor activo</div>
            <div class="kpi-sub">Soporte hemodinámico</div>
        </div>
    </div>
</div>

{{-- KPIs clínicos fila 2 --}}
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#6c757d,#495057)">
            <i class="bi bi-graph-down-arrow kpi-icon"></i>
            <div class="kpi-number">{{ $mortalidadCruda !== null ? $mortalidadCruda.'%' : '—' }}</div>
            <div class="kpi-label">Mortalidad cruda UCI</div>
            <div class="kpi-sub">Últimos 30 días · {{ $totalEgresos }} egresos</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#20c997,#199d77)">
            <i class="bi bi-clock kpi-icon"></i>
            <div class="kpi-number">{{ $estanciaMedia !== null ? $estanciaMedia.'d' : '—' }}</div>
            <div class="kpi-label">Estancia media (KPI-O03)</div>
            <div class="kpi-sub">Promedio días egresados 30d</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#0dcaf0,#09a3c1)">
            <i class="bi bi-hospital kpi-icon"></i>
            <div class="kpi-number">{{ $ocupacionPct !== null ? $ocupacionPct.'%' : '—' }}</div>
            <div class="kpi-label">Ocupación UCI (KPI-O01)</div>
            <div class="kpi-sub">{{ $totalActivos }}/{{ $totalCamas }} camas</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="kpi-card" style="background:linear-gradient(135deg,#198754,#145e3c)">
            <i class="bi bi-arrow-repeat kpi-icon"></i>
            <div class="kpi-number">{{ $giroCama !== null ? $giroCama : '—' }}</div>
            <div class="kpi-label">Giro de cama (KPI-O02)</div>
            <div class="kpi-sub">Egresos/cama últimos 30d</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Ocupación por subunidad --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-building text-primary"></i> Ocupación por Subunidad
                <i class="bi bi-info-circle text-muted ms-auto" title="UCI: {{ $desgloseOcupacion['uci'] }} · UCIN: {{ $desgloseOcupacion['ucin'] }} · Hospitalizados/alta: {{ $desgloseOcupacion['hospitalizados'] }}"></i>
            </div>
            <div class="card-body">
                @foreach($unidades as $unidad)
                @php
                    $sub = $unidad->nombre;
                    $cap = $capacidades[$sub] ?? 0;
                    $ocu = $porSubunidad[$sub] ?? 0;
                    $pct = $cap > 0 ? round($ocu / $cap * 100) : 0;
                    $color = $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                @endphp
                <div class="mb-2 {{ $cap === 0 ? 'opacity-50' : '' }}">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="font-size:0.82rem;font-weight:600;">{{ $sub }}</span>
                        <span style="font-size:0.8rem;" class="text-{{ $color }}">{{ $cap === 0 ? 'Inhabilitada' : $ocu.'/'.$cap }}</span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;">
                        <div class="progress-bar bg-{{ $color }}" style="width:{{ $pct }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Ocupación histórica últimos 30 días --}}
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-graph-up text-primary"></i> Ocupación Histórica (últimos 30 días)
            </div>
            <div class="card-body">
                @if($ocupacionHistorica->isEmpty())
                    <div class="text-center text-muted py-4" style="font-size:0.875rem;">
                        <i class="bi bi-bar-chart-line fs-3 d-block mb-2 opacity-25"></i>
                        Sin datos suficientes. Se construirá con cargas diarias.
                    </div>
                @else
                    <canvas id="chartOcupacion" style="max-height:200px;"></canvas>
                    @php $ocupacionNoConfiable = $ocupacionHistorica->where('confiable', false); @endphp
                    @if($ocupacionNoConfiable->isNotEmpty())
                    <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:0.78rem;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <strong>Puntos naranjas:</strong> ocupación estimada por ingresos y egresos. Puntos rojos: carga incompleta sin estimación.
                    </div>
                    @endif
                @endif
            </div>
        </div>

    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Soporte --}}
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-lungs text-primary"></i> Soporte Activo
            </div>
            <div class="card-body">
                <p class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Ventilatorio</p>
                @forelse($porVentilatorio as $tipo => $cnt)
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:0.82rem;">{{ $tipo }}</span>
                    <span class="badge bg-primary rounded-pill">{{ $cnt }}</span>
                </div>
                @empty <p class="text-muted small">Sin datos</p> @endforelse
                <hr class="my-2">
                <p class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Hemodinámico</p>
                @forelse($porHemodinamico as $tipo => $cnt)
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:0.82rem;">{{ $tipo }}</span>
                    <span class="badge bg-danger rounded-pill">{{ $cnt }}</span>
                </div>
                @empty <p class="text-muted small">Sin datos</p> @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-pie-chart text-primary"></i> Por Criterio
            </div>
            <div class="card-body d-flex align-items-center gap-4">
                <canvas id="chartCriterio" style="max-height:210px;max-width:260px;"></canvas>
                <div class="flex-grow-1">
                    @php $cc = ['ESTANCIA EN UNIDAD CUIDADO INTENSIVO'=>['UCI Intensivo','#dc3545'],'ESTANCIA EN UNIDAD CUIDADO INTERMEDIO'=>['UCI Intermedio','#fd7e14'],'OTROS CRITERIOS(Hosp, Alta)'=>['Otros','#6c757d']]; @endphp
                    @foreach($porCriterio as $c => $n)
                    @php $i = $cc[$c] ?? [$c,'#aaa']; @endphp
                    <div class="d-flex align-items-center gap-2 mb-2"><span style="width:10px;height:10px;border-radius:50%;background:{{ $i[1] }};display:inline-block;flex-shrink:0;"></span><span style="font-size:0.85rem;">{{ $i[0] }}</span><span class="ms-auto fw-bold">{{ $n }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    {{-- Promedios escalas --}}
    <div class="col-12">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-clipboard2-pulse text-primary"></i> Promedios Escalas Clínicas
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @php
                    $escalas = [
                        'NEWS'    => ['bi-thermometer-half','primary','Alerta temprana', null],
                        'BARTHEL' => ['bi-person-walking','success','Funcionalidad', null],
                        'RASS'    => ['bi-moon-stars','info','Sedación', null],
                        'EVA'     => ['bi-emoji-frown','warning','Dolor (EVA)', 4],
                        'BPS'     => ['bi-emoji-frown-fill','danger','Dolor (BPS)', 6],
                    ];
                    @endphp
                    @foreach($escalas as $e => $info)
                    @php $eVal = $promedios[$e] ?? null; $umbral = $info[3]; $esAlerta = $umbral !== null && $eVal !== null && $eVal > $umbral; @endphp
                    <div class="col-6 col-xl-4">
                        <div class="text-center p-3 rounded-3 {{ $esAlerta ? 'border border-warning' : '' }}" style="background:{{ $esAlerta ? '#fff8e1' : '#f8f9fa' }};">
                            <i class="bi {{ $info[0] }} text-{{ $info[1] }}" style="font-size:1.4rem;"></i>
                            <div class="fw-bold fs-4 mt-1">{{ $eVal !== null ? $eVal : '—' }}</div>
                            <div class="fw-semibold" style="font-size:0.82rem;">{{ $e }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $info[2] }}</div>
                            @if($e === 'NEWS' && ($eVal ?? 0) >= 5)
                                <span class="badge bg-danger mt-1" style="font-size:0.65rem;">ALERTA</span>
                            @elseif($esAlerta)
                                <span class="badge bg-warning text-dark mt-1" style="font-size:0.65rem;">DOLOR</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    {{-- CAM-UCI hoy --}}
                    <div class="col-6 col-xl-4">
                        <div class="text-center p-3 rounded-3 {{ $camPositivosHoy->count() > 0 ? 'border border-danger' : '' }}"
                             style="background:{{ $camPositivosHoy->count() > 0 ? '#fff5f5' : '#f8f9fa' }};">
                            <i class="bi bi-brain text-{{ $camPositivosHoy->count() > 0 ? 'danger' : 'secondary' }}" style="font-size:1.4rem;"></i>
                            <div class="fw-bold fs-4 mt-1 {{ $camPositivosHoy->count() > 0 ? 'text-danger' : '' }}">
                                {{ $camPositivosHoy->count() ?: '0' }}
                            </div>
                            <div class="fw-semibold" style="font-size:0.82rem;">CAM-UCI+</div>
                            <div class="text-muted" style="font-size:0.72rem;">Delirium hoy</div>
                            @if($camTotalHoy > 0)
                                <div class="text-muted" style="font-size:0.68rem;">{{ $camTotalHoy }} evaluados · {{ $camPctHoy }}%</div>
                            @else
                                <div class="text-muted" style="font-size:0.68rem;">Sin evaluar hoy</div>
                            @endif
                            @if($camPositivosHoy->count() > 0)
                                <span class="badge bg-danger mt-1" style="font-size:0.65rem;">DELIRIUM</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Movilización temprana --}}
                <hr class="my-3">
                <p class="mb-2 fw-semibold" style="font-size:0.78rem;text-transform:uppercase;letter-spacing:1px;">
                    <i class="bi bi-person-arms-up text-success me-1"></i>Movilización Temprana (activos)
                </p>
                <div class="row g-2">
                    <div class="col-4">
                        <div class="text-center p-2 rounded-3" style="background:#e8f5e9;">
                            <div class="fw-bold fs-5 text-success">{{ $movilizacion['temprana'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">&lt; 48 horas</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 rounded-3" style="background:#fff3e0;">
                            <div class="fw-bold fs-5 text-warning">{{ $movilizacion['tardia'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">&gt; 48 horas</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 rounded-3" style="background:#f8f9fa;">
                            <div class="fw-bold fs-5 text-muted">{{ $movilizacion['sin_dato'] }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">Sin registro</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Pacientes con espera prolongada --}}
@if($pacientesEsperaLarga->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2 text-danger">
        <i class="bi bi-exclamation-triangle-fill"></i>
        Pacientes con espera prolongada para egreso (+4h con indicación médica para hospitalización)
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Paciente</th><th>Cama</th><th>Subunidad</th><th>Indicación médica</th><th>Tiempo esperando</th><th></th></tr></thead>
                <tbody>
                    @foreach($pacientesEsperaLarga as $p)
                    <tr>
                        <td>
                            <div class="fw-semibold" style="font-size:0.875rem;">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                        </td>
                        <td>{{ $p->ultimoSnapshot->ubicacion ?? '—' }}</td>
                        <td style="font-size:0.82rem;">{{ $p->ultimoSnapshot->subunidad ?? '—' }}</td>
                        <td style="font-size:0.82rem;">{{ $p->salida_hospitalizacion->format('d/m/Y H:i') }}</td>
                        <td><span class="tiempo-espera">{{ $p->tiempoEsperaHospitalizacion() }}</span></td>
                        <td><a href="{{ route('pacientes.show', $p) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Panel: Soportes prolongados >2 días --}}
@if($pacientesSoporteProlongado->count() > 0)
<div class="card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-hourglass-split text-danger"></i>
        <strong>Soporte farmacológico prolongado &gt; 2 días</strong>
        <span class="badge bg-danger ms-1">{{ $pacientesSoporteProlongado->count() }}</span>
        <span class="text-muted ms-2" style="font-size:0.78rem;">Pacientes activos con soporte continuo</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:0.85rem;">
                <thead>
                    <tr>
                        <th>Paciente</th>
                        <th class="text-center">
                            <i class="bi bi-lungs text-info me-1"></i>VMI
                        </th>
                        <th class="text-center">
                            <i class="bi bi-heart-pulse text-danger me-1"></i>Vasopresor
                        </th>
                        <th class="text-center">
                            <i class="bi bi-activity text-warning me-1"></i>Inotropico
                        </th>
                        <th class="text-center">
                            <i class="bi bi-pulse text-primary me-1"></i>Antiarrítmico
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pacientesSoporteProlongado as $p)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $p->nombre }}</div>
                            <div class="text-muted" style="font-size:0.72rem;">{{ $p->documento }}</div>
                        </td>
                        <td class="text-center">
                            @if($p->dias_vmi > 2)
                                <span class="badge bg-info text-dark">{{ $p->dias_vmi }}d</span>
                            @elseif($p->dias_vmi > 0)
                                <span class="text-muted">{{ $p->dias_vmi }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($p->dias_vasopresor > 2)
                                <span class="badge bg-danger">{{ $p->dias_vasopresor }}d</span>
                            @elseif($p->dias_vasopresor > 0)
                                <span class="text-muted">{{ $p->dias_vasopresor }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($p->dias_inotropico > 2)
                                <span class="badge bg-warning text-dark">{{ $p->dias_inotropico }}d</span>
                            @elseif($p->dias_inotropico > 0)
                                <span class="text-muted">{{ $p->dias_inotropico }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($p->dias_antiarritmico > 2)
                                <span class="badge bg-primary">{{ $p->dias_antiarritmico }}d</span>
                            @elseif($p->dias_antiarritmico > 0)
                                <span class="text-muted">{{ $p->dias_antiarritmico }}d</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('pacientes.show', $p->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Riesgos + Transfusiones --}}
<div class="row g-3 mb-4">

    {{-- Distribución de riesgos --}}
    @if(count($porRiesgo) > 0)
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle text-warning"></i>
                <strong>Distribución de Riesgos en UCI</strong>
                <span class="text-muted ms-1" style="font-size:0.75rem;">(% de pacientes activos)</span>
            </div>
            <div class="card-body">
                @foreach($porRiesgo as $nombre => $datos)
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span style="font-size:0.85rem;font-weight:600;">
                            <i class="bi bi-exclamation-circle me-1 text-warning"></i>{{ $nombre }}
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-warning text-dark" style="font-size:0.7rem;">{{ $datos['count'] }} pac.</span>
                            <span class="fw-bold" style="font-size:0.85rem;min-width:35px;text-align:right;">{{ $datos['pct'] }}%</span>
                        </div>
                    </div>
                    <div class="progress" style="height:8px;border-radius:4px;">
                        <div class="progress-bar" style="width:{{ $datos['pct'] }}%;background:{{ $datos['pct'] >= 50 ? '#dc3545' : ($datos['pct'] >= 25 ? '#fd7e14' : '#ffc107') }};"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Transfusiones + KPIs extra --}}
    <div class="{{ count($porRiesgo) > 0 ? 'col-lg-5' : 'col-12' }}">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-droplet-fill text-danger"></i>
                <strong>Hemoderivados / Transfusiones</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-center p-3 rounded" style="background:#fff5f5;border:1px solid #f5c6c6;">
                            <div class="fw-bold fs-3 text-danger">{{ $transfusionesHoy }}</div>
                            <div style="font-size:0.8rem;" class="text-muted">Pacientes hoy</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 rounded" style="background:#f8f9fa;">
                            <div class="fw-bold fs-3 text-secondary">{{ $transfusionesSemana }}</div>
                            <div style="font-size:0.8rem;" class="text-muted">Últimos 7 días</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-bar-chart-line text-primary"></i>
                <strong>KPIs adicionales (30d)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0" style="font-size:0.82rem;">
                    <tbody>
                        <tr>
                            <td class="ps-3 text-muted">Mortalidad cruda (KPI-C01)</td>
                            <td class="fw-bold text-end pe-3">
                                @if($mortalidadCruda !== null)
                                    <span class="badge {{ $mortalidadCruda > 20 ? 'bg-danger' : 'bg-success' }}">{{ $mortalidadCruda }}%</span>
                                @else <span class="text-muted">—</span> @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Estancia media UCI (KPI-O03)</td>
                            <td class="fw-bold text-end pe-3">{{ $estanciaMedia !== null ? $estanciaMedia.' días' : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Ocupación UCI (KPI-O01)</td>
                            <td class="fw-bold text-end pe-3">
                                @if($ocupacionPct !== null)
                                    <span class="badge {{ $ocupacionPct >= 90 ? 'bg-danger' : ($ocupacionPct >= 70 ? 'bg-warning text-dark' : 'bg-success') }}">{{ $ocupacionPct }}%</span>
                                @else <span class="text-muted">—</span> @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Giro de cama (KPI-O02)</td>
                            <td class="fw-bold text-end pe-3">{{ $giroCama !== null ? $giroCama : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="ps-3 text-muted">Ratio VM/UCI (KPI-C10)</td>
                            <td class="fw-bold text-end pe-3">{{ $ratioVmUci !== null ? $ratioVmUci.'%' : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// Gráfico criterios
const ctxC = document.getElementById('chartCriterio');
if (ctxC) {
    new Chart(ctxC, {
        type: 'doughnut',
        data: {
            labels: {!! json_encode($porCriterio->keys()->map(fn($k) => match(true) {
                str_contains($k,'INTENSIVO') => 'UCI Intensivo',
                str_contains($k,'INTERMEDIO') => 'UCI Intermedio',
                default => 'Otros'
            })->values()) !!},
            datasets: [{ data: {!! json_encode($porCriterio->values()) !!}, backgroundColor: ['#dc3545','#fd7e14','#6c757d'], borderWidth: 0 }]
        },
        options: { plugins: { legend: { display: false } }, cutout: '70%', responsive: true, maintainAspectRatio: true }
    });
}

// Gráfico ocupación histórica
const ctxO = document.getElementById('chartOcupacion');
if (ctxO) {
    const hist = {!! json_encode($ocupacionHistorica) !!};
    new Chart(ctxO, {
        type: 'line',
        data: {
            labels: hist.map(h => h.fecha),
            datasets: [{
                label: 'Pacientes activos',
                data: hist.map(h => h.total),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true, tension: 0.3,
                pointRadius: hist.map(h => h.confiable ? 3 : 5),
                pointBackgroundColor: hist.map(h => h.estimado ? '#fd7e14' : (h.confiable ? '#fff' : '#dc3545')),
                pointBorderColor: hist.map(h => h.estimado ? '#fd7e14' : (h.confiable ? '#0d6efd' : '#dc3545')),
            }, {
                label: 'Capacidad habilitada (100%)',
                data: hist.map(h => h.capacidad),
                borderColor: '#198754',
                borderDash: [6, 4],
                borderWidth: 2,
                pointRadius: 0,
                fill: false,
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: false, ticks: { stepSize: 5 } }, x: { ticks: { maxTicksLimit: 10, maxRotation: 0 } } },
            plugins: {
                legend: { display: true, position: 'bottom', labels: { boxWidth: 12 } },
                tooltip: {
                    callbacks: {
                        title: items => {
                            const corte = hist[items[0].dataIndex];
                            const estado = corte.estimado ? ' · estimado' : (!corte.confiable ? ' · datos incompletos' : '');
                            return `${corte.fecha} · corte ${corte.hora_carga}${estado}`;
                        },
                        label: item => {
                            const corte = hist[item.dataIndex];
                            const faltantes = corte.faltantes.length ? ` · Faltan: ${corte.faltantes.join(', ')}` : '';
                            const porcentaje = corte.capacidad > 0 ? Math.round((corte.total / corte.capacidad) * 100) : 0;
                            return item.datasetIndex === 1
                                ? `${item.parsed.y} camas habilitadas (100%)`
                                : `${item.parsed.y} camas ocupadas (${porcentaje}%)${faltantes}`;
                        },
                    }
                }
            }
        }
    });
}
</script>
@endpush
