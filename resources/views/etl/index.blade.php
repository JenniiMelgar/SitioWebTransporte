@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header">
                <h1 class="h3 fw-bold">
                    <i class="fas fa-database me-2 text-primary"></i>
                    Procesos ETL - Sistema de Carga por Batch
                </h1>
                <p class="text-muted">Gestión y ejecución de procesos de Transformación de datos</p>
            </div>
        </div>
    </div>

    <!-- Estado del Sistema -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card analytics-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        Estado del Sistema
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadSystemStatus()">
                        <i class="fas fa-refresh me-1"></i>Actualizar
                    </button>
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

    <!-- Progreso de Carga -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                        Progreso de Carga
                    </h5>
                </div>
                <div class="card-body">
                    <div id="batch-progress">
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

    <div class="row g-4">
        <!-- Panel de Control Principal -->
        <div class="col-lg-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-play-circle me-2 text-primary"></i>
                        Procesos Principales
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Limpieza y Preparación -->
                        <div class="col-md-6">
                            <div class="process-card text-center p-4 border rounded-3 h-100">
                                <div class="process-icon mb-3">
                                    <i class="fas fa-broom fa-2x text-warning"></i>
                                </div>
                                <h6>Limpieza y Preparación</h6>
                                <p class="text-muted small">Prepara tablas para ETL</p>
                                <button class="btn btn-outline-warning btn-sm w-100" 
                                        onclick="executeETLProcess('limpiar_preparar')">
                                    Ejecutar
                                </button>
                            </div>
                        </div>


                        <!-- Cargar Dimensiones -->
                        <div class="col-md-6">
                            <div class="process-card text-center p-4 border rounded-3 h-100">
                                <div class="process-icon mb-3">
                                    <i class="fas fa-layer-group fa-2x text-primary"></i>
                                </div>
                                <h6>Cargar Dimensiones</h6>
                                <p class="text-muted small">Actualizar dimensiones</p>
                                <button class="btn btn-outline-primary btn-sm w-100" 
                                        onclick="executeETLProcess('carga_dimensiones')">
                                    Ejecutar
                                </button>
                            </div>
                        </div>

                        <!-- Proceso Completo -->
                        <div class="col-md-6">
                            <div class="process-card text-center p-4 border rounded-3 h-100">
                                <div class="process-icon mb-3">
                                    <i class="fas fa-sync-alt fa-2x text-success"></i>
                                </div>
                                <h6>Proceso Completo</h6>
                                <p class="text-muted small">ETL completo automático</p>
                                <button class="btn btn-outline-success btn-sm w-100" 
                                        onclick="executeETLProcess('reprocesar_todo')">
                                    Ejecutar Todo
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Control de Batches -->
        <div class="col-lg-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-forward me-2 text-primary"></i>
                        Control de Batches - Carga de Hechos
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tamaño del Batch: <strong>200,000 registros (Fijo)</strong></label>
                        <div class="alert alert-info py-2">
                            <small>
                                <i class="fas fa-info-circle me-1"></i>
                                Tamaño optimizado para el procesamiento de ~8 millones de registros
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Batch Recomendado</label>
                        <div class="input-group">
                            <span class="input-group-text">#</span>
                            <input type="number" class="form-control" id="batch-number" value="1" min="1" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="actualizarBatchRecomendado()">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            Se actualiza automáticamente según el progreso
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="executeBatchAutomatico()">
                            <i class="fas fa-play me-2"></i>Ejecutar Siguiente Batch (Automático)
                        </button>
                        <button class="btn btn-outline-info" onclick="executeAllBatches()">
                            <i class="fas fa-forward-fast me-2"></i>Ejecutar Todos los Batches Restantes
                        </button>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Nota:</strong> Cada batch procesa exactamente 200,000 registros. 
                            El sistema detecta automáticamente cuál es el siguiente batch a procesar.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection



@push('scripts')
<script>
// Variables globales
let currentBatch = 1;
let isProcessing = false;

// Inicializar página
document.addEventListener('DOMContentLoaded', function() {
    loadSystemStatus();
    loadBatchProgress();
    
    // Actualizar cada 30 segundos
    setInterval(() => {
        if (!isProcessing) {
            loadSystemStatus();
            loadBatchProgress();
        }
    }, 30000);
});


// Cargar estado del sistema
async function loadSystemStatus() {
    try {
        const response = await fetch('/etl/system-status');
        const data = await response.json();
        
        if (data.success) {
            const status = data.status;
            const batchInfo = data.batchInfo;
            
            let html = `
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <h4 class="text-primary">${status.total_hechos?.toLocaleString() || '0'}</h4>
                            <small class="text-muted">Hechos Procesados</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <h4 class="text-info">${status.total_datos?.toLocaleString() || '0'}</h4>
                            <small class="text-muted">Total Registros</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <h4 class="text-warning">${status.total_no_hechos?.toLocaleString() || '0'}</h4>
                            <small class="text-muted">Pendientes</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <h4 class="text-success">${batchInfo?.total_batches || '1'}</h4>
                            <small class="text-muted">Total Batches</small>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Dimensión</th>
                                        <th>Registros</th>
                                        <th>Max Key</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Localización</td>
                                        <td>${status.total_localizaciones?.toLocaleString() || '0'}</td>
                                        <td>${status.max_loc_key || '1'}</td>
                                    </tr>
                                    <tr>
                                        <td>Clima</td>
                                        <td>${status.total_clima?.toLocaleString() || '0'}</td>
                                        <td>-</td>
                                    </tr>
                                    <tr>
                                        <td>Infraestructura</td>
                                        <td>${status.total_infraestructura?.toLocaleString() || '0'}</td>
                                        <td>-</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('system-status').innerHTML = html;
        }
    } catch (error) {
        console.error('Error cargando estado del sistema:', error);
        document.getElementById('system-status').innerHTML = `
            <div class="alert alert-danger text-center">
                Error al cargar estado del sistema: ${error.message}
            </div>
        `;
    }
}

// Cargar progreso de batches
async function loadBatchProgress() {
    try {
        const response = await fetch('/etl/batch-progress');
        const data = await response.json();
        
        if (data.success) {
            const progress = data.progress;
            const porcentaje = data.porcentaje;
            const batchRecomendado = data.batch_recomendado;
            const procesoCompletado = data.proceso_completado;
            
            let estadoProceso = '';
            if (procesoCompletado) {
                estadoProceso = '<div class="alert alert-success text-center">🎉 ¡Proceso ETL Completado!</div>';
            } else if (porcentaje > 0) {
                estadoProceso = `<div class="alert alert-info text-center">⏳ Procesando: ${porcentaje}% completado</div>`;
            }
            
            let html = `
                ${estadoProceso}
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Progreso Total: ${porcentaje}%</span>
                        <span>${progress.hechos_procesados?.toLocaleString() || '0'} / ${progress.total_registros?.toLocaleString() || '0'}</span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped ${procesoCompletado ? '' : 'progress-bar-animated'}" 
                             role="progressbar" 
                             style="width: ${porcentaje}%">
                            ${porcentaje}%
                        </div>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <h6 class="mb-1 text-success">${progress.batches_completados || '0'}</h6>
                            <small class="text-muted">Batches Completados</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <h6 class="mb-1 text-primary">${progress.total_batches || '1'}</h6>
                            <small class="text-muted">Total Batches</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded p-2">
                            <h6 class="mb-1 text-warning">${batchRecomendado || '1'}</h6>
                            <small class="text-muted">Siguiente Batch</small>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('batch-progress').innerHTML = html;
            document.getElementById('batch-number').value = batchRecomendado;
            currentBatch = batchRecomendado;
        } else {
            document.getElementById('batch-progress').innerHTML = `
                <div class="alert alert-warning text-center">
                    ${data.message || 'Error al cargar progreso'}
                </div>
            `;
        }
    } catch (error) {
        console.error('Error cargando progreso:', error);
        document.getElementById('batch-progress').innerHTML = `
            <div class="alert alert-danger text-center">
                Error de conexión: ${error.message}
            </div>
        `;
    }
}

// Ejecutar proceso ETL 
async function executeETLProcess(process) {
    if (isProcessing) {
        showAlert('error', 'Ya hay un proceso en ejecución. Espere a que termine.');
        return;
    }
    
    if (!confirm(`¿Está seguro de ejecutar el proceso: ${process}?`)) {
        return;
    }
    
    isProcessing = true;
    showProcessingState();
    
    try {
        const response = await fetch('/etl/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ process: process })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', data.message);
            // Recargar datos
            loadSystemStatus();
            loadBatchProgress();
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        console.error('Error ejecutando proceso:', error);
        showAlert('error', 'Error de conexión: ' + error.message);
    } finally {
        isProcessing = false;
        hideProcessingState();
    }
}

// Ejecutar batch AUTOMÁTICO
async function executeBatchAutomatico() {
    if (isProcessing) {
        showAlert('error', 'Ya hay un proceso en ejecución. Espere a que termine.');
        return;
    }
    
    const batchRecomendado = document.getElementById('batch-number').value;
    
    if (!confirm(`¿Ejecutar Batch ${batchRecomendado} (200,000 registros)?`)) {
        return;
    }
    
    isProcessing = true;
    showProcessingState();
    
    try {
        const response = await fetch('/etl/execute', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ 
                process: 'carga_hechos_batch',
                batch_size: 200000,
                batch_number: parseInt(batchRecomendado)
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('success', `Batch ${batchRecomendado} completado: ${data.message}`);
            // Recargar datos para actualizar el siguiente batch
            loadSystemStatus();
            loadBatchProgress();
        } else {
            showAlert('error', data.message);
        }
    } catch (error) {
        console.error('Error ejecutando batch:', error);
        showAlert('error', 'Error de conexión: ' + error.message);
    } finally {
        isProcessing = false;
        hideProcessingState();
    }
}

// Ejecutar todos los batches restantes automáticamente
async function executeAllBatches() {
    if (isProcessing) {
        showAlert('error', 'Ya hay un proceso en ejecución. Espere a que termine.');
        return;
    }
    
    if (!confirm('¿Ejecutar TODOS los batches restantes automáticamente?\n\nEsto puede tomar varios minutos.')) {
        return;
    }
    
    isProcessing = true;
    showProcessingState();
    
    try {
        await loadBatchProgress();
        
        const totalBatches = parseInt(document.querySelector('#batch-progress .col-md-4:nth-child(2) h6')?.textContent || '1');
        let batchActual = parseInt(document.getElementById('batch-number').value) || 1;
        
        let batchesCompletados = 0;
        let batchesConError = 0;
        
        for (let batch = batchActual; batch <= totalBatches; batch++) {
            document.getElementById('batch-number').value = batch;
            
            const response = await fetch('/etl/execute', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ 
                    process: 'carga_hechos_batch',
                    batch_size: 200000,
                    batch_number: batch
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                batchesCompletados++;
                showAlert('success', `Batch ${batch}/${totalBatches} completado`);
            } else {
                batchesConError++;
                showAlert('error', `Error en batch ${batch}: ${data.message}`);
                
                if (!confirm(`Error en batch ${batch}. ¿Continuar con los siguientes batches?`)) {
                    break;
                }
            }
            
            await loadBatchProgress();
            await new Promise(resolve => setTimeout(resolve, 2000));
        }
        
        if (batchesConError === 0) {
            showAlert('success', `🎉 ¡Todos los ${batchesCompletados} batches completados exitosamente!`);
        } else {
            showAlert('warning', `Completados ${batchesCompletados} batches, ${batchesConError} con errores.`);
        }
        
    } catch (error) {
        console.error('Error crítico en executeAllBatches:', error);
        showAlert('error', `Error en ejecución automática: ${error.message}`);
    } finally {
        isProcessing = false;
        hideProcessingState();
    }
}

// Actualizar batch recomendado manualmente
async function actualizarBatchRecomendado() {
    await loadBatchProgress();
    showAlert('info', 'Batch recomendado actualizado');
}

// Utilidades de UI
function showAlert(type, message) {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show mt-3`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

function showProcessingState() {
    document.body.style.cursor = 'wait';
}

function hideProcessingState() {
    document.body.style.cursor = 'default';
}

function formatNumber(num) {
    if (!num && num !== 0) return '0';
    return parseInt(num).toLocaleString('es-ES');
}
</script>
@endpush