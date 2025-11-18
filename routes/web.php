<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalistaController;
use App\Http\Controllers\CargaController;
use App\Http\Controllers\ETLController;
use App\Http\Controllers\InvitadoController;
use App\Http\Controllers\ProcesoETLController;
use Illuminate\Support\Facades\Route;

// Página de inicio pública -> Login
Route::get('/', function () {
    return redirect('/login');
});

// Rutas de autenticación
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Nuevas rutas para el dashboard optimizado
Route::middleware(['auth'])->group(function () {
    // Métricas del dashboard
    Route::get('/dashboard-metrics', [DashboardController::class, 'getDashboardMetrics']);
    Route::get('/chart-data', [DashboardController::class, 'getChartData']);
    Route::post('/datos-filtrados', [DashboardController::class, 'getDatosFiltrados'])->name('datos.filtrados');
    Route::get('/estadisticas-avanzadas', [DashboardController::class, 'getEstadisticasAvanzadas']);
    Route::get('/map-data', [DashboardController::class, 'getMapData'])->name('map.data');
    Route::post('/map-data-filtrado', [DashboardController::class, 'getMapDataFiltrado'])->name('map.data.filtrado');
});


// Rutas protegidas por autenticación
Route::middleware(['auth'])->group(function () {
   
    // Dashboard principal (redirige según rol)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
   
    // DASHBOARD ESPECÍFICOS POR ROL
    Route::middleware(['role:Administrador'])->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/users', [AdminController::class, 'users'])->name('admin.users');
        Route::get('/system', [AdminController::class, 'system'])->name('admin.system');
    });

   
    Route::middleware(['role:Analista'])->prefix('analista')->group(function () {
        Route::get('/dashboard', [AnalistaController::class, 'dashboard'])->name('analista.dashboard');
    });
   
    Route::middleware(['role:Invitado'])->prefix('invitado')->group(function () {
        Route::get('/dashboard', [InvitadoController::class, 'dashboard'])->name('invitado.dashboard');
    });

    // REPORTES - Solo Analistas y Administradores
    Route::middleware(['role:Analista,Administrador'])->group(function () {
        Route::get('/reportes', function () {
            return view('reportes');
        })->name('reportes');
    });
   
    // CARGA DE ARCHIVOS - Solo Administradores (ACTUALIZADO)
    Route::middleware(['role:Administrador'])->group(function () {
        Route::get('/carga', [CargaController::class, 'index'])->name('carga'); // CAMBIADO
        Route::post('/carga/upload', [CargaController::class, 'upload'])->name('carga.upload');
        Route::get('/carga/history', [CargaController::class, 'getUploadHistory'])->name('carga.history');
    });
   
    // ETL
    Route::middleware(['role:Analista,Administrador'])->controller(ProcesoETLController::class)->prefix('etl')->group(function () {
        Route::get('/', 'index')->name('etl');
        Route::post('/execute', 'executeETL')->name('etl.execute');
        Route::get('/logs', 'getLogs')->name('etl.logs');
        Route::get('/system-status', 'getSystemStatus')->name('etl.system-status');
        Route::get('/batch-progress', 'getBatchProgress')->name('etl.batch-progress');
    });
   
    // API endpoints
    Route::get('/kpis', [DashboardController::class, 'getKPIs']);
    Route::post('/datos-filtrados', [DashboardController::class, 'getDatosFiltrados']);
    Route::get('/estadisticas-avanzadas', [DashboardController::class, 'getEstadisticasAvanzadas']);

    // Rutas para comparadores
    Route::get('/comparadores', function () {
        return view('comparadores');
    })->name('comparadores');

    // API endpoints para comparadores
    Route::get('/api/comparadores/opciones', [DashboardController::class, 'getOpcionesComparadores']);
    Route::get('/api/comparadores/ejecutar', [DashboardController::class, 'getComparadores']);
});