<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Cache public des vitrines (BC-27 SHOWCASE, #6867).
 *
 * - Lecture publique mise en cache Redis (TTL borné, clé = slug tenant →
 *   jamais de contenu d'un tenant sous le slug d'un autre, spec §8) ;
 * - invalidation à chaque mutation d'une vitrine publiée (sections,
 *   publication/dépublication — #6871) via {@see forget()}.
 *
 * Le cache stocke le DTO public (tableau JSON-safe), jamais de modèles
 * Eloquent.
 */
final class ShowcasePublicCache
{
    public const TTL_SECONDS = 900; // 15 min

    public const KEY_PREFIX = 'showcase:public:vitrine:';

    public static function key(string $slug): string
    {
        return self::KEY_PREFIX.$slug;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $resolver
     * @return T
     */
    public function remember(string $slug, \Closure $resolver): mixed
    {
        return Cache::remember(self::key($slug), now()->addSeconds(self::TTL_SECONDS), $resolver);
    }

    public function forget(string $slug): void
    {
        Cache::forget(self::key($slug));
    }
}
