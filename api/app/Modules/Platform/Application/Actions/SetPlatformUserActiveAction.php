<?php

declare(strict_types=1);

namespace App\Modules\Platform\Application\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Activation/désactivation d'un utilisateur plateforme (schéma public) avec
 * garde d'auto-désactivation — logique extraite de PlatformUsersController
 * (issue #6569, audit DDD M1).
 *
 * Retour : statut `not_found` (utilisateur inconnu), `self_disable` (le
 * super-admin courant tenterait de désactiver son propre compte) ou
 * `updated` avec la ligne fraîche enrichie (`->company`, `->roles`).
 */
final class SetPlatformUserActiveAction
{
    /** Schéma tenant partagé (mode de déploiement par défaut de la plateforme). */
    private const TENANT_SCHEMA = 'shared_tenants';

    /**
     * @return array{status: 'not_found'|'self_disable'|'updated', row?: \stdClass}
     */
    public function execute(int $userId, bool $isActive, ?string $actorEmail): array
    {
        DB::statement('SET search_path TO public');

        $row = DB::table('users')->where('id', $userId)->first();

        if ($row === null) {
            return ['status' => 'not_found'];
        }

        // Garde auto-désactivation : le super-admin courant ne peut pas
        // désactiver un compte partageant son email (se tirerait la porte).
        if (
            ! $isActive
            && $actorEmail !== null
            && strcasecmp($actorEmail, (string) $row->email) === 0
        ) {
            return ['status' => 'self_disable'];
        }

        DB::table('users')
            ->where('id', $userId)
            ->update(['status' => $isActive ? 'active' : 'disabled']);

        /** @var \stdClass $fresh */
        $fresh = DB::table('users')->where('id', $userId)->first();

        $rows = collect([$fresh]);
        $this->enrichWithCompanies($rows);

        /** @var \stdClass $enriched */
        $enriched = $rows->first();
        $enriched->roles = $this->rolesFor($enriched->id);

        return ['status' => 'updated', 'row' => $enriched];
    }

    /**
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

    /**
     * @return array<int, array{role: mixed, manager_role: mixed, company_id: mixed, link_status: mixed}>
     */
    private function rolesFor(int $userId): array
    {
        return DB::table('user_employee_links as l')
            ->join(self::TENANT_SCHEMA.'.employees as e', 'e.id', '=', 'l.employee_id')
            ->where('l.user_id', $userId)
            ->select(['e.role', 'e.manager_role', 'l.company_id', 'l.status as link_status'])
            ->get()
            ->map(fn ($r): array => [
                'role' => $r->role,
                'manager_role' => $r->manager_role,
                'company_id' => $r->company_id,
                'link_status' => $r->link_status,
            ])
            ->all();
    }
}
