<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario que fue desactivado, o cuyo productor fue desactivado, mientras estaba conectado.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->canAccessSystem()) {
            $message = __($user->is_active ? 'auth.producer_inactive' : 'auth.inactive');

            // Peticiones de la API (token): no hay sesión que cerrar ni pantalla de login a la que volver.
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
