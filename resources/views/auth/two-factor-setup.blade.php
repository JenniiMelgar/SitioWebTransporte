{{-- resources/views/auth/two-factor-setup.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card analytics-card">
                <div class="card-header bg-primary text-white">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Configurar Autenticación de Dos Factores
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Mejora la seguridad de tu cuenta</strong><br>
                        La autenticación de dos factores añade una capa adicional de seguridad requiriendo un código único además de tu contraseña.
                    </div>

                    <div class="text-center mb-4">
                        <h5>Paso 1: Escanear código QR</h5>
                        <p class="text-muted">Usa tu aplicación de autenticación (Google Authenticator, Authy, etc.)</p>
                        <div class="mb-3 border rounded p-3 d-inline-block">
                            {!! $qrCode !!}
                        </div>
                        
                        <h5 class="mt-4">Paso 2: Ingresar código secreto</h5>
                        <p class="text-muted">Si no puedes escanear el código, ingresa este manualmente:</p>
                        <div class="bg-light p-3 rounded mb-3">
                            <code class="h5">{{ $secret }}</code>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('2fa.enable') }}">
                        @csrf
                        <div class="mb-4">
                            <label for="code" class="form-label fw-semibold">
                                <h5>Paso 3: Verificar código</h5>
                            </label>
                            <input type="text" 
                                   class="form-control form-control-lg @error('code') is-invalid @enderror" 
                                   id="code" 
                                   name="code" 
                                   placeholder="000000" 
                                   required
                                   maxlength="6"
                                   autofocus
                                   style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5em;">
                            @error('code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-center">
                                Ingresa el código de 6 dígitos que muestra tu aplicación de autenticación
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check me-2"></i>Habilitar Autenticación de Dos Factores
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Configurar después
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('code');
    
    // Auto-avance entre campos (si decides hacerlo de dígito en dígito)
    codeInput.addEventListener('input', function(e) {
        if (this.value.length === 6) {
            this.form.submit();
        }
    });
});
</script>
@endsection