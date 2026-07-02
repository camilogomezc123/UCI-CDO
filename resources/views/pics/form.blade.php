@extends('layouts.app')

@section('title', 'Evaluación PICS · ' . $paciente->nombre)

@section('content')
<div class="container-fluid">

    {{-- Encabezado --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-clipboard-plus me-2 text-primary"></i>
                Evaluación PICS · {{ $labelMom }}
            </h4>
            <div class="text-muted small mt-1">
                <strong>{{ $paciente->nombre }}</strong>
                · {{ $paciente->identificacion ?? 'Sin ID' }}
                · Egreso: {{ $paciente->egreso_uci?->format('d/m/Y') ?? '—' }}
                · Días transcurridos: <strong>{{ $diasDesdeEgreso }}</strong>
            </div>
        </div>
        <a href="{{ route('pics.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    @php
        $esFamilia = $esFamilia ?? (old('datos.tipo') === 'familia');
    @endphp

    <form action="{{ route('pics.store', [$paciente, $momento]) }}" method="POST">
        @csrf
        <input type="hidden" name="tipo" value="{{ $esFamilia ? 'familia' : 'paciente' }}">

        {{-- ── DISFAGIA (solo en egreso y tipo paciente) ─────────────────────────── --}}
        @if($momento === 'egreso' && !$esFamilia)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-cup-straw me-2 text-info"></i>Prueba de deglución (post-extubación)
                <span class="badge bg-info-subtle text-info ms-2 fw-normal">Cribado disfagia</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Prueba de deglución con agua 50 mL. Observar tos, cambio de voz, desaturación. Aplica si el paciente estuvo intubado ≥ 24 horas.
                </p>
                <div class="d-flex gap-3 flex-wrap">
                    @foreach(['pasa' => ['success','check-circle','Pasa'], 'falla' => ['danger','x-circle','Falla'], 'no_aplica' => ['secondary','dash-circle','No aplica'], 'pendiente' => ['warning','clock','Pendiente']] as $val => $info)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="datos[disfagia]"
                               id="disfagia_{{ $val }}" value="{{ $val }}"
                               {{ old('datos.disfagia') === $val ? 'checked' : '' }}>
                        <label class="form-check-label" for="disfagia_{{ $val }}">
                            <span class="badge bg-{{ $info[0] }}">
                                <i class="bi bi-{{ $info[1] }} me-1"></i>{{ $info[2] }}
                            </span>
                        </label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @if($esFamilia)
        {{-- ── PICS-F: CUIDADOR FAMILIAR ─────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-people me-2 text-purple"></i>PICS-F · Evaluación del cuidador familiar
                <span class="badge bg-secondary ms-2 fw-normal">5 ítems · 0-20 pts</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Completar con el cuidador principal. Cada ítem: Nunca=0, Pocas veces=1, Frecuentemente=2, Casi siempre=3, Siempre=4.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Ítem</th>
                                @foreach(['Nunca','Pocas veces','Frecuentemente','Casi siempre','Siempre'] as $i => $opt)
                                <th class="text-center">{{ $opt }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(\App\Http\Controllers\PicsController::PICSF_ITEMS as $idx => [$preg, $opts])
                            <tr>
                                <td class="small">{{ $idx+1 }}. {{ $preg }}</td>
                                @foreach($opts as $val => $opt)
                                <td class="text-center">
                                    <input type="radio" name="datos[picsf][{{ $idx }}]"
                                           class="form-check-input" value="{{ $val }}"
                                           {{ old("datos.picsf.$idx") == $val ? 'checked' : '' }}>
                                </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @else
        {{-- ── AMT-10: COGNITIVO ────────────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-brain me-2 text-primary"></i>AMT-10 · Estado cognitivo
                <span class="badge bg-secondary ms-2 fw-normal">10 preguntas · ≥8 normal</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Lea cada pregunta en voz alta. Marque <strong>Sí</strong> si responde correctamente.
                    Score ≥8: normal · 6-7: deterioro leve · &lt;6: deterioro significativo.
                </p>
                <div class="row row-cols-1 row-cols-md-2 g-2">
                    @foreach(\App\Http\Controllers\PicsController::AMT_ITEMS as $idx => $preg)
                    <div class="col">
                        <div class="d-flex align-items-center gap-3 p-2 border rounded bg-light">
                            <span class="badge bg-primary rounded-pill">{{ $idx+1 }}</span>
                            <span class="small flex-grow-1">{{ $preg }}</span>
                            <div class="d-flex gap-2">
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio"
                                           name="datos[amt][{{ $idx }}]" id="amt_{{ $idx }}_1"
                                           value="1" {{ old("datos.amt.$idx") == '1' ? 'checked' : '' }}>
                                    <label class="form-check-label text-success fw-semibold" for="amt_{{ $idx }}_1">Sí</label>
                                </div>
                                <div class="form-check form-check-inline mb-0">
                                    <input class="form-check-input" type="radio"
                                           name="datos[amt][{{ $idx }}]" id="amt_{{ $idx }}_0"
                                           value="0" {{ old("datos.amt.$idx") == '0' ? 'checked' : '' }}>
                                    <label class="form-check-label text-danger" for="amt_{{ $idx }}_0">No</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="mt-2 text-end">
                    <span class="small text-muted">Total: <strong id="amt_total">0</strong> / 10</span>
                </div>
            </div>
        </div>

        {{-- ── HADS: ANSIEDAD + DEPRESIÓN ────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-emoji-neutral me-2 text-warning"></i>HADS · Ansiedad y Depresión
                <span class="badge bg-secondary ms-2 fw-normal">14 ítems · ≤7 normal · ≥11 clínico</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    ítems 1-7: Ansiedad · ítems 8-14: Depresión. Cada ítem 0-3 pts. Subescalas: ≤7=normal, 8-10=limítrofe, ≥11=clínico.
                </p>

                {{-- Ansiedad --}}
                <h6 class="text-warning"><i class="bi bi-lightning me-1"></i>Ansiedad (ítems 1-7)</h6>
                @foreach(\App\Http\Controllers\PicsController::HADS_ITEMS as $idx => [$preg, $opts])
                @if($idx < 7)
                <div class="mb-3">
                    <p class="small fw-semibold mb-1">{{ $idx+1 }}. {{ $preg }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($opts as $val => $opt)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input hads-ans" type="radio"
                                   name="datos[hads][{{ $idx }}]" id="hads_{{ $idx }}_{{ $val }}"
                                   value="{{ $val }}" {{ old("datos.hads.$idx") == $val ? 'checked' : '' }}>
                            <label class="form-check-label small" for="hads_{{ $idx }}_{{ $val }}">
                                <span class="badge bg-light text-dark border">{{ $val }} – {{ $opt }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach
                <div class="text-end mb-3">
                    <small class="text-muted">Subtotal Ansiedad: <strong id="hads_ans_total">0</strong> / 21</small>
                </div>

                <hr>

                {{-- Depresión --}}
                <h6 class="text-info"><i class="bi bi-cloud-drizzle me-1"></i>Depresión (ítems 8-14)</h6>
                @foreach(\App\Http\Controllers\PicsController::HADS_ITEMS as $idx => [$preg, $opts])
                @if($idx >= 7)
                <div class="mb-3">
                    <p class="small fw-semibold mb-1">{{ $idx+1 }}. {{ $preg }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($opts as $val => $opt)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input hads-dep" type="radio"
                                   name="datos[hads][{{ $idx }}]" id="hads_{{ $idx }}_{{ $val }}"
                                   value="{{ $val }}" {{ old("datos.hads.$idx") == $val ? 'checked' : '' }}>
                            <label class="form-check-label small" for="hads_{{ $idx }}_{{ $val }}">
                                <span class="badge bg-light text-dark border">{{ $val }} – {{ $opt }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach
                <div class="text-end">
                    <small class="text-muted">Subtotal Depresión: <strong id="hads_dep_total">0</strong> / 21</small>
                </div>
            </div>
        </div>

        {{-- ── PC-PTSD-5 ──────────────────────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-shield-exclamation me-2 text-danger"></i>PC-PTSD-5 · Estrés postraumático
                <span class="badge bg-secondary ms-2 fw-normal">5 ítems · ≥3 probable PTSD</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Lea cada pregunta pensando en su experiencia en la UCI durante el último mes. Responda Sí o No.
                </p>
                @foreach(\App\Http\Controllers\PicsController::PCPTSD_ITEMS as $idx => $preg)
                <div class="d-flex align-items-center gap-3 p-2 border rounded mb-2 bg-light">
                    <span class="badge bg-danger rounded-pill">{{ $idx+1 }}</span>
                    <span class="small flex-grow-1">{{ $preg }}</span>
                    <div class="d-flex gap-3">
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input ptsd-input" type="radio"
                                   name="datos[pcptsd][{{ $idx }}]" id="ptsd_{{ $idx }}_1"
                                   value="1" {{ old("datos.pcptsd.$idx") == '1' ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold text-danger" for="ptsd_{{ $idx }}_1">Sí</label>
                        </div>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input ptsd-input" type="radio"
                                   name="datos[pcptsd][{{ $idx }}]" id="ptsd_{{ $idx }}_0"
                                   value="0" {{ old("datos.pcptsd.$idx") == '0' ? 'checked' : '' }}>
                            <label class="form-check-label text-success" for="ptsd_{{ $idx }}_0">No</label>
                        </div>
                    </div>
                </div>
                @endforeach
                <div class="mt-2 text-end">
                    <small class="text-muted">Total: <strong id="ptsd_total">0</strong> / 5
                        <span id="ptsd_alerta" class="badge bg-danger ms-2 d-none">Probable PTSD ≥ 3</span>
                    </small>
                </div>
            </div>
        </div>

        {{-- ── FATIGA Y DOLOR ─────────────────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-activity me-2 text-secondary"></i>Fatiga y Dolor crónico
                <span class="badge bg-secondary ms-2 fw-normal">Escalas NRS 0-10</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Fatiga percibida
                            <span class="text-muted fw-normal">(0=ninguna, 10=agotamiento total)</span>
                        </label>
                        <input type="range" class="form-range" min="0" max="10" step="0.5"
                               name="datos[fatiga]" id="fatiga_range"
                               value="{{ old('datos.fatiga', 0) }}"
                               oninput="document.getElementById('fatiga_val').textContent = this.value">
                        <div class="text-center fw-bold"><span id="fatiga_val">{{ old('datos.fatiga', 0) }}</span> / 10</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Dolor en reposo (NRS)</label>
                        <input type="range" class="form-range" min="0" max="10" step="0.5"
                               name="datos[dolor_reposo]" id="dolor_r_range"
                               value="{{ old('datos.dolor_reposo', 0) }}"
                               oninput="document.getElementById('dolor_r_val').textContent = this.value">
                        <div class="text-center fw-bold"><span id="dolor_r_val">{{ old('datos.dolor_reposo', 0) }}</span> / 10</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Dolor con movimiento (NRS)</label>
                        <input type="range" class="form-range" min="0" max="10" step="0.5"
                               name="datos[dolor_movimiento]" id="dolor_m_range"
                               value="{{ old('datos.dolor_movimiento', 0) }}"
                               oninput="document.getElementById('dolor_m_val').textContent = this.value">
                        <div class="text-center fw-bold"><span id="dolor_m_val">{{ old('datos.dolor_movimiento', 0) }}</span> / 10</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── PTG-SF (solo 90d y 180d) ────────────────────────────────────────── --}}
        @if($conPtg)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-tree me-2 text-success"></i>PTG-SF · Crecimiento Postraumático
                <span class="badge bg-secondary ms-2 fw-normal">10 ítems × 0-5 · Total 0-50</span>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    "¿En qué medida ha experimentado estos cambios como resultado de su paso por la UCI?"
                    0=Ningún cambio, 1=Muy poco, 2=Poco, 3=Moderado, 4=Bastante, 5=Muchísimo.
                </p>
                @foreach(\App\Http\Controllers\PicsController::PTG_ITEMS as $idx => $preg)
                <div class="mb-3">
                    <label class="small fw-semibold mb-1 d-block">{{ $idx+1 }}. {{ $preg }}</label>
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach([0,1,2,3,4,5] as $val)
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input ptg-input" type="radio"
                                   name="datos[ptg][{{ $idx }}]" id="ptg_{{ $idx }}_{{ $val }}"
                                   value="{{ $val }}" {{ old("datos.ptg.$idx") == $val ? 'checked' : '' }}>
                            <label class="form-check-label" for="ptg_{{ $idx }}_{{ $val }}">
                                <span class="badge bg-light text-dark border">{{ $val }}</span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
                <div class="text-end">
                    <small class="text-muted">Total PTG: <strong id="ptg_total">0</strong> / 50</small>
                </div>
            </div>
        </div>
        @endif

        @endif {{-- fin !esFamilia --}}

        {{-- ── OBSERVACIONES ──────────────────────────────────────────────────── --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-chat-text me-2 text-muted"></i>Observaciones
            </div>
            <div class="card-body">
                <textarea name="datos[observaciones]" class="form-control" rows="3"
                          placeholder="Observaciones clínicas relevantes, barreras, contexto...">{{ old('datos.observaciones') }}</textarea>
            </div>
        </div>

        {{-- ── Botones ─────────────────────────────────────────────────────────── --}}
        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="{{ route('pics.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-x me-1"></i>Cancelar
            </a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="bi bi-save me-1"></i>Guardar evaluación
            </button>
        </div>
    </form>

</div>

@push('scripts')
<script>
// AMT contador en tiempo real
document.querySelectorAll('[name^="datos[amt]"]').forEach(el => {
    el.addEventListener('change', () => {
        const total = document.querySelectorAll('[name^="datos[amt]"]:checked[value="1"]').length;
        document.getElementById('amt_total').textContent = total;
    });
});

// HADS contadores
function sumarHads(cls, outputId) {
    return document.querySelectorAll('.' + cls + ':checked').reduce((s, el) => s + parseInt(el.value), 0);
}
document.querySelectorAll('.hads-ans, .hads-dep').forEach(el => {
    el.addEventListener('change', () => {
        document.getElementById('hads_ans_total').textContent = sumarHads('hads-ans', 'hads_ans_total');
        document.getElementById('hads_dep_total').textContent = sumarHads('hads-dep', 'hads_dep_total');
    });
});

// PC-PTSD contador + alerta
document.querySelectorAll('.ptsd-input').forEach(el => {
    el.addEventListener('change', () => {
        const total = document.querySelectorAll('.ptsd-input:checked[value="1"]').length;
        document.getElementById('ptsd_total').textContent = total;
        document.getElementById('ptsd_alerta').classList.toggle('d-none', total < 3);
    });
});

// PTG contador
document.querySelectorAll('.ptg-input').forEach(el => {
    el.addEventListener('change', () => {
        const total = Array.from(document.querySelectorAll('.ptg-input:checked'))
            .reduce((s, el) => s + parseInt(el.value), 0);
        const el2 = document.getElementById('ptg_total');
        if (el2) el2.textContent = total;
    });
});
</script>
@endpush
@endsection
