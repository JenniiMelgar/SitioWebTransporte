@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header">
                <h1 class="h3 fw-bold">
                    <i class="fas fa-balance-scale me-2 text-primary"></i>
                    Comparadores Avanzados
                </h1>
                <p class="text-muted">Compare datos entre diferentes períodos, regiones o categorías</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Panel de Control -->
        <div class="col-lg-4">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>
                        Configuración de Comparación
                    </h5>
                </div>
                <div class="card-body">
                    <form id="form-comparacion">
                        <!-- Tipo de Comparación -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Tipo de Comparación</label>
                            <select class="form-select" id="tipo-comparacion">
                                <option value="periodos">Períodos (Años)</option>
                                <option value="regiones">Regiones (Estados)</option>
                                <option value="categorias">Categorías (Clima)</option>
                            </select>
                            
                            <!-- Botones rápidos -->
                            <div class="btn-group w-100 mt-2" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-tipo-comparacion="periodos">
                                    <i class="fas fa-calendar me-1"></i>Años
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-tipo-comparacion="regiones">
                                    <i class="fas fa-map me-1"></i>Estados
                                </button>
                                <button type="button" class="btn btn-outline-primary" data-tipo-comparacion="categorias">
                                    <i class="fas fa-cloud me-1"></i>Clima
                                </button>
                            </div>
                        </div>

                        <!-- Parámetros de Comparación -->
                        <div class="mb-3">
                            <label for="parametro1" class="form-label">Año 1</label>
                            <select class="form-select" id="parametro1">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="parametro2" class="form-label">Año 2</label>
                            <select class="form-select" id="parametro2">
                                <option value="">Seleccione...</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-primary" id="btn-comparar">
                                <i class="fas fa-chart-bar me-2"></i>Comparar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Información -->
            <div class="card analytics-card mt-4">
                <div class="card-header">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2 text-primary"></i>
                        Información
                    </h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        <strong>Períodos:</strong> Compare datos entre diferentes años.
                    </p>
                    <p class="small text-muted mb-2">
                        <strong>Regiones:</strong> Compare datos entre diferentes estados.
                    </p>
                    <p class="small text-muted mb-0">
                        <strong>Categorías:</strong> Compare datos entre diferentes condiciones climáticas.
                    </p>
                </div>
            </div>
        </div>

        <!-- Resultados -->
        <div class="col-lg-8">
            <div class="card analytics-card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line me-2 text-primary"></i>
                        Resultados de la Comparación
                    </h5>
                </div>
                <div class="card-body">
                    <div id="resultados-comparacion">
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-balance-scale fa-3x mb-3"></i>
                            <h5>Configura tu comparación</h5>
                            <p class="mb-0">Selecciona los parámetros y haz clic en "Comparar" para ver los resultados.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/comparadores.js') }}"></script>
@endpush