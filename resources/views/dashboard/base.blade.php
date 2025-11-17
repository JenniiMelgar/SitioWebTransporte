@extends('layouts.app')

@section('content')
<!-- Header del Dashboard -->
<div class="dashboard-header">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="page-title">
                    <h1 class="display-5 fw-bold text-white mb-3">
                        <i class="fas fa-analytics me-3"></i>{{ $dashboardTitle }}
                    </h1>
                    <p class="lead text-white-50 mb-0">
                        {{ $dashboardSubtitle }}
                    </p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="user-welcome-card text-end">
                    <div class="welcome-message">
                        <h6 class="text-white mb-1">Bienvenido</h6>
                        <h5 class="text-white fw-bold mb-1">{{ auth()->user()->name }}</h5>
                        <span class="badge bg-{{ $roleBadgeColor }}">{{ auth()->user()->rol }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid py-4">
    <!-- KPIs Principales -->
    <div class="row g-4 mb-5">
        @foreach($kpis as $kpi)
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card {{ $kpi['bgClass'] ?? '' }} {{ $kpi['textClass'] ?? '' }}">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="kpi-icon me-3">
                            <i class="{{ $kpi['icon'] }}"></i>
                        </div>
                        <div class="kpi-content flex-grow-1">
                            <h3 class="kpi-value" data-kpi="{{ $kpi['dataKpi'] ?? strtolower(str_replace(' ', '-', $kpi['label'])) }}">
                                {{ $kpi['value'] }}
                            </h3>
                            <p class="kpi-label">{{ $kpi['label'] }}</p>
                        </div>
                        @if(isset($kpi['trend']))
                        <div class="kpi-trend">
                            <span class="badge {{ $kpi['trendBadge'] ?? 'bg-success' }}">
                                <i class="fas fa-chart-line me-1"></i>{{ $kpi['trend'] }}
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <!-- Mapa Interactivo con Leaflet -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="card analytics-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-map-marked-alt me-2 text-primary"></i>
                                Mapa Interactivo - Distribución Geográfica de Accidentes
                            </h5>
                            <div class="card-actions">
                                <button class="btn btn-sm btn-outline-primary" onclick="loadLeafletMapData()">
                                    <i class="fas fa-refresh"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="map-controls">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary active" onclick="changeLeafletMapType('circles')">
                                            <i class="fas fa-circle"></i> Círculos
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> Haz clic en los círculos para más detalles
                                </small>
                            </div>
                        </div>
                        
                        <div id="leaflet-map-container" style="height: 500px; border-radius: 8px; overflow: hidden;">
                            <div id="leaflet-map" style="height: 100%; width: 100%;"></div>
                        </div>
                        
                        <div class="mt-3">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="legend-color" style="background-color: #16a34a; width: 20px; height: 10px; border-radius: 2px; margin-right: 8px;"></div>
                                        <small>Baja frecuencia</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="legend-color" style="background-color: #ea580c; width: 20px; height: 10px; border-radius: 2px; margin-right: 8px;"></div>
                                        <small>Media frecuencia</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="d-flex align-items-center">
                                        <div class="legend-color" style="background-color: #dc2626; width: 20px; height: 10px; border-radius: 2px; margin-right: 8px;"></div>
                                        <small>Alta frecuencia</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Gráficos y Análisis -->
    <div class="row g-4">
        <!-- Gráficos Principales -->
        <div class="col-xl-8">
            <div class="row g-4">
                <!-- Gráfico de Estados -->
                <div class="col-12">
                    <div class="card analytics-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-bar me-2 text-primary"></i>
                                    Accidentes por Estado (Top 10)
                                </h5>
                                <div class="card-actions">
                                    <button class="btn btn-sm btn-outline-primary" onclick="loadCharts()">
                                        <i class="fas fa-refresh"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <div id="grafico-estados" class="chart-placeholder">
                                    <div class="placeholder-content text-center">
                                        <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">Gráfico de Estados</h6>
                                        <p class="text-muted small">Los datos se cargarán aquí</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                

                <!-- Gráficos Secundarios -->
                <div class="col-lg-6">
                    <div class="card analytics-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line me-2 text-primary"></i>
                                Tendencia Mensual
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <div id="grafico-mensual" class="chart-placeholder">
                                    <div class="placeholder-content text-center">
                                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">Tendencia Mensual</h6>
                                        <p class="text-muted small">Datos por mes</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
               
                <div class="col-lg-6">
                    <div class="card analytics-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-clock me-2 text-primary"></i>
                                Distribución por Horas
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <div id="grafico-horario" class="chart-placeholder">
                                    <div class="placeholder-content text-center">
                                        <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">Distribución Horaria</h6>
                                        <p class="text-muted small">Datos por hora del día</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Control -->
        <div class="col-xl-4">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>
                        Panel de Control
                    </h5>
                </div>
                <div class="card-body">
                    <form id="filtros-form">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Filtros Avanzados</label>
                        
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" id="filtro-estado">
                                    <option value="">Todos los estados</option>
                                    <option value="CA">California</option>
                                    <option value="TX">Texas</option>
                                    <option value="FL">Florida</option>
                                    <option value="NY">Nueva York</option>
                                    <option value="IL">Illinois</option>
                                    <option value="PA">Pennsylvania</option>
                                    <option value="OH">Ohio</option>
                                    <option value="GA">Georgia</option>
                                    <option value="NC">Carolina del Norte</option>
                                    <option value="MI">Michigan</option>
                                </select>
                            </div>
                        
                            <div class="mb-3">
                                <label class="form-label">Nivel de Severidad</label>
                                <select class="form-select" id="filtro-severidad">
                                    <option value="">Todos los niveles</option>
                                    <option value="1">Nivel 1 - Mínimo</option>
                                    <option value="2">Nivel 2 - Impacto Leve</option>
                                    <option value="3">Nivel 3 - Impacto Moderado</option>
                                    <option value="4">Nivel 4 - Impacto Severo</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Condiciones Climáticas</label>
                                <select class="form-select" id="filtro-clima">
                                    <option value="">Todas las condiciones</option>
                                    <option value="Despejado">Despejado (Fair/Clear)</option>
                                    <option value="Lluvia">Lluvia</option>
                                    <option value="Nieve">Nieve</option>
                                    <option value="Niebla">Niebla</option>
                                    <option value="Nublado">Nublado</option>
                                    <option value="Tormenta">Tormenta</option>
                                    <option value="Llovizna">Llovizna</option>
                                    <option value="Bruma">Bruma</option>
                                    <option value="Ventoso">Ventoso</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Momento del Día</label>
                                <select class="form-select" id="filtro-luz">
                                    <option value="">Todos los momentos</option>
                                    <option value="Día">Día</option>
                                    <option value="Noche">Noche</option>
                                    <!-- No incluir Amanecer/Atardecer ya que no hay datos -->
                                </select>
                            </div>
                        </div>
                    
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-filter me-2"></i>Aplicar Filtros
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                <i class="fas fa-eraser me-2"></i>Limpiar Filtros
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


    <!-- Tabla de Estados (Solo para Analistas) -->
    @if(isset($topEstados) && isset($analistaMetrics))
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-table me-2 text-primary"></i>
                        Top Estados con Más Accidentes
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Estado</th>
                                    <th>Total Accidentes</th>
                                    <th>Porcentaje</th>
                                    <th>Severidad Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topEstados as $estado)
                                @php
                                    $porcentaje = $analistaMetrics['totalAccidentes'] > 0
                                        ? ($estado->total / $analistaMetrics['totalAccidentes']) * 100
                                        : 0;
                                    
                                    $severidadColor = 'secondary';
                                    $severidadValor = 'N/A';
                                    if (isset($estado->severidad_promedio)) {
                                        $severidadValor = number_format($estado->severidad_promedio, 1);
                                        $severidadColor = $estado->severidad_promedio >= 3 ? 'danger' : 
                                                        ($estado->severidad_promedio >= 2 ? 'warning' : 'success');
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                        {{ $estado->estado ?? 'N/A' }}
                                    </td>
                                    <td>
                                        <span class="fw-bold">{{ number_format($estado->total) }}</span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $porcentaje; ?>%">
                                                <?php echo number_format($porcentaje, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-{{ $severidadColor }}">
                                            {{ $severidadValor }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Acciones Rápidas -->
    @if(!empty($quickActions))
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2 text-primary"></i>
                        Acciones Rápidas
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @foreach($quickActions as $action)
                        <div class="col-xl-3 col-md-6">
                            <a href="{{ $action['url'] }}" class="quick-action-card">
                                <div class="action-icon">
                                    <i class="{{ $action['icon'] }}"></i>
                                </div>
                                <div class="action-content">
                                    <h6>{{ $action['title'] }}</h6>
                                    <p class="text-muted small">{{ $action['description'] }}</p>
                                </div>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
.leaflet-container {
    background: #f8f9fa;
}
.map-tooltip {
    font-size: 12px;
    font-weight: bold;
}
.info-window {
    min-width: 200px;
}
.legend-color {
    display: inline-block;
    margin-right: 8px;
}
</style>
@endpush


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="{{ asset('js/dashboard-charts.js') }}"></script>
<script src="{{ asset('js/filter-manager.js') }}"></script>
<script>
// Instancias globales
window.chartManager = null;
window.filterManager = null;
window.leafletMap = null;
window.mapCircles = [];

// Inicializar mapa Leaflet 
function initLeafletMap() {
    console.log('Inicializando mapa Leaflet...');
    
    try {
        // Verificar que el contenedor existe
        const mapContainer = document.getElementById('leaflet-map');
        if (!mapContainer) {
            console.error(' Contenedor del mapa no encontrado');
            return;
        }

        // LIMPIAR el contenedor primero
        mapContainer.innerHTML = '';
        
        // Centro de Estados Unidos
        window.leafletMap = L.map('leaflet-map').setView([39.8283, -98.5795], 4);
        
        // Capa de OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18
        }).addTo(window.leafletMap);

        // Inicializar array de círculos
        window.mapCircles = [];

        console.log(' Mapa Leaflet inicializado correctamente');
        
        // Cargar datos inmediatamente
        loadLeafletMapData();
        
    } catch (error) {
        console.error(' Error inicializando mapa Leaflet:', error);
        showMapError('Error al inicializar el mapa: ' + error.message);
    }
}

// Cargar datos del mapa 
async function loadLeafletMapData() {
    try {
        console.log('Cargando datos para mapa Leaflet...');
        
        // MOSTRAR loading 
        showMapLoadingSafe();
        
        const response = await fetch('/map-data');
        
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
        
        const data = await response.json();
        console.log(' Datos del mapa recibidos:', data);
        
        if (data.success && data.data && data.data.length > 0) {
            console.log(`Actualizando mapa con ${data.data.length} ubicaciones`);
            updateLeafletMap(data.data);
        } else {
            console.warn(' No hay datos para mostrar en el mapa:', data.message);
            showMapNoData(data.message || 'No hay datos disponibles para el mapa');
        }
    } catch (error) {
        console.error(' Error loading map data:', error);
        showMapError('Error al cargar datos del mapa: ' + error.message);
    }
}

// Mostrar loading 
function showMapLoadingSafe() {
    const mapContainer = document.getElementById('leaflet-map');
    if (mapContainer && !window.leafletMap) {
        // Solo mostrar loading si el mapa no está inicializado
        mapContainer.innerHTML = `
            <div class="placeholder-content text-center py-5" style="background: #f8f9fa; height: 100%; display: flex; align-items: center; justify-content: center;">
                <div>
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted small">Cargando datos del mapa...</p>
                </div>
            </div>
        `;
    }
}

// Ocultar loading del mapa 
function hideMapLoading() {
    const mapContainer = document.getElementById('leaflet-map');
    if (mapContainer && window.leafletMap) {
        // Asegurarse de que el mapa esté visible
        const loadingElement = mapContainer.querySelector('.placeholder-content');
        if (loadingElement) {
            loadingElement.remove(); // Eliminar completamente el elemento de loading
        }
        console.log(' Loading del mapa ocultado correctamente');
    }
}

// Actualizar mapa 
function updateLeafletMap(mapData) {
    console.log(' Actualizando mapa Leaflet...');
    
    // Limpiar capas existentes
    clearLeafletLayers();
   
    if (!mapData || mapData.length === 0) {
        console.warn('No hay datos para mostrar en el mapa');
        showMapNoData();
        return;
    }
   
    console.log(`Procesando ${mapData.length} ubicaciones`);
   
    const maxAccidents = Math.max(...mapData.map(item => item.total || 0));
    console.log(` Máximo de accidentes: ${maxAccidents}`);
   
    let circlesAdded = 0;
    
    // Crear círculos para cada ubicación 
    mapData.forEach(location => {
        try {
            const lat = parseFloat(location.latitud);
            const lng = parseFloat(location.longitud);
           
            if (!isNaN(lat) && !isNaN(lng)) {
                const radius = getCircleRadius(location.total, maxAccidents);
                const color = getCircleColor(location.total, maxAccidents);
               
                // Manejo de severidad_promedio
                let severidadValue = location.severidad_promedio || 0;
                if (typeof severidadValue !== 'number') {
                    severidadValue = parseFloat(severidadValue) || 0;
                }
                const severidadFormateada = severidadValue.toFixed(1);
               
                const circle = L.circleMarker([lat, lng], {
                    radius: radius,
                    fillColor: color,
                    fillOpacity: 0.7,
                    color: 'white',
                    weight: 2,
                    opacity: 1
                }).addTo(window.leafletMap);
               
                // Tooltip que aparece al pasar el mouse
                circle.bindTooltip(`
                    <div class="map-tooltip">
                        <strong>${location.estado}</strong><br>
                        Accidentes: ${location.total.toLocaleString()}
                    </div>
                `);
               
                // Popup que aparece al hacer clic
                circle.bindPopup(`
                    <div class="info-window">
                        <h6><i class="fas fa-map-marker-alt"></i> ${location.estado}</h6>
                        <p><strong>Total Accidentes:</strong> ${location.total.toLocaleString()}</p>
                        <p><strong>Severidad Promedio:</strong> ${severidadFormateada}</p>
                        <small class="text-muted">Haz clic fuera para cerrar</small>
                    </div>
                `);
               
                // Efecto hover
                circle.on('mouseover', function() {
                    this.setStyle({
                        fillOpacity: 0.9,
                        weight: 3
                    });
                });
               
                circle.on('mouseout', function() {
                    this.setStyle({
                        fillOpacity: 0.7,
                        weight: 2
                    });
                });
               
                window.mapCircles.push(circle);
                circlesAdded++;
            } else {
                console.warn('Coordenadas inválidas para:', location.estado, lat, lng);
            }
        } catch (error) {
            console.error('Error creando círculo para:', location.estado, error);
        }
    });
    
    console.log(`${circlesAdded} círculos agregados al mapa`);
   
    // Ajustar el zoom para mostrar todos los círculos
    if (window.mapCircles.length > 0) {
        try {
            const group = new L.featureGroup(window.mapCircles);
            window.leafletMap.fitBounds(group.getBounds().pad(0.1));
            console.log('🎯 Vista del mapa ajustada');
            
            // OCULTAR LOADING - CON TIMEOUT PARA EVITAR CONFLICTOS
            setTimeout(() => {
                hideMapLoading();
                // FORZAR redimensionamiento del mapa
                window.leafletMap.invalidateSize();
            }, 100);
            
        } catch (error) {
            console.error('Error ajustando vista del mapa:', error);
            hideMapLoading();
        }
    } else {
        console.warn('No se pudieron agregar círculos al mapa');
        showMapNoData('No se pudieron cargar las ubicaciones en el mapa');
    }
}

// Limpiar capas del mapa
function clearLeafletLayers() {
    if (window.mapCircles && window.leafletMap) {
        window.mapCircles.forEach(circle => {
            try {
                window.leafletMap.removeLayer(circle);
            } catch (error) {
                console.warn('Error removiendo capa:', error);
            }
        });
        window.mapCircles = [];
    }
}

// Mostrar mensaje de error en el mapa
function showMapError(message) {
    const mapContainer = document.getElementById('leaflet-map');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div class="placeholder-content text-center py-5" style="background: #f8f9fa; height: 100%; display: flex; align-items: center; justify-content: center;">
                <div>
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h6 class="text-danger mb-2">Error en el mapa</h6>
                    <p class="text-muted small mb-3">${message}</p>
                    <button class="btn btn-sm btn-primary" onclick="location.reload()">
                        <i class="fas fa-redo me-1"></i>Recargar Página
                    </button>
                </div>
            </div>
        `;
    }
}

// Mostrar mensaje de no datos
function showMapNoData(message = 'No hay datos para mostrar') {
    const mapContainer = document.getElementById('leaflet-map');
    if (mapContainer) {
        mapContainer.innerHTML = `
            <div class="placeholder-content text-center py-5" style="background: #f8f9fa; height: 100%; display: flex; align-items: center; justify-content: center;">
                <div>
                    <i class="fas fa-map fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">${message}</h6>
                    <p class="text-muted small">Los datos del mapa aparecerán aquí cuando estén disponibles</p>
                </div>
            </div>
        `;
    }
}

// Obtener radio del círculo basado en accidentes
function getCircleRadius(accidents, maxAccidents) {
    const baseRadius = 8;
    const maxRadius = 30;
    if (maxAccidents === 0) return baseRadius;
    
    const intensity = accidents / maxAccidents;
    return baseRadius + (intensity * (maxRadius - baseRadius));
}

// Obtener color del círculo
function getCircleColor(accidents, maxAccidents) {
    if (maxAccidents === 0) return '#16a34a';
    
    const intensity = accidents / maxAccidents;
   
    if (intensity > 0.7) return '#dc2626'; // Rojo - alta frecuencia
    if (intensity > 0.4) return '#ea580c'; // Naranja - media frecuencia
    return '#16a34a'; // Verde - baja frecuencia
}

// Cambiar tipo de mapa 
function changeLeafletMapType(type) {
    console.log(`Cambiando a modo: ${type}`);
   
    // Actualizar botones
    document.querySelectorAll('.map-controls .btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
   
    if (type === 'circles') {
        loadLeafletMapData();
    } else {
        showMapNotAvailable();
    }
}

// Verificar que Chart.js esté disponible
function checkChartJS() {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js no está cargado');
        return false;
    }
    console.log('Chart.js está disponible, versión:', Chart.version);
    return true;
}

// Inicializar managers
function initializeManagers() {
    console.log('Inicializando managers...');
    
    try {
        // Inicializar ChartManager primero
        if (typeof DashboardCharts !== 'undefined') {
            window.chartManager = new DashboardCharts();
            console.log('ChartManager inicializado');
        } else {
            console.error('DashboardCharts no está definido');
            return false;
        }
        
        // Inicializar FilterManager
        if (typeof FilterManager !== 'undefined') {
            window.filterManager = new FilterManager();
            console.log('FilterManager inicializado');
        } else {
            console.error('FilterManager no está definido');
            return false;
        }
        
        return true;
    } catch (error) {
        console.error('Error inicializando managers:', error);
        return false;
    }
}

// Cargar y inicializar gráficos
async function loadCharts() {
    console.log('Cargando gráficos...');
    
    if (!window.chartManager) {
        console.error('ChartManager no disponible');
        if (!initializeManagers()) {
            showChartError('No se pudo inicializar el sistema de gráficos');
            return;
        }
    }

    try {
        showChartLoading();
       
        console.log('Solicitando datos de gráficos...');
        const response = await fetch('/chart-data');
       
        if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status}`);
        }
       
        const data = await response.json();
        console.log('Datos de gráficos recibidos:', data);
       
        if (data.success && data.charts) {
            initializeCharts(data.charts);
        } else {
            throw new Error(data.message || 'Error en la estructura de datos');
        }
    } catch (error) {
        console.error('Error loading charts:', error);
        showChartError('Error al cargar los gráficos: ' + error.message);
    }
}

// Cargar datos del dashboard
async function loadDashboardData() {
    try {
        console.log('Cargando KPIs...');
        const response = await fetch('/kpis');
        const data = await response.json();
        
        if (data.success) {
            // Actualizar KPIs en la interfaz
            document.querySelectorAll('[data-kpi]').forEach(element => {
                const kpiType = element.getAttribute('data-kpi');
                const kpiValue = data.data[kpiType];
                if (kpiValue) {
                    element.textContent = kpiValue;
                }
            });
            console.log('KPIs actualizados');
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
    }
}

function initializeCharts(chartData) {
    console.log('Inicializando gráficos con datos:', chartData);
    
    if (!window.chartManager) {
        console.error('ChartManager no disponible para inicializar gráficos');
        return;
    }

    // Inicializar todos los gráficos disponibles
    const chartsInitialized = [];
    
    if (chartData.porEstados && chartData.porEstados.length > 0) {
        const chart = window.chartManager.initEstadosChart(chartData.porEstados);
        if (chart) chartsInitialized.push('Estados');
    }
    
    if (chartData.porMeses && chartData.porMeses.length > 0) {
        const chart = window.chartManager.initMensualChart(chartData.porMeses);
        if (chart) chartsInitialized.push('Mensual');
    }
    
    if (chartData.porHoras && chartData.porHoras.length > 0) {
        const chart = window.chartManager.initHorarioChart(chartData.porHoras);
        if (chart) chartsInitialized.push('Horario');
    }
    
    if (chartData.porSeveridad && chartData.porSeveridad.length > 0) {
        const chart = window.chartManager.initSeveridadChart(chartData.porSeveridad);
        if (chart) chartsInitialized.push('Severidad');
    }
    
    console.log(`Gráficos inicializados: ${chartsInitialized.join(', ')}`);
    
    if (chartsInitialized.length === 0) {
        showChartError('No se pudieron cargar los gráficos. Verifica los datos.');
    }
}

function showChartLoading() {
    const placeholders = document.querySelectorAll('.chart-placeholder, .no-data-placeholder');
    placeholders.forEach(placeholder => {
        placeholder.innerHTML = `
            <div class="placeholder-content text-center py-5">
                <div class="spinner-border text-primary mb-3" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="text-muted small">Cargando datos del dashboard...</p>
            </div>
        `;
    });
}

function showChartError(message) {
    const placeholders = document.querySelectorAll('.chart-placeholder');
    placeholders.forEach(placeholder => {
        placeholder.innerHTML = `
            <div class="placeholder-content text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                <h6 class="text-danger mb-2">Error al cargar gráfico</h6>
                <p class="text-muted small mb-3">${message}</p>
                <button class="btn btn-sm btn-primary" onclick="loadCharts()">
                    <i class="fas fa-redo me-1"></i>Reintentar
                </button>
            </div>
        `;
    });
}

// Función para aplicar filtros
function applyFilters() {
    console.log('Aplicando filtros...');
    if (window.filterManager) {
        window.filterManager.applyFilters();
    } else {
        console.error('FilterManager no disponible');
        alert('Error: El sistema de filtros no está disponible. Recarga la página.');
    }
}

// Función  para limpiar filtros
function clearFilters() {
    console.log('Limpiando filtros...');
    if (window.filterManager) {
        window.filterManager.clearFilters();
    } else {
        console.error(' FilterManager no disponible');
    }
}

function initializeDashboard() {
    console.log('INICIALIZANDO DASHBOARD');
    
    // Verificar Chart.js primero
    if (!checkChartJS()) {
        console.log(' Chart.js no disponible, reintentando...');
        setTimeout(initializeDashboard, 100);
        return;
    }
    
    // Inicializar managers
    if (!initializeManagers()) {
        console.error('No se pudieron inicializar los managers');
        showChartError('Error crítico: No se pudo inicializar el dashboard');
        return;
    }
    
    console.log('Dashboard inicializado correctamente');
    
    // Cargar datos iniciales
    setTimeout(() => {
        loadCharts();
        loadDashboardData();
    }, 500);
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM cargado - Iniciando dashboard...');
    
    // Inicializar dashboard principal
    setTimeout(initializeDashboard, 100);
    
    // Inicializar Leaflet después de un delay mayor
    setTimeout(() => {
        if (typeof L !== 'undefined') {
            initLeafletMap();
        } else {
            console.error(' Leaflet no está cargado');
            showMapError('Error: Librería de mapas no cargada');
        }
    }, 1000);
});

// También intentar inicializar cuando la ventana se carga completamente
window.addEventListener('load', function() {
    console.log('Página completamente cargada');
    if (!window.chartManager || !window.filterManager) {
        console.log('🔄 Reintentando inicialización...');
        setTimeout(initializeDashboard, 200);
    }
});

// Debug: Verificar disponibilidad
setInterval(() => {
    console.log('📊 Estado managers:', {
        chartManager: !!window.chartManager,
        filterManager: !!window.filterManager,
        leafletMap: !!window.leafletMap,
        mapCircles: window.mapCircles ? window.mapCircles.length : 0
    });
}, 10000); // Cada 10 segundos 

</script>
@endpush