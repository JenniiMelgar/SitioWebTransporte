<?php
// routes/web.php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalistaController;
use App\Http\Controllers\CargaController;
use App\Http\Controllers\InvitadoController;
use App\Http\Controllers\ProcesoETLController;
use App\Http\Controllers\TwoFactorAuthController;
use Illuminate\Support\Facades\Route;

// Página de inicio pública -> Login
Route::get('/', function () {
    return redirect('/login');
});

// Rutas de autenticación (PÚBLICAS)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// ==================== RUTAS 2FA ====================
Route::middleware(['auth'])->group(function () {
    // Configuración de 2FA
    Route::get('/2fa/setup', [TwoFactorAuthController::class, 'showSetupForm'])->name('2fa.setup');
    Route::post('/2fa/enable', [TwoFactorAuthController::class, 'enable'])->name('2fa.enable');
    Route::get('/2fa/recovery', [TwoFactorAuthController::class, 'showRecoveryCodes'])->name('2fa.recovery');
    Route::post('/2fa/regenerate', [TwoFactorAuthController::class, 'regenerateRecoveryCodes'])->name('2fa.regenerate');
    Route::post('/2fa/disable', [TwoFactorAuthController::class, 'disable'])->name('2fa.disable');
    
    // Verificación de 2FA
    Route::get('/2fa/verify', [TwoFactorAuthController::class, 'showVerificationForm'])->name('2fa.verify');
    Route::post('/2fa/verify', [TwoFactorAuthController::class, 'verify'])->name('2fa.verify');
});

// ==================== APIs PÚBLICAS (SIN 2FA) ====================
Route::middleware(['auth'])->group(function () {
    // Métricas del dashboard
    Route::get('/dashboard-metrics', [DashboardController::class, 'getDashboardMetrics']);
    Route::get('/chart-data', [DashboardController::class, 'getChartData']);
    Route::post('/datos-filtrados', [DashboardController::class, 'getDatosFiltrados'])->name('datos.filtrados');
    Route::get('/estadisticas-avanzadas', [DashboardController::class, 'getEstadisticasAvanzadas']);
    Route::get('/map-data', [DashboardController::class, 'getMapData'])->name('map.data');
    Route::post('/map-data-filtrado', [DashboardController::class, 'getMapDataFiltrado'])->name('map.data.filtrado');
    
    // APIs para comparadores
    Route::get('/api/comparadores/opciones', [DashboardController::class, 'getOpcionesComparadores']);
    Route::get('/api/comparadores/ejecutar', [DashboardController::class, 'getComparadores']);
    
    // KPIs
    Route::get('/kpis', [DashboardController::class, 'getKPIs']);
});

// ==================== RUTAS PROTEGIDAS CON 2FA ====================
Route::middleware(['auth', '2fa'])->group(function () {
    
    // Dashboard principal
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Dashboards específicos por rol
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

    // CARGA DE ARCHIVOS - Solo Administradores
    Route::middleware(['role:Administrador'])->group(function () {
        Route::get('/carga', [CargaController::class, 'index'])->name('carga');
        Route::post('/carga/upload', [CargaController::class, 'upload'])->name('carga.upload');
        Route::get('/carga/history', [CargaController::class, 'getUploadHistory'])->name('carga.history');
    });
    
    // ETL - Analistas y Administradores
    Route::middleware(['role:Analista,Administrador'])->controller(ProcesoETLController::class)->prefix('etl')->group(function () {
        Route::get('/', 'index')->name('etl');
        Route::post('/execute', 'executeETL')->name('etl.execute');
        Route::get('/logs', 'getLogs')->name('etl.logs');
        Route::get('/system-status', 'getSystemStatus')->name('etl.system-status');
        Route::get('/batch-progress', 'getBatchProgress')->name('etl.batch-progress');
    });
    
    // COMPARADORES - Analistas y Administradores
    Route::middleware(['role:Analista,Administrador'])->group(function () {
        Route::get('/comparadores', function () {
            return view('comparadores');
        })->name('comparadores');
    });

});

// Ruta de fallback
Route::fallback(function () {
    return redirect('/login');
});