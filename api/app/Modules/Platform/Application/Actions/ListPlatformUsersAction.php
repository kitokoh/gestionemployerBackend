<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Liste paginée des utilisateurs plateforme (schéma public) avec recherche,
 * tri et filtre de statut — logique extraite de PlatformUsersController
 * (issue #6569, audit DDD M1). Les lignes renvoyées sont enrichies de
 * l'entreprise liée (`->company`) pour le contrat SPA admin.
 */
final class ListPlatformUsersAction
{
    /** Colonnes de tri autorisées (jamais de colonne arbitraire). */
    private const SORTABLE = ['created_at', 'last_login_at', 'email', 'last_name'];

    public function execute(
        int $perPage,
        string $search,
        string $status,
        string $sortBy,
        string $sortDir,
    ): array {
        DB::statement('SET search_path TO public');

        $sortBy = in_array($sortBy, self::SORTABLE, true) ? $sortBy : 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        $query = DB::table('users as u')
            ->select([
                'u.id',
                'u.first_name',
                'u.last_name',
                'u.email',
                'u.phone',
                'u.status',
                'u.preferred_language',
                'u.last_login_at',
                'u.failed_login_attempts',
                'u.locked_until',
                'u.created_at',
                'u.updated_at',
            ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where('u.first_name', 'ilike', $like)
                    ->orWhere('u.last_name', 'ilike', $like)
                    ->orWhere('u.email', 'ilike', $like);
            });
        }

        if (in_array($status, ['active', 'disabled', 'pending', 'suspended'], true)) {
            $query->where('u.status', $status);
        }

        $query->orderBy('u.'.$sortBy, $sortDir)->orderBy('u.id', 'desc');

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \stdClass> $paginator */
        $paginator = $query->paginate($perPage);

        $rows = collect($paginator->items());
        $this->enrichWithCompanies($rows);

        return [
            'rows' => $rows->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Enrichit une collection de lignes `users` avec l'entreprise liée
     * (lien actif le plus récent) — jamais d'info d'un autre tenant.
     *
     * @param  \Illuminate\Support\Collection<int, \stdClass>  $rows
     */
    private function enrichWithCompanies(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $userIds = $rows->pluck('id')->all();

        $links = DB::table('user_employee_links')
            ->whereIn('user_id', $userIds)
            ->orderByDesc('id')
            ->get()
            ->keyBy('user_id');

        $companyIds = $links->pluck('company_id')->unique()->filter()->all();

        $companies = $companyIds === []
            ? collect()
            : DB::table('companies')->whereIn('id', $companyIds)->get()->keyBy('id');

        foreach ($rows as $row) {
            $link = $links->get($row->id);
            $row->company = $link !== null
                ? [
                    'id' => $link->company_id,
                    'name' => $companies->get($link->company_id)?->name,
                    'link_status' => $link->status,
                    'employee_id' => $link->employee_id,
                ]
                : null;
        }
    }
}
