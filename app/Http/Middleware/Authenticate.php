<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // Siempre responde null si se trata de una API
        if ($request->is('api/*')) {
            return null;
        }

        // Si no es API, se puede redirigir a una ruta web si la tienes definida
        return route('login'); // Solo si la usaras
    }
}
