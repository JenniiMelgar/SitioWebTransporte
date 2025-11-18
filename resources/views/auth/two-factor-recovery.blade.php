{{-- resources/views/auth/two-factor-recovery.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card analytics-card">
                <div class="card-header bg-success text-white">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-key me-2"></i>
                        Códigos de Recuperación - ¡Guárdalos!
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡Estos códigos son importantes!</strong><br>
                        Son tu única forma de acceder a tu cuenta si pierdes tu dispositivo de autenticación. 
                        <strong>Guárdalos en un lugar seguro.</strong>
                    </div>

                    <div class="row mb-4">
                        @foreach($recovery_codes as $index => $code)
                            <div class="col-md-6 mb-3">
                                <div class="bg-light p-3 rounded border">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <code class="h6 mb-0 font-monospace">{{ $code }}</code>
                                        <span class="badge bg-secondary">{{ $index + 1 }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>¿Cómo usar estos códigos?</strong><br>
                        Cuando se te solicite el código de autenticación, ingresa uno de estos códigos en lugar del código de tu aplicación.
                        Cada código solo se puede usar una vez.
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                        <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg">
                            <i class="fas fa-check me-2"></i>Continuar al Dashboard
                        </a>
                        
                        <form method="POST" action="{{ route('2fa.regenerate') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-warning" 
                                    onclick="return confirm('¿Estás seguro? Esto invalidará todos tus códigos anteriores.')">
                                <i class="fas fa-redo me-2"></i>Regenerar Códigos
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection