<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Plantilla Diaria UCI — {{ now()->format('d/m/Y') }}</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root { --co-blue:#0d6efd; --co-dark:#2d2d2d; }
body { background:#f4f6f9; font-family:'Segoe UI',sans-serif; }

.topbar {
    background:var(--co-dark); color:#fff;
    padding:.75rem 1.5rem; display:flex; align-items:center; gap:1rem;
    border-bottom:3px solid var(--co-blue);
}
.topbar-title { flex:1; font-size:1rem; font-weight:700; }
.topbar-date  { font-size:.8rem; opacity:.7; }

.section-card {
    background:#fff; border-radius:12px;
    box-shadow:0 2px 12px rgba(0,0,0,.07);
    margin-bottom:1.25rem; overflow:hidden;
}
.section-header {
    display:flex; align-items:center; gap:.75rem;
    padding:.75rem 1.25rem; cursor:pointer; user-select:none;
    border-bottom:1px solid #f0f0f0;
}
.section-icon {
    width:36px; height:36px; border-radius:8px;
    display:flex; align-items:center; justify-content:center;
    font-size:1.1rem; flex-shrink:0;
}
.section-header h6 { margin:0; font-size:.9rem; font-weight:700; color:var(--co-dark); }
.section-header small { font-size:.75rem; color:#6c757d; }
.badge-count { margin-left:auto; font-size:.75rem; padding:.25em .65em; border-radius:999px; font-weight:700; }
.chevron { margin-left:.5rem; transition:transform .2s; }
.collapsed .chevron { transform:rotate(-90deg); }

.pt-table { font-size:.82rem; }
.pt-table thead th {
    background:#f8f9fa; font-weight:700; font-size:.75rem;
    color:#555; border-bottom:2px solid #e5e9f0; white-space:nowrap;
}
.pt-table tbody td { vertical-align:middle; }
.pt-table tbody tr:hover td { background:#f0f4ff; }
.pt-table .form-control, .pt-table .form-select { font-size:.82rem; }
.patient-name { font-weight:600; color:var(--co-dark); }
.patient-doc  { font-size:.72rem; color:#888; }
.cama-badge   { font-weight:700; color:var(--co-blue); }

.save-bar {
    position:sticky; bottom:0; background:rgba(255,255,255,.95);
    backdrop-filter:blur(6px); border-top:1px solid #e5e9f0;
    padding:.75rem 1.5rem; display:flex; gap:.75rem; align-items:center;
    z-index:100;
}
.empty-state { text-align:center; padding:2rem; color:#aaa; }
.empty-state i { font-size:2rem; display:block; margin-bottom:.5rem; }

@media print {
    body { background:#fff; font-size:11px; }
    .topbar { background:#000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    .no-print { display:none !important; }
    .section-card { box-shadow:none; border:1px solid #ccc; page-break-inside:avoid; }
    .section-body { display:block !important; }
    .form-control, .form-select {
        border:none; border-bottom:1px solid #999; border-radius:0;
        padding:0; background:transparent; font-size:11px;
    }
    input[type=checkbox] { accent-color:#000; }
    .btn { display:none !important; }
    .alert { display:none !important; }
    .save-bar { display:none !important; }
}
</style>
</head>
<body>

<div class="topbar no-print">
    <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light" style="font-size:.75rem;">
        <i class="bi bi-arrow-left me-1"></i>Panel
    </a>
    <div class="topbar-title">
        <i class="bi bi-clipboard2-pulse me-2"></i>Plantilla Diaria de Registro UCI
    </div>
    <div class="topbar-date">
        <i class="bi bi-calendar3 me-1"></i>{{ now()->translatedFormat('l d \d\e F \d\e Y') }}
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-light" style="font-size:.75rem;">
        <i class="bi bi-printer me-1"></i>Imprimir / PDF
    </button>
</div>

<div class="d-none d-print-block p-3 border-bottom mb-2">
    <strong>CLÍNICA DE OCCIDENTE — UCI</strong>
    &nbsp;·&nbsp; Registro diario complementario
    &nbsp;·&nbsp; Fecha: <strong>{{ now()->format('d/m/Y') }}</strong>
    &nbsp;·&nbsp; Responsable: ___________________________
</div>

<div class="container-fluid py-3 px-3 px-md-4" style="max-width:1400px;">

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show no-print" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

@php
    $totalPendientes = $sinIngreso->count() + $sinCamUci->count() + $sinBundle->count() + $pendientesEgreso->count() + $sinCausas->count();
    $causasEtiquetas = \App\Models\CausaEstancia::etiquetas();
@endphp

@if($totalPendientes === 0)
<div class="section-card no-print">
    <div class="p-4 text-center text-success">
        <i class="bi bi-check-circle-fill display-5 d-block mb-2"></i>
        <strong>¡Todo al día!</strong> No hay registros pendientes para los pacientes activos.
    </div>
</div>
@endif

<form method="POST" action="{{ route('plantilla-diaria.guardar') }}">
@csrf

{{-- ── 1. INGRESO UCI ─────────────────────────────────── --}}
<div class="section-card">
    <div class="section-header" data-bs-toggle="collapse" data-bs-target="#secIngreso">
        <div class="section-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-door-open"></i></div>
        <div>
            <h6>Fecha de ingreso UCI sin registrar</h6>
            <small>Pacientes activos sin fecha de ingreso a la UCI</small>
        </div>
        <span class="badge-count {{ $sinIngreso->count() > 0 ? 'bg-danger text-white' : 'bg-light text-muted' }}">
            {{ $sinIngreso->count() }}
        </span>
        <i class="bi bi-chevron-down chevron no-print"></i>
    </div>
    <div class="collapse show" id="secIngreso">
        @if($sinIngreso->isEmpty())
            <div class="empty-state"><i class="bi bi-check-circle text-success"></i>Sin pendientes</div>
        @else
        <div class="table-responsive">
        <table class="table table-hover pt-table mb-0">
            <thead><tr>
                <th style="width:210px">Paciente</th>
                <th>Cama</th>
                <th>Subunidad</th>
                <th>Soporte ventilatorio</th>
                <th>Fecha ingreso UCI <span class="text-danger">*</span></th>
            </tr></thead>
            <tbody>
            @foreach($sinIngreso as $p)
            <tr>
                <td>
                    <div class="patient-name">{{ $p->nombre }}</div>
                    <div class="patient-doc">{{ $p->documento }}</div>
                </td>
                <td><span class="cama-badge">{{ $p->ubicacion ?? '—' }}</span></td>
                <td>{{ $p->subunidad ?? '—' }}</td>
                <td style="max-width:200px;font-size:.78rem;">{{ $p->soporte_ventilatorio ?? '—' }}</td>
                <td>
                    <input type="date" name="ingreso[{{ $p->id }}]"
                           class="form-control form-control-sm" style="width:160px"
                           max="{{ now()->format('Y-m-d') }}">
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>

{{-- ── 2. CAM-UCI ─────────────────────────────────────── --}}
<div class="section-card">
    <div class="section-header" data-bs-toggle="collapse" data-bs-target="#secCam">
        <div class="section-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-brain"></i></div>
        <div>
            <h6>CAM-UCI pendiente hoy</h6>
            <small>Pacientes sin evaluación de delirium en {{ now()->format('d/m/Y') }}</small>
        </div>
        <span class="badge-count {{ $sinCamUci->count() > 0 ? 'bg-warning text-dark' : 'bg-light text-muted' }}">
            {{ $sinCamUci->count() }}
        </span>
        <i class="bi bi-chevron-down chevron no-print"></i>
    </div>
    <div class="collapse show" id="secCam">
        @if($sinCamUci->isEmpty())
            <div class="empty-state"><i class="bi bi-check-circle text-success"></i>Sin pendientes</div>
        @else
        <div class="table-responsive">
        <table class="table table-hover pt-table mb-0">
            <thead><tr>
                <th style="width:210px">Paciente</th>
                <th>Cama</th>
                <th>Subunidad</th>
                <th>Resultado CAM-UCI <span class="text-danger">*</span></th>
                <th>RASS al momento</th>
                <th style="min-width:160px">Observación</th>
            </tr></thead>
            <tbody>
            @foreach($sinCamUci as $p)
            <tr>
                <td>
                    <div class="patient-name">{{ $p->nombre }}</div>
                    <div class="patient-doc">{{ $p->documento }}</div>
                </td>
                <td><span class="cama-badge">{{ $p->ubicacion ?? '—' }}</span></td>
                <td>{{ $p->subunidad ?? '—' }}</td>
                <td>
                    <select name="cam[{{ $p->id }}][resultado]" class="form-select form-select-sm" style="min-width:165px">
                        <option value="">— seleccionar —</option>
                        <option value="negativo">Negativo</option>
                        <option value="positivo">Positivo</option>
                        <option value="no_evaluable">No evaluable</option>
                    </select>
                </td>
                <td>
                    <select name="cam[{{ $p->id }}][rass_momento]" class="form-select form-select-sm" style="width:85px">
                        <option value="">—</option>
                        @foreach(range(4, -5) as $r)
                            <option value="{{ $r }}">{{ $r > 0 ? '+'.$r : $r }}</option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="text" name="cam[{{ $p->id }}][observacion]"
                           class="form-control form-control-sm" placeholder="Opcional">
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>

{{-- ── 3. BUNDLE VMI ──────────────────────────────────── --}}
@if($sinBundle->isNotEmpty())
<div class="section-card">
    <div class="section-header" data-bs-toggle="collapse" data-bs-target="#secBundle">
        <div class="section-icon bg-info bg-opacity-10 text-info"><i class="bi bi-lungs"></i></div>
        <div>
            <h6>Bundle de Ventilación Mecánica pendiente hoy</h6>
            <small>Pacientes VMI sin bundle registrado en {{ now()->format('d/m/Y') }}</small>
        </div>
        <span class="badge-count bg-info text-white">{{ $sinBundle->count() }}</span>
        <i class="bi bi-chevron-down chevron no-print"></i>
    </div>
    <div class="collapse show" id="secBundle">
        <div class="table-responsive">
        <table class="table table-hover pt-table mb-0">
            <thead><tr>
                <th style="width:180px">Paciente</th>
                <th>Cama</th>
                @foreach($bundleItems as $key => $item)
                    <th class="text-center" style="min-width:90px">
                        <i class="bi {{ $item[1] }} me-1"></i>{{ $item[0] }}
                    </th>
                @endforeach
                <th style="min-width:130px">Observaciones</th>
            </tr></thead>
            <tbody>
            @foreach($sinBundle as $p)
            <tr>
                <td>
                    <div class="patient-name">{{ $p->nombre }}</div>
                    <div class="patient-doc">{{ $p->documento }}</div>
                </td>
                <td><span class="cama-badge">{{ $p->ubicacion ?? '—' }}</span></td>
                @foreach($bundleItems as $key => $item)
                    <td class="text-center">
                        <div class="form-check d-flex justify-content-center">
                            <input class="form-check-input" type="checkbox"
                                   name="bundle[{{ $p->id }}][{{ $key }}]" value="1"
                                   style="width:20px;height:20px;cursor:pointer;">
                        </div>
                    </td>
                @endforeach
                <td>
                    <input type="text" name="bundle[{{ $p->id }}][observaciones]"
                           class="form-control form-control-sm" placeholder="Opcional">
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

{{-- ── 4. EGRESOS PENDIENTES ──────────────────────────── --}}
@if($pendientesEgreso->isNotEmpty())
<div class="section-card">
    <div class="section-header" data-bs-toggle="collapse" data-bs-target="#secEgreso">
        <div class="section-icon bg-success bg-opacity-10 text-success"><i class="bi bi-box-arrow-right"></i></div>
        <div>
            <h6>Pendientes de egreso UCI</h6>
            <small>Tienen salida hospitalaria pero falta registrar egreso UCI</small>
        </div>
        <span class="badge-count bg-success text-white">{{ $pendientesEgreso->count() }}</span>
        <i class="bi bi-chevron-down chevron no-print"></i>
    </div>
    <div class="collapse show" id="secEgreso">
        <div class="table-responsive">
        <table class="table table-hover pt-table mb-0">
            <thead><tr>
                <th style="width:210px">Paciente</th>
                <th>Cama</th>
                <th>Subunidad</th>
                <th>Salida hosp.</th>
                <th>Fecha egreso UCI <span class="text-danger">*</span></th>
                <th>Tipo de egreso <span class="text-danger">*</span></th>
            </tr></thead>
            <tbody>
            @foreach($pendientesEgreso as $p)
            <tr>
                <td>
                    <div class="patient-name">{{ $p->nombre }}</div>
                    <div class="patient-doc">{{ $p->documento }}</div>
                </td>
                <td><span class="cama-badge">{{ $p->ubicacion ?? '—' }}</span></td>
                <td>{{ $p->subunidad ?? '—' }}</td>
                <td>{{ $p->salida_hospitalizacion ? \Carbon\Carbon::parse($p->salida_hospitalizacion)->format('d/m/Y') : '—' }}</td>
                <td>
                    <input type="date" name="egreso[{{ $p->id }}][fecha]"
                           class="form-control form-control-sm" style="width:155px"
                           value="{{ $p->salida_hospitalizacion ? \Carbon\Carbon::parse($p->salida_hospitalizacion)->format('Y-m-d') : '' }}"
                           max="{{ now()->format('Y-m-d') }}">
                </td>
                <td>
                    <select name="egreso[{{ $p->id }}][tipo]" class="form-select form-select-sm" style="min-width:175px">
                        <option value="">— seleccionar —</option>
                        <option value="mejoria">Alta por mejoría</option>
                        <option value="traslado">Traslado</option>
                        <option value="fallecimiento">Fallecimiento</option>
                        <option value="voluntario">Retiro voluntario</option>
                        <option value="otro">Otro</option>
                    </select>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

{{-- ── 5. CAUSAS DE ESTANCIA ──────────────────────────── --}}
@if($sinCausas->isNotEmpty())
<div class="section-card">
    <div class="section-header" data-bs-toggle="collapse" data-bs-target="#secCausas">
        <div class="section-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-clock-history"></i></div>
        <div>
            <h6>Causas de estancia prolongada sin registrar</h6>
            <small>Pacientes activos sin causas de estancia en el sistema</small>
        </div>
        <span class="badge-count bg-secondary text-white">{{ $sinCausas->count() }}</span>
        <i class="bi bi-chevron-down chevron no-print"></i>
    </div>
    <div class="collapse show" id="secCausas">
        <div class="table-responsive">
        <table class="table table-hover pt-table mb-0">
            <thead><tr>
                <th style="width:190px">Paciente</th>
                <th>Cama</th>
                @foreach($causasEtiquetas as $key => $et)
                    <th class="text-center" style="min-width:100px">
                        <i class="bi {{ $et['icon'] }} me-1"></i>{{ $et['label'] }}
                    </th>
                @endforeach
                <th style="min-width:130px">Observaciones</th>
            </tr></thead>
            <tbody>
            @foreach($sinCausas as $p)
            <tr>
                <td>
                    <div class="patient-name">{{ $p->nombre }}</div>
                    <div class="patient-doc">{{ $p->documento }}</div>
                </td>
                <td><span class="cama-badge">{{ $p->ubicacion ?? '—' }}</span></td>
                @foreach($causasEtiquetas as $key => $et)
                    <td class="text-center">
                        <div class="form-check d-flex justify-content-center">
                            <input class="form-check-input" type="checkbox"
                                   name="causas[{{ $p->id }}][{{ $key }}]" value="1"
                                   style="width:20px;height:20px;cursor:pointer;">
                        </div>
                    </td>
                @endforeach
                <td>
                    <input type="text" name="causas[{{ $p->id }}][observaciones]"
                           class="form-control form-control-sm" placeholder="Opcional">
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>
@endif

{{-- ── 5. TRANSFUSIONES / HEMODERIVADOS ────────────────── --}}
<div class="section-card">
    <div class="section-header" data-bs-toggle="collapse" data-bs-target="#secTransfusion">
        <div class="section-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-droplet-fill"></i></div>
        <div>
            <h6>Hemoderivados / Transfusiones del día</h6>
            <small>Pacientes que recibieron hemoderivados hoy — {{ now()->format('d/m/Y') }}</small>
        </div>
        @php $conTransfusionCount = count($conTransfusionHoy); @endphp
        <span class="badge-count {{ $conTransfusionCount > 0 ? 'bg-danger text-white' : 'bg-light text-muted' }}">
            {{ $conTransfusionCount }}
        </span>
        <i class="bi bi-chevron-down chevron no-print"></i>
    </div>
    <div class="collapse show" id="secTransfusion">
        @if($activos->isEmpty())
            <div class="empty-state"><i class="bi bi-people text-muted"></i>Sin pacientes activos</div>
        @else
        <div class="p-2 bg-light border-bottom" style="font-size:0.75rem;color:#888;">
            <i class="bi bi-info-circle me-1"></i>Solo complete los pacientes que recibieron hemoderivados hoy.
        </div>
        <div class="table-responsive">
        <table class="table table-hover pt-table mb-0">
            <thead><tr>
                <th style="width:210px">Paciente</th>
                <th>Cama</th>
                <th>Subunidad</th>
                <th>Hemoderivados</th>
                <th>Unidades</th>
                <th style="min-width:150px">Observación</th>
            </tr></thead>
            <tbody>
            @foreach($activos as $p)
            @php $yaRegistrado = isset($conTransfusionHoy[$p->id]); @endphp
            <tr class="{{ $yaRegistrado ? 'table-danger' : '' }}">
                <td>
                    <div class="patient-name">{{ $p->nombre }}</div>
                    <div class="patient-doc">{{ $p->documento }}</div>
                    @if($yaRegistrado)
                        <span class="badge bg-danger" style="font-size:0.65rem;"><i class="bi bi-check-circle me-1"></i>Registrado</span>
                    @endif
                </td>
                <td><span class="cama-badge">{{ $p->ubicacion ?? '—' }}</span></td>
                <td>{{ $p->subunidad ?? '—' }}</td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($tiposHemoder as $key => $label)
                        <div class="form-check form-check-inline" style="margin-right:0;">
                            <input class="form-check-input transfusion-check" type="checkbox"
                                   name="transfusion_tipos[{{ $p->id }}][]"
                                   value="{{ $key }}"
                                   id="tr_{{ $p->id }}_{{ $key }}"
                                   data-paciente="{{ $p->id }}"
                                   onchange="syncTransfProductos({{ $p->id }})">
                            <label class="form-check-label" for="tr_{{ $p->id }}_{{ $key }}" style="font-size:0.78rem;">
                                {{ $key }}
                            </label>
                        </div>
                        @endforeach
                    </div>
                    <input type="hidden" name="transfusion[{{ $p->id }}][productos]"
                           id="trProd_{{ $p->id }}" value="">
                </td>
                <td>
                    <input type="number" name="transfusion[{{ $p->id }}][unidades]"
                           min="1" max="50" value="1"
                           class="form-control form-control-sm" style="width:70px;">
                </td>
                <td>
                    <input type="text" name="transfusion[{{ $p->id }}][observaciones]"
                           class="form-control form-control-sm" placeholder="Opcional...">
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>

{{-- ── SAVE BAR ───────────────────────────────────────── --}}
<div class="save-bar no-print">
    @if($totalPendientes > 0)
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-floppy2-fill me-2"></i>Guardar todos los registros
    </button>
    @endif
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
        <i class="bi bi-printer me-2"></i>Imprimir / PDF
    </button>
    <a href="{{ route('dashboard') }}" class="btn btn-link text-muted ms-auto">
        <i class="bi bi-arrow-left me-1"></i>Volver al panel
    </a>
    @if($totalPendientes > 0)
    <small class="text-muted">
        <strong>{{ $totalPendientes }}</strong> registro{{ $totalPendientes !== 1 ? 's' : '' }} pendiente{{ $totalPendientes !== 1 ? 's' : '' }}
    </small>
    @endif
</div>

</form>

{{-- ── REFERENCIA EXCEL ───────────────────────────────── --}}
<div class="section-card mt-2">
    <div class="section-header no-print" data-bs-toggle="collapse" data-bs-target="#secRef">
        <div class="section-icon bg-primary bg-opacity-10 text-primary">
            <i class="bi bi-file-earmark-spreadsheet"></i>
        </div>
        <div>
            <h6>Referencia: datos que vienen del Excel diario</h6>
            <small>Estos campos se actualizan automáticamente al subir el archivo</small>
        </div>
        <i class="bi bi-chevron-right chevron no-print"></i>
    </div>
    <div class="collapse" id="secRef">
        <div class="p-3">
            <div class="row g-2" style="font-size:.82rem;">
                @foreach([
                    'Nombre y apellido','Documento de identidad','Edad','Sexo','EAPB',
                    'Diagnóstico (CIE-10)','Cama / ubicación','Subunidad',
                    'Soporte ventilatorio','Soporte hemodinámico',
                    'Días de VMI','NEWS','SOFA','Barthel','Movilización',
                    'RASS / BPS / EVA / MUST','Salida de hospitalización',
                ] as $item)
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f8f9fa;">
                        <i class="bi bi-check2 text-success flex-shrink-0"></i>{{ $item }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

</div>{{-- /container --}}

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function syncTransfProductos(pacienteId) {
    const checkboxes = document.querySelectorAll(`input[name="transfusion_tipos[${pacienteId}][]"]:checked`);
    const vals = Array.from(checkboxes).map(c => c.value);
    const hidden = document.getElementById(`trProd_${pacienteId}`);
    if (hidden) hidden.value = vals.join(', ');
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.transfusion-check').forEach(function(cb) {
        cb.addEventListener('change', function() {
            syncTransfProductos(this.dataset.paciente);
        });
    });
});
</script>
</body>
</html>
