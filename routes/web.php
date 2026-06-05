<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\CargaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;

// Autenticación
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas
Route::middleware(['auth'])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Pacientes
    Route::get('/pacientes', [PacienteController::class, 'index'])->name('pacientes.index');
    Route::get('/pacientes/{paciente}', [PacienteController::class, 'show'])->name('pacientes.show');
    Route::patch('/pacientes/{paciente}/ingreso', [PacienteController::class, 'actualizarIngreso'])->name('pacientes.ingreso');
    Route::patch('/pacientes/{paciente}/salida-hospitalizacion', [PacienteController::class, 'actualizarSalidaHospitalizacion'])->name('pacientes.salida-hospitalizacion');
    Route::patch('/pacientes/{paciente}/egreso-uci', [PacienteController::class, 'actualizarEgresoUci'])->name('pacientes.egreso-uci');

    // Carga de archivos
    Route::get('/carga', [CargaController::class, 'index'])->name('carga.index');
    Route::post('/carga', [CargaController::class, 'store'])->name('carga.store');
    Route::get('/carga/historial', [CargaController::class, 'historial'])->name('carga.historial');

    // Reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');

    // Usuarios (solo master)
    Route::middleware(['rol:master'])->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::patch('/usuarios/{usuario}/toggle', [UsuarioController::class, 'toggleActivo'])->name('usuarios.toggle');
        Route::patch('/usuarios/{usuario}/reset-password', [UsuarioController::class, 'resetPassword'])->name('usuarios.reset-password');
    });
});
