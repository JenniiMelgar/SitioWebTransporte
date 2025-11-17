<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DWT Analytics - Sistema de Análisis de Accidentes</title>
    <!-- Bootstrap con tema Minty -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- CSS Personalizado -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- ... otras etiquetas meta ... -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<body>
    <!-- Navbar Principal -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
                <div class="brand-icon me-2">
                    <i class="fas fa-traffic-light"></i>
                </div>
                <span>DWT Analytics</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                @auth
                    <!-- Dashboard para todos -->
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="/dashboard">
                            <i class="fas fa-chart-bar me-1"></i>Dashboard
                        </a>
                    </li>   
                    
                    <!-- ETL para Analistas y Administradores -->
                    @if(in_array(auth()->user()->rol, ['Analista', 'Administrador']))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('etl*') ? 'active' : '' }}" href="/etl">
                            <i class="fas fa-database me-1"></i>Procesos ETL
                        </a>
                    </li>
                    @endif

                    <!-- Carga para Administradores -->
                    @if(in_array(auth()->user()->rol, ['Administrador']))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('carga*') ? 'active' : '' }}" href="/carga">
                            <i class="fas fa-file-upload me-1"></i>Carga de Archivos
                        </a>
                    </li>
                    @endif
                    
                    <!-- Reportes para Analistas y Administradores -->
                    @if(in_array(auth()->user()->rol, ['Analista', 'Administrador']))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('reportes*') ? 'active' : '' }}" href="/reportes">
                            <i class="fas fa-chart-pie me-1"></i>Reportes
                        </a>
                    </li>
                    @endif

                    <!-- Comparadores para Analistas y Administradores -->
                    @if(in_array(auth()->user()->rol, ['Analista', 'Administrador']))
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('comparadores*') ? 'active' : '' }}" href="/comparadores">
                            <i class="fas fa-balance-scale me-1"></i>Comparadores
                        </a>
                    </li>
                    @endif
                @endauth
            </ul>
                
                <!-- Menú de Usuario -->
                <ul class="navbar-nav">
                    @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="user-info">
                                <div class="user-name">{{ auth()->user()->name }}</div>
                                <div class="user-role badge bg-light text-primary">{{ auth()->user()->rol }}</div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/perfil"><i class="fas fa-user me-2"></i>Mi Perfil</a></li>
                            <li><a class="dropdown-item" href="/configuracion"><i class="fas fa-cog me-2"></i>Configuración</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="/logout">
                                    @csrf
                                    <button type="submit" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                    @else
                    <li class="nav-item">
                        <a class="btn btn-outline-light" href="/login">
                            <i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión
                        </a>
                    </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-content">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="footer-brand d-flex align-items-center">
                        <i class="fas fa-traffic-light me-2 text-primary"></i>
                        <strong>DWT Analytics</strong>
                    </div>
                    <p class="mb-0 mt-2 text-muted">Sistema profesional de análisis de datos de accidentes de tránsito</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-info">
                        <span class="text-muted">Oracle Data Warehouse</span>
                        <div class="tech-stack mt-1">
                            <small class="badge bg-primary">Laravel</small>
                            <small class="badge bg-info">Oracle</small>
                            <small class="badge bg-success">Bootstrap</small>
                        </div>
                    </div>
                </div>
            </div>
            <hr class="my-3 border-secondary">
            <div class="row">
                <div class="col-12 text-center">
                    <p class="mb-0 text-muted">&copy; 2024 DWT Analytics. Todos los derechos reservados.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>