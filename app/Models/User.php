<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'USERS';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'email', 'password', 'rol'
        // NO agregar campos de 2FA aquí
    ];

    protected $hidden = [
        'password', 'remember_token'
        // NO ocultar campos de 2FA
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Verificar si 2FA está habilitado (desde sesión)
     */
    public function hasEnabledTwoFactorAuthentication(): bool
    {
        return session('2fa_enabled', false);
    }
}