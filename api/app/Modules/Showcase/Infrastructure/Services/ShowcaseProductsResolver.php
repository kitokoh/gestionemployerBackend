<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Models\CatalogProduct;

/**
 * Résolution des produits d'une section `products` (BC-27 SHOWCASE,
 * C-VITRINE #6891) — lecture du catalogue BC-28, **dépendance optionnelle**.
 *
 * La vitrine ne duplique aucune logique catalogue : elle lit les produits
 * PUBLIÉS du tenant courant (déjà résolu par le contrôleur public) et
 * retourne une shape publique minimale (slug, nom, description, prix minor,
 * devise, unité, catégorie). Si le tenant n'a pas le catalogue (flag/solution
 * absents) ou aucun produit publié, la section est **omise** du rendu
 * (jamais de vitrine cassée) — cf. critère d'acceptation #6891.
 *
 * Aucune donnée interne (stock, marge, fournisseur, meta) n'est exposée.
 */
final class ShowcaseProductsResolver
{
    public const DEFAULT_LIMIT = 6;

    public const MAX_LIMIT = 24;

    /**
     * @param  array<string, mixed>  $content  contenu de section `products`
     * @return list<array<string, mixed>> liste vide si catalogue absent/vide
     */
    public function resolve(array $content): array
    {
        try {
            $query = CatalogProduct::query()
                ->where('status', CatalogProductStatus::Published);

            $categorySlug = $content['category_slug'] ?? null;
            if (is_string($categorySlug) && trim($categorySlug) !== '') {
                /** @var CatalogCategory|null $category */
                $category = CatalogCategory::query()->where('slug', $categorySlug)->first();

                if (! $category instanceof CatalogCategory) {
                    return [];
                }

                $query->where('category_id', $category->id);
            }

            $slugs = $content['product_slugs'] ?? null;
            if (is_array($slugs) && $slugs !== []) {
                $wanted = array_values(array_filter($slugs, static fn ($slug): bool => is_string($slug) && $slug !== ''));
                $query->whereIn('slug', $wanted);
            }

            $limit = $content['limit'] ?? null;
            $limit = is_int($limit) && $limit > 0 ? min($limit, self::MAX_LIMIT) : self::DEFAULT_LIMIT;

            /** @var list<CatalogProduct> $products */
            $products = $query->orderBy('name')->limit($limit)->get()->all();
        } catch (\Throwable) {
            // Catalogue absent (module non activé / table inexistante) :
            // dépendance optionnelle → section omise, jamais d'erreur publique.
            return [];
        }

        return array_map(
            static fn (CatalogProduct $product): array => [
                'slug' => $product->slug,
                'name' => $product->name,
                'description' => $product->description,
                'price_minor' => $product->price_minor,
                'currency' => $product->currency,
                'unit' => $product->unit,
            ],
            $products
        );
    }
}
