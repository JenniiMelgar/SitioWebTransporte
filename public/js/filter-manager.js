class FilterManager {
    constructor() {
        this.currentFilters = {};
        this.isApplyingFilters = false;
    }

    async applyFilters() {
        console.log('🔄 Iniciando aplicación de filtros...');
        
        // Evitar múltiples clics
        if (this.isApplyingFilters) {
            console.log('⏳ Filtros ya en proceso, ignorando...');
            return;
        }
       
        if (!window.chartManager) {
            console.error('❌ chartManager no está disponible');
            this.showError('Error: Sistema de gráficos no disponible. Recarga la página.');
            return;
        }

        this.isApplyingFilters = true;
        this.showLoading();
        const filters = this.getCurrentFilters();
       
        console.log('🎯 Filtros a aplicar:', filters);

        try {
            const response = await fetch('/datos-filtrados', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify(filters)
            });

            console.log('📡 Respuesta del servidor:', response.status);

            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }

            const data = await response.json();
            console.log('📊 Datos recibidos:', data);
           
            if (data.success) {
                console.log('✅ Filtros aplicados exitosamente');
               
                // ACTUALIZAR GRÁFICOS
                this.updateChartsWithFilteredData(data.graficos);
               
                // ACTUALIZAR MÉTRICAS KPIs
                this.updateKPIsWithFilteredData(data.metrics);
               
                // ACTUALIZAR EL MAPA CON FILTROS
                console.log('🗺️ Actualizando mapa Leaflet con filtros...');
                await this.loadLeafletMapDataFiltrado(filters);
               
                this.showFilterResults(data.total);
            } else {
                console.error('❌ Error en respuesta:', data.message);
                this.showError(data.message || 'Error desconocido al aplicar filtros');
            }
        } catch (error) {
            console.error('💥 Error applying filters:', error);
            this.showError('Error de conexión al aplicar filtros: ' + error.message);
        } finally {
            this.hideLoading();
            this.isApplyingFilters = false;
        }
    }

    getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    getCurrentFilters() {
        // Obtener los valores seleccionados directamente del DOM
        const estadoSelect = document.getElementById('filtro-estado');
        const severidadSelect = document.getElementById('filtro-severidad');
        const climaSelect = document.getElementById('filtro-clima');
        const luzSelect = document.getElementById('filtro-luz');

        return {
            estado: estadoSelect?.value || '',
            severidad: severidadSelect?.value || '',
            clima: climaSelect?.value || '',
            luz: luzSelect?.value || ''
        };
    }

    updateChartsWithFilteredData(chartData) {
        console.log('Actualizando gráficos con datos filtrados:', chartData);
        
        if (!window.chartManager) {
            console.error('chartManager no disponible');
            return;
        }

        try {
            if (chartData.porEstados && chartData.porEstados.length > 0) {
                console.log('Actualizando gráfico de estados');
                window.chartManager.initEstadosChart(chartData.porEstados);
            } else {
                console.warn('No hay datos para gráfico de estados');
                window.chartManager.showNoData('grafico-estados', 'Estados');
            }

            if (chartData.porMeses && chartData.porMeses.length > 0) {
                console.log('Actualizando gráfico mensual');
                window.chartManager.initMensualChart(chartData.porMeses);
            } else {
                console.warn('No hay datos para gráfico mensual');
                window.chartManager.showNoData('grafico-mensual', 'Tendencia Mensual');
            }

            if (chartData.porHoras && chartData.porHoras.length > 0) {
                console.log('Actualizando gráfico horario');
                window.chartManager.initHorarioChart(chartData.porHoras);
            } else {
                console.warn('No hay datos para gráfico horario');
                window.chartManager.showNoData('grafico-horario', 'Distribución Horaria');
            }

        } catch (error) {
            console.error('Error actualizando gráficos:', error);
            this.showError('Error al actualizar gráficos: ' + error.message);
        }
    }

    // MÉTODO: Actualizar KPIs
    updateKPIsWithFilteredData(metrics) {
        if (!metrics) return;

        // Actualizar cada KPI individualmente
        const kpiElements = {
            'total-accidentes': metrics.totalAccidentes || '0',
            'severidad-promedio': metrics.severidadPromedio || '0.0',
            'con-mal-clima': metrics.accidentesClima || '0',
            'accidentes-nocturnos': metrics.accidentesNocturnos || '0'
        };

        Object.keys(kpiElements).forEach(kpiId => {
            const element = document.querySelector(`[data-kpi="${kpiId}"]`);
            if (element) {
                element.textContent = kpiElements[kpiId];
            }
        });
    }

    // Cargar mapa Leaflet con filtros
    async loadLeafletMapDataFiltrado(filters) {
        try {
            console.log('🗺️ Cargando mapa Leaflet con filtros:', filters);
            
            // MOSTRAR loading del mapa de manera segura
            this.showMapLoadingSafe();
            
            const response = await fetch('/map-data-filtrado', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.getCSRFToken()
                },
                body: JSON.stringify(filters)
            });
           
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            console.log('🗺️ Datos del mapa filtrado recibidos:', data);
           
            if (data.success) {
                this.updateLeafletMapWithFilteredData(data.data);
            } else {
                console.error('❌ Error cargando mapa filtrado:', data.message);
                this.showMapError('Error al cargar datos filtrados: ' + data.message);
            }
        } catch (error) {
            console.error('💥 Error loading filtered map data:', error);
            this.showMapError('Error de conexión al cargar mapa filtrado');
        }
    }

    // Actualizar mapa Leaflet con datos filtrados
    updateLeafletMapWithFilteredData(mapData) {
        console.log('🔄 Actualizando mapa con datos filtrados...');
        
        if (!window.leafletMap) {
            console.error('❌ Mapa Leaflet no está inicializado');
            this.showMapError('El mapa no está disponible');
            return;
        }

        // Limpiar capas existentes
        this.clearLeafletLayers();
       
        if (!mapData || mapData.length === 0) {
            console.warn('No hay datos filtrados para mostrar en el mapa');
            this.showMapNoData('No se encontraron datos con los filtros aplicados');
            return;
        }
       
        console.log(`🗺️ Procesando ${mapData.length} ubicaciones filtradas`);
       
        const maxAccidents = Math.max(...mapData.map(item => item.total || 0));
        console.log(`📊 Máximo de accidentes (filtrado): ${maxAccidents}`);
       
        let circlesAdded = 0;
        
        // Crear círculos para cada ubicación filtrada
        mapData.forEach(location => {
            try {
                const lat = parseFloat(location.latitud);
                const lng = parseFloat(location.longitud);
               
                if (!isNaN(lat) && !isNaN(lng)) {
                    const radius = this.getCircleRadius(location.total, maxAccidents);
                    const color = this.getCircleColor(location.total, maxAccidents);
                   
                    // Manejo seguro de severidad_promedio
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
                   
                    // Tooltip
                    circle.bindTooltip(`
                        <div class="map-tooltip">
                            <strong>${location.estado}</strong><br>
                            Accidentes: ${location.total.toLocaleString()}
                        </div>
                    `);
                   
                    // Popup
                    circle.bindPopup(`
                        <div class="info-window">
                            <h6><i class="fas fa-map-marker-alt"></i> ${location.estado}</h6>
                            <p><strong>Total Accidentes:</strong> ${location.total.toLocaleString()}</p>
                            <p><strong>Severidad Promedio:</strong> ${severidadFormateada}</p>
                            <small class="text-muted">Filtros aplicados</small>
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
                }
            } catch (error) {
                console.error('Error creando círculo filtrado para:', location.estado, error);
            }
        });
        
        console.log(`✅ ${circlesAdded} círculos filtrados agregados al mapa`);
       
        // Ajustar el zoom para mostrar todos los círculos
        if (window.mapCircles.length > 0) {
            try {
                const group = new L.featureGroup(window.mapCircles);
                window.leafletMap.fitBounds(group.getBounds().pad(0.1));
                console.log('🎯 Vista del mapa ajustada para datos filtrados');
                
                // OCULTAR LOADING
                setTimeout(() => {
                    this.hideMapLoading();
                    window.leafletMap.invalidateSize(); // Forzar redimensionamiento
                }, 100);
                
            } catch (error) {
                console.error('Error ajustando vista del mapa filtrado:', error);
                this.hideMapLoading();
            }
        } else {
            console.warn('No se pudieron agregar círculos filtrados al mapa');
            this.showMapNoData('No se encontraron ubicaciones con los filtros aplicados');
        }
    }

    // Mostrar loading del mapa de manera segura
    showMapLoadingSafe() {
        const mapContainer = document.getElementById('leaflet-map');
        if (mapContainer && window.leafletMap) {
            // Solo agregar loading si no existe ya
            if (!mapContainer.querySelector('.map-loading-overlay')) {
                const loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'map-loading-overlay';
                loadingOverlay.style.cssText = `
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(248, 249, 250, 0.9);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    border-radius: 8px;
                `;
                loadingOverlay.innerHTML = `
                    <div class="text-center">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="text-muted small">Aplicando filtros al mapa...</p>
                    </div>
                `;
                mapContainer.style.position = 'relative';
                mapContainer.appendChild(loadingOverlay);
            }
        }
    }

    // Ocultar loading del mapa
    hideMapLoading() {
        const mapContainer = document.getElementById('leaflet-map');
        if (mapContainer) {
            const loadingOverlay = mapContainer.querySelector('.map-loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.remove();
                console.log('✅ Loading del mapa ocultado (filtros)');
            }
        }
    }

    // Limpiar capas del mapa
    clearLeafletLayers() {
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

    // Obtener radio del círculo
    getCircleRadius(accidents, maxAccidents) {
        const baseRadius = 8;
        const maxRadius = 30;
        if (maxAccidents === 0) return baseRadius;
        
        const intensity = accidents / maxAccidents;
        return baseRadius + (intensity * (maxRadius - baseRadius));
    }

    // Obtener color del círculo
    getCircleColor(accidents, maxAccidents) {
        if (maxAccidents === 0) return '#16a34a';
        
        const intensity = accidents / maxAccidents;
       
        if (intensity > 0.7) return '#dc2626';
        if (intensity > 0.4) return '#ea580c';
        return '#16a34a';
    }

    // Mostrar mensaje de error en el mapa
    showMapError(message) {
        this.hideMapLoading(); // Asegurar que el loading se oculte
        const mapContainer = document.getElementById('leaflet-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="placeholder-content text-center py-5" style="background: #f8f9fa; height: 100%; display: flex; align-items: center; justify-content: center;">
                    <div>
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h6 class="text-danger mb-2">Error en el mapa</h6>
                        <p class="text-muted small mb-3">${message}</p>
                        <button class="btn btn-sm btn-primary" onclick="window.filterManager.clearFilters()">
                            <i class="fas fa-eraser me-1"></i>Limpiar Filtros
                        </button>
                    </div>
                </div>
            `;
        }
    }

    // Mostrar mensaje de no datos
    showMapNoData(message = 'No hay datos para mostrar') {
        this.hideMapLoading(); // Asegurar que el loading se oculte
        const mapContainer = document.getElementById('leaflet-map');
        if (mapContainer) {
            mapContainer.innerHTML = `
                <div class="placeholder-content text-center py-5" style="background: #f8f9fa; height: 100%; display: flex; align-items: center; justify-content: center;">
                    <div>
                        <i class="fas fa-map fa-3x text-muted mb-3"></i>
                        <h6 class="text-muted">${message}</h6>
                        <p class="text-muted small">Intenta con otros criterios de filtrado</p>
                    </div>
                </div>
            `;
        }
    }

    showLoading() {
        const buttons = document.querySelectorAll('#filtros-form button');
        buttons.forEach(button => {
            button.disabled = true;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            button.setAttribute('data-original-text', originalText);
        });
    }

    hideLoading() {
        const buttons = document.querySelectorAll('#filtros-form button');
        buttons.forEach(button => {
            button.disabled = false;
            const originalText = button.getAttribute('data-original-text');
            if (originalText) {
                button.innerHTML = originalText;
            }
        });
    }

    showFilterResults(total) {
        this.removeExistingAlerts();
       
        const notification = document.createElement('div');
        notification.className = 'alert alert-info alert-dismissible fade show mt-3';
        notification.innerHTML = `
            <strong><i class="fas fa-filter me-2"></i>Filtros aplicados:</strong>
            Se encontraron <strong>${total}</strong> accidentes que coinciden con los criterios.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
       
        const container = document.querySelector('.container-fluid');
        if (container) {
            container.insertBefore(notification, container.firstChild);
        }
    }

    showError(message) {
        this.removeExistingAlerts();
       
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger alert-dismissible fade show mt-3';
        errorDiv.innerHTML = `
            <strong><i class="fas fa-exclamation-triangle me-2"></i>Error:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
       
        const container = document.querySelector('.container-fluid');
        if (container) {
            container.insertBefore(errorDiv, container.firstChild);
        }
    }

    removeExistingAlerts() {
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        });
    }

    clearFilters() {
        const form = document.getElementById('filtros-form');
        if (form) {
            form.reset();
        }
       
        this.reloadDefaultData();
    }

    async reloadDefaultData() {
        this.showLoading();
        try {
            await loadCharts();
            await loadDashboardData();
            
            // Recargar mapa sin filtros
            if (typeof loadLeafletMapData === 'function') {
                await loadLeafletMapData();
            }
            
            this.showFilterResults('todos los');
        } catch (error) {
            console.error('Error reloading data:', error);
            this.showError('Error al recargar datos');
        } finally {
            this.hideLoading();
        }
    }
}