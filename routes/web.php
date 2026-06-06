<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\CargaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ReportePeriodicoController;
use App\Http\Controllers\EstanciaProlongadaController;
use App\Http\Controllers\EpidemiologiaController;
use App\Http\Controllers\PlantillaDiariaController;
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
    Route::post('/pacientes/{paciente}/notas', [PacienteController::class, 'guardarNota'])->name('pacientes.guardar-nota');
    Route::post('/pacientes/{paciente}/causa', [PacienteController::class, 'guardarCausa'])->name('pacientes.guardar-causa');
    Route::post('/pacientes/{paciente}/cam-uci', [PacienteController::class, 'guardarCamUci'])->name('pacientes.guardar-cam-uci');
    Route::post('/pacientes/{paciente}/bundle', [PacienteController::class, 'guardarBundle'])->name('pacientes.guardar-bundle');

    // Estancias prolongadas
    Route::get('/estancias-prolongadas', [EstanciaProlongadaController::class, 'index'])->name('estancias.index');

    // Carga de archivos
    Route::get('/carga', [CargaController::class, 'index'])->name('carga.index');
    Route::post('/carga', [CargaController::class, 'store'])->name('carga.store');
    Route::get('/carga/historial', [CargaController::class, 'historial'])->name('carga.historial');
    Route::delete('/carga/{carga}', [CargaController::class, 'destroy'])->name('carga.destroy');

    // Reportes
    Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/reportes/periodicos', [ReportePeriodicoController::class, 'index'])->name('reportes.periodicos');
    Route::get('/reportes/periodicos/datos', [ReportePeriodicoController::class, 'datos'])->name('reportes.periodicos.datos');
    Route::get('/reportes/periodicos/descargar', [ReportePeriodicoController::class, 'descargar'])->name('reportes.periodicos.descargar');

    // Epidemiología
    Route::get('/epidemiologia', [EpidemiologiaController::class, 'index'])->name('epidemiologia.index');

    // Plantilla de registro diario
    Route::get('/plantilla-diaria', [PlantillaDiariaController::class, 'index'])->name('plantilla-diaria');
    Route::post('/plantilla-diaria/guardar', [PlantillaDiariaController::class, 'guardar'])->name('plantilla-diaria.guardar');

    // Usuarios (solo master)
    Route::middleware(['rol:master'])->group(function () {
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::patch('/usuarios/{usuario}/toggle', [UsuarioController::class, 'toggleActivo'])->name('usuarios.toggle');
        Route::patch('/usuarios/{usuario}/reset-password', [UsuarioController::class, 'resetPassword'])->name('usuarios.reset-password');
    });
});
