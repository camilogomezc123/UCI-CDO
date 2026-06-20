@extends('layouts.app')
@section('title', 'Trazadores UCI')
@section('page-title', 'Pacientes Trazadores')

@push('styles')
<style>
    /* ── KPI cards ─────────────────────────────────────────────────────────── */
    .tz-kpi {
        border-radius: 12px; padding: 1.1rem 1.25rem;
        display: flex; align-items: center; gap: 1rem;
        color: #fff; position: relative; overflow: hidden;
    }
    .tz-kpi .tz-kpi-icon {
        font-size: 2rem; opacity: .22; position: absolute;
        right: 1rem; top: 50%; transform: translateY(-50%);
    }
    .tz-kpi .tz-kpi-val  { font-size: 1.9rem; font-weight: 800; line-height: 1; }
    .tz-kpi .tz-kpi-lbl  { font-size: .75rem; opacity: .88; margin-top: .15rem; }

    /* ── Semáforo global ───────────────────────────────────────────────────── */
    .sem-ring {
        width: 90px; height: 90px; border-radius: 50%;
        display: flex; flex-direction: column;
        align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.4rem;
        border: 5px solid currentColor;
    }
    .sem-ring .sem-sub { font-size: .6rem; font-weight: 500; margin-top: .1rem; }

    /* ── Navegación de patologías ──────────────────────────────────────────── */
    .tz-pat-nav {
        display: flex; align-items: center; gap: .5rem;
        flex-wrap: wrap; margin-bottom: 1rem;
    }
    .tz-pat-btn {
        border: 2px solid transparent; border-radius: 8px;
        padding: .45rem 1.1rem; font-size: .85rem; font-weight: 600;
        cursor: pointer; background: #fff; color: #555;
        transition: all .15s; text-decoration: none;
        display: inline-flex; align-items: center; gap: .4rem;
    }
    .tz-pat-btn:hover { border-color: #2d6a9f; color: #2d6a9f; }
    .tz-pat-btn.active { background: #1a3a5c; color: #fff; border-color: #1a3a5c; }
    .tz-pat-btn .tz-pat-count {
        background: rgba(255,255,255,.25); color: inherit;
        border-radius: 20px; font-size: .65rem; padding: .1rem .45rem;
    }
    .tz-pat-btn:not(.active) .tz-pat-count { background: #e9ecef; color: #555; }
    .tz-pat-future {
        border: 2px dashed #dee2e6; color: #adb5bd;
        border-radius: 8px; padding: .45rem 1rem;
        font-size: .8rem; cursor: default;
        display: inline-flex; align-items: center; gap: .4rem;
    }

    /* ── Sub-tabs de estado dentro de cada patología ───────────────────────── */
    .tz-subtab .nav-link {
        font-size: .82rem; padding: .4rem .9rem; color: #555;
    }
    .tz-subtab .nav-link.active { font-weight: 600; }

    /* ── Tabla interna ─────────────────────────────────────────────────────── */
    .tz-table th { font-size: .78rem; }
    .tz-table td { font-size: .84rem; }

    /* ── Badge de adherencia ───────────────────────────────────────────────── */
    .adh-badge {
        display: inline-block; border-radius: 6px;
        font-size: .7rem; font-weight: 700; padding: .2rem .55rem;
    }
</style>
@endpush

@section('content')

{{-- ═══════════════════════════════════════════════════════════════════════════
     DASHBOARD — KPIs globales
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="tz-kpi" style="background:linear-gradient(135deg,#0d6efd,#0a58ca)">
            <div>
                <div class="tz-kpi-val">{{ $global['total_activos'] }}</div>
                <div class="tz-kpi-lbl">Activos</div>
            </div>
            <i class="bi bi-clipboard2-pulse tz-kpi-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tz-kpi" style="background:linear-gradient(135deg,#fd7e14,#dc6000)">
            <div>
                <div class="tz-kpi-val">{{ $global['total_pendientes'] }}</div>
                <div class="tz-kpi-lbl">Pendientes encuesta DESPUÉS</div>
            </div>
            <i class="bi bi-clock-history tz-kpi-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="tz-kpi" style="background:linear-gradient(135deg,#198754,#146c43)">
            <div>
                <div class="tz-kpi-val">{{ $global['total_cerrados'] }}</div>
                <div class="tz-kpi-lbl">Cerrados</div>
            </div>
            <i class="bi bi-check2-all tz-kpi-icon"></i>
        </div>
    </div>
    <div class="col-6 col-md-3">
        @php
            $gp = $global['cumplimiento_prom'];
            $gpColor = $gp >= 90 ? '#198754' : ($gp >= 70 ? '#d97706' : '#dc3545');
        @endphp
        <div class="tz-kpi" style="background:linear-gradient(135deg,#1a3a5c,#2d6a9f)">
            <div>
                <div class="tz-kpi-val">{{ $gp > 0 ? $gp.'%' : '—' }}</div>
                <div class="tz-kpi-lbl">Cumplimiento global promedio</div>
            </div>
            <i class="bi bi-bar-chart-line tz-kpi-icon"></i>
        </div>
    </div>
</div>

{{-- ── Tendencia + semáforo ─────────────────────────────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up me-2 text-primary"></i>Tendencia mensual — últimos 6 meses</span>
            </div>
            <div class="card-body" style="padding:.75rem 1rem;">
                <canvas id="tzTrendChart" height="80"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <i class="bi bi-stoplight me-2 text-warning"></i>Estado por patología
            </div>
            <div class="card-body">
                @forelse($tiposActivos as $tipo)
                @php
                    $g   = $grupos[$tipo];
                    $cp  = $g['cumplimiento_prom'];
                    $col = $cp >= 90 ? '#198754' : ($cp >= 70 ? '#d97706' : '#dc3545');
                    $lbl = $cp >= 90 ? 'Verde' : ($cp >= 70 ? 'Amarillo' : ($cp > 0 ? 'Rojo' : 'Sin datos'));
                    $etiqs = $etiquetas[$tipo] ?? strtoupper($tipo);
                @endphp
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="sem-ring" style="color:{{ $col }}">
                        <span>{{ $cp > 0 ? round($cp).'%' : '—' }}</span>
                        <span class="sem-sub">{{ $lbl }}</span>
                    </div>
                    <div>
                        <div class="fw-bold" style="font-size:.95rem;">{{ $etiqs }}</div>
                        <div style="font-size:.78rem; color:#666;">
                            <span class="badge bg-primary">{{ $g['activos']->count() }} activos</span>
                            @if($g['pendientesDespues']->count() > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $g['pendientesDespues']->count() }} pendientes</span>
                            @endif
                            <span class="badge bg-success ms-1">{{ $g['cerrados']->count() }} cerrados</span>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-muted text-center" style="font-size:.85rem;">
                    <i class="bi bi-clipboard2-pulse d-block fs-3 opacity-25 mb-2"></i>
                    Aún no hay trazadores. Márquelos desde el censo de UCI.
                </p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     SUBGRUPOS POR PATOLOGÍA
     ═══════════════════════════════════════════════════════════════════════════ --}}
<div class="card">
    <div class="card-header">
        <i class="bi bi-folders me-2 text-primary"></i>Bandejas por patología
    </div>
    <div class="card-body pt-3">

        {{-- Selector de patología --}}
        <div class="tz-pat-nav" id="tzPatNav">
            @forelse($tiposActivos as $tipo)
            @php
                $g = $grupos[$tipo];
                $total = $g['activos']->count() + $g['pendientesDespues']->count() + $g['estadisticas']->count() + $g['cerrados']->count();
                $etiqs = $etiquetas[$tipo] ?? strtoupper($tipo);
            @endphp
            <button class="tz-pat-btn {{ $loop->first ? 'active' : '' }}"
                    onclick="mostrarPatologia('{{ $tipo }}')" id="btn-pat-{{ $tipo }}">
                <i class="bi bi-clipboard2-pulse"></i>
                {{ $etiqs }}
                <span class="tz-pat-count">{{ $total }}</span>
            </button>
            @empty
            @endforelse
            {{-- Placeholder futuras patologías --}}
            <span class="tz-pat-future">
                <i class="bi bi-plus-circle"></i> Nueva patología (próx.)
            </span>
        </div>

        {{-- Panel por patología --}}
        @forelse($tiposActivos as $tipo)
        @php
            $g = $grupos[$tipo];
            $etiqs = $etiquetas[$tipo] ?? strtoupper($tipo);
        @endphp
        <div id="panel-pat-{{ $tipo }}" class="tz-pat-panel" style="{{ $loop->first ? '' : 'display:none;' }}">

            {{-- Sub-tabs de estado --}}
            <ul class="nav nav-tabs tz-subtab mb-3" id="tabs-{{ $tipo }}">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab"
                       href="#{{ $tipo }}-activos">
                        <i class="bi bi-activity me-1 text-primary"></i>Activos
                        @if($g['activos']->count() > 0)
                            <span class="badge bg-primary ms-1">{{ $g['activos']->count() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab"
                       href="#{{ $tipo }}-pendientes">
                        <i class="bi bi-clock me-1 text-warning"></i>Pendientes DESPUÉS
                        @if($g['pendientesDespues']->count() > 0)
                            <span class="badge bg-warning text-dark ms-1">{{ $g['pendientesDespues']->count() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab"
                       href="#{{ $tipo }}-seguimiento">
                        <i class="bi bi-bar-chart me-1 text-info"></i>En seguimiento
                        @if($g['estadisticas']->count() > 0)
                            <span class="badge bg-info text-dark ms-1">{{ $g['estadisticas']->count() }}</span>
                        @endif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab"
                       href="#{{ $tipo }}-cerrados">
                        <i class="bi bi-archive me-1 text-success"></i>Cerrados
                        <span class="badge bg-success ms-1">{{ $g['cerrados']->count() }}</span>
                    </a>
                </li>
            </ul>

            <div class="tab-content" id="tc-{{ $tipo }}">

                {{-- ── Activos ────────────────────────────────────────── --}}
                <div class="tab-pane fade show active" id="{{ $tipo }}-activos">
                    @if($g['activos']->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                            Sin trazadores en diligenciamiento inicial.<br>
                            <a href="{{ route('pacientes.index') }}" class="btn btn-sm btn-primary mt-2">
                                <i class="bi bi-people me-1"></i>Ir al censo UCI
                            </a>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 tz-table">
                            <thead><tr>
                                <th>Paciente</th><th>Documento</th><th>Marcado</th><th>Acciones</th>
                            </tr></thead>
                            <tbody>
                            @foreach($g['activos'] as $t)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $t->paciente->nombre ?? '—' }}</div>
                                </td>
                                <td class="text-muted">{{ $t->paciente->documento ?? '—' }}</td>
                                <td>{{ $t->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('trazadores.edit', $t) }}"
                                       class="btn btn-sm btn-primary" target="_blank">
                                        <i class="bi bi-pencil me-1"></i>Completar
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                {{-- ── Pendientes DESPUÉS ─────────────────────────────── --}}
                <div class="tab-pane fade" id="{{ $tipo }}-pendientes">
                    @if($g['pendientesDespues']->isEmpty())
                        <p class="text-muted text-center py-3">Ningún trazador pendiente de encuesta DESPUÉS.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 tz-table">
                            <thead><tr>
                                <th>Paciente</th><th>Fecha objetivo</th><th>Días</th>
                                <th>Cumpl. inicial</th><th>Acciones</th>
                            </tr></thead>
                            <tbody>
                            @foreach($g['pendientesDespues'] as $t)
                            @php $dias = $t->diasRestantes(); @endphp
                            <tr class="{{ $dias !== null && $dias < 0 ? 'table-danger' : 'table-warning' }}">
                                <td>
                                    <div class="fw-semibold">{{ $t->paciente->nombre ?? '—' }}</div>
                                    <small class="text-muted">{{ $t->paciente->documento ?? '' }}</small>
                                </td>
                                <td>{{ $t->fecha_objetivo_despues?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    @if($dias !== null && $dias < 0)
                                        <span class="badge bg-danger">{{ abs($dias) }}d vencido</span>
                                    @elseif($dias === 0)
                                        <span class="badge bg-warning text-dark">Hoy</span>
                                    @else
                                        <span class="badge bg-secondary">{{ $dias }}d restantes</span>
                                    @endif
                                </td>
                                <td>
                                    @php $gl = $t->resultados['puntuacion_global_pct'] ?? null; @endphp
                                    @if($gl !== null)
                                        @php $bc = $gl >= 90 ? 'success' : ($gl >= 70 ? 'warning' : 'danger'); @endphp
                                        <span class="adh-badge bg-{{ $bc }} text-{{ in_array($bc,['warning']) ? 'dark' : 'white' }}">
                                            {{ $gl }}%
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="d-flex gap-1">
                                    <a href="{{ route('trazadores.despues.edit', $t) }}"
                                       class="btn btn-sm btn-warning" target="_blank">
                                        <i class="bi bi-pencil me-1"></i>Encuesta DESPUÉS
                                    </a>
                                    <a href="{{ route('trazadores.show', $t) }}"
                                       class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                {{-- ── En seguimiento ─────────────────────────────────── --}}
                <div class="tab-pane fade" id="{{ $tipo }}-seguimiento">
                    @if($g['estadisticas']->isEmpty())
                        <p class="text-muted text-center py-3">Aún no hay trazadores con formulario inicial guardado.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 tz-table">
                            <thead><tr>
                                <th>Paciente</th><th>Guardado</th><th>Encuesta DESPUÉS</th>
                                <th>Reanimación</th><th>ABCDEF</th><th>Global</th><th>Acciones</th>
                            </tr></thead>
                            <tbody>
                            @foreach($g['estadisticas'] as $t)
                            @php
                                $r  = $t->resultados ?? [];
                                $re = $r['adherencia_reanimacion_pct'] ?? null;
                                $ab = $r['adherencia_abcdef_pct']      ?? null;
                                $gl = $r['puntuacion_global_pct']      ?? null;
                                $bc = $gl >= 90 ? 'success' : ($gl >= 70 ? 'warning' : 'danger');
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $t->paciente->nombre ?? '—' }}</div>
                                    <small class="text-muted">{{ $t->paciente->documento ?? '' }}</small>
                                </td>
                                <td>{{ $t->fecha_guardado_inicial?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $t->fecha_objetivo_despues?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $re !== null ? $re.'%' : '—' }}</td>
                                <td>{{ $ab !== null ? $ab.'%' : '—' }}</td>
                                <td>
                                    @if($gl !== null)
                                        <span class="adh-badge bg-{{ $bc }} text-{{ $bc === 'warning' ? 'dark' : 'white' }}">{{ $gl }}%</span>
                                    @else —
                                    @endif
                                </td>
                                <td class="d-flex gap-1">
                                    <a href="{{ route('trazadores.show', $t) }}"
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-eye me-1"></i>Ver
                                    </a>
                                    <a href="{{ route('trazadores.edit', $t) }}"
                                       class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

                {{-- ── Cerrados ────────────────────────────────────────── --}}
                <div class="tab-pane fade" id="{{ $tipo }}-cerrados">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <span class="text-muted" style="font-size:.83rem;">
                            <i class="bi bi-check2-circle text-success me-1"></i>
                            {{ $g['cerrados']->count() }} caso(s) cerrado(s)
                            @if($g['cumplimiento_prom'])
                            — Cumplimiento promedio:
                            <strong class="{{ $g['cumplimiento_prom'] >= 90 ? 'text-success' : ($g['cumplimiento_prom'] >= 70 ? 'text-warning' : 'text-danger') }}">
                                {{ round($g['cumplimiento_prom'], 1) }}%
                            </strong>
                            @endif
                        </span>
                        <form method="GET" action="{{ route('trazadores.exportar') }}"
                              class="d-flex gap-2 align-items-center flex-wrap">
                            <input type="hidden" name="tipo_trazador" value="{{ $tipo }}">
                            <select name="periodo" class="form-select form-select-sm" style="width:auto;">
                                <option value="mensual">Mensual</option>
                                <option value="trimestral">Trimestral</option>
                                <option value="anual">Anual</option>
                            </select>
                            <input type="month" name="fecha" value="{{ now()->format('Y-m') }}"
                                   class="form-control form-control-sm" style="width:auto;">
                            <button type="submit" class="btn btn-sm btn-success">
                                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
                            </button>
                        </form>
                    </div>

                    @if($g['cerrados']->isEmpty())
                        <p class="text-muted text-center py-3">No hay casos cerrados aún.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 tz-table">
                            <thead><tr>
                                <th>Paciente</th><th>Cierre</th>
                                <th>Reanimación</th><th>ABCDEF</th><th>Global</th>
                                <th>Barthel Δ</th><th>Acciones</th>
                            </tr></thead>
                            <tbody>
                            @foreach($g['cerrados'] as $t)
                            @php
                                $r    = $t->resultados ?? [];
                                $re   = $r['adherencia_reanimacion_pct'] ?? null;
                                $ab   = $r['adherencia_abcdef_pct']      ?? null;
                                $gl   = $r['puntuacion_global_pct']      ?? null;
                                $bart = $r['comparativo']['barthel_total'] ?? null;
                                $bc   = $gl !== null ? ($gl >= 90 ? 'success' : ($gl >= 70 ? 'warning' : 'danger')) : 'secondary';
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $t->paciente->nombre ?? '—' }}</div>
                                    <small class="text-muted">{{ $t->paciente->documento ?? '' }}</small>
                                </td>
                                <td>{{ $t->fecha_cierre?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $re !== null ? $re.'%' : '—' }}</td>
                                <td>{{ $ab !== null ? $ab.'%' : '—' }}</td>
                                <td>
                                    @if($gl !== null)
                                        <span class="adh-badge bg-{{ $bc }} text-{{ $bc === 'warning' ? 'dark' : 'white' }}">{{ $gl }}%</span>
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    @if($bart !== null)
                                        <span class="{{ $bart >= 0 ? 'text-success' : 'text-danger' }} fw-semibold">
                                            {{ $bart >= 0 ? '+' : '' }}{{ $bart }}
                                        </span>
                                    @else —
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('trazadores.show', $t) }}"
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-eye me-1"></i>Ver
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>

            </div>{{-- /tab-content --}}
        </div>{{-- /panel-pat --}}
        @empty
        <div class="text-center text-muted py-5">
            <i class="bi bi-clipboard2-pulse fs-1 d-block mb-2 opacity-25"></i>
            <p>No hay pacientes trazadores registrados aún.</p>
            <a href="{{ route('pacientes.index') }}" class="btn btn-primary">
                <i class="bi bi-people me-1"></i>Ir al censo UCI para marcar pacientes
            </a>
        </div>
        @endforelse

    </div>{{-- /card-body --}}
</div>{{-- /card --}}

@endsection

@push('scripts')
<script>
// ── Cambio de patología activa ────────────────────────────────────────────────
function mostrarPatologia(tipo) {
    document.querySelectorAll('.tz-pat-panel').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tz-pat-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('panel-pat-' + tipo).style.display = '';
    document.getElementById('btn-pat-' + tipo).classList.add('active');
}

// ── Gráfico de tendencia ──────────────────────────────────────────────────────
const tzCtx = document.getElementById('tzTrendChart');
if (tzCtx) {
    const labels = @json(collect($tendencia)->pluck('label'));
    const cerrados = @json(collect($tendencia)->pluck('cerrados'));
    const cumpl    = @json(collect($tendencia)->pluck('cumplimiento'));

    new Chart(tzCtx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Casos cerrados',
                    data: cerrados,
                    backgroundColor: 'rgba(13,110,253,.25)',
                    borderColor: '#0d6efd',
                    borderWidth: 2,
                    borderRadius: 6,
                    yAxisID: 'y',
                },
                {
                    label: 'Cumplimiento % (prom.)',
                    data: cumpl,
                    type: 'line',
                    borderColor: '#198754',
                    backgroundColor: 'transparent',
                    pointBackgroundColor: '#198754',
                    tension: .35,
                    yAxisID: 'y2',
                },
            ],
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 } } },
            },
            scales: {
                y:  { beginAtZero: true, title: { display: true, text: 'Cerrados', font: { size: 10 } }, ticks: { font: { size: 10 } } },
                y2: { beginAtZero: true, max: 100, position: 'right', grid: { drawOnChartArea: false },
                      title: { display: true, text: 'Cumplimiento %', font: { size: 10 } },
                      ticks: { font: { size: 10 }, callback: v => v + '%' } },
            },
        },
    });
}
</script>
@endpush
