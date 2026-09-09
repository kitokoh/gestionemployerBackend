<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Support;

use App\Core\Tenant\Domain\Models\Company;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache du catalogue public B2B (BC-28 CATALOG, #6882).
 *
 * La liste publique (`GET /public/catalog/{companySlug}`) est coûteuse à
 * reconstruire (jointures catégories/produits sous contexte tenant) et ne
 * change que lors des mutations du tenant (CRUD produit/catégorie,
 * publication/dépublication). On la met en cache Redis TTL court et on
 * l'invalide explicitement à chaque mutation — jamais de donnée interne en
 * cache : seul le DTO public construit par CatalogPublicController y est
 * stocké (clé par slug tenant, contenu sans company_id/meta/stocks).
 */
final class CatalogPublicCache
{
    /** TTL court : la fraîcheur prime sur le hit-rate (spec C-PUBLIC #6882). */
    private const TTL_SECONDS = 300;

    public static function key(string $companySlug): string
    {
        return 'catalog:public:v1:'.$companySlug;
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $companySlug, Closure $callback): mixed
    {
        return Cache::remember(self::key($companySlug), self::TTL_SECONDS, $callback);
    }

    /** Invalidation à la publication/mutation (spec C-PUBLIC #6882). */
    public static function forget(string $companySlug): void
    {
        Cache::forget(self::key($companySlug));
    }

    /**
     * Invalidation côté API privée (contexte tenant) : on ne connaît que le
     * company_id de l'acteur — on résout le slug pour purger la bonne clé.
     */
    public static function forgetForCompany(string $companyId): void
    {
        $slug = Company::query()->whereKey($companyId)->value('slug');

        if (is_string($slug) && $slug !== '') {
            self::forget($slug);
        }
    }
}
