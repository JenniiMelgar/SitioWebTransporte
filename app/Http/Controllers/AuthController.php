<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        // Si ya está autenticado, redirigir según su rol
        if (Auth::check()) {
            return $this->redirectToRole(Auth::user()->rol);
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Validar credenciales
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Buscar usuario en Oracle
        $user = User::where('email', $credentials['email'])->first();

        // Verificar contraseña SIN hasheo (texto plano)
        if ($user && $user->password === $credentials['password']) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            
            // Redirigir según el rol
            return $this->redirectToRole($user->rol)
                ->with('success', 'Bienvenido ' . $user->name);
        }

        return back()->withErrors([
            'email' => 'Las credenciales proporcionadas no son válidas.',
        ])->onlyInput('email');
    }

    private function redirectToRole($rol)
    {
        switch ($rol) {
            case 'Administrador':
                return redirect('/admin/dashboard');
            case 'Analista':
                return redirect('/analista/dashboard');
            case 'Invitado':
                return redirect('/invitado/dashboard');
            default:
                return redirect('/dashboard');
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Sesión cerrada correctamente.');
    }
}