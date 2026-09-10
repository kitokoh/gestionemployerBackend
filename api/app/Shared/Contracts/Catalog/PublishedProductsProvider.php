<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Catalog;

/**
 * Contrat partagé de lecture des produits PUBLIÉS du catalogue (BC-28 CATALOG).
 *
 * Permet aux autres BC (ex. BC-27 SHOWCASE — section `products`) de composer
 * une surface publique à partir du catalogue SANS import croisé
 * `Modules/Showcase -> Modules/Catalog` (règle d'isolation #5584) : les
 * consommateurs ne dépendent que de ce contrat, implémenté par
 * `Catalog\Infrastructure\Services\CatalogPublishedProductsProvider` et bindé
 * par `CatalogServiceProvider` (pattern `Shared\Contracts\Notification`).
 *
 * Aucune donnée interne (id, company_id, statut, stock, marge, `meta`) ne
 * transite : la forme retournée est la forme publique minimale (slug, nom,
 * description, prix en minor units, devise, unité).
 */
interface PublishedProductsProvider
{
    /**
     * Produits PUBLIÉS du tenant courant pour une section `products`.
     *
     * @param  array<string, mixed>  $content  contenu de section (`limit`, `category_slug`, `product_slugs`)
     * @return list<array<string, mixed>> liste vide si le catalogue est absent ou vide (dépendance optionnelle)
     */
    public function resolve(array $content): array;
}
