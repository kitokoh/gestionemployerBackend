<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_returns_ok_with_checks_matrix(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'version',
            'checks' => [
                'database' => ['ok'],
                'redis',
                'storage' => ['ok'],
                'queue',
                'memory',
                'web' => ['ok', 'driver', 'app_key_set'],
            ],
            'timestamp',
        ]);

        $response->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => ['ok' => true],
                'storage' => ['ok' => true],
                'web' => ['ok' => true, 'app_key_set' => true],
            ],
        ]);
    }

    public function test_health_web_check_flags_missing_app_key(): void
    {
        // #6957 : une APP_KEY absente fait 500 sur toutes les routes web
        // (EncryptCookies/StartSession) sans affecter l'API — le check `web`
        // doit le signaler pour que l'observabilité alerte au lieu de subir
        // des 500 muets.
        $originalKey = config('app.key');
        config(['app.key' => null]);

        try {
            $response = $this->getJson('/api/v1/health');

            $response->assertOk();
            $response->assertJsonPath('checks.web.ok', false);
            $response->assertJsonPath('checks.web.app_key_set', false);
            $response->assertJsonPath('checks.web.reason', 'app_key_missing');
        } finally {
            config(['app.key' => $originalKey]);
        }
    }
}
