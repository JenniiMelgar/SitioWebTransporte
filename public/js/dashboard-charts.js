class DashboardCharts {
    constructor() {
        this.charts = new Map();
        this.isInitialized = false;
    }

    // Verificar si Chart.js está disponible
    checkChartJSAvailability() {
        if (typeof Chart === 'undefined') {
            console.error('Chart.js no está disponible');
            return false;
        }
        return true;
    }

    initChart(chartId, type, data, options = {}) {
        if (!this.checkChartJSAvailability()) {
            console.error('No se puede inicializar gráfico: Chart.js no disponible');
            return null;
        }

        const ctx = document.getElementById(chartId);
        if (!ctx) {
            console.error(`Elemento con ID ${chartId} no encontrado`);
            return null;
        }

        // Si el elemento es un div placeholder, crear canvas
        if (ctx.tagName.toLowerCase() === 'div') {
            const canvas = document.createElement('canvas');
            ctx.innerHTML = '';
            ctx.appendChild(canvas);
            ctx._canvas = canvas;
        }

        // Usar el canvas directamente
        const canvasElement = ctx.tagName.toLowerCase() === 'canvas' ? ctx : ctx.querySelector('canvas');
        if (!canvasElement) {
            console.error(`No se pudo encontrar canvas para ${chartId}`);
            return null;
        }

        // Destruir gráfico existente
        if (this.charts.has(chartId)) {
            try {
                this.charts.get(chartId).destroy();
            } catch (error) {
                console.warn(`Error al destruir gráfico ${chartId}:`, error);
            }
        }

        // Configuración por defecto mejorada
        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 15
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 10,
                    cornerRadius: 4
                }
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false
            },
            elements: {
                line: {
                    tension: 0.4
                }
            }
        };

        try {
            const chart = new Chart(canvasElement, {
                type: type,
                data: data,
                options: { ...defaultOptions, ...options }
            });

            this.charts.set(chartId, chart);
            this.isInitialized = true;
            
            console.log(`Gráfico ${chartId} inicializado correctamente`);
            return chart;
        } catch (error) {
            console.error(`Error al crear gráfico ${chartId}:`, error);
            return null;
        }
    }

    // Gráfico de barras para estados
    initEstadosChart(data) {
        if (!data || !Array.isArray(data) || data.length === 0) {
            console.warn('Datos vacíos para gráfico de estados');
            this.showNoData('grafico-estados', 'Estados');
            return null;
        }

        const labels = data.map(item => item.estado || item.STATE || 'N/A');
        const values = data.map(item => item.total || 0);

        // Colores dinámicos
        const backgroundColors = this.generateColors(values.length, 0.6);
        const borderColors = this.generateColors(values.length, 1);

        return this.initChart('grafico-estados', 'bar', {
            labels: labels,
            datasets: [{
                label: 'Accidentes por Estado',
                data: values,
                backgroundColor: backgroundColors,
                borderColor: borderColors,
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false,
            }]
        }, {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad de Accidentes',
                        font: {
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Estados',
                        font: {
                            weight: 'bold'
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            }
        });
    }

    // Gráfico de línea para tendencia mensual
    initMensualChart(data) {
        if (!data || !Array.isArray(data) || data.length === 0) {
            console.warn('Datos vacíos para gráfico mensual');
            this.showNoData('grafico-mensual', 'Tendencia Mensual');
            return null;
        }

        const labels = data.map(item => {
            if (!item.mes) return 'N/A';
            const [year, month] = item.mes.split('-');
            const monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 
                               'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            return `${monthNames[parseInt(month) - 1]}/${year}`;
        });
        
        const values = data.map(item => item.total || 0);

        return this.initChart('grafico-mensual', 'line', {
            labels: labels,
            datasets: [{
                label: 'Accidentes por Mes',
                data: values,
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: 'rgba(255, 99, 132, 1)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        }, {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad de Accidentes',
                        font: {
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Meses',
                        font: {
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                }
            }
        });
    }

    // Gráfico de barras para distribución horaria
    initHorarioChart(data) {
        if (!data || !Array.isArray(data) || data.length === 0) {
            console.warn('Datos vacíos para gráfico horario');
            this.showNoData('grafico-horario', 'Distribución Horaria');
            return null;
        }

        // Ordenar por hora
        const sortedData = data.sort((a, b) => (a.hora || 0) - (b.hora || 0));
        const labels = sortedData.map(item => `${item.hora || 0}:00`);
        const values = sortedData.map(item => item.total || 0);

        return this.initChart('grafico-horario', 'bar', {
            labels: labels,
            datasets: [{
                label: 'Accidentes por Hora',
                data: values,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        }, {
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad de Accidentes'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Hora del Día'
                    }
                }
            }
        });
    }

    // GENERADOR DE COLORES - MÉTODO QUE FALTABA
    generateColors(count, alpha = 1) {
        const colors = [
            `rgba(54, 162, 235, ${alpha})`,
            `rgba(255, 99, 132, ${alpha})`,
            `rgba(75, 192, 192, ${alpha})`,
            `rgba(255, 159, 64, ${alpha})`,
            `rgba(153, 102, 255, ${alpha})`,
            `rgba(255, 205, 86, ${alpha})`,
            `rgba(201, 203, 207, ${alpha})`,
            `rgba(255, 99, 71, ${alpha})`,
            `rgba(50, 205, 50, ${alpha})`,
            `rgba(138, 43, 226, ${alpha})`
        ];
        
        // Si necesitamos más colores que los predefinidos, generamos aleatorios
        if (count > colors.length) {
            for (let i = colors.length; i < count; i++) {
                colors.push(`rgba(${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, ${Math.floor(Math.random() * 255)}, ${alpha})`);
            }
        }
        
        return colors.slice(0, count);
    }

    showNoData(chartId, chartName) {
        const element = document.getElementById(chartId);
        if (element) {
            element.innerHTML = `
                <div class="no-data-placeholder text-center py-5">
                    <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                    <h6 class="text-muted">${chartName}</h6>
                    <p class="text-muted small">No hay datos disponibles para mostrar</p>
                </div>
            `;
        }
    }

    // Destruir todos los gráficos
    destroyAll() {
        this.charts.forEach((chart, chartId) => {
            try {
                chart.destroy();
            } catch (error) {
                console.warn(`Error al destruir gráfico ${chartId}:`, error);
            }
        });
        this.charts.clear();
        this.isInitialized = false;
    }
}