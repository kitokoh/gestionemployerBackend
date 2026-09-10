<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Cache public du catalogue B2B (BC-28 CATALOG, C-PUBLIC #6882).
 *
 * Infrastructure (et non Domain) : l'invalidation s'appuie sur la facade
 * `Cache` (Redis) — interdite en Domain par la garde de pureté de couches
 * (#6568). Les clés restent exposées aux Interfaces qui les lisent.
 *
 * Deux clés par tenant (UUID company_id, jamais de slug mutable) :
 *   - snapshot : liste publique (company + catégories + produits publiés) ;
 *   - fiche    : produit publié individuel (`…/p/{slug}`).
 *
 * Invalidation écrite depuis les mutations privées du tenant (publication,
 * dépublication, update, destroy, catégories) — spec : « Cache Redis TTL ;
 * invalidation à la publication ; 404 propre ». Le TTL (config
 * `catalog.public_cache_ttl`, 600 s) borne les cas sans flush dédié.
 */
final class CatalogPublicCache
{
    private const PREFIX = 'catalog:public:v1';

    public static function snapshotKey(string $companyId): string
    {
        return self::PREFIX.':'.$companyId;
    }

    public static function productKey(string $companyId, string $productSlug): string
    {
        return self::PREFIX.':'.$companyId.':p:'.$productSlug;
    }

    /** Invalide le snapshot liste d'un tenant (mutations catégorie, store produit). */
    public static function forgetCompany(string $companyId): void
    {
        Cache::forget(self::snapshotKey($companyId));
    }

    /** Invalide la fiche d'un produit ET le snapshot (mutations produit). */
    public static function forgetProduct(string $companyId, string $productSlug): void
    {
        Cache::forget(self::productKey($companyId, $productSlug));
        Cache::forget(self::snapshotKey($companyId));
    }
}
