{{-- resources/views/auth/two-factor-verify.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card analytics-card">
                <div class="card-header bg-warning text-dark">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Verificación de Seguridad Requerida
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Verificación de dos factores requerida</strong><br>
                        Para continuar, ingresa el código de tu aplicación de autenticación.
                    </div>

                    <form method="POST" action="{{ route('2fa.verify') }}">
                        @csrf
                        <div class="mb-4">
                            <label for="code" class="form-label fw-semibold">
                                Código de verificación
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
                                Ingresa el código de 6 dígitos de tu aplicación de autenticación<br>
                                <small class="text-muted">O usa un código de recuperación si no tienes acceso</small>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-check me-2"></i>Verificar y Continuar
                            </button>
                            
                            <div class="text-center mt-3">
                                <a href="#" onclick="showRecoveryHelp()" class="text-muted small">
                                    <i class="fas fa-question-circle me-1"></i>
                                    ¿Problemas con tu código de autenticación?
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de ayuda -->
<div class="modal fade" id="recoveryHelpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">¿Problemas con la autenticación?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Si no puedes acceder a tu aplicación de autenticación:</p>
                <ol>
                    <li>Usa uno de tus <strong>códigos de recuperación</strong> que recibiste cuando configuraste 2FA</li>
                    <li>Si perdiste tus códigos de recuperación, contacta al administrador del sistema</li>
                    <li>Asegúrate de que la hora de tu dispositivo esté sincronizada correctamente</li>
                </ol>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Los códigos de recuperación son de 10 caracteres (ej: A1B2C3D4E5)
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<script>
function showRecoveryHelp() {
    const modal = new bootstrap.Modal(document.getElementById('recoveryHelpModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.getElementById('code');
    
    codeInput.addEventListener('input', function(e) {
        if (this.value.length === 6) {
            this.form.submit();
        }
    });
});
</script>
@endsection