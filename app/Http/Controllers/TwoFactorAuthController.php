<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorAuthController extends Controller
{

    //Mostrar formulario de verificación 2FA
    public function showVerificationForm()
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Verificar que 2FA esté habilitado
        if (!Session::get('2fa_enabled')) {
            return redirect()->route('dashboard')
                ->with('info', 'La autenticación de dos factores no está configurada.');
        }

        // Si ya está verificado, redirigir al dashboard
        if (Session::get('2fa_verified')) {
            return redirect()->intended('/dashboard');
        }

        return view('auth.two-factor-verify');
    }

    //Verificar código 2FA
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $user = Auth::user();
        $secret = Session::get('2fa_secret_final');

        if (!$secret) {
            return back()->withErrors(['code' => '2FA no configurado. Contacta al administrador.']);
        }

        $google2fa = new Google2FA();
        
        // Verificar código normal de 6 dígitos
        if ($google2fa->verifyKey($secret, $request->code)) {
            Session::put('2fa_verified', true);
            Session::put('2fa_verified_at', now());
            
            // Limpiar intended URL si existe
            $intended = session()->pull('intended', '/dashboard');
            
            return redirect()->to($intended)
                ->with('success', 'Verificación exitosa. Bienvenido de nuevo.');
        }

        // Verificar códigos de recuperación
        $recoveryCodes = Session::get('2fa_recovery_codes', []);
        if (in_array($request->code, $recoveryCodes)) {
            // Remover código usado
            $recoveryCodes = array_diff($recoveryCodes, [$request->code]);
            Session::put('2fa_recovery_codes', array_values($recoveryCodes));
            
            Session::put('2fa_verified', true);
            Session::put('2fa_verified_at', now());
            
            $intended = session()->pull('intended', '/dashboard');
            
            return redirect()->to($intended)
                ->with('warning', 'Código de recuperación usado. ' . count($recoveryCodes) . ' códigos restantes.');
        }

        return back()->withErrors(['code' => 'Código inválido. Verifica el código de 6 dígitos o el código de recuperación.']);
    }

    /**
     * Mostrar formulario de configuración 2FA
     */
    public function showSetupForm()
    {
        $user = Auth::user();
        
        // Si ya tiene 2FA habilitado, redirigir
        if (Session::get('2fa_enabled')) {
            return redirect()->route('dashboard')
                ->with('info', 'La autenticación de dos factores ya está configurada.');
        }
        
        // Generar y guardar secreto en SESIÓN (no en BD)
        if (!Session::has('2fa_secret')) {
            $google2fa = new Google2FA();
            Session::put('2fa_secret', $google2fa->generateSecretKey());
        }

        $secret = Session::get('2fa_secret');

        // Generar QR Code
        $qrCodeUrl = $this->generateQrCodeUrl($secret, $user->email);
        $qrCodeSvg = $this->generateQrCodeSvg($qrCodeUrl);

        return view('auth.two-factor-setup', [
            'qrCode' => $qrCodeSvg,
            'secret' => $secret,
        ]);
    }

    /**
     * Habilitar 2FA
     */
    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();
        $secret = Session::get('2fa_secret');

        if (!$secret) {
            return back()->withErrors(['code' => 'Sesión expirada. Recarga la página.']);
        }

        // Verificar código
        $google2fa = new Google2FA();
        $valid = $google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return back()->withErrors(['code' => 'El código de verificación es inválido.']);
        }

        // Guardar 2FA en SESIÓN
        Session::put('2fa_enabled', true);
        Session::put('2fa_secret_final', $secret);
        Session::put('2fa_verified', true);
        
        // Generar códigos de recuperación
        $recoveryCodes = $this->generateRecoveryCodes();
        Session::put('2fa_recovery_codes', $recoveryCodes);

        // Limpiar secreto temporal
        Session::forget('2fa_secret');

        return redirect()->route('2fa.recovery')
            ->with('recovery_codes', $recoveryCodes)
            ->with('success', 'Autenticación de dos factores habilitada correctamente.');
    }

    /**
     * Mostrar códigos de recuperación
     */
    public function showRecoveryCodes()
    {
        $recoveryCodes = Session::get('2fa_recovery_codes', []);

        return view('auth.two-factor-recovery', [
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Regenerar códigos de recuperación
     */
    public function regenerateRecoveryCodes()
    {
        $recoveryCodes = $this->generateRecoveryCodes();
        Session::put('2fa_recovery_codes', $recoveryCodes);

        return redirect()->route('2fa.recovery')
            ->with('recovery_codes', $recoveryCodes)
            ->with('success', 'Códigos de recuperación regenerados.');
    }

    /**
     * Deshabilitar 2FA
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password:web',
        ]);

        // Limpiar toda la información de 2FA
        Session::forget('2fa_enabled');
        Session::forget('2fa_secret');
        Session::forget('2fa_secret_final');
        Session::forget('2fa_recovery_codes');
        Session::forget('2fa_verified');
        Session::forget('2fa_verified_at');

        return redirect()->route('dashboard')
            ->with('success', 'Autenticación de dos factores deshabilitada.');
    }

    /**
     * Generar QR Code URL
     */
    private function generateQrCodeUrl($secret, $email): string
    {
        $google2fa = new Google2FA();
        
        return $google2fa->getQRCodeUrl(
            config('app.name', 'DWT Analytics'),
            $email,
            $secret
        );
    }

    /**
     * Generar QR Code SVG
     */
    private function generateQrCodeSvg(string $qrCodeUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        
        $writer = new Writer($renderer);
        return $writer->writeString($qrCodeUrl);
    }

    //Generar códigos de recuperación
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5))); // 10 caracteres
        }
        return $codes;
    }
}