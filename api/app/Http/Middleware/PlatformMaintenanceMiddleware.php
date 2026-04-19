<?php

namespace App\Http\Middleware;

use App\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PlatformMaintenanceMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip check if it is a SuperAdmin accessing the platform panel or logout
        if ($request->is('platform*') || $request->is('logout')) {
            return $next($request);
        }

        try {
            $isMaintenance = PlatformSetting::get('maintenance_mode', false);
        } catch (Throwable) {
            return $next($request);
        }

        if ($isMaintenance) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'MAINTENANCE_MODE',
                    'message' => PlatformSetting::get('maintenance_message', 'La plateforme est actuellement en maintenance. Veuillez réessayer plus tard.'),
                ], 503);
            }

            return response()->view('errors.maintenance', [
                'message' => PlatformSetting::get('maintenance_message', 'La plateforme est actuellement en maintenance.'),
            ], 503);
        }

        return $next($request);
    }
}
