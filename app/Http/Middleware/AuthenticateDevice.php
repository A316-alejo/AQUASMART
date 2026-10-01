<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica un dispositivo IoT por su token (cabecera Authorization: Bearer <token>).
 * El token nunca se compara ni se guarda en texto plano: se busca por su hash (Device::findByToken).
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = $token ? Device::findByToken($token) : null;

        if (! $device) {
            return response()->json(['message' => 'Token de dispositivo inválido o inactivo.'], 401);
        }

        $request->attributes->set('device', $device->load('farm'));

        return $next($request);
    }
}
