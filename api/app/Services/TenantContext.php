<?php

namespace App\Services;

use App\Models\Company;
use Closure;
use Illuminate\Support\Facades\DB;

class TenantContext
{
    public function withPublic(Closure $callback): mixed
    {
        return $this->withSearchPath('public', $callback);
    }

    public function withCompany(?Company $company, Closure $callback): mixed
    {
        $searchPath = $company?->tenancy_type === 'schema'
            ? $this->quoteIdentifier((string) $company->schema_name).',public'
            : 'shared_tenants,public';

        return $this->withSearchPath($searchPath, $callback);
    }

    public function applyCompany(?Company $company): void
    {
        $searchPath = $company?->tenancy_type === 'schema'
            ? $this->quoteIdentifier((string) $company->schema_name).',public'
            : 'shared_tenants,public';

        $this->setSearchPath($searchPath);
    }

    private function withSearchPath(string $searchPath, Closure $callback): mixed
    {
        $previousSearchPath = $this->currentSearchPath();

        $this->setSearchPath($searchPath);

        try {
            return $callback();
        } finally {
            if ($previousSearchPath !== null) {
                $this->setSearchPath($previousSearchPath);
            }
        }
    }

    private function currentSearchPath(): ?string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $result = DB::selectOne('SHOW search_path');

        return is_object($result) ? (string) $result->search_path : 'shared_tenants,public';
    }

    private function setSearchPath(string $searchPath): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SET search_path TO {$searchPath}");
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return sprintf('"%s"', str_replace('"', '""', $identifier ?: 'shared_tenants'));
    }
}
