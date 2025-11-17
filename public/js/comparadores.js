class ComparadoresManager {
    constructor() {
        this.currentTipo = 'periodos';
        this.isLoading = false;
    }

    init() {
        console.log('Inicializando comparadores...');
        this.bindEvents();
        this.cargarOpciones('periodos');
    }

    bindEvents() {
        // Cambiar tipo de comparación
        document.querySelectorAll('[data-tipo-comparacion]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tipo = e.target.getAttribute('data-tipo-comparacion');
                this.cambiarTipoComparacion(tipo);
            });
        });

        // Botón comparar
        document.getElementById('btn-comparar').addEventListener('click', () => {
            this.ejecutarComparacion();
        });

        // Cargar opciones cuando cambie el tipo
        document.getElementById('tipo-comparacion').addEventListener('change', (e) => {
            this.cargarOpciones(e.target.value);
        });
    }

    cambiarTipoComparacion(tipo) {
        this.currentTipo = tipo;
        document.getElementById('tipo-comparacion').value = tipo;
        
        // Actualizar UI
        document.querySelectorAll('[data-tipo-comparacion]').forEach(btn => {
            btn.classList.remove('active');
        });
        event.target.classList.add('active');

        this.cargarOpciones(tipo);
    }

    async cargarOpciones(tipo) {
        try {
            this.mostrarLoadingOpciones();
            
            const response = await fetch(`/api/comparadores/opciones?tipo=${tipo}`);
            const data = await response.json();

            if (data.success) {
                this.actualizarSelectsOpciones(data.opciones, tipo);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error cargando opciones:', error);
            this.mostrarError('Error al cargar opciones: ' + error.message);
        } finally {
            this.ocultarLoadingOpciones();
        }
    }

    actualizarSelectsOpciones(opciones, tipo) {
        const select1 = document.getElementById('parametro1');
        const select2 = document.getElementById('parametro2');

        // Limpiar selects
        select1.innerHTML = '<option value="">Seleccione...</option>';
        select2.innerHTML = '<option value="">Seleccione...</option>';

        // Llenar con nuevas opciones
        opciones.forEach(opcion => {
            const option1 = new Option(this.formatearOpcion(opcion, tipo), opcion);
            const option2 = new Option(this.formatearOpcion(opcion, tipo), opcion);
            
            select1.add(option1);
            select2.add(option2);
        });

        // Actualizar labels según el tipo
        this.actualizarLabels(tipo);
    }

    formatearOpcion(opcion, tipo) {
        switch (tipo) {
            case 'periodos':
                return `Año ${opcion}`;
            case 'regiones':
                return opcion;
            case 'categorias':
                // Mapear de inglés a español para mostrar
                const climaMapping = {
                    'Clear': 'Despejado',
                    'Rain': 'Lluvia',
                    'Snow': 'Nieve',
                    'Fog': 'Niebla',
                    'Cloudy': 'Nublado',
                    'Overcast': 'Cubierto',
                    'Haze': 'Bruma',
                    'Heavy Rain': 'Lluvia Intensa',
                    'Light Rain': 'Lluvia Ligera',
                    'Heavy Snow': 'Nieve Intensa',
                    'Light Snow': 'Nieve Ligera'
                };
                return climaMapping[opcion] || opcion;
            default:
                return opcion;
        }
    }

    actualizarLabels(tipo) {
        const label1 = document.querySelector('label[for="parametro1"]');
        const label2 = document.querySelector('label[for="parametro2"]');

        switch (tipo) {
            case 'periodos':
                label1.textContent = 'Año 1';
                label2.textContent = 'Año 2';
                break;
            case 'regiones':
                label1.textContent = 'Estado 1';
                label2.textContent = 'Estado 2';
                break;
            case 'categorias':
                label1.textContent = 'Categoría 1';
                label2.textContent = 'Categoría 2';
                break;
        }
    }

    async ejecutarComparacion() {
        if (this.isLoading) return;

        const tipo = document.getElementById('tipo-comparacion').value;
        const param1 = document.getElementById('parametro1').value;
        const param2 = document.getElementById('parametro2').value;

        if (!param1 || !param2) {
            this.mostrarError('Por favor seleccione ambos parámetros para comparar');
            return;
        }

        if (param1 === param2) {
            this.mostrarError('Los parámetros deben ser diferentes para comparar');
            return;
        }

        try {
            this.isLoading = true;
            this.mostrarLoadingComparacion();

            const response = await fetch(`/api/comparadores/ejecutar?tipo=${tipo}&param1=${encodeURIComponent(param1)}&param2=${encodeURIComponent(param2)}`);
            const data = await response.json();

            if (data.success) {
                this.mostrarResultados(data);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Error en comparación:', error);
            this.mostrarError('Error al ejecutar comparación: ' + error.message);
        } finally {
            this.isLoading = false;
            this.ocultarLoadingComparacion();
        }
    }

    mostrarResultados(data) {
        const container = document.getElementById('resultados-comparacion');
        
        if (!data.data || data.data.length === 0) {
            container.innerHTML = this.crearMensajeNoDatos();
            return;
        }

        let html = `
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Resultados de la Comparación
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>${this.getTituloColumna(data.tipo)}</th>
                                    <th>Total Accidentes</th>
                                    <th>Severidad Promedio</th>
                                    <th>Accidentes Graves</th>
                                    ${data.tipo !== 'categorias' ? '<th>Distancia Promedio (mi)</th>' : ''}
                                </tr>
                            </thead>
                            <tbody>
        `;

        data.data.forEach(item => {
            html += `
                <tr>
                    <td><strong>${item.periodo || item.region || item.categoria}</strong></td>
                    <td>${item.total_accidentes?.toLocaleString() || '0'}</td>
                    <td>
                        <span class="badge bg-${this.getColorSeveridad(item.severidad_promedio)}">
                            ${item.severidad_promedio || '0.0'}
                        </span>
                    </td>
                    <td>${item.accidentes_graves?.toLocaleString() || '0'}</td>
                    ${data.tipo !== 'categorias' ? `<td>${item.distancia_promedio || '0.0'}</td>` : ''}
                </tr>
            `;
        });

        html += `
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Gráfico de comparación -->
                    <div class="mt-4">
                        <div class="chart-container">
                            <canvas id="chart-comparacion" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
        
        // Inicializar gráfico
        this.inicializarGraficoComparacion(data);
    }

    getTituloColumna(tipo) {
        switch (tipo) {
            case 'periodos': return 'Año';
            case 'regiones': return 'Estado';
            case 'categorias': return 'Categoría Climática';
            default: return 'Parámetro';
        }
    }

    getColorSeveridad(severidad) {
        if (severidad >= 3) return 'danger';
        if (severidad >= 2) return 'warning';
        return 'success';
    }

    inicializarGraficoComparacion(data) {
        const ctx = document.getElementById('chart-comparacion');
        if (!ctx) return;

        // Destruir gráfico anterior si existe
        if (this.currentChart) {
            this.currentChart.destroy();
        }
        
        const labels = data.data.map(item => item.periodo || item.region || item.categoria);
        const accidentesData = data.data.map(item => item.total_accidentes || 0);
        const severidadData = data.data.map(item => item.severidad_promedio || 0);

        this.currentChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Total Accidentes',
                        data: accidentesData,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Severidad Promedio',
                        data: severidadData,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1,
                        type: 'line',
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Total Accidentes'
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Severidad Promedio'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    crearMensajeNoDatos() {
        return `
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <h5>No hay datos para mostrar</h5>
                <p class="mb-0">No se encontraron resultados para los parámetros seleccionados.</p>
            </div>
        `;
    }

    mostrarLoadingOpciones() {
        document.querySelectorAll('#parametro1, #parametro2').forEach(select => {
            select.disabled = true;
            select.innerHTML = '<option value="">Cargando...</option>';
        });
    }

    ocultarLoadingOpciones() {
        document.querySelectorAll('#parametro1, #parametro2').forEach(select => {
            select.disabled = false;
        });
    }

    mostrarLoadingComparacion() {
        const btn = document.getElementById('btn-comparar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Comparando...';
    }

    ocultarLoadingComparacion() {
        const btn = document.getElementById('btn-comparar');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chart-bar me-2"></i>Comparar';
    }

    mostrarError(mensaje) {
        const container = document.getElementById('resultados-comparacion');
        container.innerHTML = `
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${mensaje}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    window.comparadoresManager = new ComparadoresManager();
    window.comparadoresManager.init();
});