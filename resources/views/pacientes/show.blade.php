@extends('layouts.app')
@section('title', $paciente->nombre)
@section('page-title')
<a href="{{ route('pacientes.index') }}" class="text-decoration-none text-muted me-2" style="font-size:0.9rem;"><i class="bi bi-arrow-left"></i></a>
{{ Str::limit($paciente->nombre, 45) }}
@endsection

@section('content')
<div class="row g-3">

  {{-- COLUMNA IZQUIERDA --}}
  <div class="col-lg-4">

    {{-- Info básica + alertas --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-person-badge me-2 text-primary"></i>Datos del Paciente</span>
        <div class="d-flex gap-1">
          @if($paciente->tieneAlertaNews())
            <span class="badge bg-danger" title="NEWS crítico"><i class="bi bi-thermometer-high"></i> NEWS {{ $ultimoSnapshot->news }}</span>
          @endif
          @if($paciente->tieneAlertaSofa())
            <span class="badge bg-warning text-dark" title="SOFA crítico"><i class="bi bi-activity"></i> SOFA {{ $ultimoSnapshot->sofa }}</span>
          @endif
        </div>
      </div>
      <div class="card-body">
        @if($ultimoSnapshot)
        <div class="mb-2">
          <span class="badge bg-secondary">{{ $ultimoSnapshot->ubicacion }}</span>
          <span class="badge ms-1" style="background:#e8f0ff;color:#0d6efd;">{{ $ultimoSnapshot->subunidad }}</span>
          @if($diasVmi > 0)<span class="badge bg-info text-dark ms-1"><i class="bi bi-lungs me-1"></i>VMI {{ $diasVmi }}d</span>@endif
          @if($diasVasopresor > 0)<span class="badge bg-danger ms-1"><i class="bi bi-heart-pulse me-1"></i>Vaso {{ $diasVasopresor }}d</span>@endif
          @if($diasInotropico > 0)<span class="badge bg-warning text-dark ms-1"><i class="bi bi-activity me-1"></i>Inotr {{ $diasInotropico }}d</span>@endif
        </div>
        @endif
        <table class="table table-sm table-borderless mb-0" style="font-size:0.85rem;">
          <tr><td class="text-muted" style="width:40%">Documento</td><td class="fw-semibold">{{ $paciente->documento }}</td></tr>
          <tr><td class="text-muted">Edad</td><td>{{ $paciente->edad == 0 ? 'Neonato' : $paciente->edad.' años' }}</td></tr>
          <tr><td class="text-muted">Sexo</td><td>{{ $paciente->sexo=='F'?'Femenino':($paciente->sexo=='M'?'Masculino':'—') }}</td></tr>
          <tr><td class="text-muted">EAPB</td><td>{{ $paciente->eapb ?? '—' }}</td></tr>
          <tr><td class="text-muted">Días en UCI</td>
            <td>
              @if($paciente->ingreso_uci)
                <strong class="{{ $paciente->diasEnUci() >= 5 ? 'text-danger' : 'text-primary' }}">
                  {{ $paciente->diasEnUci() }} días
                </strong>
                @if($paciente->diasEnUci() >= 5)
                  <span class="badge bg-danger ms-1" style="font-size:0.65rem;">Estancia prolongada</span>
                @endif
              @else <span class="text-muted">Sin fecha</span> @endif
            </td>
          </tr>
        </table>
      </div>
    </div>

    {{-- Ciclo de vida UCI --}}
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-clock-history me-2 text-primary"></i>Ciclo de Vida en UCI</div>
      <div class="card-body">
        {{-- Ingreso --}}
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 mb-1">
            <div style="width:10px;height:10px;border-radius:50%;background:#198754;"></div>
            <span class="fw-semibold" style="font-size:0.85rem;">Ingreso a UCI</span>
          </div>
          @if($paciente->ingreso_uci)
            <div class="ps-4 text-success fw-bold">{{ $paciente->ingreso_uci->format('d/m/Y H:i') }}</div>
            <div class="ps-4 text-muted" style="font-size:0.78rem;">Tiempo en UCI: <strong class="tiempo-uci">{{ $paciente->tiempoEnUciTexto() }}</strong></div>
          @else
            <div class="ps-4">
              <span class="badge bg-warning text-dark mb-2">Sin registrar</span>
              <form method="POST" action="{{ route('pacientes.ingreso', $paciente) }}">
                @csrf @method('PATCH')
                <div class="input-group input-group-sm">
                  <input type="datetime-local" name="ingreso_uci" class="form-control" required>
                  <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check-lg"></i></button>
                </div>
              </form>
            </div>
          @endif
        </div>

        <div style="border-left:2px dashed #dee2e6;margin-left:4px;padding-left:1rem;margin-bottom:0.75rem;">
          {{-- Salida hospitalización --}}
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
              <div style="width:10px;height:10px;border-radius:50%;background:{{ $paciente->salida_hospitalizacion?'#fd7e14':'#dee2e6' }};"></div>
              <span class="fw-semibold" style="font-size:0.85rem;">Salida para hospitalización</span>
            </div>
            <div class="ms-3">
              <form method="POST" action="{{ route('pacientes.salida-hospitalizacion', $paciente) }}">
                @csrf @method('PATCH')
                <div class="input-group input-group-sm" style="max-width:280px;">
                  <input type="datetime-local" name="salida_hospitalizacion"
                         value="{{ $paciente->salida_hospitalizacion?->format('Y-m-d\TH:i') }}"
                         class="form-control">
                  <button class="btn btn-{{ $paciente->salida_hospitalizacion?'outline-warning':'warning' }} btn-sm" type="submit">
                    <i class="bi bi-{{ $paciente->salida_hospitalizacion?'pencil':'check-lg' }}"></i>
                  </button>
                </div>
              </form>
              @if($paciente->salida_hospitalizacion && !$paciente->egreso_uci)
                <div class="text-danger mt-1" style="font-size:0.78rem;">
                  Esperando egreso: <strong>{{ $paciente->tiempoEsperaHospitalizacion() }}</strong>
                </div>
              @endif
            </div>
          </div>
        </div>

        {{-- Egreso UCI --}}
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <div style="width:10px;height:10px;border-radius:50%;background:{{ $paciente->egreso_uci?'#dc3545':'#dee2e6' }};"></div>
            <span class="fw-semibold" style="font-size:0.85rem;">Egreso efectivo de UCI</span>
          </div>
          @if($paciente->egreso_uci)
            <div class="ps-3 text-danger fw-bold">{{ $paciente->egreso_uci->format('d/m/Y H:i') }}</div>
            @if($paciente->tipo_egreso)
            @php $te = ['mejoria'=>['success','bi-check-circle','Mejoría'],'traslado'=>['info','bi-arrow-right-circle','Traslado'],'fallecimiento'=>['dark','bi-x-circle','Fallecimiento']][$paciente->tipo_egreso] @endphp
            <div class="ps-3 mt-1"><span class="badge bg-{{ $te[0] }}"><i class="bi {{ $te[1] }} me-1"></i>{{ $te[2] }}</span></div>
            @endif
            @if($paciente->salida_hospitalizacion)
            <div class="ps-3 text-muted" style="font-size:0.78rem;">Espera: <strong class="tiempo-espera">{{ $paciente->tiempoEsperaHospitalizacion() }}</strong></div>
            @endif
          @else
            <div class="ps-3">
              <form method="POST" action="{{ route('pacientes.egreso-uci', $paciente) }}">
                @csrf @method('PATCH')
                <div class="mb-2" style="max-width:280px;">
                  <input type="datetime-local" name="egreso_uci" class="form-control form-control-sm mb-2" required>
                  <select name="tipo_egreso" class="form-select form-select-sm mb-2" required>
                    <option value="">-- Tipo de egreso --</option>
                    <option value="mejoria">Mejoría / Alta a hospitalización</option>
                    <option value="traslado">Traslado a otra institución</option>
                    <option value="fallecimiento">Fallecimiento</option>
                  </select>
                  <button class="btn btn-danger btn-sm w-100" type="submit">
                    <i class="bi bi-check-lg me-1"></i>Registrar egreso
                  </button>
                </div>
              </form>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- Notas libres --}}
    <div class="card">
      <div class="card-header"><i class="bi bi-journal-text me-2 text-primary"></i>Notas Clínicas</div>
      <div class="card-body">
        <form method="POST" action="{{ route('pacientes.guardar-nota', $paciente) }}" class="mb-3">
          @csrf
          <textarea name="nota" class="form-control form-control-sm mb-2" rows="3"
                    placeholder="Escriba una nota clínica de hoy..." required style="font-size:0.85rem;"></textarea>
          <button type="submit" class="btn btn-sm btn-primary w-100">
            <i class="bi bi-plus-circle me-1"></i>Agregar nota
          </button>
        </form>
        @forelse($notas as $nota)
        <div class="mb-2 p-2 rounded" style="background:#f8f9fa;font-size:0.82rem;">
          <div class="d-flex justify-content-between mb-1">
            <strong>{{ $nota->fecha->format('d/m/Y') }}</strong>
            <span class="text-muted">{{ $nota->usuario->name ?? '—' }}</span>
          </div>
          <div style="white-space:pre-line;">{{ $nota->nota }}</div>
        </div>
        @empty
        <p class="text-muted text-center" style="font-size:0.82rem;">Sin notas registradas.</p>
        @endforelse
      </div>
    </div>

    {{-- CAM-UCI: evaluación diaria de delirium --}}
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-brain me-2 text-primary"></i>CAM-UCI — Delirium</span>
        @php $camHoy = $camUciHoy; @endphp
        @if($camHoy)
          @php $etq = $camHoy->etiqueta(); @endphp
          <span class="badge bg-{{ $etq[1] }}" style="font-size:0.72rem;"><i class="bi {{ $etq[2] }} me-1"></i>Hoy: {{ $etq[0] }}</span>
        @else
          <span class="badge bg-warning text-dark" style="font-size:0.72rem;"><i class="bi bi-clock me-1"></i>Sin evaluar hoy</span>
        @endif
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('pacientes.guardar-cam-uci', $paciente) }}" class="mb-3">
          @csrf
          <div class="row g-2 align-items-end">
            <div class="col-sm-5">
              <label class="form-label mb-1" style="font-size:0.78rem;">Resultado CAM-UCI de hoy</label>
              <select name="resultado" class="form-select form-select-sm" required>
                <option value="">-- Seleccionar --</option>
                <option value="positivo"     {{ $camHoy?->resultado=='positivo'     ?'selected':'' }}>Positivo — Delirium presente</option>
                <option value="negativo"     {{ $camHoy?->resultado=='negativo'     ?'selected':'' }}>Negativo — Sin delirium</option>
                <option value="no_evaluable" {{ $camHoy?->resultado=='no_evaluable' ?'selected':'' }}>No evaluable (RASS ≤ -3)</option>
              </select>
            </div>
            <div class="col-sm-3">
              <label class="form-label mb-1" style="font-size:0.78rem;">RASS al momento</label>
              <input type="number" name="rass_momento" min="-5" max="4" step="1"
                     value="{{ $camHoy?->rass_momento }}"
                     class="form-control form-control-sm" placeholder="-5 a +4">
            </div>
            <div class="col-sm-4">
              <label class="form-label mb-1" style="font-size:0.78rem;">Observación</label>
              <input type="text" name="observacion" value="{{ $camHoy?->observacion }}"
                     class="form-control form-control-sm" placeholder="Opcional...">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i>{{ $camHoy ? 'Actualizar evaluación de hoy' : 'Registrar evaluación' }}
              </button>
            </div>
          </div>
        </form>
        {{-- Historial últimos 14 días --}}
        @if($camUciRegistros->count() > 0)
        <div class="d-flex gap-1 flex-wrap">
          @foreach($camUciRegistros->take(14)->sortBy('fecha') as $cam)
          @php $e = $cam->etiqueta(); @endphp
          <div class="text-center" title="{{ $cam->fecha->format('d/m') }}: {{ $e[0] }}">
            <div class="rounded" style="width:28px;height:28px;background:{{ match($cam->resultado){'positivo'=>'#dc3545','negativo'=>'#198754','no_evaluable'=>'#6c757d'} }};display:flex;align-items:center;justify-content:center;">
              <i class="bi {{ $e[2] }} text-white" style="font-size:0.7rem;"></i>
            </div>
            <div style="font-size:0.6rem;color:#999;margin-top:2px;">{{ $cam->fecha->format('d/m') }}</div>
          </div>
          @endforeach
        </div>
        @php
          $totalCam     = $camUciRegistros->count();
          $positivos    = $camUciRegistros->where('resultado','positivo')->count();
          $pctDelirium  = $totalCam > 0 ? round($positivos/$totalCam*100) : 0;
        @endphp
        <div class="mt-2 d-flex gap-3" style="font-size:0.78rem;">
          <span><span class="fw-bold text-danger">{{ $positivos }}</span> positivo(s)</span>
          <span><span class="fw-bold text-success">{{ $camUciRegistros->where('resultado','negativo')->count() }}</span> negativo(s)</span>
          <span class="ms-auto text-muted">{{ $pctDelirium }}% días con delirium</span>
        </div>
        @endif
      </div>
    </div>

    {{-- Bundle Ventilador (solo si tiene VMI) --}}
    @if($diasVmi > 0 || ($ultimoSnapshot && str_contains(strtolower($ultimoSnapshot->soporte_ventilatorio ?? ''), 'vmi')))
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-lungs me-2 text-primary"></i>Bundle Ventilador</span>
        @if($cumplimientoBundle !== null)
          <span class="badge bg-{{ $cumplimientoBundle >= 80 ? 'success' : ($cumplimientoBundle >= 50 ? 'warning text-dark' : 'danger') }}">
            Cumplimiento {{ $cumplimientoBundle }}%
          </span>
        @endif
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('pacientes.guardar-bundle', $paciente) }}">
          @csrf
          <p class="text-muted mb-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">
            Checklist de hoy — {{ today()->format('d/m/Y') }}
          </p>
          <div class="row g-2 mb-3">
            @foreach(\App\Models\BundleVentilacion::items() as $campo => $item)
            <div class="col-sm-6">
              <div class="form-check p-2 rounded {{ $bundleHoy?->$campo ? 'bg-success bg-opacity-10' : '' }}">
                <input class="form-check-input" type="checkbox" name="{{ $campo }}"
                       id="b_{{ $campo }}" {{ $bundleHoy?->$campo ? 'checked' : '' }}>
                <label class="form-check-label d-flex align-items-center gap-2" for="b_{{ $campo }}" style="font-size:0.82rem;">
                  <i class="bi {{ $item[1] }} text-primary"></i>{{ $item[0] }}
                </label>
              </div>
            </div>
            @endforeach
          </div>
          <input type="text" name="observaciones" value="{{ $bundleHoy?->observaciones }}"
                 class="form-control form-control-sm mb-2" placeholder="Observaciones del bundle...">
          <button type="submit" class="btn btn-sm btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i>{{ $bundleHoy ? 'Actualizar bundle de hoy' : 'Registrar bundle' }}
          </button>
        </form>

        {{-- Historial compliance --}}
        @if($bundleRegistros->count() > 1)
        <hr class="my-2">
        <p class="text-muted mb-2" style="font-size:0.72rem;">Últimos días registrados</p>
        <div class="d-flex gap-1 flex-wrap">
          @foreach($bundleRegistros->take(14)->sortBy('fecha') as $b)
          @php $pct = $b->cumplimiento(); @endphp
          <div class="text-center" title="{{ $b->fecha->format('d/m') }}: {{ $pct }}%">
            <div class="rounded" style="width:28px;height:28px;background:{{ $pct==100?'#198754':($pct>=50?'#fd7e14':'#dc3545') }};display:flex;align-items:center;justify-content:center;">
              <span style="font-size:0.55rem;color:white;font-weight:bold;">{{ $pct }}%</span>
            </div>
            <div style="font-size:0.6rem;color:#999;margin-top:2px;">{{ $b->fecha->format('d/m') }}</div>
          </div>
          @endforeach
        </div>
        @endif
      </div>
    </div>
    @endif

  </div>

  {{-- COLUMNA DERECHA --}}
  <div class="col-lg-8">

    {{-- Datos clínicos actuales --}}
    @if($ultimoSnapshot)
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Datos Clínicos Actuales</span>
        <span class="text-muted" style="font-size:0.78rem;">{{ $ultimoSnapshot->fecha_snapshot->format('d/m/Y') }}</span>
      </div>
      <div class="card-body">
        <div class="row g-3">
          @if($ultimoSnapshot->cie10)
          <div class="col-12">
            <label class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">CIE-10</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->cie10 }}</div>
          </div>
          @endif
          @if($ultimoSnapshot->diagnosticos)
          <div class="col-12">
            <label class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Diagnósticos</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->diagnosticos }}</div>
          </div>
          @endif
          @if($ultimoSnapshot->especialidad)
          <div class="col-12">
            <label class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Especialidad</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->especialidad }}</div>
          </div>
          @endif
          <div class="col-md-6">
            <label class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Soportes</label>
            <div class="d-flex gap-2 flex-wrap">
              @if($ultimoSnapshot->soporte_ventilatorio)
                <span class="badge bg-info text-dark"><i class="bi bi-lungs me-1"></i>{{ $ultimoSnapshot->soporte_ventilatorio }}
                  @if($diasVmi > 0)<small>({{ $diasVmi }}d)</small>@endif
                </span>
              @endif
              @if($ultimoSnapshot->soporte_hemodinamico)
                <span class="badge bg-danger"><i class="bi bi-heart-pulse me-1"></i>{{ $ultimoSnapshot->soporte_hemodinamico }}
                  @if($diasVasopresor > 0)<small>({{ $diasVasopresor }}d)</small>@endif
                </span>
              @endif
              @if(!$ultimoSnapshot->soporte_ventilatorio && !$ultimoSnapshot->soporte_hemodinamico)
                <span class="text-muted" style="font-size:0.85rem;">Sin soporte activo</span>
              @endif
            </div>
          </div>
          <div class="col-12">
            <label class="text-muted mb-2" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Escalas Clínicas</label>
            <div class="row g-2">
              @foreach(['news'=>'NEWS','sofa'=>'SOFA','barthel'=>'BARTHEL','rass'=>'RASS','bps'=>'BPS','eva'=>'EVA','must'=>'MUST'] as $c => $l)
              @if($ultimoSnapshot->$c !== null)
              <div class="col-auto">
                <div class="text-center px-3 py-2 rounded {{ ($c=='news'&&$ultimoSnapshot->$c>=5)?'bg-danger text-white':'' }}" style="{{ ($c=='news'&&$ultimoSnapshot->$c>=5)?'':'background:#f8f9fa;' }}min-width:65px;">
                  <div class="fw-bold" style="font-size:1.1rem;">{{ $ultimoSnapshot->$c }}</div>
                  <div style="font-size:0.7rem;">{{ $l }}</div>
                </div>
              </div>
              @endif
              @endforeach
            </div>
          </div>
          @if($ultimoSnapshot->riesgos)
          <div class="col-12">
            <label class="text-muted mb-1" style="font-size:0.72rem;text-transform:uppercase;letter-spacing:1px;">Riesgos</label>
            <div style="font-size:0.82rem;white-space:pre-line;background:#fff8e1;padding:0.5rem 0.75rem;border-radius:6px;border-left:3px solid #ffc107;">{{ $ultimoSnapshot->riesgos }}</div>
          </div>
          @endif
        </div>
      </div>
    </div>
    @endif

    {{-- Gráfico de tendencia escalas --}}
    @if($tendencia->count() > 1)
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Tendencia de Escalas Clínicas</div>
      <div class="card-body">
        <canvas id="chartTendencia" style="max-height:200px;"></canvas>
      </div>
    </div>
    @endif

    {{-- Historial de cambios --}}
    <div class="card">
      <div class="card-header"><i class="bi bi-calendar-week me-2 text-primary"></i>Historial de Cambios por Carga</div>
      <div class="card-body p-0">
        <div class="accordion accordion-flush" id="historialAcc">
          @foreach($historial as $i => $snap)
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $i>0?'collapsed':'' }}" type="button"
                      data-bs-toggle="collapse" data-bs-target="#snap{{ $snap->id }}">
                <div class="d-flex align-items-center gap-3 w-100">
                  <span class="fw-semibold">{{ $snap->fecha_snapshot->format('d/m/Y') }}</span>
                  <span class="text-muted" style="font-size:0.78rem;">{{ $snap->carga->usuario->name ?? 'Sistema' }}</span>
                  @if($snap->cambios->count()>0)
                    <span class="badge bg-warning text-dark ms-auto me-3" style="font-size:0.7rem;">{{ $snap->cambios->count() }} cambio(s)</span>
                  @elseif($i==0)
                    <span class="badge bg-success ms-auto me-3" style="font-size:0.7rem;">Actual</span>
                  @endif
                </div>
              </button>
            </h2>
            <div id="snap{{ $snap->id }}" class="accordion-collapse collapse {{ $i==0?'show':'' }}">
              <div class="accordion-body py-2">
                @if($snap->cambios->count()>0)
                <table class="table table-sm mb-2" style="font-size:0.8rem;">
                  <thead><tr><th>Campo</th><th>Anterior</th><th>Nuevo</th></tr></thead>
                  <tbody>
                    @foreach($snap->cambios as $cambio)
                    <tr>
                      <td class="fw-semibold text-muted">{{ ucfirst(str_replace('_',' ',$cambio->campo)) }}</td>
                      <td class="text-danger" style="white-space:pre-line;max-width:180px;">{{ $cambio->valor_anterior ?? '—' }}</td>
                      <td class="text-success" style="white-space:pre-line;max-width:180px;">{{ $cambio->valor_nuevo ?? '—' }}</td>
                    </tr>
                    @endforeach
                  </tbody>
                </table>
                @else
                <span class="text-muted" style="font-size:0.82rem;">Sin cambios respecto al día anterior.</span>
                @endif
                <div class="d-flex gap-3" style="font-size:0.75rem;color:#aaa;">
                  <span>{{ $snap->ubicacion }} · {{ $snap->subunidad }}</span>
                  @if($snap->criterio_atencion)<span>{{ $snap->criterio_atencion }}</span>@endif
                </div>
              </div>
            </div>
          </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@if($tendencia->count() > 1)
<script>
const labels = {!! json_encode($tendencia->pluck('fecha_snapshot')->map(fn($d)=>$d->format('d/m'))) !!};
new Chart(document.getElementById('chartTendencia'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [
            { label: 'NEWS',    data: {!! json_encode($tendencia->pluck('news')) !!},    borderColor: '#dc3545', tension: 0.3, spanGaps: true, pointRadius: 4 },
            { label: 'RASS',    data: {!! json_encode($tendencia->pluck('rass')) !!},    borderColor: '#0dcaf0', tension: 0.3, spanGaps: true, pointRadius: 4 },
            { label: 'EVA',     data: {!! json_encode($tendencia->pluck('eva')) !!},     borderColor: '#ffc107', tension: 0.3, spanGaps: true, pointRadius: 4 },
            { label: 'BARTHEL', data: {!! json_encode($tendencia->pluck('barthel')) !!}, borderColor: '#198754', tension: 0.3, spanGaps: true, pointRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: false } },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
    }
});
</script>
@endif
@endpush
