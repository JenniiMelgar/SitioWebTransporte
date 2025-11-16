@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header">
                <h1 class="h3 fw-bold">
                    <i class="fas fa-cogs me-2 text-primary"></i>
                    Configuración del Sistema
                </h1>
                <p class="text-muted">Configuración general y mantenimiento del sistema</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Configuración General</h5>
                </div>
                <div class="card-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Nombre del Sistema</label>
                            <input type="text" class="form-control" value="DWT Analytics">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tiempo de sesión (minutos)</label>
                            <input type="number" class="form-control" value="120">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Límite de registros por consulta</label>
                            <input type="number" class="form-control" value="1000">
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar Configuración</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">Mantenimiento</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-warning">
                            <i class="fas fa-trash me-2"></i>Limpiar Cache
                        </button>
                        <button class="btn btn-outline-info">
                            <i class="fas fa-database me-2"></i>Optimizar Base de Datos
                        </button>
                        <button class="btn btn-outline-danger">
                            <i class="fas fa-broom me-2"></i>Limpiar Logs Antiguos
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection