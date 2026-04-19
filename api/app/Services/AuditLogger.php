<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public static function log(
        string $actorType,
        int|string $actorId,
        ?string $companyId,
        string $action,
        Request $request,
        array $metadata = [],
    ): void {
        $originalPath = 'shared_tenants,public';
        try {
            $pathResult = DB::selectOne('SHOW search_path');
            $originalPath = $pathResult->search_path;
        } catch (\Throwable) {}

        DB::statement('SET search_path TO public');

        try {
            DB::table('audit_logs')->insert([
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'company_id' => $companyId,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } finally {
            DB::statement("SET search_path TO {$originalPath}");
        }
    }
}
