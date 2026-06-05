@extends('layouts.app')
@section('title', $paciente->nombre)
@section('page-title')
<a href="{{ route('pacientes.index') }}" class="text-decoration-none text-muted me-2" style="font-size:0.9rem;"><i class="bi bi-arrow-left"></i></a>
Paciente: {{ $paciente->nombre }}
@endsection

@section('content')
<div class="row g-3">

  {{-- Panel izquierdo: datos + ciclo de vida --}}
  <div class="col-lg-4">

    {{-- Info básica --}}
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-person-badge me-2 text-primary"></i>Datos del Paciente</div>
      <div class="card-body">
        @if($ultimoSnapshot)
        <div class="mb-2">
          <span class="badge bg-secondary">{{ $ultimoSnapshot->ubicacion }}</span>
          <span class="badge ms-1" style="background:#e8f0ff;color:#0d6efd;">{{ $ultimoSnapshot->subunidad }}</span>
        </div>
        @endif
        <table class="table table-sm table-borderless mb-0" style="font-size:0.85rem;">
          <tr><td class="text-muted" style="width:40%">Documento</td><td class="fw-semibold">{{ $paciente->documento }}</td></tr>
          <tr><td class="text-muted">Edad</td><td>{{ $paciente->edad == 0 ? 'Neonato' : $paciente->edad . ' años' }}</td></tr>
          <tr><td class="text-muted">Sexo</td><td>{{ $paciente->sexo == 'F' ? 'Femenino' : ($paciente->sexo == 'M' ? 'Masculino' : '—') }}</td></tr>
          <tr><td class="text-muted">EAPB</td><td>{{ $paciente->eapb ?? '—' }}</td></tr>
          @if($ultimoSnapshot)
          <tr><td class="text-muted">Criterio</td><td>
            @php
              $c = $ultimoSnapshot->criterio_atencion ?? '';
              $label = str_contains($c,'INTENSIVO') ? 'UCI Intensivo' : (str_contains($c,'INTERMEDIO') ? 'UCI Intermedio' : 'Otro');
              $cls = str_contains($c,'INTENSIVO') ? 'criterio-intensivo' : (str_contains($c,'INTERMEDIO') ? 'criterio-intermedio' : 'criterio-otros');
            @endphp
            <span class="badge badge-criterio {{ $cls }}">{{ $label }}</span>
          </td></tr>
          @endif
        </table>
      </div>
    </div>

    {{-- Ciclo de vida UCI --}}
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-clock-history me-2 text-primary"></i>Ciclo de Vida en UCI</div>
      <div class="card-body">

        {{-- Ingreso UCI --}}
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 mb-1">
            <div style="width:10px;height:10px;border-radius:50%;background:#198754;"></div>
            <span class="fw-semibold" style="font-size:0.85rem;">Ingreso a UCI</span>
          </div>
          @if($paciente->ingreso_uci)
            <div class="ps-4 text-success fw-bold" style="font-size:0.9rem;">
              {{ $paciente->ingreso_uci->format('d/m/Y H:i') }}
            </div>
            <div class="ps-4 text-muted" style="font-size:0.78rem;">Tiempo en UCI: <strong class="tiempo-uci">{{ $paciente->tiempoEnUciTexto() }}</strong></div>
          @else
            <div class="ps-4">
              <span class="badge bg-warning text-dark mb-2">Sin registrar</span>
              <form method="POST" action="{{ route('pacientes.ingreso', $paciente) }}">
                @csrf @method('PATCH')
                <div class="input-group input-group-sm">
                  <input type="datetime-local" name="ingreso_uci" class="form-control @error('ingreso_uci') is-invalid @enderror" required>
                  <button class="btn btn-success btn-sm" type="submit">
                    <i class="bi bi-check-lg"></i>
                  </button>
                </div>
                @error('ingreso_uci')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </form>
            </div>
          @endif
        </div>

        <div style="border-left:2px dashed #dee2e6;margin-left:4px;padding-left:1rem;margin-bottom:0.75rem;">

          {{-- Salida para hospitalización --}}
          <div class="mb-3">
            <div class="d-flex align-items-center gap-2 mb-1">
              <div style="width:10px;height:10px;border-radius:50%;background:{{ $paciente->salida_hospitalizacion ? '#fd7e14' : '#dee2e6' }};"></div>
              <span class="fw-semibold" style="font-size:0.85rem;">Salida para hospitalización</span>
            </div>
            @if($paciente->salida_hospitalizacion)
              <div class="ms-3 text-warning fw-bold" style="font-size:0.9rem;">
                {{ $paciente->salida_hospitalizacion->format('d/m/Y H:i') }}
              </div>
              @if(!$paciente->egreso_uci)
                <div class="ms-3 text-danger" style="font-size:0.78rem;">
                  Esperando egreso hace: <strong>{{ $paciente->tiempoEsperaHospitalizacion() }}</strong>
                </div>
              @endif
              <div class="ms-3 mt-1">
                <form method="POST" action="{{ route('pacientes.salida-hospitalizacion', $paciente) }}">
                  @csrf @method('PATCH')
                  <div class="input-group input-group-sm" style="max-width:280px;">
                    <input type="datetime-local" name="salida_hospitalizacion"
                           value="{{ $paciente->salida_hospitalizacion->format('Y-m-d\TH:i') }}"
                           class="form-control">
                    <button class="btn btn-outline-warning btn-sm" type="submit" title="Actualizar">
                      <i class="bi bi-pencil"></i>
                    </button>
                  </div>
                </form>
              </div>
            @else
              <div class="ms-3">
                <form method="POST" action="{{ route('pacientes.salida-hospitalizacion', $paciente) }}">
                  @csrf @method('PATCH')
                  <div class="input-group input-group-sm" style="max-width:280px;">
                    <input type="datetime-local" name="salida_hospitalizacion" class="form-control @error('salida_hospitalizacion') is-invalid @enderror" required>
                    <button class="btn btn-warning btn-sm" type="submit">
                      <i class="bi bi-check-lg"></i> Registrar
                    </button>
                  </div>
                  @error('salida_hospitalizacion')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </form>
              </div>
            @endif
          </div>

        </div>

        {{-- Egreso efectivo UCI --}}
        <div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <div style="width:10px;height:10px;border-radius:50%;background:{{ $paciente->egreso_uci ? '#dc3545' : '#dee2e6' }};"></div>
            <span class="fw-semibold" style="font-size:0.85rem;">Egreso efectivo de UCI</span>
          </div>
          @if($paciente->egreso_uci)
            <div class="ps-3 text-danger fw-bold" style="font-size:0.9rem;">
              {{ $paciente->egreso_uci->format('d/m/Y H:i') }}
            </div>
            @if($paciente->salida_hospitalizacion)
            <div class="ps-3 text-muted" style="font-size:0.78rem;">
              Tiempo espera con crit. hospitalización: <strong class="tiempo-espera">{{ $paciente->tiempoEsperaHospitalizacion() }}</strong>
            </div>
            @endif
            @if($paciente->ingreso_uci)
            <div class="ps-3 text-muted" style="font-size:0.78rem;">
              Tiempo total en UCI: <strong class="tiempo-uci">{{ $paciente->tiempoEnUciTexto() }}</strong>
            </div>
            @endif
          @else
            <div class="ps-3">
              <form method="POST" action="{{ route('pacientes.egreso-uci', $paciente) }}">
                @csrf @method('PATCH')
                <div class="input-group input-group-sm" style="max-width:280px;">
                  <input type="datetime-local" name="egreso_uci" class="form-control @error('egreso_uci') is-invalid @enderror" required>
                  <button class="btn btn-danger btn-sm" type="submit">
                    <i class="bi bi-check-lg"></i> Registrar egreso
                  </button>
                </div>
                @error('egreso_uci')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
              </form>
            </div>
          @endif
        </div>

      </div>
    </div>
  </div>

  {{-- Panel derecho: datos clínicos + historial --}}
  <div class="col-lg-8">

    {{-- Datos clínicos del último snapshot --}}
    @if($ultimoSnapshot)
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Datos Clínicos Actuales</span>
        <span class="text-muted" style="font-size:0.78rem;">
          Último snapshot: {{ $ultimoSnapshot->fecha_snapshot->format('d/m/Y') }}
        </span>
      </div>
      <div class="card-body">
        <div class="row g-3">

          {{-- CIE10 --}}
          @if($ultimoSnapshot->cie10)
          <div class="col-12">
            <label class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">CIE-10</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->cie10 }}</div>
          </div>
          @endif

          @if($ultimoSnapshot->diagnosticos)
          <div class="col-12">
            <label class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Diagnósticos</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->diagnosticos }}</div>
          </div>
          @endif

          @if($ultimoSnapshot->especialidad)
          <div class="col-md-6">
            <label class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Especialidad</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->especialidad }}</div>
          </div>
          @endif

          {{-- Soportes --}}
          <div class="col-md-6">
            <label class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Soportes</label>
            <div class="d-flex gap-2 flex-wrap">
              @if($ultimoSnapshot->soporte_ventilatorio)
                <span class="badge bg-info text-dark"><i class="bi bi-lungs me-1"></i>{{ $ultimoSnapshot->soporte_ventilatorio }}</span>
              @endif
              @if($ultimoSnapshot->soporte_hemodinamico)
                <span class="badge bg-danger"><i class="bi bi-heart-pulse me-1"></i>{{ $ultimoSnapshot->soporte_hemodinamico }}</span>
              @endif
              @if(!$ultimoSnapshot->soporte_ventilatorio && !$ultimoSnapshot->soporte_hemodinamico)
                <span class="text-muted" style="font-size:0.85rem;">Sin soporte activo</span>
              @endif
            </div>
          </div>

          {{-- Escalas clínicas --}}
          <div class="col-12">
            <label class="text-muted mb-2" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Escalas Clínicas</label>
            <div class="row g-2">
              @foreach(['news'=>'NEWS','sofa'=>'SOFA','barthel'=>'BARTHEL','rass'=>'RASS','bps'=>'BPS','eva'=>'EVA','must'=>'MUST'] as $campo => $label)
              @if($ultimoSnapshot->$campo !== null)
              <div class="col-auto">
                <div class="text-center px-3 py-2 rounded" style="background:#f8f9fa;min-width:65px;">
                  <div class="fw-bold" style="font-size:1.1rem;">{{ $ultimoSnapshot->$campo }}</div>
                  <div class="text-muted" style="font-size:0.7rem;">{{ $label }}</div>
                </div>
              </div>
              @endif
              @endforeach
            </div>
          </div>

          {{-- Riesgos --}}
          @if($ultimoSnapshot->riesgos)
          <div class="col-12">
            <label class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Riesgos</label>
            <div style="font-size:0.82rem;white-space:pre-line;background:#fff8e1;padding:0.5rem 0.75rem;border-radius:6px;border-left:3px solid #ffc107;">{{ $ultimoSnapshot->riesgos }}</div>
          </div>
          @endif

          @if($ultimoSnapshot->observaciones)
          <div class="col-md-6">
            <label class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Observaciones</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->observaciones }}</div>
          </div>
          @endif

          @if($ultimoSnapshot->metas_clinicas)
          <div class="col-md-6">
            <label class="text-muted mb-1" style="font-size:0.75rem;text-transform:uppercase;letter-spacing:1px;">Metas Clínicas</label>
            <div style="font-size:0.85rem;white-space:pre-line;">{{ $ultimoSnapshot->metas_clinicas }}</div>
          </div>
          @endif
        </div>
      </div>
    </div>
    @endif

    {{-- Historial de snapshots --}}
    <div class="card">
      <div class="card-header"><i class="bi bi-calendar-week me-2 text-primary"></i>Historial de Cambios</div>
      <div class="card-body p-0">
        <div class="accordion accordion-flush" id="historialAcc">
          @foreach($historial as $i => $snap)
          <div class="accordion-item">
            <h2 class="accordion-header">
              <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }}" type="button"
                      data-bs-toggle="collapse" data-bs-target="#snap{{ $snap->id }}">
                <div class="d-flex align-items-center gap-3 w-100">
                  <span class="fw-semibold">{{ $snap->fecha_snapshot->format('d/m/Y') }}</span>
                  <span class="text-muted" style="font-size:0.78rem;">
                    {{ $snap->carga->usuario->name ?? 'Sistema' }} — Cama {{ $snap->ubicacion }}
                  </span>
                  @if($snap->cambios->count() > 0)
                    <span class="badge bg-warning text-dark ms-auto me-3" style="font-size:0.7rem;">
                      {{ $snap->cambios->count() }} cambio(s)
                    </span>
                  @elseif($i == 0)
                    <span class="badge bg-success ms-auto me-3" style="font-size:0.7rem;">Actual</span>
                  @endif
                </div>
              </button>
            </h2>
            <div id="snap{{ $snap->id }}" class="accordion-collapse collapse {{ $i == 0 ? 'show' : '' }}">
              <div class="accordion-body py-2">
                @if($snap->cambios->count() > 0)
                  <table class="table table-sm mb-2" style="font-size:0.8rem;">
                    <thead><tr><th>Campo modificado</th><th>Valor anterior</th><th>Valor nuevo</th></tr></thead>
                    <tbody>
                      @foreach($snap->cambios as $cambio)
                      <tr>
                        <td class="fw-semibold text-muted">{{ ucfirst(str_replace('_', ' ', $cambio->campo)) }}</td>
                        <td class="text-danger" style="white-space:pre-line;max-width:200px;overflow:hidden;text-overflow:ellipsis;">{{ $cambio->valor_anterior ?? '—' }}</td>
                        <td class="text-success" style="white-space:pre-line;max-width:200px;overflow:hidden;text-overflow:ellipsis;">{{ $cambio->valor_nuevo ?? '—' }}</td>
                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                @else
                  <span class="text-muted" style="font-size:0.82rem;">Sin cambios detectados respecto al día anterior.</span>
                @endif
                <div class="d-flex gap-3" style="font-size:0.78rem;color:#888;">
                  <span><i class="bi bi-geo-alt me-1"></i>{{ $snap->ubicacion }} · {{ $snap->subunidad }}</span>
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
