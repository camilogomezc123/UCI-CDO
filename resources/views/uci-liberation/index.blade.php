@extends('layouts.app')
@section('title', 'UCI Liberation · Bundle ABCDEF')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0"><i class="bi bi-activity me-2 text-primary"></i>UCI Liberation · Bundle ABCDEF</h4>
            <small class="text-muted">Cumplimiento diario por paciente · {{ $fecha->format('d/m/Y') }}</small>
        </div>
        <form class="d-flex gap-2 align-items-center">
            <input type="date" name="fecha" class="form-control form-control-sm"
                   value="{{ $fecha->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}">
            <button class="btn btn-sm btn-outline-primary">Ver</button>
        </form>
    </div>

    {{-- KPIs del día --}}
    <div class="row g-3 mb-4">
        @php
            $kpiDefs = [
                ['val'=>$kpis['pacientes'],       'label'=>'Pacientes UCI',          'icon'=>'bi-person',           'color'=>'primary'],
                ['val'=>$kpis['bundle_completo'],  'label'=>'Bundle completo (100%)', 'icon'=>'bi-check-circle-fill','color'=>'success'],
                ['val'=>round($kpis['pct_promedio'] ?? 0).'%', 'label'=>'Cumplimiento promedio', 'icon'=>'bi-bar-chart','color'=>'info'],
                ['val'=>$kpis['delirium_pos'],    'label'=>'Delirium positivo hoy',  'icon'=>'bi-brain',            'color'=>'danger'],
                ['val'=>$kpis['sin_cam'],          'label'=>'Sin CAM-UCI hoy',        'icon'=>'bi-exclamation-triangle','color'=>'warning'],
            ];
        @endphp
        @foreach($kpiDefs as $k)
        <div class="col-6 col-md">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body py-3">
                    <i class="bi {{ $k['icon'] }} text-{{ $k['color'] }} fs-4 d-block mb-1"></i>
                    <div class="fs-3 fw-bold text-{{ $k['color'] }}">{{ $k['val'] }}</div>
                    <div class="small text-muted">{{ $k['label'] }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Leyenda del bundle --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap gap-3">
                @foreach(\App\Http\Controllers\UciLiberationController::BUNDLE as $letra => [$nombre, $herramienta, $icon, $color])
                <div class="d-flex align-items-center gap-1 small">
                    <span class="badge bg-{{ $color }}">{{ $letra }}</span>
                    <span class="text-muted">{{ $nombre }}</span>
                    <span class="text-muted opacity-50">({{ $herramienta }})</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Tablero de compliance --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold py-3">
            <i class="bi bi-table me-2"></i>Tablero de turno · {{ $fecha->format('d/m/Y') }}
        </div>
        <div class="card-body p-0">
            @if($tablero->isEmpty())
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay pacientes activos en UCI.
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Paciente</th>
                            @foreach(\App\Http\Controllers\UciLiberationController::BUNDLE as $letra => [$n, $h, $icon, $color])
                            <th class="text-center">
                                <span class="badge bg-{{ $color }}">{{ $letra }}</span>
                            </th>
                            @endforeach
                            <th class="text-center">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tablero as $row)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $row['p']->nombre }}</div>
                                <div class="text-muted" style="font-size:0.7rem">
                                    {{ $row['snap']?->ubicacion ?? '—' }}
                                    @if($row['snap']?->soporte_ventilatorio)
                                    · <span class="badge bg-info-subtle text-info">VM</span>
                                    @endif
                                </div>
                            </td>
                            @foreach($row['items'] as $letra => $item)
                            <td class="text-center">
                                @if($item['ok'] === null)
                                    <span title="{{ $item['label'] }}">
                                        <i class="bi bi-dash-circle text-muted"></i>
                                    </span>
                                @elseif($item['ok'])
                                    <span title="{{ $item['valor'] }}">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                    </span>
                                @else
                                    <span title="{{ $item['valor'] }}">
                                        <i class="bi bi-x-circle-fill text-danger"></i>
                                    </span>
                                @endif
                                <div class="text-muted" style="font-size:0.65rem; max-width:80px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ Str::limit($item['valor'], 12) }}
                                </div>
                            </td>
                            @endforeach
                            <td class="text-center">
                                @if($row['pct'] !== null)
                                @php
                                    $pc = $row['pct'];
                                    $bc = $pc >= 80 ? 'success' : ($pc >= 60 ? 'warning' : 'danger');
                                @endphp
                                <span class="badge bg-{{ $bc }} rounded-pill">{{ $pc }}%</span>
                                @else
                                <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Tendencia 14 días --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold py-3">
            <i class="bi bi-graph-up me-2"></i>Tendencia — últimos 14 días
        </div>
        <div class="card-body">
            <canvas id="chartTendencia" height="80"></canvas>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
const t = @json($tendencia);
new Chart(document.getElementById('chartTendencia'), {
    type: 'bar',
    data: {
        labels: t.map(d => d.fecha),
        datasets: [
            {
                label: '% Bundle registrado',
                type: 'line',
                data: t.map(d => d.pct_bundle),
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                tension: 0.3,
                yAxisID: 'yPct',
                fill: true,
            },
            {
                label: 'Delirium positivos',
                data: t.map(d => d.delirium_pos),
                backgroundColor: 'rgba(220,53,69,0.7)',
                yAxisID: 'yN',
            },
            {
                label: 'Sin CAM-UCI',
                data: t.map(d => d.sin_cam),
                backgroundColor: 'rgba(255,193,7,0.7)',
                yAxisID: 'yN',
            },
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
            yPct: { type: 'linear', position: 'left', min: 0, max: 100,
                    title: { display: true, text: '% Bundle' } },
            yN:   { type: 'linear', position: 'right', min: 0,
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Pacientes' } },
        }
    }
});
</script>
@endpush
