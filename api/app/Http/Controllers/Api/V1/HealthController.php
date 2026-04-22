<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Endpoint /api/v1/health : sonde "live + ready" consommee par :
 *
 * - le deploy hook Render (`DEFAULT_API_HEALTHCHECK_URL` cherche `"status":"ok"`)
 * - les futurs scrapers de supervision (UptimeRobot, Better Uptime, etc.)
 *
 * La reponse inclut toujours `status`, `version` et la matrice `checks`.
 * Le HTTP code est 200 tant qu'au moins la base de donnees repond ; si la DB
 * tombe on renvoie 503 pour que Render detecte immediatement une instance
 * non disponible.
 *
 * Les checks secondaires (Redis, storage) sont `degraded` en cas d'echec
 * mais ne declenchent pas un 503 : l'API reste partiellement servable
 * (les jobs/queues peuvent etre degrades, pas l'auth).
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $version = (string) config('app.version');

        $database = $this->checkDatabase();
        $redis = $this->checkRedis();
        $storage = $this->checkStorage();

        $globalOk = $database['ok'];

        $payload = [
            'status' => $globalOk ? 'ok' : 'fail',
            'version' => $version,
            'checks' => [
                'database' => $database,
                'redis' => $redis,
                'storage' => $storage,
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        return response()->json($payload, $globalOk ? 200 : 503);
    }

    /**
     * @return array{ok: bool, latency_ms?: int, error?: string}
     */
    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');

            return [
                'ok' => true,
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, status?: string, latency_ms?: int, error?: string}
     */
    private function checkRedis(): array
    {
        // Redis est optionnel (cache/sessions/queues). On considere Redis
        // "non configure" si aucune des variables d'env explicites n'est
        // posee ET qu'aucun driver applicatif (cache/queue/session) ne
        // l'utilise. Le default `127.0.0.1` de `config/database.php` ne
        // suffit pas a considerer Redis voulu : ce check est justement la
        // pour eviter de bloquer quelques secondes sur un `tcp connect`
        // vers un Redis inexistant sur chaque requete `/health`.
        $envConfigured = ! empty(env('REDIS_HOST')) || ! empty(env('REDIS_URL'));
        $driverUsesRedis = in_array(config('cache.default'), ['redis'], true)
            || in_array(config('queue.default'), ['redis'], true)
            || in_array(config('session.driver'), ['redis'], true);

        if (! $envConfigured && ! $driverUsesRedis) {
            return ['ok' => true, 'status' => 'skipped'];
        }

        $start = microtime(true);
        try {
            $response = Redis::connection()->ping();
            $ok = $response === true || $response === 'PONG' || $response === '+PONG';

            return [
                'ok' => $ok,
                'status' => $ok ? 'pong' : 'unexpected',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => 'degraded',
                'error' => class_basename($e),
            ];
        }
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default', 'local'));
            // Laravel 11 : les disques sont en `throw => false` par defaut,
            // donc `put()` retourne `false` au lieu de lever. On verifie
            // explicitement la valeur de retour pour vraiment detecter un
            // disque non inscriptible.
            $written = $disk->put('.healthcheck', (string) now()->timestamp);
            $disk->delete('.healthcheck');

            return ['ok' => (bool) $written];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => class_basename($e),
            ];
        }
    }
}
