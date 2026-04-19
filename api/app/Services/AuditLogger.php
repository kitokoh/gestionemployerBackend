<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $tableName = DB::getDriverName() === 'pgsql' ? 'public.audit_logs' : 'audit_logs';

        try {
            DB::table($tableName)->insert([
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'company_id' => $companyId,
                'action' => $action,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            // Log error to system log but don't crash the request for an audit failure
            Log::error('Failed to write audit log: '.$e->getMessage());
        }
    }
}
