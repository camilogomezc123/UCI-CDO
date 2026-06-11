@extends('layouts.app')
@section('title', 'Descargas Excel UCI')
@section('page-title', 'Descargas de informes en Excel')

@section('content')

<div class="row justify-content-center">
  <div class="col-xl-8">

    <div class="alert alert-light border mb-4" style="font-size:0.85rem;">
      <i class="bi bi-info-circle me-2 text-primary"></i>
      Selecciona el tipo de informe, el período y la fecha de referencia para generar el archivo Excel.
    </div>

    <form method="GET" action="{{ route('reportes.descargas.descargar') }}">

      {{-- ── Tipo de informe ── --}}
      <div class="card mb-4">
        <div class="card-header fw-semibold">
          <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>Tipo de informe
        </div>
        <div class="card-body">
          <div class="row g-3">

            <div class="col-md-4">
              <input type="radio" class="btn-check" name="tipo_reporte" id="r_epid" value="epidemiologia" checked>
              <label class="btn btn-outline-primary w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 py-3" for="r_epid">
                <i class="bi bi-heart-pulse fs-2"></i>
                <div class="fw-semibold">Perfil Epidemiológico</div>
                <small class="text-muted text-center" style="font-size:0.75rem;">
                  Demografía, CIE-10, mortalidad, escalas, ingresos/egresos
                </small>
              </label>
            </div>

            <div class="col-md-4">
              <input type="radio" class="btn-check" name="tipo_reporte" id="r_mort" value="mortalidad">
              <label class="btn btn-outline-danger w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 py-3" for="r_mort">
                <i class="bi bi-person-x-fill fs-2"></i>
                <div class="fw-semibold">Informe de Mortalidad</div>
                <small class="text-muted text-center" style="font-size:0.75rem;">
                  Pacientes fallecidos, CIE-10, soportes, delirium, bundle
                </small>
              </label>
            </div>

            <div class="col-md-4">
              <input type="radio" class="btn-check" name="tipo_reporte" id="r_sub" value="subunidad">
              <label class="btn btn-outline-info w-100 h-100 d-flex flex-column align-items-center justify-content-center gap-2 py-3" for="r_sub">
                <i class="bi bi-diagram-3 fs-2"></i>
                <div class="fw-semibold">Reporte por Subunidad</div>
                <small class="text-muted text-center" style="font-size:0.75rem;">
                  Estadísticas, ocupación y detalle por cada UCI
                </small>
              </label>
            </div>

          </div>
        </div>
      </div>

      {{-- ── Período ── --}}
      <div class="card mb-4">
        <div class="card-header fw-semibold">
          <i class="bi bi-calendar-range text-primary me-2"></i>Período
        </div>
        <div class="card-body">
          <div class="row g-3 align-items-end">

            <div class="col-md-7">
              <label class="form-label fw-semibold" style="font-size:0.85rem;">Tipo de período</label>
              <div class="d-flex flex-wrap gap-2">
                @foreach([
                  'mensual'    => ['bi-calendar-month',  'Mensual',    'Un mes completo'],
                  'trimestral' => ['bi-calendar3',        'Trimestral', 'Q1 / Q2 / Q3 / Q4'],
                  'semestral'  => ['bi-calendar2-range',  'Semestral',  'S1 (Ene–Jun) o S2 (Jul–Dic)'],
                  'anual'      => ['bi-calendar4',        'Anual',      'Año completo'],
                ] as $val => [$ico, $lab, $desc])
                <div>
                  <input type="radio" class="btn-check" name="periodo" id="p_{{ $val }}" value="{{ $val }}"
                         {{ $val === 'mensual' ? 'checked' : '' }}>
                  <label class="btn btn-outline-secondary d-flex flex-column align-items-center px-3 py-2" for="p_{{ $val }}"
                         style="min-width:110px;" title="{{ $desc }}">
                    <i class="bi {{ $ico }} mb-1"></i>
                    <span style="font-size:0.82rem;">{{ $lab }}</span>
                    <small style="font-size:0.68rem;color:#888;">{{ $desc }}</small>
                  </label>
                </div>
                @endforeach
              </div>
            </div>

            <div class="col-md-5">
              <label class="form-label fw-semibold" style="font-size:0.85rem;">
                Fecha de referencia
                <small class="text-muted fw-normal">(cualquier día del período)</small>
              </label>
              <input type="date" name="fecha" class="form-control"
                     value="{{ now()->format('Y-m-d') }}" required>
            </div>

          </div>
        </div>
      </div>

      {{-- ── Contenido de cada informe ── --}}
      <div class="card mb-4 border-0" style="background:#f8f9fa;">
        <div class="card-body py-3">
          <div class="row g-3">

            <div class="col-md-4 reporte-info" id="info_epidemiologia">
              <div class="fw-semibold mb-1" style="font-size:0.82rem;"><i class="bi bi-heart-pulse text-primary me-1"></i>Perfil Epidemiológico incluye:</div>
              <ul style="font-size:0.78rem;padding-left:1.2rem;color:#555;margin:0;">
                <li>Hoja 1 — KPIs del período (ingresos, egresos, mortalidad, escalas)</li>
                <li>Hoja 2 — Top 30 CIE-10 del período</li>
                <li>Hoja 3 — Desglose mes a mes (si trim./sem./anual)</li>
              </ul>
            </div>

            <div class="col-md-4 reporte-info d-none" id="info_mortalidad">
              <div class="fw-semibold mb-1" style="font-size:0.82rem;"><i class="bi bi-person-x-fill text-danger me-1"></i>Informe de Mortalidad incluye:</div>
              <ul style="font-size:0.78rem;padding-left:1.2rem;color:#555;margin:0;">
                <li>Hoja 1 — Resumen agregado de fallecidos</li>
                <li>Hoja 2 — Distribución CIE-10 (% y estancia)</li>
                <li>Hoja 3 — Tabla completa por paciente (35+ variables clínicas)</li>
              </ul>
            </div>

            <div class="col-md-4 reporte-info d-none" id="info_subunidad">
              <div class="fw-semibold mb-1" style="font-size:0.82rem;"><i class="bi bi-diagram-3 text-info me-1"></i>Reporte por Subunidad incluye:</div>
              <ul style="font-size:0.78rem;padding-left:1.2rem;color:#555;margin:0;">
                <li>Hoja 1 — Resumen estadístico por subunidad</li>
                <li>Hoja 2 — Ocupación mes a mes por subunidad</li>
                <li>Hoja 3 — Detalle de todos los pacientes del período</li>
              </ul>
            </div>

          </div>
        </div>
      </div>

      {{-- ── Botón ── --}}
      <div class="d-flex align-items-center gap-3">
        <button type="submit" class="btn btn-success btn-lg px-5">
          <i class="bi bi-file-earmark-arrow-down me-2"></i>Descargar Excel
        </button>
        <span class="text-muted" style="font-size:0.82rem;">
          El archivo se genera en tiempo real con los datos actuales de la base de datos.
        </span>
      </div>

    </form>

  </div>
</div>

@endsection

@push('scripts')
<script>
document.querySelectorAll('input[name="tipo_reporte"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.reporte-info').forEach(el => el.classList.add('d-none'));
        const info = document.getElementById('info_' + this.value);
        if (info) info.classList.remove('d-none');
    });
});
</script>
@endpush
