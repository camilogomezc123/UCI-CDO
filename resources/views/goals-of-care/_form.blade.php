<div class="mb-3">
    <label class="form-label fw-semibold">Fecha de conversación</label>
    <input type="date" name="fecha_conversacion" class="form-control"
           value="{{ $goc?->fecha_conversacion?->toDateString() ?? today()->toDateString() }}" required>
</div>

<div class="mb-3">
    <label class="form-label fw-semibold">Nivel de esfuerzo terapéutico</label>
    <div class="d-flex flex-column gap-2">
        @foreach($niveles as $key => [$label, $color, $icon])
        <div class="form-check border rounded p-3 {{ $goc?->nivel_esfuerzo === $key ? 'border-'.$color.' bg-'.$color.'-subtle' : '' }}">
            <input class="form-check-input" type="radio" name="nivel_esfuerzo"
                   id="niv_{{ $key }}" value="{{ $key }}"
                   {{ $goc?->nivel_esfuerzo === $key ? 'checked' : ($goc === null && $key === 'maximo' ? 'checked' : '') }} required>
            <label class="form-check-label" for="niv_{{ $key }}">
                <span class="badge bg-{{ $color }} me-2"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</span>
            </label>
        </div>
        @endforeach
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="dnr" value="1" id="dnr_check"
                   {{ $goc?->dnr ? 'checked' : '' }}>
            <label class="form-check-label fw-semibold" for="dnr_check">
                <span class="badge bg-dark">DNR</span> No reanimar
            </label>
        </div>
    </div>
    <div class="col-md-8">
        <label class="form-label small">Tratamiento limitado hasta (fecha opcional)</label>
        <input type="date" name="tiempo_limitado_hasta" class="form-control form-control-sm"
               value="{{ $goc?->tiempo_limitado_hasta?->toDateString() }}">
    </div>
</div>

<div class="mb-3">
    <label class="form-label small fw-semibold">¿Quiénes participaron en la conversación?</label>
    <input name="quien_participo" class="form-control form-control-sm"
           value="{{ $goc?->quien_participo }}"
           placeholder="Ej: Médico tratante, familia (esposa), enfermera jefe...">
</div>

<div class="mb-3">
    <label class="form-label small fw-semibold">Plan de cuidados acordado</label>
    <textarea name="plan_cuidados" class="form-control form-control-sm" rows="3"
              placeholder="Ej: Se acuerda limitar ventilación mecánica, continuar analgesia y sedación para confort...">{{ $goc?->plan_cuidados }}</textarea>
</div>

<div class="mb-0">
    <label class="form-label small">Observaciones adicionales</label>
    <textarea name="observaciones" class="form-control form-control-sm" rows="2">{{ $goc?->observaciones }}</textarea>
</div>
