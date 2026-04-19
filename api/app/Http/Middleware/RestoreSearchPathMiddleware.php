<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the PostgreSQL search_path is always restored to the default
 * after each request. This prevents a modified search_path from leaking
 * to the next request on a pooled database connection.
 */
class RestoreSearchPathMiddleware
{
    private const DEFAULT_SEARCH_PATH = 'shared_tenants,public';

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('SET search_path TO '.self::DEFAULT_SEARCH_PATH);
        } catch (\Throwable) {
            // Connection may already be closed; silently ignore
        }
    }
}
