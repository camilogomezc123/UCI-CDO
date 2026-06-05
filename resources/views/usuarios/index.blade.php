@extends('layouts.app')
@section('title', 'Usuarios')
@section('page-title', 'Gestión de Usuarios')

@section('content')
<div class="row g-3">

    {{-- Formulario nuevo usuario --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-plus me-2 text-primary"></i>Nuevo Usuario</div>
            <div class="card-body">
                <form method="POST" action="{{ route('usuarios.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Nombre completo</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-sm @error('name') is-invalid @enderror" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Correo electrónico</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-sm @error('email') is-invalid @enderror" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Rol</label>
                        <select name="rol" class="form-select form-select-sm @error('rol') is-invalid @enderror" required>
                            <option value="operativo" {{ old('rol') == 'operativo' ? 'selected' : '' }}>Operativo</option>
                            <option value="master" {{ old('rol') == 'master' ? 'selected' : '' }}>Master</option>
                        </select>
                        @error('rol')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Contraseña</label>
                        <input type="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" style="font-size:0.85rem;">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-plus-circle me-1"></i>Crear usuario
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Lista de usuarios --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-people me-2 text-primary"></i>Usuarios registrados ({{ $usuarios->count() }})</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Correo</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($usuarios as $u)
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="font-size:0.875rem;">{{ $u->name }}</div>
                                    @if($u->id === auth()->id())
                                        <span class="badge bg-info text-dark" style="font-size:0.65rem;">Tú</span>
                                    @endif
                                </td>
                                <td style="font-size:0.85rem;">{{ $u->email }}</td>
                                <td>
                                    <span class="badge {{ $u->rol === 'master' ? 'bg-primary' : 'bg-secondary' }}">
                                        {{ strtoupper($u->rol) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge {{ $u->activo ? 'bg-success' : 'bg-danger' }}">
                                        {{ $u->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        {{-- Toggle activo --}}
                                        @if($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('usuarios.toggle', $u) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $u->activo ? 'btn-outline-danger' : 'btn-outline-success' }}"
                                                    title="{{ $u->activo ? 'Desactivar' : 'Activar' }}">
                                                <i class="bi {{ $u->activo ? 'bi-person-x' : 'bi-person-check' }}"></i>
                                            </button>
                                        </form>
                                        @endif
                                        {{-- Reset password --}}
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                                data-bs-target="#modalPwd{{ $u->id }}" title="Cambiar contraseña">
                                            <i class="bi bi-key"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            {{-- Modal reset password --}}
                            <div class="modal fade" id="modalPwd{{ $u->id }}" tabindex="-1">
                                <div class="modal-dialog modal-sm">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Cambiar contraseña — {{ $u->name }}</h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="{{ route('usuarios.reset-password', $u) }}">
                                            @csrf @method('PATCH')
                                            <div class="modal-body">
                                                <div class="mb-2">
                                                    <label class="form-label" style="font-size:0.82rem;">Nueva contraseña</label>
                                                    <input type="password" name="password" class="form-control form-control-sm" required minlength="6">
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label" style="font-size:0.82rem;">Confirmar</label>
                                                    <input type="password" name="password_confirmation" class="form-control form-control-sm" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
