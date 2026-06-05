@extends('layouts.app')
@section('title', 'Cargar Archivo')
@section('page-title', 'Carga Diaria de Archivo UCI')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">

        {{-- Info última carga --}}
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
            <div class="card-header">
                <i class="bi bi-cloud-upload me-2 text-primary"></i>Subir archivo Excel del Tablero UCI
            </div>
            <div class="card-body">
                <div class="alert alert-light border mb-4" style="font-size:0.85rem;">
                    <i class="bi bi-lightbulb-fill text-warning me-2"></i>
                    <strong>¿Qué hace este proceso?</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <li>Lee el archivo <code>.xlsx</code> del tablero UCI (mismo formato que <em>datos.xlsx</em>)</li>
                        <li>Pacientes <strong>nuevos</strong>: se crean en el sistema</li>
                        <li>Pacientes <strong>existentes</strong>: se actualiza su información, guardando el día anterior como histórico</li>
                        <li>Se excluyen automáticamente neonatos (UCIN) y pediátricos</li>
                        <li>Los campos de ingreso/egreso UCI <strong>no se modifican</strong></li>
                    </ul>
                </div>

                <form method="POST" action="{{ route('carga.store') }}" enctype="multipart/form-data" id="formCarga">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Seleccionar archivo Excel</label>
                        <div class="upload-area" id="uploadArea">
                            <input type="file" name="archivo" id="archivoInput"
                                   accept=".xlsx,.xls" class="d-none @error('archivo') is-invalid @enderror">
                            <div id="uploadPlaceholder" class="text-center py-4">
                                <i class="bi bi-file-earmark-spreadsheet text-success" style="font-size:3rem;"></i>
                                <p class="mt-2 mb-1 fw-semibold">Haga clic o arrastre el archivo aquí</p>
                                <p class="text-muted mb-0" style="font-size:0.82rem;">Formatos aceptados: .xlsx, .xls · Máx 10 MB</p>
                            </div>
                            <div id="uploadSelected" class="d-none text-center py-3">
                                <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem;"></i>
                                <p class="mt-2 mb-0 fw-semibold" id="nombreArchivo"></p>
                                <p class="text-muted mb-0" id="pesoArchivo" style="font-size:0.82rem;"></p>
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" onclick="clearFile()">Cambiar archivo</button>
                            </div>
                        </div>
                        @error('archivo')
                        <div class="text-danger mt-1" style="font-size:0.85rem;"><i class="bi bi-x-circle me-1"></i>{{ $message }}</div>
                        @enderror
                    </div>

                    <div id="btnSubmitContainer">
                        <button type="submit" class="btn btn-primary w-100" id="btnSubmit" disabled>
                            <i class="bi bi-cloud-upload me-2"></i>Procesar archivo
                        </button>
                    </div>

                    <div id="loadingMsg" class="d-none text-center mt-3">
                        <div class="spinner-border text-primary" role="status" style="width:1.5rem;height:1.5rem;"></div>
                        <span class="ms-2 text-muted">Procesando... por favor espere.</span>
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
}
.upload-area:hover, .upload-area.dragover {
    border-color: #0d6efd;
    background: #f0f4ff;
}
</style>
@endpush

@push('scripts')
<script>
const input = document.getElementById('archivoInput');
const area = document.getElementById('uploadArea');
const placeholder = document.getElementById('uploadPlaceholder');
const selected = document.getElementById('uploadSelected');
const btnSubmit = document.getElementById('btnSubmit');
const form = document.getElementById('formCarga');

area.addEventListener('click', () => input.click());

area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('dragover'); });
area.addEventListener('dragleave', () => area.classList.remove('dragover'));
area.addEventListener('drop', e => {
    e.preventDefault(); area.classList.remove('dragover');
    if (e.dataTransfer.files[0]) {
        input.files = e.dataTransfer.files;
        updateDisplay(e.dataTransfer.files[0]);
    }
});

input.addEventListener('change', () => { if (input.files[0]) updateDisplay(input.files[0]); });

function updateDisplay(file) {
    document.getElementById('nombreArchivo').textContent = file.name;
    document.getElementById('pesoArchivo').textContent = (file.size / 1024).toFixed(1) + ' KB';
    placeholder.classList.add('d-none');
    selected.classList.remove('d-none');
    btnSubmit.disabled = false;
}

function clearFile() {
    input.value = '';
    placeholder.classList.remove('d-none');
    selected.classList.add('d-none');
    btnSubmit.disabled = true;
}

form.addEventListener('submit', () => {
    btnSubmit.disabled = true;
    document.getElementById('loadingMsg').classList.remove('d-none');
});
</script>
@endpush
