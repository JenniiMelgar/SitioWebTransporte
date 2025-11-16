@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header">
                <h1 class="h3 fw-bold">
                    <i class="fas fa-database me-2 text-primary"></i>
                    Procesos ETL
                </h1>
                <p class="text-muted">Gestión y ejecución de procesos de Extracción, Transformación y Carga</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Panel de Control ETL -->
        <div class="col-lg-8">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-play-circle me-2 text-primary"></i>
                        Ejecutar Procesos
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="process-card text-center p-4 border rounded-3">
                                <div class="process-icon mb-3">
                                    <i class="fas fa-layer-group fa-3x text-primary"></i>
                                </div>
                                <h6>Cargar Dimensiones</h6>
                                <p class="text-muted small">Actualiza tablas dimensionales</p>
                                <button class="btn btn-outline-primary btn-sm" onclick="executeETL('carga_dimensiones')">
                                    Ejecutar
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="process-card text-center p-4 border rounded-3">
                                <div class="process-icon mb-3">
                                    <i class="fas fa-cube fa-3x text-success"></i>
                                </div>
                                <h6>Cargar Hechos</h6>
                                <p class="text-muted small">Actualiza tabla de hechos</p>
                                <button class="btn btn-outline-success btn-sm" onclick="executeETL('carga_hechos')">
                                    Ejecutar
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="process-card text-center p-4 border rounded-3">
                                <div class="process-icon mb-3">
                                    <i class="fas fa-sync-alt fa-3x text-warning"></i>
                                </div>
                                <h6>Reprocesar Todo</h6>
                                <p class="text-muted small">Ejecuta proceso completo</p>
                                <button class="btn btn-outline-warning btn-sm" onclick="executeETL('reprocesar_todo')">
                                    Ejecutar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado del Sistema -->
        <div class="col-lg-4">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        Estado del Sistema
                    </h5>
                </div>
                <div class="card-body">
                    <div id="system-status">
                        <div class="d-flex justify-content-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs de Ejecución -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card analytics-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-alt me-2 text-primary"></i>
                        Logs de Ejecución
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadLogs()">
                        <i class="fas fa-refresh me-1"></i>Actualizar
                    </button>
                </div>
                <div class="card-body">
                    <div id="logs-container" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <p>Los logs se cargarán aquí</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function executeETL(process) {
    if (!confirm('¿Está seguro de ejecutar este proceso?')) return;

    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Ejecutando...';
    button.disabled = true;

    fetch('/etl/execute', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ process: process })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Proceso ejecutado correctamente');
            loadLogs();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error de conexión: ' + error.message);
    })
    .finally(() => {
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function loadLogs() {
    const container = document.getElementById('logs-container');
    container.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando logs...</span>
            </div>
        </div>
    `;

    fetch('/etl/logs')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.logs.length > 0) {
                let html = '<div class="list-group list-group-flush">';
                data.logs.forEach(log => {
                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${log.NOMBRE_DIMENSION}</h6>
                                    <p class="mb-1 text-muted small">${new Date(log.FECHA).toLocaleString()}</p>
                                </div>
                                <span class="badge bg-primary">${log.PARAMETRO}</span>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-info-circle fa-2x mb-2"></i>
                        <p>No hay logs disponibles</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            container.innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <p>Error al cargar logs</p>
                </div>
            `;
        });
}

// Cargar logs al iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadLogs();
});
</script>
@endpush