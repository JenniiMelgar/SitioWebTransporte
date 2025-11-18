<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class EnsureTwoFactorAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Verificar si 2FA está habilitado (en sesión)
        if (!Session::get('2fa_enabled')) {
            return $next($request); // 2FA no habilitado, acceso directo
        }

        // Verificar si ya está verificado
        if (Session::get('2fa_verified')) {
            $verifiedAt = Session::get('2fa_verified_at');
            
            // Re-verificar cada 24 horas
            if ($verifiedAt && now()->diffInHours($verifiedAt) < 24) {
                return $next($request);
            }
            
            // Session expirada
            Session::forget('2fa_verified');
            Session::forget('2fa_verified_at');
        }

        // Excluir rutas de 2FA
        $excludedRoutes = [
            '2fa.verify', '2fa.resend', 'logout', 
            '2fa.setup', '2fa.enable', '2fa.recovery', 
            '2fa.regenerate', '2fa.disable'
        ];

        if (in_array($request->route()->getName(), $excludedRoutes)) {
            return $next($request);
        }

        return redirect()->route('2fa.verify')->with('intended', $request->url());
    }
}