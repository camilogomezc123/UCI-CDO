@extends('layouts.app')
@section('title', 'Informe de Mortalidad UCI')
@section('page-title', 'Informe de Mortalidad UCI')

@section('content')

{{-- Filtro de fechas --}}
<div class="card mb-4">
  <div class="card-body py-2">
    <form method="GET" action="{{ route('reportes.mortalidad') }}" class="d-flex align-items-center gap-3 flex-wrap">
      <label class="fw-semibold mb-0" style="font-size:0.85rem;">Período:</label>
      <div class="input-group input-group-sm" style="max-width:180px;">
        <span class="input-group-text">Desde</span>
        <input type="date" name="desde" class="form-control" value="{{ $desde->format('Y-m-d') }}">
      </div>
      <div class="input-group input-group-sm" style="max-width:180px;">
        <span class="input-group-text">Hasta</span>
        <input type="date" name="hasta" class="form-control" value="{{ $hasta->format('Y-m-d') }}">
      </div>
      <button type="submit" class="btn btn-sm btn-primary">
        <i class="bi bi-search me-1"></i>Filtrar
      </button>
      <a href="{{ route('reportes.mortalidad') }}" class="btn btn-sm btn-outline-secondary">Último año</a>
      <span class="ms-auto text-muted" style="font-size:0.78rem;">
        {{ $desde->format('d/m/Y') }} — {{ $hasta->format('d/m/Y') }}
      </span>
    </form>
  </div>
</div>

@if($agregado['total'] === 0)
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>No hay fallecidos registrados en el período seleccionado.
</div>
@else

{{-- ══ KPIs fila 1 ══ --}}
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3">
    <div class="kpi-card" style="background:linear-gradient(135deg,#2d2d2d,#555)">
      <i class="bi bi-person-x-fill kpi-icon"></i>
      <div class="kpi-number">{{ $agregado['total'] }}</div>
      <div class="kpi-label">Fallecidos en el período</div>
      <div class="kpi-sub">{{ $desde->format('d/m/Y') }} — {{ $hasta->format('d/m/Y') }}</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="kpi-card" style="background:linear-gradient(135deg,#495057,#6c757d)">
      <i class="bi bi-clock kpi-icon"></i>
      <div class="kpi-number">{{ $agregado['dias_uci_avg'] ?? '—' }}d</div>
      <div class="kpi-label">Estancia media hasta fallecimiento</div>
      <div class="kpi-sub">Días promedio en UCI</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="kpi-card" style="background:linear-gradient(135deg,#6f42c1,#59359a)">
      <i class="bi bi-activity kpi-icon"></i>
      <div class="kpi-number">{{ $agregado['sofa_max_avg'] ?? '—' }}</div>
      <div class="kpi-label">SOFA máximo promedio</div>
      <div class="kpi-sub">SOFA ingreso prom.: {{ $agregado['sofa_adm_avg'] ?? '—' }}</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="kpi-card" style="background:linear-gradient(135deg,#dc3545,#b02a37)">
      <i class="bi bi-thermometer-high kpi-icon"></i>
      <div class="kpi-number">{{ $agregado['news_max_avg'] ?? '—' }}</div>
      <div class="kpi-label">NEWS máximo promedio</div>
      <div class="kpi-sub">NEWS ingreso prom.: {{ $agregado['news_adm_avg'] ?? '—' }}</div>
    </div>
  </div>
</div>

{{-- ══ KPIs fila 2 ══ --}}
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="card text-center p-3 h-100" style="border-top:3px solid #0dcaf0;">
      <div class="fw-bold fs-3 text-info">{{ $agregado['pct_vmi'] }}%</div>
      <div style="font-size:0.82rem;" class="fw-semibold">Con VMI</div>
      <div class="text-muted" style="font-size:0.72rem;">Prom. {{ $agregado['dias_vmi_avg'] }} días</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card text-center p-3 h-100" style="border-top:3px solid #fd7e14;">
      <div class="fw-bold fs-3 text-warning">{{ $agregado['pct_vaso'] }}%</div>
      <div style="font-size:0.82rem;" class="fw-semibold">Con vasopresor</div>
      <div class="text-muted" style="font-size:0.72rem;">Prom. {{ $agregado['dias_vaso_avg'] }} días</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card text-center p-3 h-100" style="border-top:3px solid #ffc107;">
      <div class="fw-bold fs-3" style="color:#e05c00;">{{ $agregado['pct_delirium'] }}%</div>
      <div style="font-size:0.82rem;" class="fw-semibold">Con delirium (CAM+)</div>
      <div class="text-muted" style="font-size:0.72rem;">Al menos un día positivo</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="card text-center p-3 h-100" style="border-top:3px solid #198754;">
      <div class="fw-bold fs-3 text-success">{{ $agregado['pct_mov_temp'] }}%</div>
      <div style="font-size:0.82rem;" class="fw-semibold">Movilización temprana</div>
      <div class="text-muted" style="font-size:0.72rem;">IMS pico prom.: {{ $agregado['ims_pico_avg'] ?? '—' }}/10</div>
    </div>
  </div>
</div>

{{-- ══ Distribución por CIE-10 ══ --}}
@if(count($morbilidad) > 0)
<div class="card mb-4">
  <div class="card-header d-flex align-items-center gap-2">
    <i class="bi bi-file-medical text-danger"></i>
    <strong>Distribución por CIE-10</strong>
    <span class="text-muted ms-1" style="font-size:0.75rem;">(códigos registrados en los pacientes fallecidos · % sobre total)</span>
  </div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-lg-7">
        @foreach($morbilidad as $cie => $datos)
        <div class="mb-2">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span style="font-size:0.83rem;max-width:65%;word-break:break-word;">{{ $cie }}</span>
            <div class="d-flex gap-2 align-items-center flex-shrink-0">
              <span class="badge bg-danger rounded-pill">{{ $datos['count'] }} pac.</span>
              <span class="badge bg-light text-dark border">{{ $datos['estancia_avg'] }}d</span>
              <span class="fw-bold" style="min-width:38px;text-align:right;font-size:0.83rem;">{{ $datos['pct'] }}%</span>
            </div>
          </div>
          <div class="progress" style="height:8px;border-radius:4px;">
            <div class="progress-bar bg-danger" style="width:{{ $datos['pct'] }}%;"></div>
          </div>
        </div>
        @endforeach
      </div>
      <div class="col-lg-5 d-flex align-items-center">
        <canvas id="chartMorbilidad" style="max-height:280px;width:100%;"></canvas>
      </div>
    </div>
  </div>
</div>
@endif

{{-- ══ Alertas de decisión ══ --}}
@php
  $conBundleBajo     = $pacientesData->filter(fn($d) => $d['bundlePct'] !== null && $d['bundlePct'] < 50)->count();
  $conSofaCreciente  = $pacientesData->filter(fn($d) => ($d['sofaDelta'] ?? 0) > 2)->count();
  $conMovTardia      = $pacientesData->filter(fn($d) => $d['tipoMov'] === 'Tardía')->count();
  $conVmiLargo       = $pacientesData->filter(fn($d) => $d['diasVmi'] > 7)->count();
@endphp
@if($conBundleBajo > 0 || $conSofaCreciente > 0 || $conMovTardia > 0)
<div class="card mb-4 border-warning">
  <div class="card-header bg-warning bg-opacity-10 d-flex align-items-center gap-2">
    <i class="bi bi-lightbulb-fill text-warning"></i>
    <strong>Señales para decisión clínica</strong>
  </div>
  <div class="card-body">
    <div class="row g-3">
      @if($conBundleBajo > 0)
      <div class="col-md-4">
        <div class="p-3 rounded" style="background:#fff8e1;border-left:3px solid #ffc107;">
          <div class="fw-bold fs-4 text-warning">{{ $conBundleBajo }}</div>
          <div class="fw-semibold" style="font-size:0.85rem;">Pacientes con Bundle ABCDEF &lt; 50%</div>
          <div class="text-muted" style="font-size:0.78rem;">Evaluar adherencia al protocolo</div>
        </div>
      </div>
      @endif
      @if($conSofaCreciente > 0)
      <div class="col-md-4">
        <div class="p-3 rounded" style="background:#fdecea;border-left:3px solid #dc3545;">
          <div class="fw-bold fs-4 text-danger">{{ $conSofaCreciente }}</div>
          <div class="fw-semibold" style="font-size:0.85rem;">SOFA aumentó &gt; 2 puntos</div>
          <div class="text-muted" style="font-size:0.78rem;">Deterioro orgánico progresivo</div>
        </div>
      </div>
      @endif
      @if($conMovTardia > 0)
      <div class="col-md-4">
        <div class="p-3 rounded" style="background:#e8f5e9;border-left:3px solid #198754;">
          <div class="fw-bold fs-4 text-success">{{ $conMovTardia }}</div>
          <div class="fw-semibold" style="font-size:0.85rem;">Movilización tardía (&gt; 48h)</div>
          <div class="text-muted" style="font-size:0.78rem;">Revisar barreras de movilización</div>
        </div>
      </div>
      @endif
      @if($conVmiLargo > 0)
      <div class="col-md-4">
        <div class="p-3 rounded" style="background:#e8f0fe;border-left:3px solid #0d6efd;">
          <div class="fw-bold fs-4 text-primary">{{ $conVmiLargo }}</div>
          <div class="fw-semibold" style="font-size:0.85rem;">VMI &gt; 7 días</div>
          <div class="text-muted" style="font-size:0.78rem;">Ventilación mecánica prolongada</div>
        </div>
      </div>
      @endif
    </div>
  </div>
</div>
@endif

{{-- ══ Tabla individual por paciente ══ --}}
<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><i class="bi bi-table text-danger me-2"></i><strong>Detalle por Paciente Fallecido</strong></span>
    <span class="badge bg-dark">{{ $agregado['total'] }} registros</span>
  </div>
  <div class="card-body p-0">
    <div class="accordion accordion-flush" id="accordionPacientes">
      @foreach($pacientesData as $idx => $d)
      @php $pac = $d['p']; @endphp
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#pac{{ $pac->id }}">
            <div class="d-flex align-items-center gap-3 w-100 flex-wrap" style="font-size:0.85rem;">
              {{-- Nombre --}}
              <div style="min-width:200px;">
                <span class="fw-bold">{{ $pac->nombre }}</span>
                <span class="text-muted ms-2" style="font-size:0.75rem;">{{ $pac->documento }}</span>
              </div>
              {{-- Datos básicos --}}
              <span class="badge bg-secondary">{{ $pac->edad }} años · {{ $pac->sexo=='F'?'F':'M' }}</span>
              <span class="text-muted" style="font-size:0.78rem;">
                {{ $pac->egreso_uci?->format('d/m/Y') }}
              </span>
              <span class="badge bg-light text-dark border">{{ $d['diasUci'] }}d UCI</span>
              {{-- Escalas clave --}}
              @if($d['sofaMax'] !== null)
              <span class="badge {{ $d['sofaMax'] >= 11 ? 'bg-danger' : ($d['sofaMax'] >= 7 ? 'bg-warning text-dark' : 'bg-secondary') }}">
                SOFA {{ $d['sofaMax'] }}
              </span>
              @endif
              @if($d['newsMax'] !== null)
              <span class="badge {{ $d['newsMax'] >= 7 ? 'bg-danger' : 'bg-secondary' }}">NEWS {{ $d['newsMax'] }}</span>
              @endif
              {{-- Soportes activos --}}
              @if($d['diasVmi'] > 0)
                <span class="badge bg-info text-dark"><i class="bi bi-lungs me-1"></i>VMI {{ $d['diasVmi'] }}d</span>
              @endif
              @if($d['diasVaso'] > 0)
                <span class="badge bg-warning text-dark">Vaso {{ $d['diasVaso'] }}d</span>
              @endif
              {{-- CIE-10 en el header --}}
              <div class="ms-auto d-flex gap-1 flex-wrap">
                @foreach($d['cie10s']->take(2) as $cie)
                  <span class="badge" style="background:#fdecea;color:#b71c1c;font-size:0.65rem;">{{ Str::limit($cie, 30) }}</span>
                @endforeach
              </div>
            </div>
          </button>
        </h2>
        <div id="pac{{ $pac->id }}" class="accordion-collapse collapse">
          <div class="accordion-body" style="background:#fafafa;">
            <div class="row g-3">

              {{-- BLOQUE 1: Escalas de gravedad --}}
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header py-2 bg-danger bg-opacity-10">
                    <i class="bi bi-activity text-danger me-1"></i><strong style="font-size:0.82rem;">Escalas de Gravedad</strong>
                  </div>
                  <div class="card-body py-2">
                    <table class="table table-sm mb-0" style="font-size:0.8rem;">
                      <thead><tr><th>Escala</th><th>Ingreso</th><th>Máx</th><th>Último</th><th>Tendencia</th></tr></thead>
                      <tbody>
                        <tr>
                          <td class="fw-semibold">NEWS</td>
                          <td>{{ $d['newsAdm'] ?? '—' }}</td>
                          <td><span class="badge {{ ($d['newsMax']??0)>=7?'bg-danger':'bg-secondary' }}">{{ $d['newsMax'] ?? '—' }}</span></td>
                          <td>{{ $d['newsUlt'] ?? '—' }}</td>
                          <td style="font-size:0.72rem;">{{ $d['newsTend'] ?? '—' }}</td>
                        </tr>
                        <tr>
                          <td class="fw-semibold">SOFA</td>
                          <td>{{ $d['sofaAdm'] ?? '—' }}</td>
                          <td><span class="badge {{ ($d['sofaMax']??0)>=11?'bg-danger':(($d['sofaMax']??0)>=7?'bg-warning text-dark':'bg-secondary') }}">{{ $d['sofaMax'] ?? '—' }}</span></td>
                          <td>{{ $d['sofaUlt'] ?? '—' }}</td>
                          <td style="font-size:0.72rem;">{{ $d['sofaTend'] ?? '—' }}</td>
                        </tr>
                        <tr>
                          <td class="fw-semibold text-muted" colspan="4">Delta SOFA (ingreso→fallecimiento)</td>
                          <td>
                            @if($d['sofaDelta'] !== null)
                              <span class="badge {{ $d['sofaDelta'] > 0 ? 'bg-danger' : 'bg-success' }}">
                                {{ $d['sofaDelta'] > 0 ? '+' : '' }}{{ $d['sofaDelta'] }}
                              </span>
                            @else —
                            @endif
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              {{-- BLOQUE 2: Soportes --}}
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header py-2 bg-info bg-opacity-10">
                    <i class="bi bi-lungs text-info me-1"></i><strong style="font-size:0.82rem;">Soportes (días)</strong>
                  </div>
                  <div class="card-body py-2">
                    <div class="row g-2 text-center">
                      <div class="col-6">
                        <div class="p-2 rounded" style="background:#e8f4fd;">
                          <div class="fw-bold fs-4 text-info">{{ $d['diasVmi'] }}</div>
                          <div style="font-size:0.72rem;" class="text-muted">Días VMI</div>
                          @if($d['pctVmiDias'] !== null)
                            <div style="font-size:0.68rem;" class="text-info">{{ $d['pctVmiDias'] }}% estancia</div>
                          @endif
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="p-2 rounded" style="background:#fff8e1;">
                          <div class="fw-bold fs-4 text-warning">{{ $d['diasVaso'] }}</div>
                          <div style="font-size:0.72rem;" class="text-muted">Días Vasopresor</div>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="p-2 rounded" style="background:#fce4ec;">
                          <div class="fw-bold fs-4" style="color:#c62828;">{{ $d['diasIno'] }}</div>
                          <div style="font-size:0.72rem;" class="text-muted">Días Inotrópico</div>
                        </div>
                      </div>
                      <div class="col-6">
                        <div class="p-2 rounded" style="background:{{ $d['diasVmiVaso'] > 3 ? '#fdecea' : '#f8f9fa' }};">
                          <div class="fw-bold fs-4 {{ $d['diasVmiVaso'] > 3 ? 'text-danger' : '' }}">{{ $d['diasVmiVaso'] }}</div>
                          <div style="font-size:0.72rem;" class="text-muted">VMI + Vaso simultáneo</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {{-- BLOQUE 3: Dolor · Sedación · Nutrición --}}
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header py-2" style="background:#fff3e0;">
                    <i class="bi bi-emoji-frown me-1" style="color:#e05c00;"></i><strong style="font-size:0.82rem;">Dolor · Sedación · Nutrición</strong>
                  </div>
                  <div class="card-body py-2">
                    <table class="table table-sm mb-0" style="font-size:0.8rem;">
                      <tbody>
                        <tr>
                          <td class="text-muted">EVA máximo</td>
                          <td>
                            @if($d['evaMax'] !== null)
                              <span class="badge {{ (float)$d['evaMax'] > 4 ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $d['evaMax'] }}</span>
                              @if($d['diasDolorEva'] > 0)<span class="text-muted ms-1" style="font-size:0.7rem;">({{ $d['diasDolorEva'] }}d &gt;4)</span>@endif
                            @else — @endif
                          </td>
                        </tr>
                        <tr>
                          <td class="text-muted">BPS máximo</td>
                          <td>
                            @if($d['bpsMax'] !== null)
                              <span class="badge {{ (float)$d['bpsMax'] > 6 ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ $d['bpsMax'] }}</span>
                              @if($d['diasDolorBps'] > 0)<span class="text-muted ms-1" style="font-size:0.7rem;">({{ $d['diasDolorBps'] }}d &gt;6)</span>@endif
                            @else — @endif
                          </td>
                        </tr>
                        <tr>
                          <td class="text-muted">RASS promedio</td>
                          <td>{{ $d['rassProm'] ?? '—' }}
                            @if($d['rassMin'] !== null)<span class="text-muted" style="font-size:0.7rem;">(mín: {{ $d['rassMin'] }})</span>@endif
                          </td>
                        </tr>
                        <tr>
                          <td class="text-muted">MUST</td>
                          <td>{{ $d['mustUlt'] ?? '—' }}</td>
                        </tr>
                        <tr>
                          <td class="text-muted">Nutrición</td>
                          <td style="font-size:0.75rem;">{{ $d['nutricionUlt'] ?? '—' }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              {{-- BLOQUE 4: Movilización y funcionalidad --}}
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header py-2 bg-success bg-opacity-10">
                    <i class="bi bi-person-walking text-success me-1"></i><strong style="font-size:0.82rem;">Movilización · Funcionalidad</strong>
                  </div>
                  <div class="card-body py-2">
                    <div class="row g-2 text-center mb-2">
                      <div class="col-4">
                        <div class="p-2 rounded" style="background:#f8f9fa;">
                          <div class="fw-bold fs-5 {{ $d['barthelAdm'] !== null && (float)$d['barthelAdm'] < 40 ? 'text-danger' : '' }}">{{ $d['barthelAdm'] ?? '—' }}</div>
                          <div style="font-size:0.68rem;" class="text-muted">Barthel ingreso</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded" style="background:#f8f9fa;">
                          <div class="fw-bold fs-5">{{ $d['barthelUlt'] ?? '—' }}</div>
                          <div style="font-size:0.68rem;" class="text-muted">Barthel último</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded" style="background:#e8f5e9;">
                          <div class="fw-bold fs-5 text-success">{{ $d['imsPico'] ?? '—' }}</div>
                          <div style="font-size:0.68rem;" class="text-muted">IMS pico</div>
                        </div>
                      </div>
                    </div>
                    <table class="table table-sm mb-0" style="font-size:0.8rem;">
                      <tbody>
                        <tr>
                          <td class="text-muted">Días hasta 1ª movilización</td>
                          <td>
                            @if($d['diasHastaMov'] !== null)
                              <span class="badge {{ $d['diasHastaMov'] > 2 ? 'bg-warning text-dark' : 'bg-success' }}">{{ $d['diasHastaMov'] }}d</span>
                            @else <span class="text-muted">No registrado</span>
                            @endif
                          </td>
                        </tr>
                        <tr>
                          <td class="text-muted">Tipo movilización</td>
                          <td>
                            @if($d['tipoMov'])
                              <span class="badge {{ $d['tipoMov']=='Temprana'?'bg-success':'bg-warning text-dark' }}">{{ $d['tipoMov'] }}</span>
                            @else — @endif
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              {{-- BLOQUE 5: Delirium + Bundle --}}
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header py-2" style="background:#fff8e1;">
                    <i class="bi bi-brain me-1 text-warning"></i><strong style="font-size:0.82rem;">Delirium · Bundle ABCDEF</strong>
                  </div>
                  <div class="card-body py-2">
                    <div class="row g-2 text-center mb-2">
                      <div class="col-4">
                        <div class="p-2 rounded" style="background:{{ $d['camPos'] > 0 ? '#fdecea' : '#f8f9fa' }};">
                          <div class="fw-bold fs-5 {{ $d['camPos'] > 0 ? 'text-danger' : '' }}">{{ $d['camPos'] }}</div>
                          <div style="font-size:0.68rem;" class="text-muted">Días CAM+</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded" style="background:#f8f9fa;">
                          <div class="fw-bold fs-5">{{ $d['camTot'] }}</div>
                          <div style="font-size:0.68rem;" class="text-muted">Días evaluados</div>
                        </div>
                      </div>
                      <div class="col-4">
                        <div class="p-2 rounded" style="background:{{ ($d['pctDeli']??0) > 50 ? '#fdecea' : '#f8f9fa' }};">
                          <div class="fw-bold fs-5 {{ ($d['pctDeli']??0) > 50 ? 'text-danger' : '' }}">{{ $d['pctDeli'] !== null ? $d['pctDeli'].'%' : '—' }}</div>
                          <div style="font-size:0.68rem;" class="text-muted">% días delirium</div>
                        </div>
                      </div>
                    </div>
                    <hr class="my-2">
                    <div class="row g-2 text-center">
                      <div class="col-6">
                        <div class="fw-bold fs-5 {{ $d['bundlePct'] !== null && $d['bundlePct'] < 50 ? 'text-danger' : 'text-success' }}">
                          {{ $d['bundlePct'] !== null ? $d['bundlePct'].'%' : '—' }}
                        </div>
                        <div style="font-size:0.72rem;" class="text-muted">Cumplimiento Bundle</div>
                      </div>
                      <div class="col-6">
                        <div class="fw-bold fs-5">{{ $d['bundleDias'] }}</div>
                        <div style="font-size:0.72rem;" class="text-muted">Días con bundle</div>
                      </div>
                    </div>
                    @if($d['bundlePct'] !== null)
                    <div class="progress mt-2" style="height:5px;">
                      <div class="progress-bar {{ $d['bundlePct'] >= 80 ? 'bg-success' : ($d['bundlePct'] >= 50 ? 'bg-warning' : 'bg-danger') }}"
                           style="width:{{ $d['bundlePct'] }}%"></div>
                    </div>
                    @endif
                  </div>
                </div>
              </div>

              {{-- BLOQUE 6: CIE-10 y Diagnósticos --}}
              <div class="col-lg-4">
                <div class="card h-100">
                  <div class="card-header py-2 bg-dark bg-opacity-10">
                    <i class="bi bi-file-medical me-1"></i><strong style="font-size:0.82rem;">CIE-10 · Diagnósticos</strong>
                  </div>
                  <div class="card-body py-2">
                    @if($d['cie10s']->isNotEmpty())
                      <div class="mb-1" style="font-size:0.72rem;color:#888;text-transform:uppercase;letter-spacing:1px;">Códigos CIE-10</div>
                      @foreach($d['cie10s'] as $cie)
                        <div style="font-size:0.8rem;background:#fdecea;color:#b71c1c;padding:3px 8px;border-radius:4px;margin-bottom:3px;font-weight:600;">{{ $cie }}</div>
                      @endforeach
                    @else
                      <div class="text-muted" style="font-size:0.8rem;">Sin CIE-10 registrado</div>
                    @endif
                    @if($d['diags']->isNotEmpty())
                      <div class="mt-2 mb-1" style="font-size:0.72rem;color:#888;text-transform:uppercase;letter-spacing:1px;">Diagnósticos</div>
                      @foreach($d['diags']->take(3) as $dg)
                        <div style="font-size:0.75rem;background:#f8f9fa;padding:3px 8px;border-radius:4px;margin-bottom:3px;">{{ Str::limit($dg, 120) }}</div>
                      @endforeach
                    @endif
                  </div>
                </div>
              </div>

            </div>{{-- /row --}}

            <div class="mt-2 text-end">
              <a href="{{ route('pacientes.show', $pac) }}" class="btn btn-sm btn-outline-dark" target="_blank">
                <i class="bi bi-person-lines-fill me-1"></i>Ver ficha completa
              </a>
            </div>

          </div>{{-- /accordion-body --}}
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>

@endif {{-- /total > 0 --}}

@endsection

@push('scripts')
@if(count($morbilidad) > 0)
<script>
new Chart(document.getElementById('chartMorbilidad'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_keys($morbilidad)) !!},
        datasets: [{
            data: {!! json_encode(array_column(array_values($morbilidad), 'count')) !!},
            backgroundColor: ['#dc3545','#e07000','#ffc107','#6f42c1','#0d6efd','#20c997','#fd7e14','#6c757d','#0dcaf0','#198754','#e91e63','#9c27b0','#3f51b5','#009688','#ff5722','#795548','#607d8b','#f06292','#aed581','#4dd0e1'],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 14, padding: 10 } }
        },
        cutout: '55%'
    }
});
</script>
@endif
@endpush
