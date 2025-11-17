@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header">
                <h1 class="h3 fw-bold">
                    <i class="fas fa-file-upload me-2 text-primary"></i>
                    Carga de Archivos
                </h1>
                <p class="text-muted">Importar datos desde archivos CSV, Excel o JSON</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Formulario de Carga -->
        <div class="col-lg-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-upload me-2 text-primary"></i>
                        Subir Archivo a Tabla DATOS
                    </h5>
                </div>
                <div class="card-body">
                    <form id="upload-form" enctype="multipart/form-data">
                        @csrf
                       
                        <div class="mb-3">
                            <label class="form-label">Archivo</label>
                            <input type="file" class="form-control" name="archivo" id="archivo"
                                accept=".csv,.xlsx,.xls,.json" required>
                            <div class="form-text">
                                Formatos soportados: CSV, Excel (xlsx, xls), JSON. Tamaño máximo: 10MB
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary" id="btn-upload">
                                <i class="fas fa-upload me-2"></i>Procesar Archivo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Información y Formatos -->
        <div class="col-lg-6">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        Información de Formatos
                    </h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="formatAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#formatCsv">
                                    Formato CSV
                                </button>
                            </h2>
                            <div id="formatCsv" class="accordion-collapse collapse show" data-bs-parent="#formatAccordion">
                                <div class="accordion-body">
                                    <p><strong>Encabezados requeridos para accidentes:</strong></p>
                                    <code>SEVERITY,START_TIME,END_TIME,START_LAT,START_LNG,STREET,CITY,STATE,WEATHER_CONDITION,TEMPERATURE_F</code>
                                    <p class="mt-2 mb-0"><small>Separador: coma, Codificación: UTF-8</small></p>
                                </div>
                            </div>
                        </div>
                       
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#formatExcel">
                                    Formato Excel
                                </button>
                            </h2>
                            <div id="formatExcel" class="accordion-collapse collapse" data-bs-parent="#formatAccordion">
                                <div class="accordion-body">
                                    <p>Misma estructura que CSV. Primera fila debe contener los encabezados.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progreso de Carga -->
                    <div class="mt-4" id="upload-progress" style="display: none;">
                        <div class="d-flex align-items-center mb-2">
                            <div class="progress flex-grow-1" style="height: 10px;">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <span class="ms-2" id="progress-text">0%</span>
                        </div>
                        <div id="upload-status" class="small"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Historial de Cargas -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card analytics-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2 text-primary"></i>
                        Historial de Cargas
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadUploadHistory()">
                        <i class="fas fa-refresh me-1"></i>Actualizar
                    </button>
                </div>
                <div class="card-body">
                    <div id="upload-history">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-clock fa-2x mb-2"></i>
                            <p>El historial se cargará aquí</p>
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
document.getElementById('upload-form').addEventListener('submit', function(e) {
    e.preventDefault();
   
    const formData = new FormData(this);
    const btn = document.getElementById('btn-upload');
    const originalText = btn.innerHTML;
   
    // Mostrar progreso
    const progressDiv = document.getElementById('upload-progress');
    const progressBar = progressDiv.querySelector('.progress-bar');
    const progressText = document.getElementById('progress-text');
    const statusDiv = document.getElementById('upload-status');
   
    progressDiv.style.display = 'block';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
    statusDiv.innerHTML = 'Validando archivo...';
   
    fetch('{{ route("carga.upload") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            progressBar.style.width = '100%';
            progressText.textContent = '100%';
            statusDiv.innerHTML = `<span class="text-success">${data.message}</span>`;
            loadUploadHistory();
        } else {
            statusDiv.innerHTML = `<span class="text-danger">${data.message}</span>`;
        }
    })
    .catch(error => {
        statusDiv.innerHTML = `<span class="text-danger">Error de conexión: ${error.message}</span>`;
    })
    .finally(() => {
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
            progressDiv.style.display = 'none';
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
        }, 3000);
    });
});

function loadUploadHistory() {
    const container = document.getElementById('upload-history');
    container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Cargando historial...</p></div>';
   
    fetch('{{ route("carga.history") }}')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Datos recibidos del historial:', data);
           
            if (data.success && data.history && data.history.length > 0) {
                let html = '<div class="list-group list-group-flush">';
               
                data.history.forEach(item => {
                    console.log('Procesando item:', item);
                   
                    // Usar los nombres exactos de las columnas de Oracle
                    const nombreArchivo = item.NOMBRE_ARCHIVO || item.nombre_archivo || 'Sin nombre';
                    const tipoCarga = item.TIPO_CARGA || item.tipo_carga || 'DATOS';
                    const registros = item.REGISTROS_PROCESADOS || item.registros_procesados || 0;
                    const estado = item.ESTADO || item.estado || 'desconocido';
                    const usuario = item.USUARIO || item.usuario || 'Sistema';
                    const fechaUpload = item.FECHA_UPLOAD || item.fecha_upload;
                   
                    // Manejar la fecha
                    let fechaDisplay = 'Fecha inválida';
                    if (fechaUpload) {
                        try {
                            let fechaObj;
                            if (fechaUpload.includes('T')) {
                                fechaObj = new Date(fechaUpload);
                            } else {
                                fechaObj = new Date(fechaUpload.replace(' ', 'T') + 'Z');
                            }
                           
                            if (!isNaN(fechaObj.getTime())) {
                                fechaDisplay = fechaObj.toLocaleString('es-ES', {
                                    year: 'numeric',
                                    month: '2-digit',
                                    day: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });
                            } else {
                                fechaDisplay = fechaUpload;
                            }
                        } catch (e) {
                            console.error('Error parseando fecha:', e, fechaUpload);
                            fechaDisplay = fechaUpload;
                        }
                    }

                    // Determinar clase del badge
                    let badgeClass = 'bg-warning';
                    const estadoLower = estado.toLowerCase();
                    if (estadoLower === 'completado') {
                        badgeClass = 'bg-success';
                    } else if (estadoLower === 'error' || estadoLower === 'fallido') {
                        badgeClass = 'bg-danger';
                    }

                    html += `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold">${nombreArchivo}</h6>
                                    <p class="mb-1 text-muted small">
                                        <strong>Tipo:</strong> ${tipoCarga} |
                                        <strong>Registros:</strong> ${registros} |
                                        <strong>Usuario:</strong> ${usuario}
                                    </p>
                                    <p class="mb-1 text-muted small">
                                        <strong>Fecha:</strong> ${fechaDisplay}
                                    </p>
                                </div>
                                <span class="badge ${badgeClass} ms-2">
                                    ${estado}
                                </span>
                            </div>
                        </div>
                    `;
                });
               
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-3"></i>
                        <p class="mb-0">No hay historial de cargas</p>
                        <small>Los archivos que subas aparecerán aquí</small>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error cargando historial:', error);
            container.innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3"></i>
                    <p class="mb-0">Error al cargar historial</p>
                    <small>${error.message}</small>
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="loadUploadHistory()">
                            <i class="fas fa-refresh me-1"></i>Reintentar
                        </button>
                    </div>
                </div>
            `;
        });
}

// Cargar historial al iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadUploadHistory();
});
</script>
@endpush