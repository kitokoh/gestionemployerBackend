<?php

declare(strict_types=1);

/**
 * Configuration du module Catalog B2B (BC-28 CATALOG).
 *
 * Clés :
 *  - public_cache_ttl : TTL (secondes) du cache public du catalogue
 *    (snapshot liste + fiches produits publiées). Invalidation explicite
 *    à la publication/dépublication et sur chaque mutation tenant
 *    (CatalogPublicCache). Défaut 600 s — borne l'éventuelle incohérence
 *    temporaire (ex. renommage de catégorie) en l'absence d'événement
 *    d'invalidation dédié (v1 C-PUBLIC #6882).
 */
return [
    'public_cache_ttl' => (int) env('CATALOG_PUBLIC_CACHE_TTL', 600),
];
