@extends('layouts.app')
@section('title', 'Cargar Archivo')
@section('page-title', 'Carga Diaria de Archivo UCI')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">

        @if($ultimaCarga)
        <div class="alert alert-info d-flex align-items-start gap-3 mb-4" style="border-radius:12px;">
            <i class="bi bi-info-circle-fill fs-4 mt-1"></i>
            <div>
                <strong>Última carga registrada</strong><br>
                <span style="font-size:0.875rem;">
                    {{ $ultimaCarga->nombre_archivo }} —
                    {{ $ultimaCarga->created_at->format('d/m/Y H:i') }}
                    por <strong>{{ $ultimaCarga->usuario->name ?? 'Desconocido' }}</strong>
                </span><br>
                <span style="font-size:0.8rem;" class="text-muted">
                    {{ $ultimaCarga->nuevos }} nuevos · {{ $ultimaCarga->actualizados }} actualizados · {{ $ultimaCarga->omitidos }} omitidos
                </span>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-cloud-upload text-primary fs-5"></i>
                <span class="fw-semibold">Subir archivos Excel del Tablero UCI</span>
                <span class="badge bg-secondary ms-auto">Múltiples archivos</span>
            </div>
            <div class="card-body">

                <div class="alert alert-light border mb-4" style="font-size:0.85rem;">
                    <i class="bi bi-lightbulb-fill text-warning me-2"></i>
                    <strong>¿Qué hace este proceso?</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <li>Acepta archivos <code>.xlsx</code> y <code>.xls</code> de cualquier torre o unidad</li>
                        <li>Puede subir varios archivos a la vez (ej: Torre A + Torre B + Torre C)</li>
                        <li>La fecha del snapshot se extrae automáticamente del nombre del archivo</li>
                        <li>Pacientes <strong>nuevos</strong> se crean; <strong>existentes</strong> se actualizan con histórico</li>
                        <li>Se excluyen automáticamente neonatos (UCIN) y pediátricos</li>
                    </ul>
                </div>

                <form method="POST" action="{{ route('carga.store') }}" enctype="multipart/form-data" id="formCarga">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Seleccionar archivos Excel</label>

                        <div class="upload-area" id="uploadArea">
                            <input type="file" name="archivos[]" id="archivoInput"
                                   accept=".xlsx,.xls" multiple class="d-none @error('archivos') is-invalid @enderror @error('archivos.*') is-invalid @enderror">

                            <div id="uploadPlaceholder" class="text-center py-4">
                                <i class="bi bi-file-earmark-spreadsheet text-success" style="font-size:3rem;"></i>
                                <p class="mt-2 mb-1 fw-semibold">Haga clic o arrastre los archivos aquí</p>
                                <p class="text-muted mb-0" style="font-size:0.82rem;">
                                    Formatos: .xlsx, .xls · Máx 20 MB por archivo · Hasta 10 archivos simultáneos
                                </p>
                            </div>

                            <div id="uploadSelected" class="d-none p-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <span class="fw-semibold text-success">
                                        <i class="bi bi-check-circle-fill me-1"></i>
                                        <span id="contadorArchivos"></span>
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFiles()">
                                        <i class="bi bi-x me-1"></i>Limpiar
                                    </button>
                                </div>
                                <ul id="listaArchivos" class="list-group list-group-flush" style="font-size:0.85rem;"></ul>
                            </div>
                        </div>

                        @error('archivos')
                        <div class="text-danger mt-1" style="font-size:0.85rem;"><i class="bi bi-x-circle me-1"></i>{{ $message }}</div>
                        @enderror
                        @error('archivos.*')
                        <div class="text-danger mt-1" style="font-size:0.85rem;"><i class="bi bi-x-circle me-1"></i>{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="btnSubmitContainer">
                        <button type="submit" class="btn btn-primary w-100" id="btnSubmit" disabled>
                            <i class="bi bi-cloud-upload me-2"></i>
                            <span id="btnTexto">Procesar archivos</span>
                        </button>
                    </div>

                    <div id="loadingMsg" class="d-none text-center mt-3">
                        <div class="spinner-border text-primary" role="status" style="width:1.5rem;height:1.5rem;"></div>
                        <span class="ms-2 text-muted">Procesando archivos... por favor espere.</span>
                    </div>
                </form>

            </div>
            <div class="card-footer text-end">
                <a href="{{ route('carga.historial') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-clock-history me-1"></i>Ver historial de cargas
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    cursor: pointer;
    transition: border-color 0.2s, background 0.2s;
    background: #fafafa;
    min-height: 120px;
}
.upload-area:hover, .upload-area.dragover {
    border-color: #0d6efd;
    background: #f0f4ff;
}
#listaArchivos .list-group-item {
    padding: 0.4rem 0.25rem;
    background: transparent;
    border: none;
    border-bottom: 1px solid #f0f0f0;
}
#listaArchivos .list-group-item:last-child { border-bottom: none; }
</style>
@endpush

@push('scripts')
<script>
const input       = document.getElementById('archivoInput');
const area        = document.getElementById('uploadArea');
const placeholder = document.getElementById('uploadPlaceholder');
const selected    = document.getElementById('uploadSelected');
const btnSubmit   = document.getElementById('btnSubmit');
const btnTexto    = document.getElementById('btnTexto');
const form        = document.getElementById('formCarga');

area.addEventListener('click', e => {
    if (!e.target.closest('button')) input.click();
});

area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('dragover'); });
area.addEventListener('dragleave', () => area.classList.remove('dragover'));
area.addEventListener('drop', e => {
    e.preventDefault();
    area.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        // Asignar archivos al input via DataTransfer
        const dt = new DataTransfer();
        Array.from(e.dataTransfer.files).forEach(f => dt.items.add(f));
        input.files = dt.files;
        renderArchivos(input.files);
    }
});

input.addEventListener('change', () => {
    if (input.files.length) renderArchivos(input.files);
});

function renderArchivos(files) {
    const lista = document.getElementById('listaArchivos');
    const contador = document.getElementById('contadorArchivos');

    lista.innerHTML = '';
    let totalKB = 0;

    Array.from(files).forEach((f, i) => {
        const kb = (f.size / 1024).toFixed(1);
        totalKB += f.size / 1024;
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center gap-2';
        li.innerHTML = `<i class="bi bi-file-earmark-excel text-success"></i>
            <span class="flex-grow-1">${f.name}</span>
            <span class="text-muted">${kb} KB</span>`;
        lista.appendChild(li);
    });

    contador.textContent = files.length === 1
        ? '1 archivo seleccionado'
        : `${files.length} archivos seleccionados (${(totalKB / 1024).toFixed(1)} MB total)`;

    btnTexto.textContent = files.length === 1 ? 'Procesar archivo' : `Procesar ${files.length} archivos`;

    placeholder.classList.add('d-none');
    selected.classList.remove('d-none');
    btnSubmit.disabled = false;
}

function clearFiles() {
    input.value = '';
    placeholder.classList.remove('d-none');
    selected.classList.add('d-none');
    btnSubmit.disabled = true;
    btnTexto.textContent = 'Procesar archivos';
}

form.addEventListener('submit', () => {
    btnSubmit.disabled = true;
    document.getElementById('loadingMsg').classList.remove('d-none');
});
</script>
@endpush
